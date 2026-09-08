<?php

namespace Wsc\Analytics\Controller\Api;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Wsc\Analytics\Model\CoreService;

class Pagespeed implements HttpPostActionInterface
{
    private JsonFactory $jsonFactory;
    private RequestInterface $request;
    private StoreManagerInterface $storeManager;
    private CoreService $coreService;

    public function __construct(
        JsonFactory $jsonFactory,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        CoreService $coreService
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->coreService = $coreService;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $raw = (string) $this->request->getContent();
        $data = json_decode($raw, true) ?: [];

        $storeUrl = $this->storeManager->getStore()->getBaseUrl();
        $url = !empty($data['url']) ? trim($data['url']) : $storeUrl;
        $strategy = (!empty($data['strategy']) && in_array($data['strategy'], ['mobile', 'desktop'], true)) ? $data['strategy'] : 'mobile';

        $response = $this->coreService->triggerSpeedSentinelAudit($url, $strategy);
        return $result->setData($response);
    }
}
