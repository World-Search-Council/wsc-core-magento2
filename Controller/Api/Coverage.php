<?php

namespace Wsc\Analytics\Controller\Api;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\Client\Curl;
use Wsc\Analytics\Model\CoreService;

class Coverage implements HttpPostActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private CoreService $coreService;
    private Curl $curl;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CoreService $coreService,
        Curl $curl
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->coreService = $coreService;
        $this->curl = $curl;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $content = $this->request->getContent();
        $data = json_decode($content, true);

        $queryUrl = trim((string) ($data['query'] ?? ''));
        $timeframe = trim((string) ($data['timeframe'] ?? '30d'));

        if (empty($queryUrl)) {
            return $result->setHttpResponseCode(400)->setData(['ok' => false, 'error' => 'query_required']);
        }

        $creds = $this->coreService->getProjectCredentials();
        if (empty($creds['project_id']) || empty($creds['api_secret'])) {
            return $result->setHttpResponseCode(400)->setData(['ok' => false, 'error' => 'not_configured']);
        }

        $payload = json_encode([
            'project_id' => $creds['project_id'],
            'query'      => $queryUrl,
            'timeframe'  => in_array($timeframe, ['7d', '30d'], true) ? $timeframe : '30d'
        ]);

        $signature = hash_hmac('sha256', $payload, $creds['api_secret']);

        try {
            $this->curl->setHeaders([
                'Content-Type' => 'application/json',
                'X-WSC-Sig'    => $signature,
            ]);
            $this->curl->setTimeout(10);
            $this->curl->post('https://api.worldsearchcouncil.org/v1/ai-coverage/query', $payload);

            $responseBody = $this->curl->getBody();
            $decoded = json_decode($responseBody, true);

            return $result->setData($decoded ?: ['status' => 'error']);
        } catch (\Exception $e) {
            return $result->setHttpResponseCode(502)->setData(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
