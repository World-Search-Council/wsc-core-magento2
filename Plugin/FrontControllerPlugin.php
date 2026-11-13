<?php

declare(strict_types=1);

namespace Wsc\Analytics\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Wsc\Analytics\Helper\Data as HelperData;
use Wsc\Analytics\Model\BotTracker;
use Wsc\Analytics\Model\CoreService;

/**
 * Early Edge AI bot interceptor, /llms.txt AI Discovery Manifest,
 * and Opt-In IndexNow key verification responder on Magento FrontController.
 */
class FrontControllerPlugin
{
    private HelperData $helper;
    private BotTracker $botTracker;
    private CoreService $coreService;
    private RawFactory $rawFactory;

    public function __construct(
        HelperData $helper,
        BotTracker $botTracker,
        CoreService $coreService,
        RawFactory $rawFactory
    ) {
        $this->helper = $helper;
        $this->botTracker = $botTracker;
        $this->coreService = $coreService;
        $this->rawFactory = $rawFactory;
    }

    public function aroundDispatch(
        FrontControllerInterface $subject,
        callable $proceed,
        RequestInterface $request
    ) {
        if ($request instanceof HttpRequest) {
            $requestUri = (string) $request->getRequestUri();
            $pathOnly = '/' . ltrim((string) parse_url($requestUri, PHP_URL_PATH), '/');

            // 1. Edge Bot Sensor (Zero-PII: salted daily SHA-256 session hash, never raw IP)
            if ($this->helper->isTrackingActive()) {
                $botLevel = $this->helper->getBotTrackingLevel();
                if ($botLevel !== 'off') {
                    $ua = (string) $request->getHeader('User-Agent');
                    if ($ua !== '') {
                        $bot = $this->botTracker->detectBot($ua);
                        if ($bot && !($botLevel === 'ai_only' && ($bot['category'] ?? '') !== 'ai')) {
                            $this->coreService->recordBotHit(
                                $bot,
                                $pathOnly,
                                (string) $request->getHeader('Referer'),
                                (string) ($request->getClientIp() ?: ''),
                                $ua
                            );
                        }
                    }
                }
            }

            // 2. Dynamic /llms.txt AI Discovery Manifest
            if ($pathOnly === '/llms.txt' && $this->coreService->isLlmsTxtEnabled()) {
                $manifest = $this->coreService->generateLlmsTxt();
                $raw = $this->rawFactory->create();
                $raw->setHttpResponseCode(200);
                $raw->setHeader('Content-Type', 'text/markdown; charset=UTF-8', true);
                $raw->setHeader('X-Robots-Tag', 'noindex', true);
                $raw->setContents($manifest);
                return $raw;
            }

            // 3. Opt-In IndexNow Ownership Verification File (/{32-hex-key}.txt)
            if ($this->coreService->isIndexNowEnabled() && preg_match('#^/([a-f0-9]{32})\.txt$#i', $pathOnly, $m)) {
                $configuredKey = $this->coreService->getIndexNowKey();
                if (hash_equals(strtolower($configuredKey), strtolower($m[1]))) {
                    $raw = $this->rawFactory->create();
                    $raw->setHttpResponseCode(200);
                    $raw->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
                    $raw->setHeader('Cache-Control', 'public, max-age=3600', true);
                    $raw->setContents($configuredKey);
                    return $raw;
                }
            }
        }

        return $proceed($request);
    }
}
