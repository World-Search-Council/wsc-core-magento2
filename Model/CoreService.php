<?php

namespace Wsc\Analytics\Model;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\HTTP\Client\Curl;
use Wsc\Analytics\Helper\Data as HelperData;
use Psr\Log\LoggerInterface;
use PDO;
use Exception;

/**
 * Core business logic, encryption, and gateway sync for Magento 2.
 */
class CoreService
{
    private HelperData $helper;
    private DatabaseManager $dbManager;
    private WriterInterface $configWriter;
    private Curl $curl;
    private LoggerInterface $logger;

    public function __construct(
        HelperData $helper,
        DatabaseManager $dbManager,
        WriterInterface $configWriter,
        Curl $curl,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->dbManager = $dbManager;
        $this->configWriter = $configWriter;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    /**
     * Get or initialize Project ID + AES-256 Key.
     */
    public function getProjectCredentials(): array
    {
        $projectId = $this->helper->getProjectId();
        $localKey  = $this->helper->getLocalKey();

        if (empty($projectId) || empty($localKey)) {
            $projectId = 'wsc_m2_' . bin2hex(random_bytes(8));
            $localKey  = bin2hex(random_bytes(32)); // 256-bit AES key

            $this->configWriter->save(HelperData::XML_PATH_PROJECT_ID, $projectId);
            $this->configWriter->save(HelperData::XML_PATH_LOCAL_KEY, $localKey);

            $this->registerWithGateway($projectId, $localKey);
        }

        return [
            'project_id' => $projectId,
            'local_key'  => $localKey,
        ];
    }

    /**
     * Register project credentials with Gateway.
     */
    public function registerWithGateway(string $projectId, string $localKey): bool
    {
        $gatewayUrl = $this->helper->getGatewayUrl();
        $url = rtrim($gatewayUrl, '/') . '/v1/register';

        $payload = json_encode([
            'project_id' => $projectId,
            'local_key'  => $localKey,
            'cms'        => 'magento2',
            'version'    => '2.2.0',
        ]);

        try {
            $this->curl->setOption(CURLOPT_TIMEOUT, 8);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post($url, $payload);

            $status = $this->curl->getStatus();
            return $status === 200 || $status === 201;
        } catch (Exception $e) {
            $this->logger->warning('WSC Gateway registration deferred: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Enqueue event into local SQLite queue.
     */
    public function trackEvent(string $type, string $path = '/', ?string $referrer = '', array $meta = []): bool
    {
        try {
            $pdo = $this->dbManager->getQueuePdo();
            $stmt = $pdo->prepare("
                INSERT INTO wsc_events_queue (created_at, event_type, url_path, referrer, payload_json, synced)
                VALUES (:created_at, :event_type, :url_path, :referrer, :payload_json, 0)
            ");

            $stmt->execute([
                ':created_at'   => time(),
                ':event_type'   => $type,
                ':url_path'     => $path ?: '/',
                ':referrer'     => $referrer ?: '',
                ':payload_json' => json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('WSC Magento Queue Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Export micro-batch events with AES-256-CBC and HMAC-SHA256 signature.
     */
    public function processBatchExport(int $batchSize = 500): int
    {
        $creds = $this->getProjectCredentials();
        $pdo = $this->dbManager->getQueuePdo();

        $stmt = $pdo->prepare("SELECT * FROM wsc_events_queue WHERE synced = 0 ORDER BY id ASC LIMIT :limit");
        $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return 0;
        }

        $ids = [];
        $events = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
            $events[] = [
                't' => (int) $row['created_at'],
                'e' => $row['event_type'],
                'p' => $row['url_path'],
                'r' => $row['referrer'],
                'm' => json_decode($row['payload_json'], true) ?: [],
            ];
        }

        $payloadJson = json_encode(['events' => $events], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // AES-256-CBC Encryption
        $keyBinary = hex2bin($creds['local_key']);
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($payloadJson, 'AES-256-CBC', $keyBinary, OPENSSL_RAW_DATA, $iv);
        $encryptedBody = base64_encode($iv . $ciphertext);

        // HMAC-SHA256 Signature
        $signature = hash_hmac('sha256', $encryptedBody, $creds['local_key']);

        $gatewayUrl = $this->helper->getGatewayUrl();
        $url = rtrim($gatewayUrl, '/') . '/v1/ingest';

        try {
            $this->curl->setOption(CURLOPT_TIMEOUT, 10);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('X-WSC-Project', $creds['project_id']);
            $this->curl->addHeader('X-WSC-Sig', $signature);

            $this->curl->post($url, $encryptedBody);
            $status = $this->curl->getStatus();

            if ($status === 200 || $status === 202) {
                $inClause = implode(',', $ids);
                $pdo->exec("UPDATE wsc_events_queue SET synced = 1 WHERE id IN ($inClause)");
                return count($ids);
            }
        } catch (Exception $e) {
            $this->logger->warning('WSC Ingest Sync Error: ' . $e->getMessage());
        }

        return 0;
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

            $cardMappings = [
                'card1'           => ['traffic', $data['card1'] ?? ($data['card1_traffic'] ?? null)],
                'card1_traffic'   => ['traffic', $data['card1_traffic'] ?? ($data['card1'] ?? null)],
                'card2'           => ['insights', $data['card2'] ?? ($data['card2_insights'] ?? null)],
                'card2_insights'  => ['insights', $data['card2_insights'] ?? ($data['card2'] ?? null)],
                'card3'           => ['bots', $data['card3'] ?? ($data['card3_bots'] ?? null)],
                'card3_bots'      => ['bots', $data['card3_bots'] ?? ($data['card3'] ?? null)],
                'card_engagement' => ['engagement', $data['card_engagement'] ?? ($data['card4'] ?? null)],
                'card_outbound'   => ['outbound', $data['card_outbound'] ?? ($data['card5'] ?? null)],
                'card_ecommerce'  => ['ecommerce', $data['card_ecommerce'] ?? ($data['card6'] ?? null)],
                'domain_health'   => ['sentinel', $data['domain_health'] ?? ($data['sentinel'] ?? ($data['card7'] ?? null))],
                'pagespeed'       => ['pagespeed', $data['pagespeed'] ?? null],
            ];

            foreach ($cardMappings as $cardId => [$type, $payload]) {
                if ($payload !== null) {
                    $stmt->execute([
                        ':id'          => $cardId,
                        ':report_type' => $type,
                        ':period_type' => 'multi',
                        ':updated_at'  => $now,
                        ':payload'     => is_string($payload) ? $payload : json_encode($payload),
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
     * Retrieve all 7 Canonical Cards for Dashboard view.
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
        ];

        try {
            $pdo = $this->dbManager->getReportsPdo();
            $stmt = $pdo->query("SELECT id, payload FROM wsc_dashboard_reports");
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $id = $row['id'];
                $decoded = json_decode($row['payload'], true);
                $cards[$id] = $decoded;

                if ($id === 'card1' && empty($cards['card1_traffic'])) $cards['card1_traffic'] = $decoded;
                if ($id === 'card1_traffic' && empty($cards['card1'])) $cards['card1'] = $decoded;
                if ($id === 'card2' && empty($cards['card2_insights'])) $cards['card2_insights'] = $decoded;
                if ($id === 'card2_insights' && empty($cards['card2'])) $cards['card2'] = $decoded;
                if ($id === 'card3' && empty($cards['card3_bots'])) $cards['card3_bots'] = $decoded;
                if ($id === 'card3_bots' && empty($cards['card3'])) $cards['card3'] = $decoded;
            }
        } catch (Exception $e) {
            $this->logger->error('WSC Read Cards Error: ' . $e->getMessage());
        }

        return $cards;
    }

    /**
     * Trigger SpeedSentinel 2.0 Lab Audit from Magento 2.
     */
    public function triggerSpeedSentinelAudit(string $url, string $strategy = 'mobile'): array
    {
        $gatewayUrl = $this->helper->getGatewayUrl();
        $creds = $this->getProjectCredentials();

        $payload = json_encode(['url' => $url, 'strategy' => $strategy]);
        $sig = hash_hmac('sha256', $payload, $creds['local_key']);

        try {
            $this->curl->setOption(CURLOPT_TIMEOUT, 15);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('X-WSC-Project', $creds['project_id']);
            $this->curl->addHeader('X-WSC-Sig', $sig);
            $this->curl->post(rtrim($gatewayUrl, '/') . '/v1/pagespeed/audit', $payload);

            $status = $this->curl->getStatus();
            if ($status === 200 || $status === 202) {
                return json_decode($this->curl->getBody(), true) ?: ['ok' => true, 'status' => 'queued'];
            }
            return ['ok' => false, 'error' => 'Gateway error: ' . $status];
        } catch (Exception $e) {
            $this->logger->error('SpeedSentinel trigger error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Trigger Sentinel Domain Health Scan from Magento 2.
     */
    public function triggerSentinelScan(string $domain): array
    {
        $gatewayUrl = $this->helper->getGatewayUrl();
        $creds = $this->getProjectCredentials();

        $payload = json_encode(['domain' => $domain, 'sync' => false]);
        $sig = hash_hmac('sha256', $payload, $creds['local_key']);

        try {
            $this->curl->setOption(CURLOPT_TIMEOUT, 15);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('X-WSC-Project', $creds['project_id']);
            $this->curl->addHeader('X-WSC-Sig', $sig);
            $this->curl->post(rtrim($gatewayUrl, '/') . '/v1/sentinel/scan', $payload);

            $status = $this->curl->getStatus();
            if ($status === 200 || $status === 202) {
                return json_decode($this->curl->getBody(), true) ?: ['ok' => true, 'status' => 'queued'];
            }
            return ['ok' => false, 'error' => 'Gateway error: ' . $status];
        } catch (Exception $e) {
            $this->logger->error('Sentinel trigger error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Daily retention cleanup.
     */
    public function cleanupOldData(): void
    {
        $threshold = time() - (30 * 86400);
        try {
            $pdo = $this->dbManager->getQueuePdo();
            $pdo->exec("DELETE FROM wsc_events_queue WHERE synced = 1 AND created_at < $threshold");

            $pdo->exec('PRAGMA optimize;');
            $this->dbManager->getReportsPdo()->exec('PRAGMA optimize;');
        } catch (Exception $e) {
            $this->logger->error('WSC Cleanup Error: ' . $e->getMessage());
        }
    }
}
