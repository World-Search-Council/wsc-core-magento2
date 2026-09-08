<?php

namespace Wsc\Analytics\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Wsc\Analytics\Helper\Data as HelperData;
use Wsc\Analytics\Model\BotTracker;
use Wsc\Analytics\Model\CoreService;

/**
 * Early Edge AI bot interceptor plugin on Magento FrontController.
 */
class FrontControllerPlugin
{
    private HelperData $helper;
    private BotTracker $botTracker;
    private CoreService $coreService;

    public function __construct(
        HelperData $helper,
        BotTracker $botTracker,
        CoreService $coreService
    ) {
        $this->helper = $helper;
        $this->botTracker = $botTracker;
        $this->coreService = $coreService;
    }

    public function aroundDispatch(
        FrontControllerInterface $subject,
        callable $proceed,
        RequestInterface $request
    ) {
        if ($this->helper->isTrackingActive() && $request instanceof HttpRequest) {
            $botLevel = $this->helper->getBotTrackingLevel();
            if ($botLevel !== 'off') {
                $ua = (string) $request->getHeader('User-Agent');
                if (!empty($ua)) {
                    $bot = $this->botTracker->detectBot($ua);
                    if ($bot && !($botLevel === 'ai_only' && ($bot['category'] ?? '') !== 'ai')) {
                        $this->coreService->trackEvent('bot_hit', $request->getRequestUri(), (string)$request->getHeader('Referer'), [
                            'bot_name'     => $bot['name'],
                            'bot_category' => $bot['category'],
                            'bot_type'     => $bot['type'],
                            'ip'           => $request->getClientIp(),
                        ]);
                    }
                }
            }
        }

        return $proceed($request);
    }
}
