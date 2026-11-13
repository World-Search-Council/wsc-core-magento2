<?php

declare(strict_types=1);

namespace Wsc\Analytics\Model;

use Exception;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PDO;
use Psr\Log\LoggerInterface;
use Wsc\Analytics\Helper\Data as HelperData;

/**
 * Core business logic, AES-256-CBC/HMAC-SHA256 telemetry, WSC Member Credential Bridge,
 * AI Coverage Inspector, Dynamic Industry Benchmarks, /llms.txt & Opt-In IndexNow for Magento 2 (v2.2.3).
 */
class CoreService
{
    public const VERSION = '2.2.3';
    public const GATEWAY_DEFAULT = 'https://api.worldsearchcouncil.org';
    public const PORTAL_LICENSE_VERIFY_URL = 'https://www.worldsearchcouncil.org/api/v1/license/verify';
    public const PORTAL_PARTNERS_FEED_URL = 'https://www.worldsearchcouncil.org/api/v1/partners/featured';
    public const INDEXNOW_ENDPOINT = 'https://api.indexnow.org/indexnow';

    public const DEMO_KEYS = [
        'WSC-MEMBER-DEMO'  => ['tier' => 'member',           'holder' => 'Demo Member Account (Offline Sandbox)'],
        'WSC-AGENCY-DEMO'  => ['tier' => 'agency',           'holder' => 'Demo Partner Agency (Offline Sandbox)'],
        'WSC-PARTNER-DEMO' => ['tier' => 'founding_partner', 'holder' => 'Demo Founding Partner (Offline Sandbox)'],
    ];

