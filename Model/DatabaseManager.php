<?php

declare(strict_types=1);

namespace Wsc\Analytics\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;
use PDO;
use Exception;

/**
 * Manages local SQLite storage with WAL mode in Magento 2 (Zero-PII).
 */
class DatabaseManager
{
    private string $storageDir;
    private ?PDO $queuePdo = null;
    private ?PDO $reportsPdo = null;
    private LoggerInterface $logger;

    public function __construct(Filesystem $filesystem, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $varDir = $filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath();
        $this->storageDir = rtrim($varDir, '/') . '/wsc_analytics';
        $this->ensureStorageDirectory();
        $this->initSchemas();
    }

    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0750, true);
        }

        // Security Hardening: write .htaccess, web.config, index.php
        $htaccess = $this->storageDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
        $webconfig = $this->storageDir . '/web.config';
        if (!file_exists($webconfig)) {
            @file_put_contents($webconfig, '<configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');
        }
        $indexphp = $this->storageDir . '/index.php';
        if (!file_exists($indexphp)) {
            @file_put_contents($indexphp, '<?php // Silence is golden.');
        }
    }

    public function getQueuePdo(): PDO
    {
        if ($this->queuePdo === null) {
            $path = $this->storageDir . '/queue_temp.sqlite';
            $this->queuePdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->queuePdo->exec('PRAGMA journal_mode = WAL;');
            $this->queuePdo->exec('PRAGMA synchronous = NORMAL;');
            $this->queuePdo->exec('PRAGMA busy_timeout = 5000;');
        }
        return $this->queuePdo;
    }

    public function getReportsPdo(): PDO
    {
        if ($this->reportsPdo === null) {
            $path = $this->storageDir . '/reports_temp.sqlite';
            $this->reportsPdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->reportsPdo->exec('PRAGMA journal_mode = WAL;');
            $this->reportsPdo->exec('PRAGMA synchronous = NORMAL;');
            $this->reportsPdo->exec('PRAGMA busy_timeout = 5000;');
        }
        return $this->reportsPdo;
    }

    private function initSchemas(): void
    {
        try {
            $qPdo = $this->getQueuePdo();
            $qPdo->exec("
                CREATE TABLE IF NOT EXISTS wsc_events_queue (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    created_at INTEGER NOT NULL,
                    event_type TEXT NOT NULL,
                    url_path TEXT NOT NULL,
                    referrer TEXT,
                    payload_json TEXT NOT NULL,
                    synced INTEGER DEFAULT 0
                );
                CREATE INDEX IF NOT EXISTS idx_wsc_queue_synced ON wsc_events_queue (synced, created_at);
            ");

            $rPdo = $this->getReportsPdo();
            $rPdo->exec("
                CREATE TABLE IF NOT EXISTS wsc_dashboard_reports (
                    id TEXT PRIMARY KEY,
                    report_type TEXT NOT NULL,
                    period_type TEXT NOT NULL,
                    updated_at INTEGER NOT NULL,
                    payload TEXT NOT NULL
                );

                CREATE TABLE IF NOT EXISTS wsc_daily_overview (
                    date TEXT PRIMARY KEY,
                    human_visitors INTEGER DEFAULT 0,
                    human_pageviews INTEGER DEFAULT 0,
                    bot_hits INTEGER DEFAULT 0,
                    ai_training_hits INTEGER DEFAULT 0,
                    ai_rag_hits INTEGER DEFAULT 0,
                    orders_count INTEGER DEFAULT 0,
                    revenue_total REAL DEFAULT 0.0,
                    human_pct REAL DEFAULT 100.0
                );

                CREATE TABLE IF NOT EXISTS wsc_bot_stats (
                    date TEXT NOT NULL,
                    bot_name TEXT NOT NULL,
                    bot_category TEXT NOT NULL,
                    bot_type TEXT NOT NULL,
                    hits INTEGER DEFAULT 1,
                    blocked INTEGER DEFAULT 0,
                    PRIMARY KEY (date, bot_name)
                );

                CREATE TABLE IF NOT EXISTS wsc_bot_hits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    hit_at INTEGER NOT NULL,
                    bot_name TEXT NOT NULL,
                    bot_category TEXT NOT NULL,
                    bot_type TEXT NOT NULL,
                    url_path TEXT NOT NULL,
                    session_hash TEXT DEFAULT ''
                );
                CREATE INDEX IF NOT EXISTS idx_wsc_bot_hits_time ON wsc_bot_hits (hit_at DESC);
                CREATE INDEX IF NOT EXISTS idx_wsc_bot_hits_path ON wsc_bot_hits (url_path);
            ");
        } catch (Exception $e) {
            $this->logger->error('WSC Magento SQLite Init Error: ' . $e->getMessage());
        }
    }
}
