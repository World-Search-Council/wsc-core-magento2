# WSC Search & Health for Magento 2 / Adobe Commerce (`v2.2.3`)

<p align="center">
  <strong>Official Magento 2 / Adobe Commerce Connector of the World Search Council (WSC) Standard</strong><br>
  100% Cookieless Zero-PII Analytics, FrontController AI Bot Sensor, Ad-Blocker Proof Order Telemetry, Live WSC Member Bridge, AI Coverage Inspector, Dynamic Benchmarks, <code>/llms.txt</code> & Opt-In <code>IndexNow</code>
</p>

<p align="center">
  <a href="https://www.worldsearchcouncil.org"><img src="https://img.shields.io/badge/Portal-worldsearchcouncil.org-0F172A?style=flat-square" alt="World Search Council"></a>
  <img src="https://img.shields.io/badge/Version-2.2.3-F26322?style=flat-square&logo=magento" alt="Magento 2 v2.2.3">
  <img src="https://img.shields.io/badge/Magento-2.4.x_|_Adobe_Commerce-F26322?style=flat-square" alt="Magento 2.4">
  <img src="https://img.shields.io/badge/Privacy-100%25_Cookieless_Zero--PII-10B981?style=flat-square" alt="Cookieless">
  <img src="https://img.shields.io/badge/License-GPL--2.0--or--later-orange?style=flat-square" alt="License">
</p>

---

## 🌟 Key Features (`v2.2.3` — Full WordPress SSOT Parity)

* **100% Cookieless & Strict Zero-PII Privacy**: GDPR, TDDDG, ePrivacy, and CCPA compliant — computes daily rotating salted session hashes (`SHA256(IP + UA + DailySalt)`), never sets analytics cookies, and never stores raw client IP addresses in SQLite or outbound payloads.
* **Strict Opt-In Cloud Handshake & Gateway `v2.3` Wire Contract**:
  * Local SQLite WAL telemetry works 100% standalone out of the box (`hybrid_enabled = 0`, `indexnow_enabled = 0` by default).
  * When opted in, pairs via `POST /v1/register` (`project_id` + `api_secret`) and transmits AES-256-CBC encrypted, `HMAC-SHA256` (`X-WSC-Sig` with `api_secret`) signed envelopes to `POST /v1/ingest`.
* **Live WSC Authority Portal Credential Bridge & 100% Ad-Free Member UI**:
  * Real-time license verification & domain fleet heartbeat against `POST https://www.worldsearchcouncil.org/api/v1/license/verify`, bidirectional Agency Co-Branding sync (`co_branding_update`), and `GET /api/v1/partners/featured` rendered strictly for unlicensed Community installs.
* **AI Coverage Inspector & Dynamic Industry Benchmarks**:
  * URL-level RAG vs. Training vs. Search crawler inspection (`POST /v1/ai-coverage/query` with local SQLite fallback) plus 10-vertical industry percentile benchmarks (`POST /v1/benchmarks/query` & `/v1/benchmarks/classify`).
* **AI Discovery Manifest (`/llms.txt`) & Opt-In `IndexNow`**:
  * Serves dynamic Markdown `/llms.txt` cataloging published Magento products, categories, and CMS pages via `FrontControllerPlugin`, plus opt-in `IndexNow` instant URL push (`ProductSaveAfterObserver` + virtual `/{32-hex-key}.txt` ownership verification).
* **SSRF-Hardened Diagnostics & Canonical 7-Card Bento Suite (`1d`, `7d`, `30d`, `90d`, `365d`)**:
  * Enforces public-DNS (`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`) and same-host validation on **SpeedSentinel 2.0** and **Port-25-Free Domain Sentinel** triggers.

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
3. Configure optional WSC Gateway sync at **Stores → Configuration → World Search Council → WSC Search & Health** and open the 7-Card Bento Dashboard under **Reports → WSC Analytics & Bot Shield**.

---

## 🔗 Resources & License

- **Official Portal & Member Console:** [https://www.worldsearchcouncil.org](https://www.worldsearchcouncil.org)
- **GitHub Organization & Maintainer:** [World-Search-Council](https://github.com/World-Search-Council) · Founding Lead Maintainer: **[@thebennos](https://github.com/thebennos)**
- **License:** [GNU General Public License v2.0 or later (GPL-2.0-or-later)](LICENSE)