    public const BENCHMARK_TAXONOMY = [
        '/Shopping/Apparel' => [
            'label'     => 'E-Commerce: Fashion, Apparel & Footwear',
            'icon'      => '👗',
            'keywords'  => ['mode', 'fashion', 'kleidung', 'schuhe', 'apparel', 'outfit', 'accessoire', 'style', 'dress', 'shirt', 'pants', 'damenmode', 'herrenmode'],
            'nodes'     => 142,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 8.5,  'p75' => 12.1, 'p90' => 17.4],
                'engaged_time'    => ['p50' => 58.0, 'p75' => 72.0, 'p90' => 94.0],
                'ai_coverage_pct' => ['p50' => 46.0, 'p75' => 62.5, 'p90' => 78.0],
                'human_ratio_pct' => ['p50' => 88.5, 'p75' => 93.2, 'p90' => 96.8],
            ],
        ],
        '/Shopping/Consumer Electronics' => [
            'label'     => 'E-Commerce: Consumer Electronics & Tech',
            'icon'      => '💻',
            'keywords'  => ['elektronik', 'electronics', 'computer', 'hardware', 'gadget', 'smartphone', 'tech', 'audio', 'kabel', 'akku', 'display', 'laptop'],
            'nodes'     => 98,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 11.2, 'p75' => 15.4, 'p90' => 21.0],
                'engaged_time'    => ['p50' => 64.0, 'p75' => 82.0, 'p90' => 110.0],
                'ai_coverage_pct' => ['p50' => 52.0, 'p75' => 68.0, 'p90' => 84.5],
                'human_ratio_pct' => ['p50' => 86.0, 'p75' => 91.5, 'p90' => 95.0],
            ],
        ],
        '/Computers & Electronics/Enterprise Software' => [
            'label'     => 'IT, SaaS & Enterprise Software',
            'icon'      => '☁️',
            'keywords'  => ['saas', 'software', 'cloud', 'enterprise', 'api', 'plattform', 'platform', 'developer', 'automation', 'crm', 'erp'],
            'nodes'     => 86,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 18.4, 'p75' => 26.0, 'p90' => 36.5],
                'engaged_time'    => ['p50' => 85.0, 'p75' => 115.0, 'p90' => 155.0],
                'ai_coverage_pct' => ['p50' => 68.0, 'p75' => 82.0, 'p90' => 92.0],
                'human_ratio_pct' => ['p50' => 82.0, 'p75' => 89.0, 'p90' => 94.0],
            ],
        ],
        '/Health/Medical & Wellness' => [
            'label'     => 'Health, Medical & Wellness',
            'icon'      => '🩺',
            'keywords'  => ['gesundheit', 'health', 'medical', 'medizin', 'arzt', 'klinik', 'praxis', 'wellness', 'therapie', 'apotheke', 'pharma', 'fitness'],
            'nodes'     => 74,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 15.2, 'p75' => 21.5, 'p90' => 29.0],
                'engaged_time'    => ['p50' => 78.0, 'p75' => 98.0, 'p90' => 130.0],
                'ai_coverage_pct' => ['p50' => 59.0, 'p75' => 74.0, 'p90' => 88.0],
                'human_ratio_pct' => ['p50' => 89.0, 'p75' => 94.0, 'p90' => 97.2],
            ],
        ],
        '/Business & Industrial/Services' => [
            'label'     => 'B2B, Consulting & Professional Services',
            'icon'      => '🏢',
            'keywords'  => ['beratung', 'consulting', 'handwerk', 'b2b', 'agentur', 'agency', 'dienstleistung', 'service', 'industrie'],
            'nodes'     => 115,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 10.5, 'p75' => 16.0, 'p90' => 24.0],
                'engaged_time'    => ['p50' => 62.0, 'p75' => 84.0, 'p90' => 118.0],
                'ai_coverage_pct' => ['p50' => 48.0, 'p75' => 65.0, 'p90' => 81.0],
                'human_ratio_pct' => ['p50' => 87.0, 'p75' => 92.5, 'p90' => 96.0],
            ],
        ],
        '/Food & Drink/Restaurants & Groceries' => [
            'label'     => 'Food, Restaurants & Groceries',
            'icon'      => '🍽️',
            'keywords'  => ['restaurant', 'essen', 'food', 'lebensmittel', 'rezept', 'recipe', 'cafe', 'bar', 'wein', 'kaffee', 'gourmet'],
            'nodes'     => 62,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 6.4,  'p75' => 9.8,  'p90' => 14.5],
                'engaged_time'    => ['p50' => 44.0, 'p75' => 58.0, 'p90' => 76.0],
                'ai_coverage_pct' => ['p50' => 38.0, 'p75' => 51.0, 'p90' => 68.0],
                'human_ratio_pct' => ['p50' => 91.0, 'p75' => 95.2, 'p90' => 98.0],
            ],
        ],
        '/Home & Garden' => [
            'label'     => 'Furniture, Home & Garden',
            'icon'      => '🏡',
            'keywords'  => ['wohnen', 'moebel', 'furniture', 'garten', 'garden', 'haus', 'pflanzen', 'einrichtung', 'interieur', 'deko', 'kueche'],
            'nodes'     => 58,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 7.8,  'p75' => 11.5, 'p90' => 16.0],
                'engaged_time'    => ['p50' => 52.0, 'p75' => 68.0, 'p90' => 90.0],
                'ai_coverage_pct' => ['p50' => 41.0, 'p75' => 56.0, 'p90' => 73.0],
                'human_ratio_pct' => ['p50' => 89.0, 'p75' => 93.8, 'p90' => 97.0],
            ],
        ],
        '/News/Media & Publishing' => [
            'label'     => 'News, Media & Digital Publishing',
            'icon'      => '📰',
            'keywords'  => ['news', 'nachrichten', 'magazin', 'magazine', 'blog', 'zeitung', 'artikel', 'journalismus'],
            'nodes'     => 69,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 22.0, 'p75' => 31.0, 'p90' => 42.0],
                'engaged_time'    => ['p50' => 92.0, 'p75' => 128.0, 'p90' => 175.0],
                'ai_coverage_pct' => ['p50' => 74.0, 'p75' => 88.0, 'p90' => 96.0],
                'human_ratio_pct' => ['p50' => 81.0, 'p75' => 87.0, 'p90' => 92.5],
            ],
        ],
        '/Finance/Banking & Insurance' => [
            'label'     => 'Finance, Banking & Insurance',
            'icon'      => '💳',
            'keywords'  => ['finanzen', 'finance', 'bank', 'kredit', 'versicherung', 'insurance', 'investment', 'fintech'],
            'nodes'     => 54,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 12.0, 'p75' => 17.5, 'p90' => 25.0],
                'engaged_time'    => ['p50' => 70.0, 'p75' => 92.0, 'p90' => 124.0],
                'ai_coverage_pct' => ['p50' => 54.0, 'p75' => 70.0, 'p90' => 86.0],
                'human_ratio_pct' => ['p50' => 88.0, 'p75' => 93.0, 'p90' => 96.5],
            ],
        ],
        '/Travel/Tourism & Hospitality' => [
            'label'     => 'Travel, Tourism & Hospitality',
            'icon'      => '✈️',
            'keywords'  => ['reise', 'travel', 'urlaub', 'hotel', 'tourismus', 'flug', 'buchen', 'ferien', 'resort'],
            'nodes'     => 47,
            'baselines' => [
                'ai_rag_share'    => ['p50' => 9.6,  'p75' => 14.2, 'p90' => 20.5],
                'engaged_time'    => ['p50' => 66.0, 'p75' => 88.0, 'p90' => 116.0],
                'ai_coverage_pct' => ['p50' => 49.0, 'p75' => 66.0, 'p90' => 83.0],
                'human_ratio_pct' => ['p50' => 90.0, 'p75' => 94.5, 'p90' => 97.5],
            ],
        ],
    ];

    private HelperData $helper;
    private DatabaseManager $dbManager;
    private WriterInterface $configWriter;
    private CurlFactory $curlFactory;
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;
    private ?ReinitableConfigInterface $reinitableConfig;
    private ?ResourceConnection $resourceConnection;
    private ?ScopeConfigInterface $scopeConfig;

    /** @var array<string, mixed> In-request runtime config overrides to avoid stale ScopeConfig reads */
    private array $runtimeConfig = [];

    public function __construct(
        HelperData $helper,
        DatabaseManager $dbManager,
        WriterInterface $configWriter,
        CurlFactory $curlFactory,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ?ReinitableConfigInterface $reinitableConfig = null,
        ?ResourceConnection $resourceConnection = null,
        ?ScopeConfigInterface $scopeConfig = null
    ) {
        $this->helper = $helper;
        $this->dbManager = $dbManager;
        $this->configWriter = $configWriter;
        $this->curlFactory = $curlFactory;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->reinitableConfig = $reinitableConfig;
        $this->resourceConnection = $resourceConnection;
        $this->scopeConfig = $scopeConfig;
    }

    private function saveConfigValues(array $kv): void
    {
        foreach ($kv as $path => $val) {
            $strVal = is_bool($val) ? ($val ? '1' : '0') : (string) $val;
            $this->runtimeConfig[$path] = $strVal;
            $this->configWriter->save($path, $strVal);
        }
        if ($this->reinitableConfig !== null) {
            try {
                $this->reinitableConfig->reinit();
            } catch (\Throwable $e) {
                // Non-blocking config cache reinit
            }
        }
    }

    // =========================================================================
    // PAKET CMS-1: Gateway v2.3 Handshake, Ingest Envelope & Zero-PII Privacy
    // =========================================================================

    public function isHybridEnabled(?int $storeId = null): bool
    {
        if (array_key_exists(HelperData::XML_PATH_HYBRID_ENABLED, $this->runtimeConfig)) {
            return $this->runtimeConfig[HelperData::XML_PATH_HYBRID_ENABLED] === '1';
        }
        return $this->helper->isHybridEnabled($storeId);
    }

    public function getGatewayUrl(?int $storeId = null): string
    {
        $url = $this->helper->getGatewayUrl($storeId);
        return $url !== '' ? rtrim($url, '/') : self::GATEWAY_DEFAULT;
    }

    /**
     * Resolves canonical public Magento storefront base URL without trailing slash.
     */
    public function resolveSiteBaseUrl(?int $storeId = null, ?string $explicitBaseUrl = null): string
    {
        if (!empty($explicitBaseUrl)) {
            return rtrim(trim($explicitBaseUrl), '/');
        }

        try {
            $store = $this->storeManager->getStore($storeId);
            $baseUrl = (string) $store->getBaseUrl(UrlInterface::URL_TYPE_WEB);
            if ($baseUrl !== '') {
                return rtrim($baseUrl, '/');
            }
        } catch (\Throwable $e) {
            // Fallback below
        }

        return 'https://localhost';
    }

    /**
     * Get local AES-256 key & Gateway credentials.
     * Strictly opt-in: NEVER calls /v1/register unless $ensureRegistered is true AND hybrid_enabled is enabled.
     */
    public function getProjectCredentials(
        bool $ensureRegistered = false,
        ?int $storeId = null,
        ?string $siteBaseUrl = null
    ): array {
        $localKey = (string) ($this->runtimeConfig[HelperData::XML_PATH_LOCAL_KEY] ?? $this->helper->getLocalKey($storeId));
        $localKey = trim($localKey);
        if (strlen($localKey) !== 64 || !ctype_xdigit($localKey)) {
            $localKey = bin2hex(random_bytes(32)); // 64-char hex (256-bit AES key)
            $this->saveConfigValues([HelperData::XML_PATH_LOCAL_KEY => $localKey]);
        }

        $projectId = trim((string) ($this->runtimeConfig[HelperData::XML_PATH_PROJECT_ID] ?? $this->helper->getProjectId($storeId)));
        $apiSecret = trim((string) ($this->runtimeConfig[HelperData::XML_PATH_API_SECRET] ?? $this->helper->getApiSecret($storeId)));

        if ($ensureRegistered && ($projectId === '' || $apiSecret === '')) {
            if ($this->isHybridEnabled($storeId)) {
                $reg = $this->registerWithGateway($storeId, $siteBaseUrl, false);
                if (!empty($reg['project_id'])) {
                    $projectId = (string) $reg['project_id'];
                }
                if (!empty($reg['api_secret'])) {
                    $apiSecret = (string) $reg['api_secret'];
                }
            }
        }

        return [
            'project_id' => $projectId,
            'api_secret' => $apiSecret,
            'local_key'  => $localKey,
            'hybrid'     => $this->isHybridEnabled($storeId),
        ];
    }

    /**
     * Explicit Gateway v2.3 Handshake (`POST /v1/register`).
     * Persists BOTH `project_id` and `api_secret` in Magento system configuration.
     */
    public function registerWithGateway(
        ?int $storeId = null,
        ?string $siteBaseUrl = null,
        bool $force = false
    ): array {
        $creds = $this->getProjectCredentials(false, $storeId, $siteBaseUrl);
        if (!$force && !empty($creds['project_id']) && !empty($creds['api_secret'])) {
            return [
                'ok'         => true,
                'project_id' => $creds['project_id'],
                'api_secret' => $creds['api_secret'],
            ];
        }

        $baseUrl = $this->resolveSiteBaseUrl($storeId, $siteBaseUrl);
        $webhookUrl = $baseUrl . '/wsc/api/webhook';
        $endpoint = $this->getGatewayUrl($storeId) . '/v1/register';

        $payload = (string) json_encode([
            'domain'        => $baseUrl,
            'platform'      => 'magento2',
            'webhook_url'   => $webhookUrl,
            'local_aes_key' => $creds['local_key'],
            'version'       => self::VERSION,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $resp = $this->httpPostJson($endpoint, $payload, [], 12);
        if (in_array($resp['status'], [200, 201], true) && is_array($resp['json'])) {
            $newProjId = trim((string) ($resp['json']['project_id'] ?? ''));
            $newSecret = trim((string) ($resp['json']['api_secret'] ?? ''));
            if ($newProjId !== '' && $newSecret !== '') {
                $this->saveConfigValues([
                    HelperData::XML_PATH_PROJECT_ID => $newProjId,
                    HelperData::XML_PATH_API_SECRET => $newSecret,
                ]);
                return [
                    'ok'         => true,
                    'project_id' => $newProjId,
                    'api_secret' => $newSecret,
                ];
            }
        }

        $this->logger->warning('WSC Magento Gateway /v1/register deferred or failed (HTTP ' . $resp['status'] . ').');
        return [
            'ok'     => false,
            'status' => $resp['status'],
            'error'  => $resp['error'] ?? 'gateway_register_failed',
        ];
    }

    /**
     * Computes 100% cookieless, Zero-PII daily rotating session hash:
     * SHA256(IP + UA + gmdate('Y-m-d') + localKey)
     */
    public function computeSessionHash(string $rawIp, string $userAgent, ?int $storeId = null): string
    {
        $creds = $this->getProjectCredentials(false, $storeId);
        return hash('sha256', $rawIp . $userAgent . gmdate('Y-m-d') . $creds['local_key']);
    }

    /**
     * Enqueue event into local SQLite queue (Zero-PII: raw client IP is NEVER stored).
     */
    public function trackEvent(string $type, string $path = '/', ?string $referrer = '', array $meta = []): bool
    {
        try {
            $ua = (string) ($meta['user_agent'] ?? $meta['ua'] ?? '');
            if (isset($meta['ip'])) {
                $rawIp = (string) $meta['ip'];
                unset($meta['ip']);
                if ($rawIp !== '' && empty($meta['session_hash'])) {
                    $meta['session_hash'] = $this->computeSessionHash($rawIp, $ua);
                }
            }
            if (empty($meta['session_hash'])) {
                $meta['session_hash'] = $this->computeSessionHash('0.0.0.0', $ua);
            }

            $cleanPath = $path !== '' ? $path : '/';
            $now = time();

            $pdo = $this->dbManager->getQueuePdo();
            $stmt = $pdo->prepare("
                INSERT INTO wsc_events_queue (created_at, event_type, url_path, referrer, payload_json, synced)
                VALUES (:created_at, :event_type, :url_path, :referrer, :payload_json, 0)
            ");

            $stmt->execute([
                ':created_at'   => $now,
                ':event_type'   => $type,
                ':url_path'     => $cleanPath,
                ':referrer'     => $referrer ?: '',
                ':payload_json' => json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            $this->recordLocalOverviewEvent($type, $meta);

            return true;
        } catch (Exception $e) {
            $this->logger->error('WSC Magento Queue Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Records edge bot hit in both local SQLite reports (`wsc_bot_hits`, `wsc_bot_stats`) and queue (Zero-PII).
     */
    public function recordBotHit(
        array $bot,
        string $path,
        string $referrer = '',
        string $rawIp = '',
        string $userAgent = ''
    ): void {
        $sessionHash = $this->computeSessionHash($rawIp, $userAgent);
        $cleanPath = (string) (parse_url($path, PHP_URL_PATH) ?: '/');
        $now = time();
        $today = gmdate('Y-m-d', $now);

        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $stmt = $rPdo->prepare("
                INSERT INTO wsc_bot_hits (hit_at, bot_name, bot_category, bot_type, url_path, session_hash)
                VALUES (:hit_at, :bot_name, :bot_category, :bot_type, :url_path, :session_hash)
            ");
            $stmt->execute([
                ':hit_at'       => $now,
                ':bot_name'     => (string) ($bot['name'] ?? 'Unknown Bot'),
                ':bot_category' => (string) ($bot['category'] ?? 'other'),
                ':bot_type'     => (string) ($bot['type'] ?? 'other'),
                ':url_path'     => $cleanPath,
                ':session_hash' => $sessionHash,
            ]);

            $statStmt = $rPdo->prepare("
                INSERT INTO wsc_bot_stats (date, bot_name, bot_category, bot_type, hits, blocked)
                VALUES (:date, :bot_name, :bot_category, :bot_type, 1, 0)
                ON CONFLICT(date, bot_name) DO UPDATE SET hits = hits + 1
            ");
            $statStmt->execute([
                ':date'         => $today,
                ':bot_name'     => (string) ($bot['name'] ?? 'Unknown Bot'),
                ':bot_category' => (string) ($bot['category'] ?? 'other'),
                ':bot_type'     => (string) ($bot['type'] ?? 'other'),
            ]);
        } catch (Exception $e) {
            $this->logger->warning('WSC Magento Bot Hit SQLite Error: ' . $e->getMessage());
        }

        $this->trackEvent('bot_hit', $cleanPath, $referrer, [
            'bot_name'     => $bot['name'] ?? 'Unknown Bot',
            'bot_category' => $bot['category'] ?? 'other',
            'bot_type'     => $bot['type'] ?? 'other',
            'session_hash' => $sessionHash,
            'user_agent'   => $userAgent,
        ]);
    }

    private function recordLocalOverviewEvent(string $type, array $meta): void
    {
        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $today = gmdate('Y-m-d');
            $rPdo->prepare("INSERT OR IGNORE INTO wsc_daily_overview (date) VALUES (:d)")->execute([':d' => $today]);

            if ($type === 'pageview') {
                $rPdo->prepare("UPDATE wsc_daily_overview SET human_pageviews = human_pageviews + 1, human_visitors = human_visitors + 1 WHERE date = :d")
                     ->execute([':d' => $today]);
            } elseif ($type === 'bot_hit') {
                $bType = (string) ($meta['bot_type'] ?? '');
                $ragInc = ($bType === 'rag') ? 1 : 0;
                $trainInc = ($bType === 'training') ? 1 : 0;
                $rPdo->prepare("UPDATE wsc_daily_overview SET bot_hits = bot_hits + 1, ai_rag_hits = ai_rag_hits + :rag, ai_training_hits = ai_training_hits + :tr WHERE date = :d")
                     ->execute([':rag' => $ragInc, ':tr' => $trainInc, ':d' => $today]);
            } elseif ($type === 'purchase') {
                $rev = (float) ($meta['grand_total'] ?? $meta['amount_total'] ?? 0.0);
                $rPdo->prepare("UPDATE wsc_daily_overview SET orders_count = orders_count + 1, revenue_total = revenue_total + :rev WHERE date = :d")
                     ->execute([':rev' => $rev, ':d' => $today]);
            }
        } catch (\Throwable $e) {
            // Non-blocking local counter
        }
    }

    /**
     * Export micro-batch events using Canonical Gateway v2.3 Envelope (`POST /v1/ingest`):
     * - Inner batch encrypted via AES-256-CBC (hex2bin($localKey), 16-byte random IV, OPENSSL_RAW_DATA)
     * - Outer envelope signed via HMAC-SHA256 (`X-WSC-Sig`) using `$apiSecret`
     */
    public function processBatchExport(
        int $batchSize = 500,
        ?int $storeId = null,
        ?string $siteBaseUrl = null
    ): int {
        if (!$this->isHybridEnabled($storeId)) {
            return 0;
        }

        $creds = $this->getProjectCredentials(true, $storeId, $siteBaseUrl);
        if (empty($creds['project_id']) || empty($creds['api_secret']) || strlen($creds['local_key']) !== 64) {
            return 0;
        }

        $pdo = $this->dbManager->getQueuePdo();
        $stmt = $pdo->prepare("SELECT * FROM wsc_events_queue WHERE synced = 0 ORDER BY id ASC LIMIT :limit");
        $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return 0;
        }

        $mode = $this->helper->getTrackingMode($storeId) ?: 'cookieless';
        $keyBinary = hex2bin($creds['local_key']);
        if ($keyBinary === false) {
            return 0;
        }

        $ids = [];
        $grouped = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
            $meta = json_decode((string) $row['payload_json'], true) ?: [];
            $sessionHash = (string) ($meta['session_hash'] ?? hash('sha256', '0.0.0.0' . gmdate('Y-m-d') . $creds['local_key']));
            $ua = (string) ($meta['user_agent'] ?? '');
            unset($meta['ip'], $meta['session_hash'], $meta['user_agent']);

            $groupKey = $sessionHash . '|' . md5($ua);
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'ip_hash' => $sessionHash,
                    'ua'      => $ua,
                    'events'  => [],
                ];
            }

            $grouped[$groupKey]['events'][] = [
                't' => (int) $row['created_at'],
                'e' => (string) $row['event_type'],
                'p' => (string) $row['url_path'],
                'r' => (string) ($row['referrer'] ?? ''),
                'm' => $meta,
            ];
        }

        $encryptedBatches = [];
        foreach ($grouped as $group) {
            $cleartextBatch = json_encode([
                'mode'        => $mode,
                'server_data' => [
                    'ip'      => $group['ip_hash'],
                    'ua'      => $group['ua'],
                    'headers' => [],
                ],
                'events'      => $group['events'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $iv = random_bytes(16);
            $ciphertext = openssl_encrypt((string) $cleartextBatch, 'AES-256-CBC', $keyBinary, OPENSSL_RAW_DATA, $iv);
            if ($ciphertext !== false) {
                $encryptedBatches[] = base64_encode($iv . $ciphertext);
            }
        }

        if (empty($encryptedBatches)) {
            return 0;
        }

        $outerJson = (string) json_encode([
            'project_id' => $creds['project_id'],
            'timestamp'  => time(),
            'source'     => 'wsc-magento2',
            'version'    => self::VERSION,
            'data'       => $encryptedBatches,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signature = hash_hmac('sha256', $outerJson, $creds['api_secret']);
        $url = $this->getGatewayUrl($storeId) . '/v1/ingest';

        $resp = $this->httpPostJson($url, $outerJson, [
            'X-WSC-Sig' => $signature,
        ], 15);

        if (in_array($resp['status'], [200, 201, 202], true)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $upd = $pdo->prepare("UPDATE wsc_events_queue SET synced = 1 WHERE id IN ($placeholders)");
            $upd->execute($ids);
            return count($ids);
        }

        $this->logger->warning('WSC Magento Ingest Sync HTTP ' . $resp['status']);
        return 0;
    }

    /**
     * Verifies inbound Gateway Webhook `X-WSC-Sig` against `$apiSecret`
     * (falling back to `$localKey` ONLY when `$apiSecret` has not yet been provisioned).
     */
    public function verifyWebhookSignature(string $rawBody, string $signature, ?int $storeId = null): bool
    {
        if ($signature === '' || $rawBody === '') {
            return false;
        }

        $creds = $this->getProjectCredentials(false, $storeId);
        $signingSecret = !empty($creds['api_secret']) ? $creds['api_secret'] : $creds['local_key'];
        if ($signingSecret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $signingSecret);
        return hash_equals($expected, $signature);
    }

    /**
     * SSRF Hardening: Validates that a target URL belongs to the registered site host
     * and resolves strictly to public non-private/non-reserved IP addresses.
     */
    public function validateSameHostPublicUrl(string $targetUrl, string $siteBaseUrl): array
    {
        $parsedTarget = parse_url(trim($targetUrl));
        $parsedSite = parse_url(trim($siteBaseUrl));

        $scheme = strtolower((string) ($parsedTarget['scheme'] ?? ''));
        $targetHost = strtolower((string) ($parsedTarget['host'] ?? ''));
        $siteHost = strtolower((string) ($parsedSite['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true) || $targetHost === '') {
            return ['valid' => false, 'error' => 'Invalid URL scheme or host.'];
        }

        if ($siteHost !== '' && $targetHost !== $siteHost) {
            return ['valid' => false, 'error' => 'Target URL must match the registered site hostname (' . $siteHost . ').'];
        }

        if (!$this->hostResolvesToPublicIp($targetHost)) {
            return ['valid' => false, 'error' => 'Target hostname resolves to a private, loopback, or reserved IP address (SSRF protection).'];
        }

        return ['valid' => true, 'url' => trim($targetUrl), 'host' => $targetHost];
    }

    public function hostResolvesToPublicIp(string $host): bool
    {
        $cleanHost = trim($host, '[]');
        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            return filter_var($cleanHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        $ips = [];
        $records = @dns_get_record($cleanHost, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $rec) {
                if (!empty($rec['ip'])) {
                    $ips[] = $rec['ip'];
                }
                if (!empty($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }

        if (empty($ips)) {
            $v4 = @gethostbynamel($cleanHost);
            if (is_array($v4)) {
                $ips = array_merge($ips, $v4);
            }
        }

        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Save webhook report payload.
     */
    public function saveWebhookReport(array $data): bool
    {
        try {
            $pdo = $this->dbManager->getReportsPdo();
            $stmt = $pdo->prepare("
                INSERT INTO wsc_dashboard_reports (id, report_type, period_type, updated_at, payload)
                VALUES (:id, :report_type, :period_type, :updated_at, :payload)
                ON CONFLICT(id) DO UPDATE SET
                    updated_at = excluded.updated_at,
                    payload = excluded.payload
            ");

            $now = time();

            if (!empty($data['insights']) && is_array($data['insights'])) {
                foreach ($data['insights'] as $item) {
                    $type = (string) ($item['report_type'] ?? $item['type'] ?? 'unknown');
                    $stmt->execute([
                        ':id'          => $type,
                        ':report_type' => $type,
                        ':period_type' => 'multi',
                        ':updated_at'  => $now,
                        ':payload'     => json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            $cardMappings = [
                'card1'           => ['traffic',    $data['card1'] ?? ($data['card1_traffic'] ?? null)],
                'card1_traffic'   => ['traffic',    $data['card1_traffic'] ?? ($data['card1'] ?? null)],
                'card2'           => ['insights',   $data['card2'] ?? ($data['card2_insights'] ?? null)],
                'card2_insights'  => ['insights',   $data['card2_insights'] ?? ($data['card2'] ?? null)],
                'card3'           => ['bots',       $data['card3'] ?? ($data['card3_bots'] ?? null)],
                'card3_bots'      => ['bots',       $data['card3_bots'] ?? ($data['card3'] ?? null)],
                'card_engagement' => ['engagement', $data['card_engagement'] ?? ($data['card4'] ?? null)],
                'card_outbound'   => ['outbound',   $data['card_outbound'] ?? ($data['card5'] ?? null)],
                'card_ecommerce'  => ['ecommerce',  $data['card_ecommerce'] ?? ($data['card6'] ?? null)],
                'domain_health'   => ['sentinel',   $data['domain_health'] ?? ($data['sentinel'] ?? ($data['card7'] ?? null))],
                'pagespeed'       => ['pagespeed',  $data['pagespeed'] ?? ($data['pagespeed_audit'] ?? null)],
                'benchmarks'      => ['benchmarks', $data['benchmarks'] ?? null],
            ];

            foreach ($cardMappings as $cardId => [$type, $payload]) {
                if ($payload !== null) {
                    $stmt->execute([
                        ':id'          => $cardId,
                        ':report_type' => $type,
                        ':period_type' => 'multi',
                        ':updated_at'  => $now,
                        ':payload'     => is_string($payload) ? $payload : (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error('WSC Webhook Save Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve all 7 Canonical Cards + Benchmarks for Dashboard view.
     */
    public function getDashboardCards(): array
    {
        $cards = [
            'card1'           => null,
            'card2'           => null,
            'card3'           => null,
            'card1_traffic'   => null,
            'card2_insights'  => null,
            'card3_bots'      => null,
            'card_engagement' => null,
            'card_outbound'   => null,
            'card_ecommerce'  => null,
            'domain_health'   => null,
            'pagespeed'       => null,
            'benchmarks'      => null,
        ];

        try {
            $pdo = $this->dbManager->getReportsPdo();
            $stmt = $pdo->query("SELECT id, payload FROM wsc_dashboard_reports");
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $id = (string) $row['id'];
                $decoded = json_decode((string) $row['payload'], true);

                if (array_key_exists($id, $cards)) {
                    $cards[$id] = $decoded;
                } elseif ($id === 'card4') {
                    $cards['card_engagement'] = $decoded;
                } elseif ($id === 'card5') {
                    $cards['card_outbound'] = $decoded;
                } elseif ($id === 'card6') {
                    $cards['card_ecommerce'] = $decoded;
                } elseif ($id === 'sentinel' || $id === 'card7') {
                    $cards['domain_health'] = $decoded;
                } elseif ($id === 'pagespeed_audit') {
                    $cards['pagespeed'] = $decoded;
                }

                if ($id === 'card1' && empty($cards['card1_traffic'])) {
                    $cards['card1_traffic'] = $decoded;
                }
                if ($id === 'card1_traffic' && empty($cards['card1'])) {
                    $cards['card1'] = $decoded;
                }
                if ($id === 'card2' && empty($cards['card2_insights'])) {
                    $cards['card2_insights'] = $decoded;
                }
                if ($id === 'card2_insights' && empty($cards['card2'])) {
                    $cards['card2'] = $decoded;
                }
                if ($id === 'card3' && empty($cards['card3_bots'])) {
                    $cards['card3_bots'] = $decoded;
                }
                if ($id === 'card3_bots' && empty($cards['card3'])) {
                    $cards['card3'] = $decoded;
                }
            }
        } catch (Exception $e) {
            $this->logger->error('WSC Read Cards Error: ' . $e->getMessage());
        }

        return $cards;
    }

    /**
     * Trigger SpeedSentinel 2.0 Lab Audit from Magento 2 (SSRF-hardened + apiSecret HMAC).
     */
    public function triggerSpeedSentinelAudit(
        string $url,
        string $strategy = 'mobile',
        ?int $storeId = null,
        ?string $siteBaseUrl = null
    ): array {
        $baseUrl = $this->resolveSiteBaseUrl($storeId, $siteBaseUrl);
        $check = $this->validateSameHostPublicUrl($url !== '' ? $url : $baseUrl, $baseUrl);
        if (!$check['valid']) {
            return ['ok' => false, 'error' => 'ssrf_validation_failed', 'message' => $check['error']];
        }

        $creds = $this->getProjectCredentials(true, $storeId, $baseUrl);
        if (empty($creds['project_id']) || empty($creds['api_secret'])) {
            return [
                'ok'      => false,
                'error'   => 'hybrid_not_connected',
                'message' => 'Enable Hybrid Sync in Stores -> Configuration -> World Search Council to dispatch remote SpeedSentinel audits.',
            ];
        }

        $payload = (string) json_encode([
            'project_id' => $creds['project_id'],
            'url'        => $check['url'],
            'strategy'   => in_array($strategy, ['mobile', 'desktop'], true) ? $strategy : 'mobile',
            'sync'       => false,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $sig = hash_hmac('sha256', $payload, $creds['api_secret']);
        $endpoint = $this->getGatewayUrl($storeId) . '/v1/pagespeed/audit';

        $resp = $this->httpPostJson($endpoint, $payload, ['X-WSC-Sig' => $sig], 15);
        if (in_array($resp['status'], [200, 201, 202], true)) {
            return is_array($resp['json']) ? array_merge(['ok' => true], $resp['json']) : ['ok' => true, 'status' => 'queued'];
        }

        return ['ok' => false, 'error' => $resp['error'] ?? ('HTTP ' . $resp['status'])];
    }

    /**
     * Trigger Sentinel Domain Health Scan from Magento 2 (SSRF-hardened + apiSecret HMAC).
     */
    public function triggerSentinelScan(
        string $domain,
        ?int $storeId = null,
        ?string $siteBaseUrl = null
    ): array {
        $baseUrl = $this->resolveSiteBaseUrl($storeId, $siteBaseUrl);
        $siteHost = strtolower((string) (parse_url($baseUrl, PHP_URL_HOST) ?: ''));
        $cleanDomain = strtolower(trim((string) preg_replace('#^https?://#i', '', $domain), '/'));
        if ($cleanDomain === '') {
            $cleanDomain = $siteHost;
        }

        if ($siteHost !== '' && $cleanDomain !== $siteHost) {
            return [
                'ok'      => false,
                'error'   => 'domain_mismatch',
                'message' => 'Sentinel scans are restricted to the registered storefront hostname (' . $siteHost . ').',
            ];
        }

        if (!$this->hostResolvesToPublicIp($cleanDomain)) {
            return [
                'ok'      => false,
                'error'   => 'ssrf_validation_failed',
                'message' => 'Domain resolves to a local/private IP address and cannot be audited by cloud workers.',
            ];
        }

        $creds = $this->getProjectCredentials(true, $storeId, $baseUrl);
        if (empty($creds['project_id']) || empty($creds['api_secret'])) {
            return [
                'ok'      => false,
                'error'   => 'hybrid_not_connected',
                'message' => 'Enable Hybrid Sync in Stores -> Configuration -> World Search Council to dispatch remote Sentinel scans.',
            ];
        }

        $payload = (string) json_encode([
            'project_id' => $creds['project_id'],
            'domain'     => $cleanDomain,
            'sync'       => false,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $sig = hash_hmac('sha256', $payload, $creds['api_secret']);
        $endpoint = $this->getGatewayUrl($storeId) . '/v1/sentinel/scan';

        $resp = $this->httpPostJson($endpoint, $payload, ['X-WSC-Sig' => $sig], 15);
        if (in_array($resp['status'], [200, 201, 202], true)) {
            return is_array($resp['json']) ? array_merge(['ok' => true], $resp['json']) : ['ok' => true, 'status' => 'queued'];
        }

        return ['ok' => false, 'error' => $resp['error'] ?? ('HTTP ' . $resp['status'])];
    }

    // =========================================================================
    // PAKET CMS-2: Live WSC Member Credential Bridge, Co-Branding & Partner Feed
    // =========================================================================

    public function getMembershipState(?int $storeId = null): array
    {
        $key = strtoupper(trim((string) ($this->runtimeConfig[HelperData::XML_PATH_LICENSE_KEY] ?? $this->helper->getLicenseKey($storeId))));
        $status = strtolower(trim((string) ($this->runtimeConfig[HelperData::XML_PATH_LICENSE_STATUS] ?? $this->helper->getLicenseStatus($storeId)))) ?: 'unregistered';
        if (!in_array($status, ['unregistered', 'active', 'expired', 'revoked', 'sanctioned'], true)) {
            $status = 'unregistered';
        }

        $tier = strtolower(trim((string) ($this->runtimeConfig[HelperData::XML_PATH_LICENSE_TIER] ?? $this->helper->getLicenseTier($storeId)))) ?: 'community';
        if (!in_array($tier, ['community', 'member', 'agency', 'founding_partner'], true)) {
            $tier = 'community';
        }

        $holder = trim((string) ($this->runtimeConfig[HelperData::XML_PATH_LICENSE_HOLDER] ?? $this->helper->getLicenseHolder($storeId)));
        $expires = (int) ($this->runtimeConfig[HelperData::XML_PATH_LICENSE_EXPIRES] ?? $this->helper->getLicenseExpires($storeId));
        $rawDemo = $this->runtimeConfig[HelperData::XML_PATH_LICENSE_IS_DEMO] ?? null;
        $isDemo = ($rawDemo !== null ? ($rawDemo === '1') : $this->helper->isLicenseDemo($storeId)) || isset(self::DEMO_KEYS[$key]);

        if ($status === 'active' && $expires > 0 && $expires < time()) {
            $status = 'expired';
        }

        $isActiveLicensed = ($key !== '' && $status === 'active' && in_array($tier, ['member', 'agency', 'founding_partner'], true));
        $isAgencyTier = $isActiveLicensed && in_array($tier, ['agency', 'founding_partner'], true);

        $tierLabels = [
            'community'        => 'Community (Open Source)',
            'member'           => 'Verified WSC Member',
            'agency'           => 'Certified WSC Partner (Agency)',
            'founding_partner' => 'WSC Founding Partner',
        ];

        $rawBranding = (string) ($this->runtimeConfig[HelperData::XML_PATH_CO_BRANDING] ?? $this->helper->getCoBrandingRaw($storeId));
        $decodedBranding = json_decode($rawBranding, true);
        $coBranding = $this->normalizeCoBranding(is_array($decodedBranding) ? $decodedBranding : []);

        return [
            'key'                => $key,
            'status'             => $status,
            'tier'               => $tier,
            'tier_label'         => $tierLabels[$tier] ?? $tierLabels['community'],
            'holder'             => $holder,
            'expires_at'         => $expires,
            'is_demo'            => $isDemo,
            'is_active_licensed' => $isActiveLicensed,
            'is_agency_tier'     => $isAgencyTier,
            'verify_url'         => $key !== '' ? ('https://www.worldsearchcouncil.org/verify/' . rawurlencode($key)) : '',
            'member_console_url' => 'https://www.worldsearchcouncil.org/member',
            'co_branding'        => $coBranding,
        ];
    }

    private function normalizeCoBranding(array $input): array
    {
        $logoUrl = trim((string) ($input['logo_url'] ?? $input['agency_logo'] ?? ''));
        $supportUrl = trim((string) ($input['support_url'] ?? $input['agency_url'] ?? ''));
        $email = trim((string) ($input['support_email'] ?? ''));

        return [
            'enabled'       => !empty($input['enabled']),
            'agency_name'   => strip_tags(trim((string) ($input['agency_name'] ?? ''))),
            'logo_url'      => (filter_var($logoUrl, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $logoUrl)) ? $logoUrl : '',
            'support_url'   => (filter_var($supportUrl, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $supportUrl)) ? $supportUrl : '',
            'support_email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
            'custom_footer' => strip_tags(trim((string) ($input['custom_footer'] ?? $input['custom_notice'] ?? ''))),
        ];
    }

    /**
     * Verifies license key against `POST https://www.worldsearchcouncil.org/api/v1/license/verify`.
     * Offline fallback applies STRICTLY to `WSC-MEMBER-DEMO`, `WSC-AGENCY-DEMO`, `WSC-PARTNER-DEMO`.
     */
    public function verifyLicense(
        string $rawKey,
        ?int $storeId = null,
        ?string $siteBaseUrl = null,
        ?array $coBrandingUpdate = null
    ): array {
        $key = strtoupper(trim($rawKey));
        if ($key === '') {
            return $this->disconnectLicense($storeId);
        }

        $baseUrl = $this->resolveSiteBaseUrl($storeId, $siteBaseUrl);
        $creds = $this->getProjectCredentials(false, $storeId, $baseUrl);

        $payloadArr = [
            'license_key' => $key,
            'domain'      => $baseUrl,
            'site_url'    => $baseUrl,
            'platform'    => 'magento2',
            'version'     => self::VERSION,
            'wsc_version' => self::VERSION,
            'project_id'  => $creds['project_id'],
        ];

        if ($coBrandingUpdate !== null) {
            $payloadArr['co_branding_update'] = $coBrandingUpdate;
        }

        $resp = $this->httpPostJson(
            self::PORTAL_LICENSE_VERIFY_URL,
            (string) json_encode($payloadArr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ['User-Agent' => 'WSC-Magento2/' . self::VERSION],
            12
        );

        // Network unreachable or 5xx: Offline sandbox fallback strictly for official DEMO keys
        if ($resp['status'] === 0 || $resp['status'] >= 500 || !is_array($resp['json'])) {
            if (isset(self::DEMO_KEYS[$key])) {
                return $this->activateOfflineDemoKey($key, $storeId);
            }
            return [
                'ok'            => false,
                'success'       => false,
                'network_error' => true,
                'message'       => 'Could not reach WSC Authority Portal (www.worldsearchcouncil.org). Please check connectivity.',
                'membership'    => $this->getMembershipState($storeId),
            ];
        }

        $body = $resp['json'];
        $remoteStatus = strtolower(trim((string) ($body['status'] ?? '')));
        $isValid = !empty($body['valid']) && ($remoteStatus === '' || $remoteStatus === 'active');

        if ($isValid) {
            $rawTier = strtolower(trim((string) ($body['tier'] ?? 'member')));
            $tier = in_array($rawTier, ['member', 'agency', 'founding_partner'], true) ? $rawTier : 'member';
            $holder = strip_tags(trim((string) ($body['holder'] ?? 'Verified WSC Member')));
            $expires = isset($body['expires_at']) ? (int) $body['expires_at'] : (time() + 31536000);
            $isDemo = !empty($body['is_demo']) || isset(self::DEMO_KEYS[$key]);

            $toSave = [
                HelperData::XML_PATH_LICENSE_KEY     => $key,
                HelperData::XML_PATH_LICENSE_STATUS  => 'active',
                HelperData::XML_PATH_LICENSE_TIER    => $tier,
                HelperData::XML_PATH_LICENSE_HOLDER  => $holder,
                HelperData::XML_PATH_LICENSE_EXPIRES => (string) $expires,
                HelperData::XML_PATH_LICENSE_IS_DEMO => $isDemo ? '1' : '0',
            ];

            if (!empty($body['co_branding']) && is_array($body['co_branding'])) {
                $existing = $this->getMembershipState($storeId)['co_branding'];
                $merged = $this->normalizeCoBranding(array_merge($existing, $body['co_branding'], ['enabled' => true]));
                $toSave[HelperData::XML_PATH_CO_BRANDING] = (string) json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $this->saveConfigValues($toSave);
            $state = $this->getMembershipState($storeId);

            return [
                'ok'         => true,
                'success'    => true,
                'message'    => $isDemo
                    ? 'Verified in Demo Sandbox Mode (' . $state['tier_label'] . ').'
                    : 'WSC Credential verified: ' . $state['tier_label'] . ' (' . $holder . ').',
                'membership' => $state,
            ];
        }

        if (isset(self::DEMO_KEYS[$key]) && $remoteStatus === '') {
            return $this->activateOfflineDemoKey($key, $storeId);
        }

        $mappedStatus = in_array($remoteStatus, ['expired', 'revoked', 'sanctioned'], true) ? $remoteStatus : 'unregistered';
        $errMsg = strip_tags(trim((string) ($body['message'] ?? 'Invalid or unrecognized WSC credential key.')));

        $this->saveConfigValues([
            HelperData::XML_PATH_LICENSE_KEY     => $mappedStatus === 'unregistered' ? '' : $key,
            HelperData::XML_PATH_LICENSE_STATUS  => $mappedStatus,
            HelperData::XML_PATH_LICENSE_TIER    => 'community',
            HelperData::XML_PATH_LICENSE_IS_DEMO => '0',
        ]);

        return [
            'ok'         => false,
            'success'    => false,
            'status'     => $mappedStatus,
            'message'    => $errMsg,
            'membership' => $this->getMembershipState($storeId),
        ];
    }

    private function activateOfflineDemoKey(string $key, ?int $storeId = null): array
    {
        $meta = self::DEMO_KEYS[$key];
        $expires = time() + 31536000;

        $this->saveConfigValues([
            HelperData::XML_PATH_LICENSE_KEY     => $key,
            HelperData::XML_PATH_LICENSE_STATUS  => 'active',
            HelperData::XML_PATH_LICENSE_TIER    => $meta['tier'],
            HelperData::XML_PATH_LICENSE_HOLDER  => $meta['holder'],
            HelperData::XML_PATH_LICENSE_EXPIRES => (string) $expires,
            HelperData::XML_PATH_LICENSE_IS_DEMO => '1',
        ]);

        $state = $this->getMembershipState($storeId);
        return [
            'ok'         => true,
            'success'    => true,
            'is_demo'    => true,
            'message'    => 'Activated in Offline Demo Sandbox Mode (' . $state['tier_label'] . ').',
            'membership' => $state,
        ];
    }

    public function disconnectLicense(?int $storeId = null): array
    {
        $this->saveConfigValues([
            HelperData::XML_PATH_LICENSE_KEY     => '',
            HelperData::XML_PATH_LICENSE_STATUS  => 'unregistered',
            HelperData::XML_PATH_LICENSE_TIER    => 'community',
            HelperData::XML_PATH_LICENSE_HOLDER  => '',
            HelperData::XML_PATH_LICENSE_EXPIRES => '0',
            HelperData::XML_PATH_LICENSE_IS_DEMO => '0',
        ]);

        return [
            'ok'         => true,
            'success'    => true,
            'message'    => 'Credential disconnected. Reverted to Open-Source Community tier (all local features remain unlocked).',
            'membership' => $this->getMembershipState($storeId),
        ];
    }

    public function saveCoBranding(array $brandingInput, ?int $storeId = null, ?string $siteBaseUrl = null): array
    {
        $normalized = $this->normalizeCoBranding($brandingInput);
        $this->saveConfigValues([
            HelperData::XML_PATH_CO_BRANDING => (string) json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $state = $this->getMembershipState($storeId);
        if ($state['is_agency_tier'] && !$state['is_demo'] && $state['key'] !== '') {
            $this->verifyLicense($state['key'], $storeId, $siteBaseUrl, $normalized);
            $state = $this->getMembershipState($storeId);
        }

        return [
            'ok'          => true,
            'success'     => true,
            'message'     => 'Agency Co-Branding configuration saved.',
            'co_branding' => $normalized,
            'membership'  => $state,
        ];
    }

    /**
     * Retrieves featured WSC Partners (`GET https://www.worldsearchcouncil.org/api/v1/partners/featured`),
     * cached 12h in SQLite when licensed or `hybrid_enabled`, curated local fallback otherwise.
     */
    public function getFeaturedPartners(int $limit = 3, ?int $storeId = null): array
    {
        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $stmt = $rPdo->prepare("SELECT updated_at, payload FROM wsc_dashboard_reports WHERE id = 'partners_feed'");
            $stmt->execute();
            $cached = $stmt->fetch();
            if ($cached && ((int) $cached['updated_at'] >= (time() - 43200))) {
                $decoded = json_decode((string) $cached['payload'], true);
                if (is_array($decoded) && !empty($decoded)) {
                    return array_slice($decoded, 0, $limit);
                }
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        $membership = $this->getMembershipState($storeId);
        $canFetchRemote = $membership['is_active_licensed'] || $this->isHybridEnabled($storeId);

        if ($canFetchRemote) {
            $resp = $this->httpGetJson(self::PORTAL_PARTNERS_FEED_URL . '?limit=6', 6);
            if ($resp['status'] === 200 && is_array($resp['json']) && !empty($resp['json']['partners']) && is_array($resp['json']['partners'])) {
                $normalized = [];
                foreach ($resp['json']['partners'] as $item) {
                    if (empty($item['name'])) {
                        continue;
                    }
                    $slug = (string) preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($item['slug'] ?? str_replace(' ', '-', (string) $item['name']))));
                    $profileUrl = !empty($item['profile_url']) ? (string) $item['profile_url'] : ('https://www.worldsearchcouncil.org/partner/' . $slug);
                    $normalized[] = [
                        'name'        => strip_tags((string) $item['name']),
                        'slug'        => $slug,
                        'specialty'   => strip_tags((string) ($item['specialty'] ?? 'GEO & Search Health')),
                        'location'    => strip_tags((string) ($item['location'] ?? 'Global')),
                        'rating'      => strip_tags((string) ($item['rating'] ?? '5.0')),
                        'badge'       => strip_tags((string) ($item['badge'] ?? 'Certified Partner')),
                        'profile_url' => $profileUrl,
                    ];
                }

                if (!empty($normalized)) {
                    $this->saveInternalReportCache('partners_feed', 'partners', $normalized);
                    return array_slice($normalized, 0, $limit);
                }
            }
        }

        return array_slice([
            [
                'name'        => 'Apex Search Architecture',
                'slug'        => 'apex-search-architecture',
                'specialty'   => 'Enterprise Magento & Adobe Commerce GEO',
                'location'    => 'London, UK',
                'rating'      => '5.0',
                'badge'       => 'Founding Partner',
                'profile_url' => 'https://www.worldsearchcouncil.org/partner/apex-search-architecture?ref=magento2',
            ],
            [
                'name'        => 'Search Sentinel Labs',
                'slug'        => 'search-sentinel-labs',
                'specialty'   => 'AI Bot-Defense & Core Web Vitals',
                'location'    => 'Cologne, DE',
                'rating'      => '5.0',
                'badge'       => 'Founding Partner',
                'profile_url' => 'https://www.worldsearchcouncil.org/partner/search-sentinel-labs?ref=magento2',
            ],
            [
                'name'        => 'IndexWave Digital',
                'slug'        => 'indexwave-digital',
                'specialty'   => 'Schema 2.0 & E-Commerce Reality Matrix',
                'location'    => 'Zurich, CH',
                'rating'      => '4.9',
                'badge'       => 'Certified Partner',
                'profile_url' => 'https://www.worldsearchcouncil.org/partner/indexwave-digital?ref=magento2',
            ],
        ], 0, $limit);
    }

    // =========================================================================
    // PAKET CMS-3.1: AI Coverage Inspector (`POST /v1/ai-coverage/query`)
    // =========================================================================

    public function queryAiCoverage(
        string $rawQuery,
        string $timeframe = '30d',
        ?int $storeId = null,
        ?string $siteBaseUrl = null
    ): array {
        $baseUrl = $this->resolveSiteBaseUrl($storeId, $siteBaseUrl);
        $canonical = $this->canonicalizeCoverageQuery($rawQuery, $baseUrl);
        $tf = in_array($timeframe, ['7d', '30d'], true) ? $timeframe : '30d';

        if ($this->isHybridEnabled($storeId)) {
            $creds = $this->getProjectCredentials(true, $storeId, $baseUrl);
            if (!empty($creds['project_id']) && !empty($creds['api_secret'])) {
                $payload = (string) json_encode([
                    'project_id' => $creds['project_id'],
                    'query'      => $canonical['canonical_url'],
                    'timeframe'  => $tf,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $sig = hash_hmac('sha256', $payload, $creds['api_secret']);
                $resp = $this->httpPostJson(
                    $this->getGatewayUrl($storeId) . '/v1/ai-coverage/query',
                    $payload,
                    ['X-WSC-Sig' => $sig],
                    10
                );

                if ($resp['status'] === 200 && is_array($resp['json']) && ($resp['json']['status'] ?? '') === 'success') {
                    $gw = $resp['json'];
                    $gw['ok'] = true;
                    $gw['source'] = 'gateway';
                    return $gw;
                }
            }
        }

        return $this->queryLocalAiCoverage($canonical);
    }

    private function canonicalizeCoverageQuery(string $query, string $siteBaseUrl): array
    {
        $raw = trim($query);
        $siteHost = strtolower((string) (parse_url($siteBaseUrl, PHP_URL_HOST) ?: ''));

        if (!str_starts_with($raw, 'http://') && !str_starts_with($raw, 'https://')) {
            if (str_starts_with($raw, '/')) {
                $raw = rtrim($siteBaseUrl, '/') . $raw;
            } elseif (str_contains($raw, '.')) {
                $raw = 'https://' . $raw;
            } else {
                $raw = rtrim($siteBaseUrl, '/') . '/' . ltrim($raw, '/');
            }
        }

        $parsed = parse_url($raw);
        $host = strtolower((string) ($parsed['host'] ?? $siteHost));
        $path = (string) ($parsed['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }

        return [
            'raw'             => $query,
            'canonical_url'   => $raw,
            'host'            => $host,
            'root_domain'     => $host,
            'url_path'        => $path,
            'is_cross_domain' => ($siteHost !== '' && $host !== $siteHost),
        ];
    }

    private function queryLocalAiCoverage(array $canonical): array
    {
        $now = time();
        $thirtyDaysAgo = $now - (30 * 86400);
        $sevenDaysAgo = $now - (7 * 86400);

        $urlMetrics = [
            'is_known_in_network' => false,
            'total_hits_30d'      => 0,
            'rag' => [
                'visited'         => false,
                'hits_7d'         => 0,
                'hits_30d'        => 0,
                'last_seen'       => null,
                'last_seen_human' => 'Never seen locally',
                'top_bots'        => [],
            ],
            'training' => [
                'visited'         => false,
                'hits_7d'         => 0,
                'hits_30d'        => 0,
                'last_seen'       => null,
                'last_seen_human' => 'Never seen locally',
                'top_bots'        => [],
            ],
        ];

        $domainCoverage = [
            'root_domain'           => $canonical['root_domain'],
            'known_urls_count'      => max(1, $this->estimateKnownUrlsCount()),
            'ai_covered_urls_count' => 0,
            'ai_coverage_pct'       => 0.0,
            'rag_coverage_pct'      => 0.0,
            'training_coverage_pct' => 0.0,
            'network_confidence'    => $canonical['is_cross_domain'] ? 'low' : 'local_sqlite',
        ];

        if ($canonical['is_cross_domain']) {
            return [
                'ok'              => true,
                'status'          => 'success',
                'source'          => 'local_offline_notice',
                'query'           => $canonical,
                'url_metrics'     => $urlMetrics,
                'domain_coverage' => $domainCoverage,
                'notice'          => 'Cross-domain AI visibility queries require Opt-In Hybrid Sync to query the global WSC sensor network.',
            ];
        }

        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $path = $canonical['url_path'];
            $altPath = $path !== '/' ? rtrim($path, '/') : '/';

            $stmt = $rPdo->prepare("
                SELECT bot_name, bot_type, hit_at
                FROM wsc_bot_hits
                WHERE (url_path = :p1 OR url_path = :p2)
                  AND hit_at >= :cutoff
                ORDER BY hit_at DESC
            ");
            $stmt->execute([':p1' => $path, ':p2' => $altPath, ':cutoff' => $thirtyDaysAgo]);
            $rows = $stmt->fetchAll();

            if (!empty($rows)) {
                $urlMetrics['is_known_in_network'] = true;
                $urlMetrics['total_hits_30d'] = count($rows);

                $ragBots = [];
                $trainBots = [];
                $rag7 = 0;
                $rag30 = 0;
                $train7 = 0;
                $train30 = 0;
                $lastRag = 0;
                $lastTrain = 0;

                foreach ($rows as $r) {
                    $bName = (string) $r['bot_name'];
                    $bType = (string) $r['bot_type'];
                    $hitAt = (int) $r['hit_at'];

                    if ($bType === 'rag') {
                        $rag30++;
                        if ($hitAt >= $sevenDaysAgo) {
                            $rag7++;
                        }
                        if ($hitAt > $lastRag) {
                            $lastRag = $hitAt;
                        }
                        $ragBots[$bName] = ($ragBots[$bName] ?? 0) + 1;
                    } elseif ($bType === 'training') {
                        $train30++;
                        if ($hitAt >= $sevenDaysAgo) {
                            $train7++;
                        }
                        if ($hitAt > $lastTrain) {
                            $lastTrain = $hitAt;
                        }
                        $trainBots[$bName] = ($trainBots[$bName] ?? 0) + 1;
                    }
                }

                arsort($ragBots);
                arsort($trainBots);

                $topRag = [];
                foreach (array_slice($ragBots, 0, 5, true) as $n => $h) {
                    $topRag[] = ['name' => $n, 'hits' => $h];
                }
                $topTrain = [];
                foreach (array_slice($trainBots, 0, 5, true) as $n => $h) {
                    $topTrain[] = ['name' => $n, 'hits' => $h];
                }

                $urlMetrics['rag'] = [
                    'visited'         => $rag30 > 0,
                    'hits_7d'         => $rag7,
                    'hits_30d'        => $rag30,
                    'last_seen'       => $lastRag ?: null,
                    'last_seen_human' => $lastRag > 0 ? gmdate('Y-m-d H:i', $lastRag) . ' UTC' : 'Never seen locally',
                    'top_bots'        => $topRag,
                ];
                $urlMetrics['training'] = [
                    'visited'         => $train30 > 0,
                    'hits_7d'         => $train7,
                    'hits_30d'        => $train30,
                    'last_seen'       => $lastTrain ?: null,
                    'last_seen_human' => $lastTrain > 0 ? gmdate('Y-m-d H:i', $lastTrain) . ' UTC' : 'Never seen locally',
                    'top_bots'        => $topTrain,
                ];
            }

            $domStmt = $rPdo->prepare("SELECT DISTINCT url_path, bot_type FROM wsc_bot_hits WHERE hit_at >= :cutoff");
            $domStmt->execute([':cutoff' => $thirtyDaysAgo]);
            $all = $domStmt->fetchAll();

            $aiUrls = [];
            $ragUrls = [];
            $trainUrls = [];
            foreach ($all as $item) {
                $u = (string) $item['url_path'];
                $bt = (string) $item['bot_type'];
                if ($bt === 'rag' || $bt === 'training') {
                    $aiUrls[$u] = true;
                }
                if ($bt === 'rag') {
                    $ragUrls[$u] = true;
                }
                if ($bt === 'training') {
                    $trainUrls[$u] = true;
                }
            }

            $knownCount = max(count($aiUrls), $domainCoverage['known_urls_count'], 1);
            $domainCoverage['known_urls_count'] = $knownCount;
            $domainCoverage['ai_covered_urls_count'] = count($aiUrls);
            $domainCoverage['ai_coverage_pct'] = round((count($aiUrls) / $knownCount) * 100, 1);
            $domainCoverage['rag_coverage_pct'] = round((count($ragUrls) / $knownCount) * 100, 1);
            $domainCoverage['training_coverage_pct'] = round((count($trainUrls) / $knownCount) * 100, 1);
        } catch (\Throwable $e) {
            $this->logger->warning('WSC Magento Local AI Coverage Error: ' . $e->getMessage());
        }

        return [
            'ok'              => true,
            'status'          => 'success',
            'source'          => 'local_sqlite',
            'query'           => $canonical,
            'url_metrics'     => $urlMetrics,
            'domain_coverage' => $domainCoverage,
        ];
    }

    private function estimateKnownUrlsCount(): int
    {
        if ($this->resourceConnection !== null) {
            try {
                $conn = $this->resourceConnection->getConnection();
                $urTable = $this->resourceConnection->getTableName('url_rewrite');
                $cnt = (int) $conn->fetchOne("SELECT COUNT(DISTINCT request_path) FROM {$urTable} WHERE redirect_type = 0");
                if ($cnt > 0) {
                    return $cnt;
                }
            } catch (\Throwable $e) {
                // Fallback to SQLite below
            }
        }

        try {
            $qPdo = $this->dbManager->getQueuePdo();
            $cnt = (int) $qPdo->query("SELECT COUNT(DISTINCT url_path) FROM wsc_events_queue")->fetchColumn();
            return max(1, $cnt);
        } catch (\Throwable $e) {
            return 10;
        }
    }

    // =========================================================================
    // PAKET CMS-3.2: Dynamic Industry Benchmarks (`/v1/benchmarks/query` & `classify`)
    // =========================================================================

    public function getCurrentVertical(?int $storeId = null): string
    {
        $saved = trim((string) ($this->runtimeConfig[HelperData::XML_PATH_INDUSTRY_VERTICAL] ?? $this->helper->getIndustryVertical($storeId)));
        if ($saved !== '' && isset(self::BENCHMARK_TAXONOMY[$saved])) {
            return $saved;
        }
        return '/Shopping/Apparel';
    }

    public function collectLocalBenchmarkMetrics(int $days = 7): array
    {
        $cutoff = time() - (max(1, $days) * 86400);
        $dateCutoff = gmdate('Y-m-d', $cutoff);

        $aiRagShare = 0.0;
        $engagedTime = 0.0;
        $aiCoveragePct = 0.0;
        $humanRatioPct = 100.0;

        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $qPdo = $this->dbManager->getQueuePdo();

            $stmtTot = $rPdo->prepare("SELECT COUNT(*) FROM wsc_bot_hits WHERE hit_at >= :c");
            $stmtTot->execute([':c' => $cutoff]);
            $totBots = (int) $stmtTot->fetchColumn();

            if ($totBots > 0) {
                $stmtRag = $rPdo->prepare("
                    SELECT COUNT(*) FROM wsc_bot_hits
                    WHERE hit_at >= :c AND (bot_type = 'rag' OR bot_name LIKE '%GPTBot%' OR bot_name LIKE '%Perplexity%' OR bot_name LIKE '%Claude%')
                ");
                $stmtRag->execute([':c' => $cutoff]);
                $ragHits = (int) $stmtRag->fetchColumn();
                $aiRagShare = round(($ragHits / $totBots) * 100, 1);
            }

            $ovStmt = $rPdo->prepare("SELECT SUM(human_pageviews) AS hp, SUM(bot_hits) AS bh FROM wsc_daily_overview WHERE date >= :dc");
            $ovStmt->execute([':dc' => $dateCutoff]);
            $ov = $ovStmt->fetch();
            $hp = (int) ($ov['hp'] ?? 0);
            $bh = (int) ($ov['bh'] ?? 0);
            if (($hp + $bh) > 0) {
                $humanRatioPct = round(($hp / ($hp + $bh)) * 100, 1);
            }

            $engStmt = $qPdo->prepare("SELECT payload_json FROM wsc_events_queue WHERE event_type IN ('time_on_page', 'engagement') AND created_at >= :c LIMIT 200");
            $engStmt->execute([':c' => $cutoff]);
            $engRows = $engStmt->fetchAll();
            if (!empty($engRows)) {
                $sumSec = 0.0;
                $cntSec = 0;
                foreach ($engRows as $er) {
                    $m = json_decode((string) $er['payload_json'], true) ?: [];
                    $sec = (float) ($m['seconds'] ?? $m['duration'] ?? $m['engaged_time'] ?? 0);
                    if ($sec > 0) {
                        $sumSec += $sec;
                        $cntSec++;
                    }
                }
                if ($cntSec > 0) {
                    $engagedTime = round($sumSec / $cntSec, 1);
                }
            }
            if ($engagedTime <= 0.0) {
                $cards = $this->getDashboardCards();
                $rawEng = (string) ($cards['card1_traffic']['data_7d']['avg_engaged_time'] ?? $cards['card_engagement']['data_7d']['avg_reading_time'] ?? '');
                if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $rawEng, $matches)) {
                    $engagedTime = (float) $matches[1];
                }
            }

            $aiUrlStmt = $rPdo->prepare("SELECT COUNT(DISTINCT url_path) FROM wsc_bot_hits WHERE hit_at >= :c AND bot_type IN ('rag', 'training')");
            $aiUrlStmt->execute([':c' => time() - (30 * 86400)]);
            $aiUrlCnt = (int) $aiUrlStmt->fetchColumn();
            if ($aiUrlCnt > 0) {
                $known = max($aiUrlCnt, $this->estimateKnownUrlsCount(), 1);
                $aiCoveragePct = min(100.0, round(($aiUrlCnt / $known) * 100, 1));
            }
        } catch (\Throwable $e) {
            $this->logger->warning('WSC Magento Benchmark Metric Error: ' . $e->getMessage());
        }

        return [
            'ai_rag_share'    => $aiRagShare,
            'engaged_time'    => $engagedTime,
            'ai_coverage_pct' => $aiCoveragePct,
            'human_ratio_pct' => $humanRatioPct,
        ];
    }

    public function calculateBenchmarkRating(float $siteVal, float $p50, float $p90): array
    {
        $delta = ($p50 <= 0.0001) ? 0.0 : round((($siteVal - $p50) / $p50) * 100, 1);

        if ($siteVal >= $p90) {
            return [
                'delta_pct' => $delta,
                'rating'    => 'top_10',
                'badge'     => '🏆 Top 10%',
                'css_class' => 'wsc-bm-top10',
            ];
        }

        if ($delta > 10.0) {
            return [
                'delta_pct' => $delta,
                'rating'    => 'above_average',
                'badge'     => sprintf('🟢 +%s%% vs. Median', number_format($delta, 1)),
                'css_class' => 'wsc-bm-above',
            ];
        }

        if ($delta < -10.0) {
            return [
                'delta_pct' => $delta,
                'rating'    => 'below_average',
                'badge'     => sprintf('🔴 %s%% vs. Median', number_format($delta, 1)),
                'css_class' => 'wsc-bm-below',
            ];
        }

        return [
            'delta_pct' => $delta,
            'rating'    => 'average',
            'badge'     => '🟡 Industry Standard',
            'css_class' => 'wsc-bm-avg',
        ];
    }

    /**
     * Queries Gateway `/v1/benchmarks/query` (6h cache in SQLite `wsc_dashboard_reports` id `benchmarks`),
     * falling back cleanly to local baselines when offline or Hybrid Sync is disabled.
     */
    public function getBenchmarkReport(
        int $days = 7,
        bool $forceRefresh = false,
        ?int $storeId = null,
        ?string $siteBaseUrl = null
    ): array {
        $vertical = $this->getCurrentVertical($storeId);
        $meta = self::BENCHMARK_TAXONOMY[$vertical] ?? self::BENCHMARK_TAXONOMY['/Shopping/Apparel'];
        $site = $this->collectLocalBenchmarkMetrics($days);
        $baselines = $meta['baselines'];
        $nodesCount = (int) $meta['nodes'];
        $source = 'local_baseline';

        $gwCached = null;
        if (!$forceRefresh) {
            try {
                $rPdo = $this->dbManager->getReportsPdo();
                $stmt = $rPdo->prepare("SELECT updated_at, payload FROM wsc_dashboard_reports WHERE id = 'benchmarks'");
                $stmt->execute();
                $row = $stmt->fetch();
                if ($row && ((int) $row['updated_at'] >= (time() - 21600))) {
                    $decoded = json_decode((string) $row['payload'], true);
                    if (is_array($decoded) && ($decoded['vertical'] ?? '') === $vertical && !empty($decoded['gw_metrics'])) {
                        $gwCached = $decoded;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore cache read error
            }
        }

        if ($gwCached !== null) {
            $source = 'gateway_cached';
            $nodesCount = (int) ($gwCached['nodes_in_benchmark'] ?? $nodesCount);
            foreach (['ai_rag_share', 'engaged_time', 'ai_coverage_pct'] as $mKey) {
                if (!empty($gwCached['gw_metrics'][$mKey])) {
                    $baselines[$mKey]['p50'] = (float) ($gwCached['gw_metrics'][$mKey]['industry_median'] ?? $baselines[$mKey]['p50']);
                    $baselines[$mKey]['p75'] = (float) ($gwCached['gw_metrics'][$mKey]['industry_p75'] ?? $baselines[$mKey]['p75']);
                    $baselines[$mKey]['p90'] = (float) ($gwCached['gw_metrics'][$mKey]['industry_p90'] ?? $baselines[$mKey]['p90']);
                }
            }
        } elseif ($this->isHybridEnabled($storeId)) {
            $baseUrl = $this->resolveSiteBaseUrl($storeId, $siteBaseUrl);
            $creds = $this->getProjectCredentials(true, $storeId, $baseUrl);
            if (!empty($creds['project_id']) && !empty($creds['api_secret'])) {
                $bodyJson = (string) json_encode([
                    'project_id'           => $creds['project_id'],
                    'vertical'             => $vertical,
                    'period_days'          => $days,
                    'site_ai_rag_share'    => $site['ai_rag_share'],
                    'site_engaged_time'    => $site['engaged_time'],
                    'site_ai_coverage_pct' => $site['ai_coverage_pct'],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $sig = hash_hmac('sha256', $bodyJson, $creds['api_secret']);
                $resp = $this->httpPostJson(
                    $this->getGatewayUrl($storeId) . '/v1/benchmarks/query',
                    $bodyJson,
                    ['X-WSC-Sig' => $sig],
                    8
                );

                if ($resp['status'] === 200 && is_array($resp['json']) && !empty($resp['json']['metrics'])) {
                    $source = 'gateway_live';
                    $nodesCount = (int) ($resp['json']['nodes_in_benchmark'] ?? $nodesCount);
                    $gwm = $resp['json']['metrics'];
                    foreach (['ai_rag_share', 'engaged_time', 'ai_coverage_pct'] as $mKey) {
                        if (!empty($gwm[$mKey]) && is_array($gwm[$mKey])) {
                            $baselines[$mKey]['p50'] = (float) ($gwm[$mKey]['industry_median'] ?? $baselines[$mKey]['p50']);
                            $baselines[$mKey]['p75'] = (float) ($gwm[$mKey]['industry_p75'] ?? $baselines[$mKey]['p75']);
                            $baselines[$mKey]['p90'] = (float) ($gwm[$mKey]['industry_p90'] ?? $baselines[$mKey]['p90']);
                        }
                    }

                    $this->saveInternalReportCache('benchmarks', 'benchmarks', [
                        'vertical'           => $vertical,
                        'nodes_in_benchmark' => $nodesCount,
                        'gw_metrics'         => $gwm,
                    ]);
                }
            }
        }

        $metricDefs = [
            'ai_rag_share'    => ['label' => 'AI Search & RAG Share',       'unit' => '%'],
            'engaged_time'    => ['label' => 'Avg. Engaged Reading Time',   'unit' => 's'],
            'ai_coverage_pct' => ['label' => 'Domain AI Citation Coverage', 'unit' => '%'],
            'human_ratio_pct' => ['label' => 'Human Traffic Shield Ratio',  'unit' => '%'],
        ];

        $metricsOut = [];
        foreach ($metricDefs as $key => $def) {
            $rating = $this->calculateBenchmarkRating($site[$key], $baselines[$key]['p50'], $baselines[$key]['p90']);
            $metricsOut[$key] = array_merge([
                'label'           => $def['label'],
                'site_val'        => $site[$key],
                'unit'            => $def['unit'],
                'industry_median' => $baselines[$key]['p50'],
                'industry_p75'    => $baselines[$key]['p75'],
                'industry_p90'    => $baselines[$key]['p90'],
            ], $rating);
        }

        $verticalOptions = [];
        foreach (self::BENCHMARK_TAXONOMY as $vPath => $vMeta) {
            $verticalOptions[$vPath] = $vMeta['icon'] . ' ' . $vMeta['label'];
        }

        return [
            'ok'                 => true,
            'status'             => 'active',
            'source'             => $source,
            'vertical'           => $vertical,
            'vertical_label'     => $meta['label'],
            'vertical_icon'      => $meta['icon'],
            'nodes_in_benchmark' => $nodesCount,
            'metrics'            => $metricsOut,
            'taxonomy_options'   => $verticalOptions,
        ];
    }

    /**
     * Classifies store vertical via `POST /v1/benchmarks/classify` (or manual selection / local keyword centroid fallback).
     */
    public function classifyBenchmarkVertical(
        ?string $manualVertical = null,
        ?int $storeId = null,
        ?string $siteBaseUrl = null
    ): array {
        if ($manualVertical !== null && isset(self::BENCHMARK_TAXONOMY[$manualVertical])) {
            $this->saveConfigValues([HelperData::XML_PATH_INDUSTRY_VERTICAL => $manualVertical]);
            $this->clearInternalReportCache('benchmarks');
            return [
                'ok'         => true,
                'method'     => 'manual',
                'vertical'   => $manualVertical,
                'benchmarks' => $this->getBenchmarkReport(7, true, $storeId, $siteBaseUrl),
            ];
        }

        $storeMeta = $this->collectStoreMetadata($storeId);
        $chosenVertical = '';
        $method = 'centroid_nlp';
        $confidence = 78;

        if ($this->isHybridEnabled($storeId)) {
            $baseUrl = $this->resolveSiteBaseUrl($storeId, $siteBaseUrl);
            $creds = $this->getProjectCredentials(true, $storeId, $baseUrl);
            if (!empty($creds['project_id']) && !empty($creds['api_secret'])) {
                $bodyJson = (string) json_encode([
                    'project_id'       => $creds['project_id'],
                    'title'            => $storeMeta['name'],
                    'meta_description' => $storeMeta['description'],
                    'keywords'         => implode(', ', $storeMeta['keywords']),
                    'sample_text'      => 'magento2 adobe commerce store products ' . implode(' ', array_slice($storeMeta['products'], 0, 10)),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $sig = hash_hmac('sha256', $bodyJson, $creds['api_secret']);
                $resp = $this->httpPostJson(
                    $this->getGatewayUrl($storeId) . '/v1/benchmarks/classify',
                    $bodyJson,
                    ['X-WSC-Sig' => $sig],
                    8
                );

                if ($resp['status'] === 200 && is_array($resp['json'])) {
                    $gwVert = (string) ($resp['json']['vertical'] ?? '');
                    if ($gwVert !== '' && isset(self::BENCHMARK_TAXONOMY[$gwVert])) {
                        $chosenVertical = $gwVert;
                        $method = 'gateway_nlp';
                        $rawConf = (float) ($resp['json']['confidence'] ?? 0.9);
                        $confidence = $rawConf <= 1.0 ? (int) round($rawConf * 100) : (int) round($rawConf);
                    }
                }
            }
        }

        if ($chosenVertical === '') {
            $corpus = strtolower($storeMeta['name'] . ' ' . $storeMeta['description'] . ' ' . implode(' ', $storeMeta['keywords']) . ' ' . implode(' ', $storeMeta['products']));
            $bestMatch = '/Shopping/Apparel';
            $maxScore = 0;
            foreach (self::BENCHMARK_TAXONOMY as $path => $meta) {
                $score = str_starts_with($path, '/Shopping') ? 3 : 0;
                foreach ($meta['keywords'] as $kw) {
                    if (stripos($corpus, $kw) !== false) {
                        $score += 2;
                    }
                }
                if ($score > $maxScore) {
                    $maxScore = $score;
                    $bestMatch = $path;
                }
            }
            $chosenVertical = $bestMatch;
            $confidence = min(98, max(70, 65 + ($maxScore * 4)));
        }

        $this->saveConfigValues([HelperData::XML_PATH_INDUSTRY_VERTICAL => $chosenVertical]);
        $this->clearInternalReportCache('benchmarks');

        return [
            'ok'         => true,
            'method'     => $method,
            'confidence' => $confidence,
            'vertical'   => $chosenVertical,
            'benchmarks' => $this->getBenchmarkReport(7, true, $storeId, $siteBaseUrl),
        ];
    }

    // =========================================================================
    // PAKET CMS-3.3: Public `/llms.txt` AI Manifest
    // =========================================================================

    public function isLlmsTxtEnabled(?int $storeId = null): bool
    {
        return $this->helper->isLlmsTxtEnabled($storeId);
    }

    public function generateLlmsTxt(?string $siteBaseUrl = null, ?int $storeId = null): string
    {
        $baseUrl = $this->resolveSiteBaseUrl($storeId, $siteBaseUrl);
        $meta = $this->collectStoreMetadata($storeId);

        $lines = [];
        $lines[] = '# ' . ($meta['name'] !== '' ? $meta['name'] : 'Magento 2 / Adobe Commerce Storefront');
        $lines[] = '';
        $lines[] = '> ' . ($meta['description'] !== '' ? $meta['description'] : 'Official e-commerce catalog and customer information manifest.');
        $lines[] = '';
        $lines[] = '- Canonical Store URL: ' . $baseUrl . '/';
        $lines[] = '- XML Sitemap: ' . $baseUrl . '/sitemap.xml';
        $lines[] = '- Platform: Magento 2 / Adobe Commerce (WSC Search & Health v' . self::VERSION . ')';
        $lines[] = '';

        if (!empty($meta['categories'])) {
            $lines[] = '## Key Store Categories';
            $lines[] = '';
            foreach ($meta['categories'] as $cat) {
                $lines[] = '- [' . $cat['name'] . '](' . $baseUrl . '/' . ltrim($cat['url'], '/') . ')';
            }
            $lines[] = '';
        }

        if (!empty($meta['product_items'])) {
            $lines[] = '## Featured Active Products';
            $lines[] = '';
            foreach ($meta['product_items'] as $prod) {
                $desc = !empty($prod['description']) ? (': ' . $prod['description']) : '';
                $lines[] = '- [' . $prod['name'] . '](' . $baseUrl . '/' . ltrim($prod['url'], '/') . ')' . $desc;
            }
            $lines[] = '';
        }

        if (!empty($meta['cms_pages'])) {
            $lines[] = '## Store Information & Policies';
            $lines[] = '';
            foreach ($meta['cms_pages'] as $page) {
                if (!empty($page['url'])) {
                    $lines[] = '- [' . $page['name'] . '](' . $baseUrl . '/' . ltrim($page['url'], '/') . ')';
                } else {
                    $lines[] = '- ' . $page['name'];
                }
            }
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    private function collectStoreMetadata(?int $storeId = null): array
    {
        $name = 'Magento 2 Store';
        $desc = '';

        try {
            $store = $this->storeManager->getStore($storeId);
            $storeName = trim((string) $store->getFrontendName());
            if ($storeName !== '') {
                $name = $storeName;
            }
            if ($this->scopeConfig !== null) {
                $cfgName = trim((string) $this->scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE, $storeId));
                if ($cfgName !== '') {
                    $name = $cfgName;
                }
                $desc = trim((string) $this->scopeConfig->getValue('design/head/default_description', ScopeInterface::SCOPE_STORE, $storeId));
            }
        } catch (\Throwable $e) {
            // Keep safe defaults
        }

        $keywords = [];
        $categories = [];
        $productNames = [];
        $productItems = [];
        $cmsPages = [];

        if ($this->resourceConnection !== null) {
            try {
                $conn = $this->resourceConnection->getConnection();
                $urTable = $this->resourceConnection->getTableName('url_rewrite');

                // 1. Top canonical category URLs
                $catRows = $conn->fetchAll(
                    "SELECT entity_id, request_path FROM {$urTable} WHERE entity_type = 'category' AND redirect_type = 0 GROUP BY entity_id LIMIT 15"
                );
                foreach ($catRows as $cr) {
                    $reqPath = trim((string) ($cr['request_path'] ?? ''));
                    if ($reqPath === '') {
                        continue;
                    }
                    $humanName = $this->humanizeSlug($reqPath);
                    $keywords[] = strtolower($humanName);
                    $categories[] = [
                        'name' => $humanName,
                        'url'  => $reqPath,
                    ];
                }
            } catch (\Throwable $e) {
                // Safe fallback
            }

            try {
                $conn = $this->resourceConnection->getConnection();
                $urTable = $this->resourceConnection->getTableName('url_rewrite');
                $prodTable = $this->resourceConnection->getTableName('catalog_product_entity');

                // 2. Top canonical product URLs
                $prodRows = $conn->fetchAll(
                    "SELECT ur.entity_id, ur.request_path, p.sku
                     FROM {$urTable} ur
                     LEFT JOIN {$prodTable} p ON p.entity_id = ur.entity_id
                     WHERE ur.entity_type = 'product' AND ur.redirect_type = 0
                     GROUP BY ur.entity_id
                     LIMIT 20"
                );
                foreach ($prodRows as $pr) {
                    $reqPath = trim((string) ($pr['request_path'] ?? ''));
                    if ($reqPath === '') {
                        continue;
                    }
                    $pName = $this->humanizeSlug($reqPath);
                    $sku = trim((string) ($pr['sku'] ?? ''));
                    $productNames[] = $pName;
                    $productItems[] = [
                        'name'        => $pName,
                        'description' => $sku !== '' ? ('SKU ' . $sku) : '',
                        'url'         => $reqPath,
                    ];
                }
            } catch (\Throwable $e) {
                // Safe fallback
            }

            try {
                $conn = $this->resourceConnection->getConnection();
                $cmsTable = $this->resourceConnection->getTableName('cms_page');
                $cmsRows = $conn->fetchAll(
                    "SELECT title, identifier FROM {$cmsTable} WHERE is_active = 1 AND identifier NOT IN ('no-route', 'enable-cookies') LIMIT 10"
                );
                foreach ($cmsRows as $cm) {
                    $title = trim((string) ($cm['title'] ?? ''));
                    $ident = trim((string) ($cm['identifier'] ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    $cmsPages[] = [
                        'name' => $title,
                        'url'  => $ident === 'home' ? '' : $ident,
                    ];
                }
            } catch (\Throwable $e) {
                // Safe fallback
            }
        }

        return [
            'name'          => $name,
            'description'   => $desc,
            'keywords'      => $keywords,
            'categories'    => $categories,
            'products'      => $productNames,
            'product_items' => $productItems,
            'cms_pages'     => $cmsPages,
        ];
    }

    private function humanizeSlug(string $requestPath): string
    {
        $base = basename((string) preg_replace('/\.(html|htm|php)$/i', '', $requestPath));
        $label = ucwords(str_replace(['-', '_'], ' ', $base));
        return $label !== '' ? $label : $requestPath;
    }

    // =========================================================================
    // PAKET CMS-3.4: Opt-In `IndexNow` Instant Push (`api.indexnow.org`)
    // =========================================================================

    public function isIndexNowEnabled(?int $storeId = null): bool
    {
        if (array_key_exists(HelperData::XML_PATH_INDEXNOW_ENABLED, $this->runtimeConfig)) {
            return $this->runtimeConfig[HelperData::XML_PATH_INDEXNOW_ENABLED] === '1';
        }
        return $this->helper->isIndexNowEnabled($storeId);
    }

    public function getIndexNowKey(?int $storeId = null): string
    {
        $key = strtolower(trim((string) ($this->runtimeConfig[HelperData::XML_PATH_INDEXNOW_KEY] ?? $this->helper->getIndexNowKey($storeId))));
        if (strlen($key) !== 32 || !ctype_xdigit($key)) {
            $key = bin2hex(random_bytes(16)); // 32-char hex
            $this->saveConfigValues([HelperData::XML_PATH_INDEXNOW_KEY => $key]);
        }
        return $key;
    }

    public function getIndexNowLogs(): array
    {
        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $stmt = $rPdo->prepare("SELECT payload FROM wsc_dashboard_reports WHERE id = 'indexnow_logs'");
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row) {
                $decoded = json_decode((string) $row['payload'], true);
                if (is_array($decoded)) {
                    return array_slice($decoded, 0, 20);
                }
            }
        } catch (\Throwable $e) {
            // Return empty
        }
        return [];
    }

    /**
     * Submits URLs to IndexNow (`timeout = 3s` for auto_product_save, `10s` for manual) and stores last 20 logs.
     */
    public function submitIndexNowUrls(
        array $rawUrls,
        string $source = 'manual',
        int $timeout = 10,
        ?string $siteBaseUrl = null,
        ?int $storeId = null
    ): array {
        $baseUrl = $this->resolveSiteBaseUrl($storeId, $siteBaseUrl);
        $siteHost = strtolower((string) (parse_url($baseUrl, PHP_URL_HOST) ?: ''));

        $validUrls = [];
        foreach ($rawUrls as $u) {
            $u = trim((string) $u);
            if ($u === '') {
                continue;
            }
            if (str_starts_with($u, '/')) {
                $u = $baseUrl . $u;
            }
            if (!filter_var($u, FILTER_VALIDATE_URL)) {
                continue;
            }
            $uHost = strtolower((string) (parse_url($u, PHP_URL_HOST) ?: ''));
            if ($siteHost !== '' && $uHost !== $siteHost) {
                continue;
            }
            $validUrls[] = $u;
        }

        $validUrls = array_values(array_unique($validUrls));
        if (empty($validUrls)) {
            return [
                'ok'      => false,
                'success' => false,
                'message' => 'Please provide at least one valid URL matching host ' . $siteHost . '.',
                'logs'    => $this->getIndexNowLogs(),
            ];
        }

        $key = $this->getIndexNowKey($storeId);
        $payload = (string) json_encode([
            'host'        => $siteHost,
            'key'         => $key,
            'keyLocation' => $baseUrl . '/' . $key . '.txt',
            'urlList'     => $validUrls,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $resp = $this->httpPostJson(self::INDEXNOW_ENDPOINT, $payload, [
            'Content-Type' => 'application/json; charset=utf-8',
        ], max(1, $timeout));

        $ok = in_array($resp['status'], [200, 202], true);
        $entry = [
            'timestamp'   => gmdate('Y-m-d H:i:s') . ' UTC',
            'source'      => $source,
            'urls'        => $validUrls,
            'count'       => count($validUrls),
            'status_code' => $resp['status'],
            'success'     => $ok,
        ];

        $logs = $this->getIndexNowLogs();
        array_unshift($logs, $entry);
        $logs = array_slice($logs, 0, 20);
        $this->saveInternalReportCache('indexnow_logs', 'indexnow', $logs);

        return [
            'ok'          => $ok,
            'success'     => $ok,
            'status_code' => $resp['status'],
            'submitted'   => count($validUrls),
            'message'     => $ok
                ? sprintf('Submitted %d URL(s) to IndexNow (HTTP %d).', count($validUrls), $resp['status'])
                : sprintf('IndexNow submission returned HTTP %d.', $resp['status']),
            'logs'        => $logs,
        ];
    }

    // =========================================================================
    // Internal Cache, HTTP Transport & Cleanup Helpers
    // =========================================================================

    private function saveInternalReportCache(string $id, string $type, array $payload): void
    {
        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $stmt = $rPdo->prepare("
                INSERT INTO wsc_dashboard_reports (id, report_type, period_type, updated_at, payload)
                VALUES (:id, :type, 'cache', :ts, :payload)
                ON CONFLICT(id) DO UPDATE SET updated_at = excluded.updated_at, payload = excluded.payload
            ");
            $stmt->execute([
                ':id'      => $id,
                ':type'    => $type,
                ':ts'      => time(),
                ':payload' => (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            // Non-blocking cache write
        }
    }

    private function clearInternalReportCache(string $id): void
    {
        try {
            $rPdo = $this->dbManager->getReportsPdo();
            $rPdo->prepare("DELETE FROM wsc_dashboard_reports WHERE id = :id")->execute([':id' => $id]);
        } catch (\Throwable $e) {
            // Non-blocking
        }
    }

    private function httpPostJson(string $url, string $jsonBody, array $extraHeaders = [], int $timeout = 10): array
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'User-Agent'   => 'WSC-Magento2/' . self::VERSION,
        ], $extraHeaders);

        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout($timeout);
            $curl->setHeaders($headers);
            $curl->post($url, $jsonBody);

            $status = (int) $curl->getStatus();
            $raw = (string) $curl->getBody();

            return [
                'status' => $status,
                'body'   => $raw,
                'json'   => json_decode($raw, true),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 0,
                'body'   => '',
                'json'   => null,
                'error'  => $e->getMessage(),
            ];
        }
    }

    private function httpGetJson(string $url, int $timeout = 6): array
    {
        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout($timeout);
            $curl->setHeaders([
                'Accept'     => 'application/json',
                'User-Agent' => 'WSC-Magento2/' . self::VERSION,
            ]);
            $curl->get($url);

            $status = (int) $curl->getStatus();
            $raw = (string) $curl->getBody();

            return [
                'status' => $status,
                'body'   => $raw,
                'json'   => json_decode($raw, true),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 0,
                'body'   => '',
                'json'   => null,
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Daily retention cleanup.
     */
    public function cleanupOldData(?int $storeId = null): void
    {
        $retention = $this->helper->getRetentionDays($storeId);
        $threshold = time() - ($retention * 86400);

        try {
            $qPdo = $this->dbManager->getQueuePdo();
            $qPdo->prepare("DELETE FROM wsc_events_queue WHERE synced = 1 AND created_at < :t")->execute([':t' => $threshold]);
            $qPdo->exec('PRAGMA optimize;');

            $rPdo = $this->dbManager->getReportsPdo();
            $rPdo->prepare("DELETE FROM wsc_bot_hits WHERE hit_at < :t")->execute([':t' => time() - (60 * 86400)]);
            $rPdo->exec('PRAGMA optimize;');
        } catch (Exception $e) {
            $this->logger->error('WSC Cleanup Error: ' . $e->getMessage());
        }
    }
}
