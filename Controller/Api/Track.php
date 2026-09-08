<?php

namespace Wsc\Analytics\Controller\Api;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Wsc\Analytics\Model\CoreService;

class Track implements HttpPostActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private CoreService $coreService;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CoreService $coreService
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->coreService = $coreService;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $content = $this->request->getContent();

        if (empty($content)) {
            return $result->setHttpResponseCode(400)->setData(['ok' => false, 'error' => 'empty_payload']);
        }

        $data = json_decode($content, true);
        if (!is_array($data) || empty($data['events']) || !is_array($data['events'])) {
            return $result->setHttpResponseCode(400)->setData(['ok' => false, 'error' => 'invalid_format']);
        }

        $ip = $this->request->getClientIp() ?: '';
        $ua = (string) $this->request->getHeader('User-Agent');
        $count = 0;

        foreach ($data['events'] as $item) {
            if (empty($item['e'])) {
                continue;
            }

            $eventType = (string) $item['e'];
            $urlPath   = (string) ($item['p'] ?? '/');
            $referrer  = (string) ($item['r'] ?? '');
            $meta      = (isset($item['m']) && is_array($item['m'])) ? $item['m'] : [];

            $meta['ip'] = $ip;
            $meta['user_agent'] = $ua;

            if ($this->coreService->trackEvent($eventType, $urlPath, $referrer, $meta)) {
                $count++;
            }
        }

        return $result->setData(['ok' => true, 'queued' => $count]);
    }
}
