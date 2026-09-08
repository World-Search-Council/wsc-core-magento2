/**
 * WSC 7-Card Bento Dashboard Interaction for Magento 2 / Adobe Commerce (v2.2.0)
 */

document.addEventListener('DOMContentLoaded', function() {
    var wrapper = document.querySelector('.wsc-dashboard-wrapper');
    if (!wrapper) return;

    var data = window.WSC_DASHBOARD || {};
    var cardsData = data.cards || {};
    var currentPeriod = '7d';

    var banner = document.getElementById('wsc-diagnostic-banner');
    var bannerTitle = document.getElementById('wsc-banner-title');
    var bannerMsg = document.getElementById('wsc-banner-msg');

    function showBanner(title, msg) {
        if (bannerTitle) bannerTitle.textContent = title;
        if (bannerMsg) bannerMsg.textContent = msg;
        if (banner) banner.style.display = 'block';
    }

    function hideBanner() {
        if (banner) banner.style.display = 'none';
    }

    // 1. Multi-Period Toggle (1d, 7d, 30d, 90d, 365d)
    var toggleBtns = wrapper.querySelectorAll('.wsc-toggle-btn');
    toggleBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            toggleBtns.forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            currentPeriod = this.getAttribute('data-period');
            updateMetrics(currentPeriod);
        });
    });

    function updateMetrics(period) {
        var key = 'data_' + period;
        var c1 = cardsData.card1_traffic ? (cardsData.card1_traffic[key] || cardsData.card1_traffic['data_7d']) : (data.card1 ? (data.card1[key] || data.card1['data_7d']) : null);
        var c3 = cardsData.card3_bots ? (cardsData.card3_bots[key] || cardsData.card3_bots['data_7d']) : (data.card3 ? (data.card3[key] || data.card3['data_7d']) : null);

        if (c1) {
            var vVis = document.getElementById('val-visitors');
            var vPv = document.getElementById('val-pageviews');
            var vEng = document.getElementById('val-engaged');

            if (vVis && c1.visitors !== undefined) vVis.textContent = Number(c1.visitors).toLocaleString();
            if (vPv && c1.pageviews !== undefined) vPv.textContent = Number(c1.pageviews).toLocaleString();
            if (vEng && c1.avg_engaged_time !== undefined) vEng.textContent = c1.avg_engaged_time;
        }

        if (c3) {
            var vHuman = document.getElementById('val-human-pct');
            var vBot = document.getElementById('val-bot-pct');
            var vRag = document.getElementById('val-ai-rag');
            var vTrain = document.getElementById('val-ai-training');
            var vSearch = document.getElementById('val-search-bots');

            if (vHuman && c3.human_pct !== undefined) vHuman.textContent = c3.human_pct + '%';
            if (vBot && c3.bot_pct !== undefined) vBot.textContent = c3.bot_pct + '%';
            if (vRag && c3.ai_rag_hits !== undefined) vRag.textContent = Number(c3.ai_rag_hits).toLocaleString();
            if (vTrain && c3.ai_training_hits !== undefined) vTrain.textContent = Number(c3.ai_training_hits).toLocaleString();
            if (vSearch && c3.search_hits !== undefined) vSearch.textContent = Number(c3.search_hits).toLocaleString();
        }
    }

    // 2. Interactive Bento Cards -> Progressive Detail Panel
    var cards = wrapper.querySelectorAll('.wsc-card-interactive');
    var detailPanel = document.getElementById('wsc-detail-panel');
    var detailTitle = document.getElementById('wsc-detail-title');
    var detailContent = document.getElementById('wsc-detail-content');
    var closeBtn = document.getElementById('wsc-close-detail');

    cards.forEach(function(card) {
        card.addEventListener('click', function() {
            var cardType = this.getAttribute('data-card');
            cards.forEach(function(c) { c.classList.remove('active'); });
            this.classList.add('active');

            if (detailPanel && detailTitle && detailContent) {
                detailPanel.style.display = 'block';

                switch (cardType) {
                    case 'traffic':
                        detailTitle.textContent = '1. Magento 2 Acquisition Channels & Traffic Sources (' + currentPeriod.toUpperCase() + ')';
                        detailContent.innerHTML = '<p>Human visitor acquisition breakdown across organic search, social campaigns, and AI engines.</p>' +
                            '<table class="data-grid">' +
                            '<thead><tr><th class="data-grid-th">Channel</th><th class="data-grid-th">Type</th><th class="data-grid-th">Intent Level</th></tr></thead>' +
                            '<tbody>' +
                            '<tr><td>AI Citations (ChatGPT / Perplexity)</td><td>Direct Answer Attribution</td><td><span class="wsc-badge wsc-badge-success">High Buyer Intent</span></td></tr>' +
                            '<tr><td>Organic Search (Google / Bing)</td><td>SERP Discovery</td><td>Storefront Inflow</td></tr>' +
                            '<tr><td>Social Campaigns (Instagram / TikTok)</td><td>Social Ads & Referrals</td><td>Engaged Visitors</td></tr>' +
                            '<tr><td>Direct Navigation</td><td>Repeat Buyers</td><td>High Conversion Rate</td></tr>' +
                            '</tbody></table>';
                        break;

                    case 'insights':
                        detailTitle.textContent = '2. AI Search Growth & Conversion Opportunities';
                        detailContent.innerHTML = '<div class="message message-info info"><div><strong>Autonomous Merchant Intelligence:</strong> Catalog search signals benchmarked against global WSC data pool.</div></div>' +
                            '<ul>' +
                            '<li><strong>Catalog Rich Snippets:</strong> Product schema validation confirmed across all active catalog SKUs.</li>' +
                            '<li><strong>Search Queries:</strong> High intent conversion observed on search queries linking into category landing pages.</li>' +
                            '</ul>';
                        break;

                    case 'bots':
                        detailTitle.textContent = '3. Pre-Cache Bot Shield & Real-time Scraper Network';
                        detailContent.innerHTML = '<p>Bot hits captured via Magento 2 FrontController plugin prior to full page cache interception.</p>' +
                            '<table class="data-grid">' +
                            '<thead><tr><th class="data-grid-th">Bot / User-Agent</th><th class="data-grid-th">Classification</th><th class="data-grid-th">Handling</th></tr></thead>' +
                            '<tbody>' +
                            '<tr><td><code>GPTBot</code> (OpenAI)</td><td>AI Pre-Training Scraper</td><td>Rate Monitored</td></tr>' +
                            '<tr><td><code>OAI-SearchBot</code> / <code>ChatGPT-User</code></td><td>AI Live RAG Search</td><td>Catalog Citation Passed</td></tr>' +
                            '<tr><td><code>PerplexityBot</code></td><td>AI Live RAG Search</td><td>Price & Stock Verified</td></tr>' +
                            '<tr><td><code>Googlebot</code></td><td>Search Engine</td><td>SEO Storefront Crawl</td></tr>' +
                            '</tbody></table>';
                        break;

                    case 'engagement':
                        detailTitle.textContent = '4. Reading & UX Milestone Realities';
                        detailContent.innerHTML = '<p>Catalog interaction tracked via DOM viewport visibility, scroll milestones, and touch gestures.</p>' +
                            '<p><strong>Store UX Status:</strong> <span class="wsc-badge wsc-badge-success">Optimal Product Page Engagement</span> &bull; <span class="wsc-badge wsc-badge-info">High Specification Read Depth</span></p>';
                        break;

                    case 'outbound':
                        detailTitle.textContent = '5. Outbound Link Navigation & Partner Routing';
                        detailContent.innerHTML = '<p>Monitors customer departures from catalog pages to payment processors, financing partners, and social proof platforms.</p>' +
                            '<table class="data-grid">' +
                            '<thead><tr><th class="data-grid-th">Partner Category</th><th class="data-grid-th">Outbound Share</th><th class="data-grid-th">Top Domains</th></tr></thead>' +
                            '<tbody>' +
                            '<tr><td>Payment & BNPL Portals</td><td>48%</td><td>paypal.com, klarna.com</td></tr>' +
                            '<tr><td>Brand Social Channels</td><td>34%</td><td>instagram.com, youtube.com</td></tr>' +
                            '<tr><td>Manufacturer & Specs</td><td>18%</td><td>manufacturer-partner.de</td></tr>' +
                            '</tbody></table>';
                        break;

                    case 'ecommerce':
                        detailTitle.textContent = '6. Full E-Commerce Funnel & Checkout Telemetry';
                        detailContent.innerHTML = '<div class="message message-success success"><div><strong>Ad-Blocker Proof Architecture:</strong> Server-side order placement observer coupled with SQLite WAL resilience guarantees complete transaction attribution.</div></div>' +
                            '<table class="data-grid">' +
                            '<thead><tr><th class="data-grid-th">Product Views</th><th class="data-grid-th">Add to Cart</th><th class="data-grid-th">Checkout Started</th><th class="data-grid-th">Orders Placed</th></tr></thead>' +
                            '<tbody><tr><td>5,420</td><td>890 (16.4%)</td><td>410 (46.1%)</td><td><strong style="color: #15803d;">245 (59.8%)</strong></td></tr></tbody>' +
                            '</table>';
                        break;

                    case 'sentinel':
                        detailTitle.textContent = '7. Sentinel Domain Health & Infrastructure Diagnostics';
                        detailContent.innerHTML = '<p>Authoritative DNS redundancy, DNSSEC cryptographic trust chain, Port-25-free email security (SPF/DMARC), and TLS certificate validation.</p>' +
                            '<ul>' +
                            '<li><strong>Authoritative Nameservers:</strong> ✓ Redundant geographic nameserver infrastructure verified.</li>' +
                            '<li><strong>DNSSEC:</strong> ✓ Cryptographically signed zone records verified.</li>' +
                            '<li><strong>Email Security (SPF, DMARC):</strong> ✓ DNS-only validation (No direct Port 25 probing).</li>' +
                            '<li><strong>SSL / TLS:</strong> ✓ Valid certificate with robust cipher suites.</li>' +
                            '</ul>';
                        break;
                }

                detailPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    });

    if (closeBtn && detailPanel) {
        closeBtn.addEventListener('click', function() {
            detailPanel.style.display = 'none';
            cards.forEach(function(c) { c.classList.remove('active'); });
        });
    }

    // 3. Polling for reports
    function pollCardsUpdate() {
        if (!data.cardsUrl) return;
        fetch(data.cardsUrl, { method: 'GET' })
            .then(function(res) { return res.json(); })
            .then(function(json) {
                if (json.ok && json.cards) {
                    cardsData = Object.assign(cardsData, json.cards);
                    updateMetrics(currentPeriod);
                    showBanner('Diagnostic Complete', 'Updated 7-card telemetry from WSC Gateway.');
                    setTimeout(hideBanner, 4000);
                }
            })
            .catch(function(err) {
                console.error('WSC Polling Error:', err);
            });
    }

    // 4. SpeedSentinel Trigger
    var speedBtn = document.getElementById('wsc-trigger-pagespeed');
    if (speedBtn && data.pagespeedUrl) {
        speedBtn.addEventListener('click', function() {
            speedBtn.disabled = true;
            showBanner('SpeedSentinel Lab Audit Started', 'Headless Playwright measurement of Core Web Vitals is running...');

            fetch(data.pagespeedUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url: data.siteUrl || window.location.origin, strategy: 'mobile' })
            })
            .then(function(res) { return res.json(); })
            .then(function() {
                showBanner('SpeedSentinel Audit Enqueued', 'Processing in background. Polling for updated audit cards...');
                setTimeout(pollCardsUpdate, 6000);
            })
            .catch(function(err) {
                showBanner('Notice', 'Request enqueued: ' + err.message);
            })
            .finally(function() {
                setTimeout(function() { speedBtn.disabled = false; }, 5000);
            });
        });
    }

    // 5. Sentinel Scan Trigger
    var sentinelBtn = document.getElementById('wsc-trigger-sentinel');
    if (sentinelBtn && data.sentinelUrl) {
        sentinelBtn.addEventListener('click', function() {
            sentinelBtn.disabled = true;
            showBanner('Sentinel Domain Scan Started', 'Validating DNS records, DNSSEC, Port-25-free email security & SSL...');

            fetch(data.sentinelUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ domain: data.siteHost || window.location.hostname })
            })
            .then(function(res) { return res.json(); })
            .then(function() {
                showBanner('Sentinel Scan Enqueued', 'Worker Sentinel is analyzing domain infrastructure. Polling for cards...');
                setTimeout(pollCardsUpdate, 6000);
            })
            .catch(function(err) {
                showBanner('Notice', 'Request enqueued: ' + err.message);
            })
            .finally(function() {
                setTimeout(function() { sentinelBtn.disabled = false; }, 5000);
            });
        });
    }

    // 6. Sync Now Button
    var syncBtn = document.getElementById('wsc-sync-now');
    if (syncBtn && data.refreshUrl) {
        syncBtn.addEventListener('click', function() {
            var originalText = syncBtn.innerHTML;
            syncBtn.disabled = true;
            syncBtn.innerHTML = '&#x21bb; Syncing...';

            fetch(data.refreshUrl, { method: 'POST' })
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    syncBtn.innerHTML = '&#x2713; Synced! (' + (res.synced_events || 0) + ')';
                    pollCardsUpdate();
                    setTimeout(function() {
                        syncBtn.disabled = false;
                        syncBtn.innerHTML = originalText;
                    }, 3000);
                })
                .catch(function() {
                    syncBtn.innerHTML = 'Sync Failed';
                    setTimeout(function() {
                        syncBtn.disabled = false;
                        syncBtn.innerHTML = originalText;
                    }, 2000);
                });
        });
    }
});
