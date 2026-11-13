/**
 * WSC v2.2.3 7-Card Bento Dashboard, AI Coverage Inspector, Industry Benchmarks,
 * Live WSC Member Portal Bridge & Opt-In IndexNow Studio for Magento 2 / Adobe Commerce.
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

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function postDispatch(actionName, payload) {
        var formKey = data.formKey || window.FORM_KEY || '';
        var url = data.dispatchUrl || '';
        if (url.indexOf('?') === -1) {
            url += '?isAjax=true&form_key=' + encodeURIComponent(formKey);
        } else {
            url += '&isAjax=true&form_key=' + encodeURIComponent(formKey);
        }
        var bodyObj = Object.assign({ wsc_action: actionName, form_key: formKey }, payload || {});
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(bodyObj)
        }).then(function(res) { return res.json(); });
    }

    function setElText(id, val) {
        var el = document.getElementById(id);
        if (el && val !== undefined && val !== null) el.textContent = String(val);
    }

    // 1. Multi-Period Toggle across all Bento Cards (1d, 7d, 30d, 90d, 365d)
    var toggleBtns = wrapper.querySelectorAll('.wsc-toggle-btn');
    toggleBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            toggleBtns.forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            currentPeriod = this.getAttribute('data-period');
            updateMetrics(currentPeriod);
        });
    });

    function resolvePeriodSlice(cardObj, period) {
        if (!cardObj) return null;
        return cardObj['data_' + period] || (cardObj.periods && cardObj.periods[period]) || cardObj['data_7d'] || null;
    }

    function updateMetrics(period) {
        var c1 = resolvePeriodSlice(cardsData.card1_traffic || data.card1, period);
        var c2 = resolvePeriodSlice(cardsData.card2_insights || data.card2, period);
        var c3 = resolvePeriodSlice(cardsData.card3_bots || data.card3, period);
        var c4 = resolvePeriodSlice(cardsData.card_engagement || data.cardEngagement, period);
        var c5 = resolvePeriodSlice(cardsData.card_outbound || data.cardOutbound, period);
        var c6 = resolvePeriodSlice(cardsData.card_ecommerce || data.cardEcommerce, period);

        if (c1) {
            if (c1.visitors !== undefined) setElText('val-visitors', Number(c1.visitors).toLocaleString());
            if (c1.pageviews !== undefined) setElText('val-pageviews', Number(c1.pageviews).toLocaleString());
            if (c1.avg_engaged_time !== undefined) setElText('val-engaged', c1.avg_engaged_time);
            if (c1.channels) {
                var aiP = Number(c1.channels.ai_pct || 0);
                var seP = Number(c1.channels.search_pct || 0);
                var soP = Number(c1.channels.social_pct || 0);
                var diP = Number(c1.channels.direct_pct || 0);
                var segAi = document.getElementById('seg-ai');
                var segSe = document.getElementById('seg-search');
                var segSo = document.getElementById('seg-social');
                var segDi = document.getElementById('seg-direct');
                if (segAi) segAi.style.width = aiP + '%';
                if (segSe) segSe.style.width = seP + '%';
                if (segSo) segSo.style.width = soP + '%';
                if (segDi) segDi.style.width = diP + '%';
                setElText('lbl-ai-pct', aiP);
                setElText('lbl-search-pct', seP);
                setElText('lbl-social-pct', soP);
            }
        }

        if (c2 && c2.summary) {
            setElText('val-insight-summary', c2.summary);
        }

        if (c3) {
            if (c3.human_pct !== undefined) setElText('val-human-pct', c3.human_pct + '%');
            if (c3.bot_pct !== undefined) setElText('val-bot-pct', c3.bot_pct + '%');
            if (c3.ai_rag_hits !== undefined) setElText('val-ai-rag', Number(c3.ai_rag_hits).toLocaleString());
            if (c3.ai_training_hits !== undefined) setElText('val-ai-training', Number(c3.ai_training_hits).toLocaleString());
            if (c3.search_hits !== undefined) setElText('val-search-bots', Number(c3.search_hits).toLocaleString());
        }

        if (c4) {
            setElText('val-read-time', c4.avg_reading_time);
            setElText('val-ttfi', c4.avg_ttfi);
            setElText('val-focus-ratio', c4.focus_ratio);
            setElText('val-scroll-25', c4.scroll_25);
            setElText('val-scroll-50', c4.scroll_50);
            setElText('val-scroll-75', c4.scroll_75);
            setElText('val-scroll-100', c4.scroll_100);
        }

        if (c5) {
            setElText('val-out-payment', c5.payment_pct);
            setElText('val-out-social', c5.social_pct);
            setElText('val-out-partners', c5.partners_pct);
            setElText('val-out-top', c5.top_domains);
        }

        if (c6) {
            setElText('val-ec-views', c6.product_views);
            setElText('val-ec-cart', c6.add_to_cart);
            setElText('val-ec-checkout', c6.checkout_started);
            setElText('val-ec-purchases', c6.purchases);
            setElText('val-ec-cr', c6.conversion_rate);
            setElText('val-ec-abandon', c6.abandonment_rate);
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
                detailTitle.textContent = 'Detailed Diagnostics — ' + cardType.toUpperCase() + ' (' + currentPeriod.toUpperCase() + ')';
                var rawCard = cardsData['card_' + cardType] || cardsData.domain_health || {};
                detailContent.innerHTML = '<p>Zero-PII SQLite WAL &amp; WSC Gateway v2.3 verified diagnostics for <strong>' + escHtml(data.siteHost) + '</strong>.</p>' +
                    '<pre style="max-height:260px;overflow:auto;background:#0f172a;color:#e2e8f0;padding:0.85rem;border-radius:6px;font-size:12px;">' +
                    escHtml(JSON.stringify(rawCard, null, 2)) + '</pre>';
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

    // 3. Polling for updated reports
    function pollCardsUpdate() {
        postDispatch('cards', {}).then(function(json) {
            if (json && json.ok && json.cards) {
                cardsData = Object.assign(cardsData, json.cards);
                updateMetrics(currentPeriod);
                showBanner('Diagnostic Complete', 'Updated 7-card telemetry from WSC Gateway.');
                setTimeout(hideBanner, 4000);
            }
        }).catch(function() {});
    }

    // 4. SpeedSentinel Trigger
    var speedBtn = document.getElementById('wsc-trigger-pagespeed');
    if (speedBtn) {
        speedBtn.addEventListener('click', function() {
            speedBtn.disabled = true;
            showBanner('SpeedSentinel 2.0 Lab Audit Started', 'Headless Playwright measurement of AI RAG Readiness & Core Web Vitals is running...');
            postDispatch('pagespeed', { url: data.siteUrl || window.location.origin, strategy: 'mobile' })
                .then(function(res) {
                    if (res && res.ok === false) {
                        showBanner('SpeedSentinel Notice', res.error || 'Gateway sync required.');
                    } else {
                        showBanner('SpeedSentinel Audit Enqueued', 'Processing asynchronously. Polling for updated cards...');
                        setTimeout(pollCardsUpdate, 6000);
                    }
                })
                .finally(function() {
                    setTimeout(function() { speedBtn.disabled = false; }, 4000);
                });
        });
    }

    // 5. Sentinel Scan Trigger
    var sentinelBtn = document.getElementById('wsc-trigger-sentinel');
    if (sentinelBtn) {
        sentinelBtn.addEventListener('click', function() {
            sentinelBtn.disabled = true;
            showBanner('Sentinel Domain Scan Started', 'Validating DNS redundancy, DNSSEC, Port-25-free email security & TLS...');
            postDispatch('sentinel', { domain: data.siteHost || window.location.hostname })
                .then(function(res) {
                    if (res && res.ok === false) {
                        showBanner('Sentinel Notice', res.error || 'Gateway sync required.');
                    } else {
                        showBanner('Sentinel Scan Enqueued', 'Worker Sentinel is analyzing domain infrastructure...');
                        setTimeout(pollCardsUpdate, 6000);
                    }
                })
                .finally(function() {
                    setTimeout(function() { sentinelBtn.disabled = false; }, 4000);
                });
        });
    }

    // 6. Sync Now Button
    var syncBtn = document.getElementById('wsc-sync-now');
    if (syncBtn) {
        syncBtn.addEventListener('click', function() {
            var orig = syncBtn.innerHTML;
            syncBtn.disabled = true;
            syncBtn.innerHTML = '&#x21bb; Syncing...';
            postDispatch('refresh', {})
                .then(function(res) {
                    syncBtn.innerHTML = '&#x2713; Synced (' + (res.synced_events || 0) + ')';
                    pollCardsUpdate();
                    setTimeout(function() { syncBtn.disabled = false; syncBtn.innerHTML = orig; }, 3000);
                })
                .catch(function() {
                    syncBtn.disabled = false;
                    syncBtn.innerHTML = orig;
                });
        });
    }

    // 7. AI Coverage Inspector
    var covBtn = document.getElementById('wsc-ai-coverage-btn');
    if (covBtn) {
        covBtn.addEventListener('click', function() {
            var q = (document.getElementById('wsc-ai-coverage-input') || {}).value || '/';
            var tf = (document.getElementById('wsc-ai-coverage-tf') || {}).value || '30d';
            var out = document.getElementById('wsc-ai-coverage-result');
            covBtn.disabled = true;
            if (out) out.textContent = 'Querying AI & Search crawler telemetry...';
            postDispatch('ai_coverage', { query: q, timeframe: tf })
                .then(function(res) {
                    if (!out) return;
                    var total = Number(res.total_hits || 0);
                    var rag = Number(res.ai_rag_hits || 0);
                    var train = Number(res.ai_training_hits || 0);
                    var search = Number(res.search_hits || 0);
                    out.innerHTML = '<strong>Status:</strong> ' + escHtml(res.coverage_status || res.status || 'checked') +
                        ' (' + escHtml(res.source || 'local') + ')<br>' +
                        '<strong>Total Hits:</strong> ' + total + ' &bull; 🤖 RAG: ' + rag +
                        ' &bull; 🧠 Training: ' + train + ' &bull; 🔍 Search: ' + search;
                })
                .finally(function() { covBtn.disabled = false; });
        });
    }

    // 8. Industry Benchmarks Classify & Refresh
    function renderBenchmarkMetrics(res) {
        if (!res) return;
        setElText('wsc-bench-label', res.vertical_label || res.vertical);
        setElText('wsc-bench-source', res.source || 'updated');
        var container = document.getElementById('wsc-bench-metrics');
        if (container && res.metrics) {
            var html = '';
            Object.keys(res.metrics).forEach(function(k) {
                var m = res.metrics[k];
                var rLbl = (m.rating && m.rating.label) ? m.rating.label : 'Median';
                html += '<div class="wsc-bot-item"><span>' + escHtml(m.label || k) + ': <strong>' +
                    escHtml(m.site_formatted || m.site) + '</strong></span><span class="wsc-badge">' +
                    escHtml(rLbl) + ' (P50: ' + escHtml(m.p50) + ')</span></div>';
            });
            container.innerHTML = html;
        }
    }

    var benchClassBtn = document.getElementById('wsc-bench-classify-btn');
    if (benchClassBtn) {
        benchClassBtn.addEventListener('click', function() {
            var sel = document.getElementById('wsc-bench-vertical');
            benchClassBtn.disabled = true;
            postDispatch('benchmarks_classify', { vertical: sel ? sel.value : '' })
                .then(function() { return postDispatch('benchmarks_query', { days: 7, force: true }); })
                .then(renderBenchmarkMetrics)
                .finally(function() { benchClassBtn.disabled = false; });
        });
    }

    var benchRefBtn = document.getElementById('wsc-bench-refresh-btn');
    if (benchRefBtn) {
        benchRefBtn.addEventListener('click', function() {
            benchRefBtn.disabled = true;
            postDispatch('benchmarks_query', { days: 7, force: true })
                .then(renderBenchmarkMetrics)
                .finally(function() { benchRefBtn.disabled = false; });
        });
    }

    // 9. IndexNow Manual Push
    var inBtn = document.getElementById('wsc-indexnow-btn');
    if (inBtn) {
        inBtn.addEventListener('click', function() {
            var urlsVal = (document.getElementById('wsc-indexnow-urls') || {}).value || '';
            var st = document.getElementById('wsc-indexnow-status');
            inBtn.disabled = true;
            postDispatch('indexnow_submit', { urls: urlsVal })
                .then(function(res) {
                    if (st) {
                        st.textContent = res.ok
                            ? ('IndexNow Push Accepted (HTTP ' + (res.status || 200) + ', ' + (res.submitted || 1) + ' URL)')
                            : ('IndexNow Notice: ' + (res.error || 'Enable IndexNow in Stores -> Configuration first.'));
                    }
                })
                .finally(function() { inBtn.disabled = false; });
        });
    }

    // 10. WSC Member License Verify, Disconnect & Co-Branding
    var licBtn = document.getElementById('wsc-license-verify-btn');
    if (licBtn) {
        licBtn.addEventListener('click', function() {
            var keyVal = (document.getElementById('wsc-license-input') || {}).value || '';
            licBtn.disabled = true;
            postDispatch('license_verify', { license_key: keyVal })
                .then(function(res) {
                    showBanner('WSC Credential Verification', res.message || ('Status: ' + (res.status || 'updated')));
                    if (res.ok) {
                        setTimeout(function() { window.location.reload(); }, 1000);
                    }
                })
                .finally(function() { licBtn.disabled = false; });
        });
    }

    var discBtn = document.getElementById('wsc-license-disconnect-btn');
    if (discBtn) {
        discBtn.addEventListener('click', function() {
            discBtn.disabled = true;
            postDispatch('license_disconnect', {}).then(function() {
                window.location.reload();
            });
        });
    }

    var cbBtn = document.getElementById('wsc-cb-save-btn');
    if (cbBtn) {
        cbBtn.addEventListener('click', function() {
            cbBtn.disabled = true;
            postDispatch('cobranding_save', {
                enabled: !!(document.getElementById('wsc-cb-enabled') || {}).checked,
                name: (document.getElementById('wsc-cb-name') || {}).value || '',
                url: (document.getElementById('wsc-cb-url') || {}).value || '',
                logo_url: (document.getElementById('wsc-cb-logo') || {}).value || '',
                tagline: (document.getElementById('wsc-cb-tagline') || {}).value || ''
            }).then(function() {
                showBanner('Agency Co-Branding Saved', 'Synchronized with WSC Member Portal.');
                setTimeout(function() { window.location.reload(); }, 900);
            }).finally(function() { cbBtn.disabled = false; });
        });
    }
});
