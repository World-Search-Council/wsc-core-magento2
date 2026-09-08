<?php

namespace Wsc\Analytics\Controller\Api;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Wsc\Analytics\Model\CoreService;

class Sentinel implements HttpPostActionInterface
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
        $host = parse_url($storeUrl, PHP_URL_HOST) ?: '';

        $domain = !empty($data['domain']) ? trim($data['domain']) : $host;

        $response = $this->coreService->triggerSentinelScan($domain);
        return $result->setData($response);
    }
}
