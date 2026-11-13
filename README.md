# WSC Analytics & AI Bot Shield for Magento 2 / Adobe Commerce (`v2.2.0`)

<p align="center">
  <strong>Official Magento 2 / Adobe Commerce Module of the World Search Council (WSC) Standard</strong><br>
  100% Cookieless Analytics, Early FrontController AI Bot Sensor, Ad-Blocker Proof Server-Side Order Tracking & Canonical 7-Card Bento Suite
</p>

<p align="center">
  <a href="https://www.worldsearchcouncil.org"><img src="https://img.shields.io/badge/Portal-worldsearchcouncil.org-0F172A?style=flat-square" alt="World Search Council"></a>
  <img src="https://img.shields.io/badge/Version-2.2.0-F26322?style=flat-square&logo=magento" alt="Magento 2 v2.2.0">
  <img src="https://img.shields.io/badge/Magento-2.4.x_|_Adobe_Commerce-F26322?style=flat-square" alt="Magento 2.4">
  <img src="https://img.shields.io/badge/Privacy-100%25_Cookieless-10B981?style=flat-square" alt="Cookieless">
  <img src="https://img.shields.io/badge/License-GPL--2.0--or--later-orange?style=flat-square" alt="License">
</p>

---

## 🌟 Key Features (`v2.2.0`)

* **100% Cookieless & Privacy-First**: GDPR, TDDDG, ePrivacy, and CCPA compliant — daily rotating salted session hashes (`SHA256(IP + UA + DailySalt)`) with zero tracking cookies and zero stored client IPs.
* **100% Ad-Blocker Immune Server-Side Order Tracking**:
  * Observes Magento's `sales_order_place_after` event (`OrderPlaceAfterObserver`) to record order increment IDs, grand totals, tax, shipping, currency, and line items directly on the server without relying on browser pixels.
* **Early HTTP Edge AI Bot Sensor**:
  * `FrontControllerPlugin` (`aroundDispatch` on `FrontControllerInterface`) classifies AI RAG agents (`ChatGPT-User`, `OAI-SearchBot`, `PerplexityBot`), AI Training scrapers (`GPTBot`, `ClaudeBot`, `CCBot`), and Search spiders (`Googlebot`, `Bingbot`).
* **Zero DB-Locking Dual SQLite WAL Architecture**:
  * Buffers events in `var/wsc_analytics/queue_temp.sqlite` and stores pre-computed Bento reports in `reports_temp.sqlite` (`PRAGMA journal_mode = WAL`), eliminating lock contention on Magento's MySQL/MariaDB cluster.
* **Canonical 7-Card Bento Suite & Multi-Period Switcher (`1d`, `7d`, `30d`, `90d`, `365d`)**:
  1. **`card1_traffic`** — Real Visitors, Pageviews, Orders, Revenue & AI/Search/Social/Direct Attribution
  2. **`card2_insights`** — Algorithmic E-Commerce & SEO Action Recommendations
  3. **`card3_bots`** — AI Bot Shield Sensor Network (Live RAG vs. Training Scrapers vs. Search Spiders)
  4. **`card_engagement`** — Engaged Reading Time, TTFI, Scroll Milestones & Bounce Trap Detection
  5. **`card_outbound`** — Categorized External Link Clicks
  6. **`card_ecommerce`** — 4-Stage Benchmark Checkout Ampel (`Product Views` → `Add to Cart` → `Initiate Checkout` → `Purchase`), AOV & Funnel Leak Diagnosis
  7. **`domain_health` + `pagespeed`** — Port-25-Free Domain Sentinel & **SpeedSentinel 2.0 Dual-Thermometer** (🤖 RAG Readiness vs. 👤 Human CWV)
* **On-Demand Cloud Diagnostics & Background Crontab Sync**:
  * Dedicated controllers (`Cards.php`, `Pagespeed.php`, `Sentinel.php`, `Coverage.php`) plus 5-minute Magento cron (`wsc_analytics_batch_sync`) pushing AES-256-CBC encrypted, HMAC-SHA256 (`X-WSC-Sig`) signed batches to `https://api.worldsearchcouncil.org`.

---

## 📦 Directory Structure

```text
wsc-core-magento2/
├── registration.php
├── composer.json                     # wsc/module-analytics v2.2.0
├── etc/
│   ├── module.xml                    # Module setup_version 2.2.0
│   ├── events.xml                    # sales_order_place_after observer
│   ├── crontab.xml                   # 5-minute encrypted batch sync cron
│   ├── frontend/
│   │   ├── di.xml                    # FrontController edge bot interceptor
│   │   └── routes.xml                # /wsc/api/* routes
│   └── adminhtml/
│       ├── system.xml                # Stores -> Configuration -> World Search Council
│       ├── menu.xml                  # Reports -> WSC Analytics & Bot Shield
│       └── routes.xml                # /wsc_admin/* routes
├── Block/
│   ├── Tracker.php                   # Storefront WSC_CONFIG & tracker script block
│   └── Adminhtml/Dashboard.php       # 7-Card Bento Admin block
├── Controller/
│   ├── Api/
│   │   ├── Track.php                 # POST /wsc/api/track
│   │   ├── Webhook.php               # POST /wsc/api/webhook (HMAC-SHA256 verified)
│   │   ├── Cards.php                 # GET /wsc/api/cards (7-Card JSON polling)
│   │   ├── Pagespeed.php             # POST /wsc/api/pagespeed (SpeedSentinel 2.0 trigger)
│   │   ├── Sentinel.php              # POST /wsc/api/sentinel (Domain Sentinel trigger)
│   │   ├── Coverage.php              # POST /wsc/api/coverage (AI Coverage Inspector proxy)
│   │   └── Refresh.php               # POST /wsc/api/refresh
│   └── Adminhtml/Dashboard/Index.php
├── Helper/Data.php
├── Model/
│   ├── DatabaseManager.php           # Hardened SQLite WAL manager
│   ├── BotTracker.php                # Canonical AI/RAG/Search bot taxonomy
│   └── CoreService.php               # 7-Card persistence, AES-256, HMAC & Gateway sync
├── Observer/OrderPlaceAfterObserver.php
├── Plugin/FrontControllerPlugin.php
├── Cron/SyncBatch.php
└── view/
    ├── frontend/
    │   ├── layout/default.xml
    │   ├── templates/tracker.phtml
    │   └── web/js/wsc-tracker.js
    └── adminhtml/
        ├── layout/wsc_admin_dashboard_index.xml
        ├── templates/dashboard.phtml # 7-Card Bento Grid template
        └── web/
            ├── js/wsc-dashboard.js   # Multi-period switcher & diagnostic progress banner
            └── css/wsc-dashboard.css
```

---

## 🚀 Installation

1. Copy this module to `app/code/Wsc/Analytics`.
2. Enable the module and compile DI:
   ```bash
   bin/magento module:enable Wsc_Analytics
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```
3. Configure optional WSC Gateway sync at **Stores → Configuration → World Search Council → WSC Analytics & Bot Shield** and open the 7-Card Bento Dashboard under **Reports → WSC Analytics & Bot Shield**.

---

## 🔗 Resources & License

- **Official Portal & Member Console:** [https://www.worldsearchcouncil.org](https://www.worldsearchcouncil.org)
- **Magento 2 Integration Docs:** [https://world-search-council.github.io/wsc-docs/platforms/magento2/](https://world-search-council.github.io/wsc-docs/platforms/magento2/)
- **License:** [GNU General Public License v2.0 or later (GPL-2.0-or-later)](LICENSE)
