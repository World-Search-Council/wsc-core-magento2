<?php

declare(strict_types=1);

namespace Wsc\Analytics\Controller\Api;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Wsc\Analytics\Model\CoreService;

class Coverage implements HttpPostActionInterface
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
        $content = (string) $this->request->getContent();
        $data = json_decode($content, true) ?: [];

        $queryUrl = trim((string) ($data['query'] ?? ''));
        $timeframe = trim((string) ($data['timeframe'] ?? '30d'));

        if ($queryUrl === '') {
            return $result->setHttpResponseCode(400)->setData(['ok' => false, 'error' => 'query_required']);
        }

        $res = $this->coreService->queryAiCoverage($queryUrl, $timeframe);
        return $result->setData($res);
    }
}
