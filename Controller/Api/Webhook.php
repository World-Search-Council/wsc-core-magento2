<?php

namespace Wsc\Analytics\Controller\Api;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Wsc\Analytics\Model\CoreService;

class Webhook implements HttpPostActionInterface
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
        $signature = (string) $this->request->getHeader('X-WSC-Sig');

        $creds = $this->coreService->getProjectCredentials();
        $expectedSig = hash_hmac('sha256', $content, $creds['local_key']);

        if (!hash_equals($expectedSig, $signature)) {
            return $result->setHttpResponseCode(403)->setData(['ok' => false, 'error' => 'invalid_signature']);
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $result->setHttpResponseCode(400)->setData(['ok' => false, 'error' => 'invalid_json']);
        }

        $saved = $this->coreService->saveWebhookReport($data);
        return $result->setData(['ok' => $saved]);
    }
}
