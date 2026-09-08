# WSC Analytics & AI Bot Shield for Magento 2 / Adobe Commerce

Official Magento 2 module of the **World Search Council (WSC) Hybrid Analytics Standard**.

Provides privacy-first, cookieless analytics, early HTTP edge AI crawler detection via FrontController plugin, 100% ad-blocker immune server-side order tracking via `sales_order_place_after`, local SQLite WAL queueing, and the standard **3-Card Anti-GA4 Smart Dashboard**.

---

## 🌟 Key Features

* **Cookieless & Privacy-First**: 100% GDPR, TTDSG/TDDDG, and CCPA compliant.
* **100% Ad-Blocker Immune Server-Side Order Tracking**:
  * Listens to Magento's `sales_order_place_after` observer event.
  * Captures increment ID, grand total, tax, shipping, currency, and line items directly into `queue_temp.sqlite` without reliance on client JavaScript or third-party pixels.
* **Zero DB-Locking Dual SQLite WAL Architecture**:
  * Writes into `var/wsc_analytics/queue_temp.sqlite` with WAL mode (`PRAGMA journal_mode = WAL;`) and synchronous normal, avoiding any table locking on Magento's MySQL database.
* **Early HTTP Edge AI Bot Sensor**:
  * Interceptor plugin (`aroundDispatch` on `\Magento\Framework\App\FrontControllerInterface`) captures AI crawlers (`GPTBot`, `ClaudeBot`, `ChatGPT-User`) before Varnish and Full Page Cache (FPC).
* **3-Card Smart Dashboard**:
  * **Card 1**: Traffic & E-Commerce Acquisition Channels (AI Citations, Search, Social, Direct).
  * **Card 2**: Strategic Growth Insights & Conversion Bottlenecks.
  * **Card 3**: AI Bot Shield Sensor Network & Crawler Traffic Ratio.
* **Automated Magento Crontab Sync**:
  * Background cron job (`wsc_analytics_batch_sync` every 5 minutes) encrypts micro-batches with AES-256-CBC and signs them with HMAC-SHA256 (`X-WSC-Sig`).

---

## 📦 Directory Structure

```text
wsc-core-magento2/
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml
│   ├── events.xml                     # Observer for sales_order_place_after
│   ├── crontab.xml                    # 5-minute batch sync cron
│   ├── frontend/
│   │   ├── di.xml                     # FrontController plugin for edge bot sensor
│   │   └── routes.xml                 # /wsc/api/* frontend routes
│   └── adminhtml/
│       ├── system.xml                 # Stores -> Configuration settings
│       ├── menu.xml                   # Admin menu link
│       └── routes.xml                 # /wsc_admin/* admin routes
├── Block/
│   ├── Tracker.php                    # Frontend script & WSC_CONFIG block
│   └── Adminhtml/
│       └── Dashboard.php              # Admin 3-Card Dashboard block
├── Controller/
│   ├── Api/
│   │   ├── Track.php                  # POST /wsc/api/track
│   │   ├── Webhook.php                # POST /wsc/api/webhook (Gateway HMAC)
│   │   └── Refresh.php                # POST /wsc/api/refresh
│   └── Adminhtml/
│       └── Dashboard/
│           └── Index.php              # Admin dashboard action
├── Helper/
│   └── Data.php                       # Configuration helper
├── Model/
│   ├── DatabaseManager.php            # SQLite WAL manager
│   ├── BotTracker.php                 # AI & Search bot taxonomy
│   ├── CoreService.php                # AES-256, HMAC & Gateway sync
│   └── Config/Source/
│       ├── TrackingMode.php           # Cookieless vs Consent options
│       └── BotLevel.php               # Bot scope options
├── Observer/
│   └── OrderPlaceAfterObserver.php    # Server-side order capture observer
├── Plugin/
│   └── FrontControllerPlugin.php      # Edge bot sensor plugin
├── Cron/
│   └── SyncBatch.php                  # Background cron runner
├── view/
│   ├── frontend/
│   │   ├── layout/default.xml         # Tracker injection
│   │   ├── templates/tracker.phtml
│   │   └── web/js/wsc-tracker.js
│   └── adminhtml/
│       ├── layout/wsc_admin_dashboard_index.xml
│       ├── templates/dashboard.phtml  # 3-Card Dashboard
│       └── web/
│           ├── js/wsc-dashboard.js
│           └── css/wsc-dashboard.css
└── README.md
```

---

## 🚀 Installation

1. Copy module to `app/code/Wsc/Analytics`.
2. Enable and upgrade Magento:
   ```bash
   bin/magento module:enable Wsc_Analytics
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```
3. Navigate to **Stores → Configuration → World Search Council → WSC Analytics & Bot Shield**.
