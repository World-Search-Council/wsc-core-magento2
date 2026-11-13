<?php

declare(strict_types=1);

namespace Wsc\Analytics\Controller\Adminhtml\Api;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Wsc\Analytics\Model\CoreService;

/**
 * ACL-protected Magento 2 Admin AJAX Dispatcher (`wsc_admin/api/dispatch`)
 * powering the 7-Card Bento Suite, SpeedSentinel 2.0, Domain Sentinel,
 * AI Coverage Inspector, Industry Benchmarks, Live WSC Member Portal Bridge,
 * and Opt-In IndexNow URL Submission.
 */
class Dispatch extends Action
{
    public const ADMIN_RESOURCE = 'Wsc_Analytics::dashboard';

    private JsonFactory $jsonFactory;
    private CoreService $coreService;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CoreService $coreService
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->coreService = $coreService;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $rawBody = (string) $this->getRequest()->getContent();
        $jsonBody = $rawBody !== '' ? (json_decode($rawBody, true) ?: []) : [];
        $params = array_merge($this->getRequest()->getParams(), is_array($jsonBody) ? $jsonBody : []);

        $action = (string) ($params['wsc_action'] ?? $params['action'] ?? 'cards');

        switch ($action) {
            case 'cards':
                return $result->setData([
                    'ok'    => true,
                    'cards' => $this->coreService->getDashboardCards(),
                ]);

            case 'refresh':
                $synced = $this->coreService->processBatchExport(500);
                return $result->setData([
                    'ok'            => true,
                    'synced_events' => $synced,
                    'last_sync'     => gmdate('Y-m-d H:i:s') . ' UTC',
                ]);

            case 'pagespeed':
                $url = trim((string) ($params['url'] ?? $this->coreService->resolveSiteBaseUrl()));
                $strategy = in_array(($params['strategy'] ?? 'mobile'), ['mobile', 'desktop'], true)
                    ? (string) $params['strategy']
                    : 'mobile';
                return $result->setData($this->coreService->triggerSpeedSentinelAudit($url, $strategy));

            case 'sentinel':
                $baseUrl = $this->coreService->resolveSiteBaseUrl();
                $defaultHost = (string) (parse_url($baseUrl, PHP_URL_HOST) ?: '');
                $domain = trim((string) ($params['domain'] ?? $defaultHost));
                return $result->setData($this->coreService->triggerSentinelScan($domain));

            case 'ai_coverage':
                $query = trim((string) ($params['query'] ?? ''));
                $timeframe = in_array(($params['timeframe'] ?? '30d'), ['7d', '30d'], true)
                    ? (string) $params['timeframe']
                    : '30d';
                return $result->setData($this->coreService->queryAiCoverage($query, $timeframe));

            case 'benchmarks_query':
                $days = max(1, min(90, (int) ($params['days'] ?? 7)));
                $force = !empty($params['force']);
                return $result->setData($this->coreService->getBenchmarkReport($days, $force));

            case 'benchmarks_classify':
                $vertical = isset($params['vertical']) && trim((string) $params['vertical']) !== ''
                    ? trim((string) $params['vertical'])
                    : null;
                return $result->setData($this->coreService->classifyBenchmarkVertical($vertical));

            case 'license_verify':
                $licenseKey = trim((string) ($params['license_key'] ?? ''));
                $coBranding = isset($params['co_branding_update']) && is_array($params['co_branding_update'])
                    ? $params['co_branding_update']
                    : null;
                return $result->setData($this->coreService->verifyLicense($licenseKey, null, null, $coBranding));

            case 'license_disconnect':
                return $result->setData($this->coreService->disconnectLicense());

            case 'cobranding_save':
                $branding = [
                    'enabled'  => !empty($params['enabled']),
                    'name'     => (string) ($params['name'] ?? ''),
                    'url'      => (string) ($params['url'] ?? ''),
                    'logo_url' => (string) ($params['logo_url'] ?? ''),
                    'tagline'  => (string) ($params['tagline'] ?? ''),
                ];
                return $result->setData($this->coreService->saveCoBranding($branding));

            case 'indexnow_submit':
                $rawUrls = $params['urls'] ?? [];
                if (is_string($rawUrls)) {
                    $rawUrls = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $rawUrls) ?: []));
                }
                return $result->setData($this->coreService->submitIndexNowUrls(is_array($rawUrls) ? $rawUrls : [], 'manual'));

            default:
                return $result->setHttpResponseCode(400)->setData([
                    'ok'    => false,
                    'error' => 'unknown_action',
                ]);
        }
    }
}
