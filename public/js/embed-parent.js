(function () {
    var FV_TASK_PARAM = 'fv-task';
    var SILENT_LOGIN_GUARD = 'fv_silent_auth';
    var pendingFvTask = readFvTaskParam();

    // Setzt der OAuth-Callback, wenn der stille Top-Level-Login keine Login-Session vorfand.
    var silentLoginDeniedOnThisPage = false;

    // Zustand der Task-Detail-Sidebar (nur eine gleichzeitig sichtbar).
    var sidebar = {
        overlay: null,
        panel: null,
        iframe: null,
        sourceIframe: null,
        sourceOrigin: '',
        keyListener: null,
    };

    // Zustand des zentrierten Modals (Review-Hinweis, Feedback), das eingebettete
    // FeatValue-Seiten per `fv-app-frame-modal` anfordern.
    var modal = {
        overlay: null,
        iframe: null,
        url: '',
        sourceWindow: null,
        sourceOrigin: '',
        keyListener: null,
    };

    // Anhang-Lightbox (Layout wie app Thumbnail.vue), angefordert per
    // `fv-app-frame-lightbox-open` aus Tasks-/Dashboard-IFrames.
    var lightbox = {
        overlay: null,
        imageEl: null,
        iframeEl: null,
        prevEl: null,
        nextEl: null,
        image: null,
        images: null,
        kinds: null,
        kind: null,
        currentIndex: 0,
        attachment: null,
        sourceWindow: null,
        sourceOrigin: '',
        keyListener: null,
    };

    function injectEmbedStyles() {
        if (document.getElementById('fv-embed-sidebar-styles')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'fv-embed-sidebar-styles';
        style.textContent = [
            '.fv-embed-sidebar-overlay{position:fixed;inset:0;z-index:2147483000;background:rgba(15,23,42,.45);',
            'display:flex;justify-content:flex-end;opacity:0;transition:opacity .18s ease;}',
            '.fv-embed-sidebar-overlay.is-open{opacity:1;}',
            '.fv-embed-sidebar-panel{position:relative;height:100%;width:520px;max-width:100%;background:transparent;',
            'box-shadow:-8px 0 24px rgba(15,23,42,.25);transform:translateX(24px);transition:transform .18s ease;',
            'display:flex;flex-direction:column;}',
            '.fv-embed-sidebar-overlay.is-open .fv-embed-sidebar-panel{transform:translateX(0);}',
            /* body p-5 (20px) + Card-Innenabstand; right etwas größer wegen Glyph-Metrik von × */
            '.fv-embed-sidebar-close{position:absolute;top:36px;right:48px;z-index:2;border:0;background:transparent;',
            'font-size:28px;line-height:1;cursor:pointer;color:#64748b;padding:0;}',
            '.fv-embed-sidebar-close:hover{background:transparent;color:#64748b;}',
            '.fv-embed-sidebar-iframe{border:0;width:100%;height:100%;flex:1 1 auto;}',
            '@media (max-width:640px){.fv-embed-sidebar-panel{width:100%;}}',
            '.fv-embed-login-gate{position:absolute;inset:0;z-index:5;display:flex;align-items:center;justify-content:center;',
            'padding:24px;background:rgba(248,250,252,.92);box-sizing:border-box;}',
            '.fv-embed-login-gate-card{max-width:360px;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:12px;',
            'box-shadow:0 10px 30px rgba(15,23,42,.12);padding:24px;text-align:center;font-family:system-ui,-apple-system,Segoe UI,sans-serif;}',
            '.fv-embed-login-gate-card p{margin:0 0 12px;color:#334155;font-size:15px;line-height:1.45;}',
            '.fv-embed-login-gate-card .fv-embed-login-hint{color:#64748b;font-size:13px;margin-bottom:18px;}',
            '.fv-embed-login-gate-button{appearance:none;border:0;border-radius:8px;background:#0f172a;color:#fff;',
            'font-size:15px;font-weight:600;padding:10px 16px;cursor:pointer;}',
            '.fv-embed-login-gate-button:hover{background:#1e293b;}',
            '.fv-embed-login-gate-button:disabled{opacity:.6;cursor:wait;}',
            '.fv-embed-login-gate-actions{display:flex;flex-direction:column;gap:10px;align-items:stretch;}',
            '.fv-embed-login-gate-button.is-secondary{background:#fff;color:#0f172a;border:1px solid #cbd5e1;}',
            '.fv-embed-login-gate-button.is-secondary:hover{background:#f8fafc;}',
            /* Über der Sidebar, weil das Modal aus einem Sidebar-IFrame angefordert werden kann. */
            '.fv-embed-modal-overlay{position:fixed;inset:0;z-index:2147483100;background:rgba(15,23,42,.45);',
            'display:flex;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;}',
            '.fv-embed-modal-panel{position:relative;width:100%;max-width:672px;border-radius:12px;overflow:hidden;',
            'box-shadow:0 20px 45px rgba(15,23,42,.35);background:#fff;}',
            '.fv-embed-modal-close{position:absolute;top:8px;right:16px;z-index:2;border:0;background:transparent;',
            'font-size:28px;line-height:1;cursor:pointer;color:#64748b;padding:0;}',
            '.fv-embed-modal-close:hover{background:transparent;color:#64748b;}',
            '.fv-embed-modal-iframe{display:block;border:0;width:100%;height:280px;background:#fff;}',
            /* Lightbox 1:1 wie app Thumbnail.vue */
            '.fv-embed-lightbox-overlay{position:fixed;inset:0;z-index:2147483200;background:rgba(0,0,0,.5);',
            'display:none;align-items:center;justify-content:center;}',
            '.fv-embed-lightbox-overlay.is-open{display:flex;}',
            '.fv-embed-lightbox-download,.fv-embed-lightbox-close{position:absolute;top:12px;cursor:pointer;',
            'color:#fff;background:transparent;border:0;padding:0;line-height:0;}',
            '.fv-embed-lightbox-download{right:44px;}',
            '.fv-embed-lightbox-close{right:12px;}',
            '.fv-embed-lightbox-nav{width:40px;height:40px;cursor:pointer;flex-shrink:0;color:#fff;}',
            '.fv-embed-lightbox-nav.is-hidden{visibility:hidden;pointer-events:none;}',
            '.fv-embed-lightbox-image{object-fit:contain;max-width:90vw;max-height:90vh;}',
            '.fv-embed-lightbox-iframe{display:none;border:0;background:#fff;width:90vw;height:90vh;max-width:90vw;max-height:90vh;}',
            '.fv-embed-lightbox-iframe.is-visible{display:block;}',
            '.fv-embed-lightbox-image.is-hidden{display:none;}',
        ].join('');
        document.head.appendChild(style);
    }

    function ensureSidebarDom() {
        if (sidebar.overlay) {
            return;
        }

        injectEmbedStyles();

        var overlay = document.createElement('div');
        overlay.className = 'fv-embed-sidebar-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        var panel = document.createElement('div');
        panel.className = 'fv-embed-sidebar-panel';

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'fv-embed-sidebar-close';
        close.setAttribute('aria-label', 'Close');
        close.innerHTML = '&times;';

        var iframe = document.createElement('iframe');
        iframe.className = 'fv-embed-sidebar-iframe';
        iframe.setAttribute('title', 'Task details');
        iframe.setAttribute('allow', 'clipboard-read; clipboard-write');

        panel.appendChild(close);
        panel.appendChild(iframe);
        overlay.appendChild(panel);
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeSidebar();
            }
        });
        close.addEventListener('click', closeSidebar);

        sidebar.overlay = overlay;
        sidebar.panel = panel;
        sidebar.iframe = iframe;
    }

    function openSidebar(url, sourceIframe, sourceOrigin) {
        if (!url) {
            return;
        }

        ensureSidebarDom();

        sidebar.sourceIframe = sourceIframe || null;
        sidebar.sourceOrigin = sourceOrigin || '';

        var overlay = sidebar.overlay;
        var currentSrc = sidebar.iframe.getAttribute('src') || '';
        var alreadyOpen = overlay.style.display === 'flex' && overlay.classList.contains('is-open');

        // Gleiche URL + bereits offen: keine erneute Animation / kein Iframe-Reload
        // (sonst „wackelt“ die Sidebar bei wiederholten fv-app-frame-sidebar-Nachrichten).
        if (alreadyOpen && currentSrc === url) {
            return;
        }

        if (currentSrc !== url) {
            sidebar.iframe.setAttribute('src', url);
        }

        overlay.style.display = 'flex';
        if (!alreadyOpen) {
            window.requestAnimationFrame(function () {
                overlay.classList.add('is-open');
            });
        } else {
            overlay.classList.add('is-open');
        }

        if (!sidebar.keyListener) {
            sidebar.keyListener = function (event) {
                if (event.key === 'Escape') {
                    closeSidebar();
                }
            };
            document.addEventListener('keydown', sidebar.keyListener);
        }
    }

    function removeJwtTokenFromUrl(url) {
        if (!url || url.indexOf('?') === -1) {
            return url;
        }

        try {
            var parts = url.split('?');
            var params = new URLSearchParams(parts[1]);
            params.delete('jwt_token');
            var query = params.toString();
            return query ? parts[0] + '?' + query : parts[0];
        } catch (e) {
            return url;
        }
    }

    function tokenFromTaskPath(path) {
        if (!path) {
            return '';
        }

        var match = String(path).match(/\/(\d+)\/task\/([^/?]+)/);
        return match ? decodeURIComponent(match[2]) : '';
    }

    function dashboardIdFromPath(path) {
        if (!path) {
            return '';
        }

        var taskMatch = String(path).match(/^\/(\d+)\/task\//);
        if (taskMatch) {
            return taskMatch[1];
        }

        var dashMatch = String(path).match(/^\/(\d+)\/?$/);
        return dashMatch ? dashMatch[1] : '';
    }

    function readFvTaskParam() {
        try {
            return new URLSearchParams(window.location.search).get(FV_TASK_PARAM) || '';
        } catch (e) {
            return '';
        }
    }

    function setFvTaskParam(taskToken) {
        try {
            var params = new URLSearchParams(window.location.search);
            if (taskToken) {
                params.set(FV_TASK_PARAM, taskToken);
            } else {
                params.delete(FV_TASK_PARAM);
            }

            var query = params.toString();
            var newUrl = window.location.pathname + (query ? '?' + query : '') + window.location.hash;
            window.history.replaceState(window.history.state, '', newUrl);
        } catch (e) {
            /* URL-Update nicht möglich */
        }
    }

    function updateParentUrl(relativeUrl) {
        var cleanedUrl = removeJwtTokenFromUrl(relativeUrl);
        if (!cleanedUrl) {
            return;
        }

        setFvTaskParam(tokenFromTaskPath(cleanedUrl));
    }

    function isSsoEmbedUrl(url) {
        try {
            var parsed = new URL(url, window.location.href);
            return /\/[a-z0-9]+(?:-[a-z0-9]+)*-sso\//.test(parsed.pathname);
        } catch (e) {
            return false;
        }
    }

    function isDashboardHost(url, dashboardOrigin) {
        try {
            var parsed = new URL(url, window.location.href);
            if (dashboardOrigin) {
                return parsed.origin === dashboardOrigin;
            }
        } catch (e) {
            return false;
        }

        return false;
    }

    function buildDashboardTaskUrl(embedBaseUrl, dashboardOrigin, dashboardId, taskToken) {
        if (!taskToken || !embedBaseUrl) {
            return '';
        }

        if (isSsoEmbedUrl(embedBaseUrl)) {
            return '';
        }

        var numericDashboardId = parseInt(String(dashboardId || ''), 10);
        if (!numericDashboardId || numericDashboardId < 1) {
            return '';
        }

        try {
            var embedUrl = new URL(embedBaseUrl, window.location.href);
            if (!isDashboardHost(embedBaseUrl, dashboardOrigin)) {
                return '';
            }

            embedUrl.pathname = '/' + numericDashboardId + '/task/' + encodeURIComponent(taskToken);
            return embedUrl.toString();
        } catch (e) {
            return '';
        }
    }

    // Das Dashboard-Iframe liegt auf einer anderen Origin als der WordPress-Parent, daher können wir
    // weder `iframe.contentWindow.location` noch die tatsächliche (nach einem Redirect wie /embed → /{id})
    // aktuelle URL über das `src`-Attribut auslesen (das bleibt bei Redirects unverändert). Die Dashboard-ID
    // ermitteln wir deshalb ausschließlich aus der `fv-app-frame-location`-postMessage, die das Dashboard
    // selbst nach jedem Laden schickt (siehe dashboard.blade.php).
    //
    // `embed=1` MUSS auf jeder Anfrage an das Dashboard gesetzt sein: `DashboardEmbedContext::syncFromRequest`
    // (dashboard-Service) löscht ohne diesen Parameter aktiv den zuvor aktivierten Embed-Session-Kontext,
    // wodurch `embedMode` false wird und die Sidebar auf die app.featvalue.io-Standalone-URL zurückfällt (dort
    // ist der WordPress-Nutzer nicht eingeloggt → Login-Maske). Für SSO-Sites enthält die Basis-URL
    // (external-auth.featvalue.io/{integration}-sso/…) `embed`/`parent_origin` erst gar nicht, daher hier immer
    // setzen statt nur aus der Basis-URL zu übernehmen.
    function buildTaskUrlFromBase(baseUrl, dashboardOrigin, dashboardId, taskToken, token) {
        if (!baseUrl || !dashboardOrigin || !dashboardId || !taskToken) {
            return '';
        }

        try {
            var base = new URL(baseUrl, window.location.href);
            var target = new URL(dashboardOrigin);
            target.pathname = '/' + dashboardId + '/task/' + encodeURIComponent(taskToken);

            target.searchParams.set('embed', '1');
            target.searchParams.set('parent_origin', base.searchParams.get('parent_origin') || window.location.origin);

            ['service_provider_company_id', 'client_company_id'].forEach(function (key) {
                var value = base.searchParams.get(key);
                if (value) {
                    target.searchParams.set(key, value);
                }
            });

            return appendAccessToken(target.toString(), token);
        } catch (e) {
            return '';
        }
    }

    function tryNavigateIframeToPendingTaskFromLocation(iframe, dashboardOrigin, locationPath) {
        if (!pendingFvTask || !iframe) {
            return false;
        }

        // Bereits auf der Task-Seite (z. B. nach unserer eigenen Navigation) — nichts zu tun.
        if (locationPath.indexOf('/task/') !== -1) {
            pendingFvTask = '';
            return false;
        }

        var dashboardId = dashboardIdFromPath(locationPath);
        if (!dashboardId) {
            return false;
        }

        var siteId = iframe.getAttribute('data-fv-site-id') || '0';
        var storedToken = readStoredToken(siteId);
        var taskUrl = buildTaskUrlFromBase(
            iframe.getAttribute('data-fv-embed-base') || '',
            dashboardOrigin,
            dashboardId,
            pendingFvTask,
            storedToken
        );

        if (!taskUrl) {
            return false;
        }

        pendingFvTask = '';
        iframe.src = taskUrl;
        return true;
    }

    function closeSidebar() {
        if (!sidebar.overlay || sidebar.overlay.style.display === 'none') {
            return;
        }

        sidebar.overlay.classList.remove('is-open');
        setFvTaskParam('');

        if (sidebar.sourceIframe && sidebar.sourceIframe.contentWindow && sidebar.sourceOrigin) {
            try {
                sidebar.sourceIframe.contentWindow.postMessage(
                    { type: 'fv-app-sidebar-closed', sidebar: null },
                    sidebar.sourceOrigin
                );
            } catch (e) {
                /* Iframe nicht erreichbar */
            }
        }

        var overlay = sidebar.overlay;
        window.setTimeout(function () {
            overlay.style.display = 'none';
            if (sidebar.iframe) {
                sidebar.iframe.setAttribute('src', 'about:blank');
            }
        }, 200);

        if (sidebar.keyListener) {
            document.removeEventListener('keydown', sidebar.keyListener);
            sidebar.keyListener = null;
        }

        sidebar.sourceIframe = null;
        sidebar.sourceOrigin = '';
    }

    function postToFrame(win, origin, message) {
        if (!win || !origin) {
            return;
        }

        try {
            win.postMessage(message, origin);
        } catch (e) {
            /* Frame nicht erreichbar */
        }
    }

    function frameOrigin(iframe) {
        try {
            return new URL(iframe.getAttribute('src') || '', window.location.href).origin;
        } catch (e) {
            return '';
        }
    }

    /**
     * Nachricht des Modals an alle eingebetteten FeatValue-Frames verteilen: Dashboard und
     * Sidebar müssen z. B. nach einem Review ihre Ansicht aktualisieren, und der Anforderer
     * des Modals kann ein IFrame innerhalb der Sidebar sein.
     */
    function notifyEmbeds(message) {
        postToFrame(modal.sourceWindow, modal.sourceOrigin, message);

        if (sidebar.iframe && sidebar.iframe.contentWindow !== modal.sourceWindow) {
            postToFrame(sidebar.iframe.contentWindow, frameOrigin(sidebar.iframe), message);
        }

        var iframes = document.querySelectorAll('iframe[data-fv-embed-base]');
        for (var i = 0; i < iframes.length; i++) {
            if (iframes[i].contentWindow === modal.sourceWindow) {
                continue;
            }
            postToFrame(iframes[i].contentWindow, dashboardOriginFromIframe(iframes[i]), message);
        }
    }

    function ensureModalDom() {
        if (modal.overlay) {
            return;
        }

        injectEmbedStyles();

        var overlay = document.createElement('div');
        overlay.className = 'fv-embed-modal-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.style.display = 'none';

        var panel = document.createElement('div');
        panel.className = 'fv-embed-modal-panel';

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'fv-embed-modal-close';
        close.setAttribute('aria-label', 'Close');
        close.innerHTML = '&times;';

        var iframe = document.createElement('iframe');
        iframe.className = 'fv-embed-modal-iframe';
        iframe.setAttribute('title', 'Task action');

        panel.appendChild(close);
        panel.appendChild(iframe);
        overlay.appendChild(panel);
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeModal(null);
            }
        });
        close.addEventListener('click', function () {
            closeModal(null);
        });

        modal.overlay = overlay;
        modal.iframe = iframe;
    }

    /**
     * Modal im Parent statt im anfordernden IFrame öffnen — nur so ist es über der ganzen
     * Seite zentriert und nicht in die schmale Task-Sidebar eingesperrt.
     */
    function openModal(url, sourceWindow, sourceOrigin) {
        if (!url) {
            return;
        }

        ensureModalDom();

        modal.url = url;
        modal.sourceWindow = sourceWindow || null;
        modal.sourceOrigin = sourceOrigin || '';
        modal.iframe.style.height = '280px';
        modal.iframe.setAttribute('src', url);
        modal.overlay.style.display = 'flex';

        if (!modal.keyListener) {
            modal.keyListener = function (event) {
                if (event.key === 'Escape') {
                    closeModal(null);
                }
            };
            document.addEventListener('keydown', modal.keyListener);
        }
    }

    function applyModalHeight(height) {
        if (!modal.iframe || typeof height !== 'number' || height <= 0) {
            return;
        }

        var cap = Math.round(window.innerHeight * 0.9);
        modal.iframe.style.height = Math.min(Math.max(height, 240), cap) + 'px';
    }

    function closeModal(result) {
        if (!modal.overlay || modal.overlay.style.display === 'none') {
            return;
        }

        var closedUrl = modal.url;

        modal.overlay.style.display = 'none';
        modal.iframe.setAttribute('src', 'about:blank');
        modal.url = '';

        if (modal.keyListener) {
            document.removeEventListener('keydown', modal.keyListener);
            modal.keyListener = null;
        }

        notifyEmbeds({ type: 'fv-app-popup-closed', url: closedUrl, result: result || null });

        modal.sourceWindow = null;
        modal.sourceOrigin = '';
    }

    function svgMarkup(paths, attrs) {
        return '<svg xmlns="http://www.w3.org/2000/svg" ' + attrs + '>' + paths + '</svg>';
    }

    function ensureLightboxDom() {
        if (lightbox.overlay) {
            return;
        }

        injectEmbedStyles();

        var overlay = document.createElement('div');
        overlay.className = 'fv-embed-lightbox-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        var downloadBtn = document.createElement('button');
        downloadBtn.type = 'button';
        downloadBtn.className = 'fv-embed-lightbox-download';
        downloadBtn.setAttribute('aria-label', 'Download');
        downloadBtn.innerHTML = svgMarkup(
            '<path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>',
            'width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"'
        );
        downloadBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            downloadLightboxImage();
        });

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'fv-embed-lightbox-close';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.innerHTML = svgMarkup(
            '<path d="M14.53 4.53l-1.06-1.06L9 7.94 4.53 3.47 3.47 4.53 7.94 9l-4.47 4.47 1.06 1.06L9 10.06l4.47 4.47 1.06-1.06L10.06 9z"></path>',
            'width="30" height="30" viewBox="0 0 18 18" fill="currentColor"'
        );
        closeBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            closeLightbox();
        });

        var prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'fv-embed-lightbox-nav';
        prevBtn.setAttribute('aria-label', 'Previous');
        prevBtn.innerHTML = svgMarkup(
            '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>',
            'width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"'
        );
        prevBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            previousLightboxImage();
        });

        var imageEl = document.createElement('img');
        imageEl.className = 'fv-embed-lightbox-image';
        imageEl.loading = 'lazy';
        imageEl.alt = '';
        imageEl.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        var iframeEl = document.createElement('iframe');
        iframeEl.className = 'fv-embed-lightbox-iframe';
        iframeEl.setAttribute('title', 'PDF preview');
        iframeEl.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        var nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'fv-embed-lightbox-nav';
        nextBtn.setAttribute('aria-label', 'Next');
        nextBtn.innerHTML = svgMarkup(
            '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>',
            'width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"'
        );
        nextBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            nextLightboxImage();
        });

        overlay.appendChild(downloadBtn);
        overlay.appendChild(closeBtn);
        overlay.appendChild(prevBtn);
        overlay.appendChild(imageEl);
        overlay.appendChild(iframeEl);
        overlay.appendChild(nextBtn);
        overlay.addEventListener('click', function () {
            closeLightbox();
        });

        document.body.appendChild(overlay);

        lightbox.overlay = overlay;
        lightbox.imageEl = imageEl;
        lightbox.iframeEl = iframeEl;
        lightbox.prevEl = prevBtn;
        lightbox.nextEl = nextBtn;
    }

    function syncLightboxNav() {
        var multi = Array.isArray(lightbox.images) && lightbox.images.length > 1;
        if (lightbox.prevEl) {
            lightbox.prevEl.classList.toggle('is-hidden', !multi);
        }
        if (lightbox.nextEl) {
            lightbox.nextEl.classList.toggle('is-hidden', !multi);
        }
    }

    function resolveLightboxKind() {
        if (Array.isArray(lightbox.kinds) && lightbox.kinds[lightbox.currentIndex]) {
            return lightbox.kinds[lightbox.currentIndex];
        }
        if (lightbox.kind) {
            return lightbox.kind;
        }
        if (lightbox.attachment && lightbox.attachment.kind) {
            return lightbox.attachment.kind;
        }
        var url = String(lightbox.image || '').toLowerCase();
        if (/\.pdf(?:$|[?#])/.test(url) || url.indexOf('.pdf') !== -1) {
            return 'pdf';
        }
        return 'image';
    }

    function syncLightboxContent() {
        var kind = resolveLightboxKind();
        lightbox.kind = kind;
        if (lightbox.attachment) {
            lightbox.attachment.kind = kind;
        }
        if (kind === 'pdf') {
            if (lightbox.imageEl) {
                lightbox.imageEl.classList.add('is-hidden');
                lightbox.imageEl.removeAttribute('src');
            }
            if (lightbox.iframeEl) {
                lightbox.iframeEl.classList.add('is-visible');
                lightbox.iframeEl.src = lightbox.image || '';
            }
            return;
        }
        if (lightbox.iframeEl) {
            lightbox.iframeEl.classList.remove('is-visible');
            lightbox.iframeEl.removeAttribute('src');
        }
        if (lightbox.imageEl) {
            lightbox.imageEl.classList.remove('is-hidden');
            lightbox.imageEl.src = lightbox.image || '';
        }
    }

    function notifyLightboxSource(message) {
        if (lightbox.sourceWindow && typeof lightbox.sourceWindow.postMessage === 'function') {
            try {
                lightbox.sourceWindow.postMessage(message, lightbox.sourceOrigin || '*');
            } catch (e) {
                /* ignore */
            }
        }
    }

    function openLightbox(payload, sourceWindow, sourceOrigin) {
        if (!payload || typeof payload.image !== 'string' || payload.image === '') {
            return;
        }

        ensureLightboxDom();

        lightbox.image = payload.image;
        lightbox.images = Array.isArray(payload.images) ? payload.images : null;
        lightbox.kinds = Array.isArray(payload.kinds) ? payload.kinds : null;
        lightbox.currentIndex = typeof payload.currentIndex === 'number' ? payload.currentIndex : 0;
        lightbox.attachment = payload.attachment || null;
        lightbox.kind = payload.kind || (payload.attachment && payload.attachment.kind) || null;
        lightbox.sourceWindow = sourceWindow || null;
        lightbox.sourceOrigin = sourceOrigin || '';

        syncLightboxContent();
        syncLightboxNav();
        lightbox.overlay.classList.add('is-open');

        if (!lightbox.keyListener) {
            lightbox.keyListener = function (event) {
                if (event.key === 'Escape') {
                    closeLightbox();
                } else if (Array.isArray(lightbox.images) && lightbox.images.length > 1) {
                    if (event.key === 'ArrowLeft') {
                        previousLightboxImage();
                    } else if (event.key === 'ArrowRight') {
                        nextLightboxImage();
                    }
                }
            };
            document.addEventListener('keydown', lightbox.keyListener);
        }
    }

    function closeLightbox() {
        if (!lightbox.overlay || !lightbox.overlay.classList.contains('is-open')) {
            return;
        }

        lightbox.overlay.classList.remove('is-open');
        if (lightbox.imageEl) {
            lightbox.imageEl.removeAttribute('src');
            lightbox.imageEl.classList.remove('is-hidden');
        }
        if (lightbox.iframeEl) {
            lightbox.iframeEl.removeAttribute('src');
            lightbox.iframeEl.classList.remove('is-visible');
        }
        lightbox.image = null;
        lightbox.images = null;
        lightbox.kinds = null;
        lightbox.kind = null;
        lightbox.currentIndex = 0;
        lightbox.attachment = null;

        if (lightbox.keyListener) {
            document.removeEventListener('keydown', lightbox.keyListener);
            lightbox.keyListener = null;
        }

        notifyLightboxSource({ type: 'fv-app-lightbox-closed' });
        lightbox.sourceWindow = null;
        lightbox.sourceOrigin = '';
    }

    function nextLightboxImage() {
        if (!Array.isArray(lightbox.images) || lightbox.images.length < 2) {
            return;
        }
        lightbox.currentIndex = (lightbox.currentIndex + 1) % lightbox.images.length;
        lightbox.image = lightbox.images[lightbox.currentIndex];
        syncLightboxContent();
        notifyLightboxSource({
            type: 'fv-app-lightbox-navigated',
            currentIndex: lightbox.currentIndex,
            image: lightbox.image,
            kind: lightbox.kind,
        });
    }

    function previousLightboxImage() {
        if (!Array.isArray(lightbox.images) || lightbox.images.length < 2) {
            return;
        }
        lightbox.currentIndex = lightbox.currentIndex === 0
            ? lightbox.images.length - 1
            : lightbox.currentIndex - 1;
        lightbox.image = lightbox.images[lightbox.currentIndex];
        syncLightboxContent();
        notifyLightboxSource({
            type: 'fv-app-lightbox-navigated',
            currentIndex: lightbox.currentIndex,
            image: lightbox.image,
            kind: lightbox.kind,
        });
    }

    function downloadLightboxImage() {
        var a = document.createElement('a');
        if (lightbox.attachment && lightbox.attachment.downloadUrl) {
            a.href = lightbox.attachment.downloadUrl;
            a.download = lightbox.attachment.filename || 'download';
        } else {
            a.href = lightbox.image || '';
            a.download = (lightbox.attachment && lightbox.attachment.filename) || '';
        }
        if (!a.href) {
            return;
        }
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function storageKey(siteId) {
        return 'featvalue_embed_access_token_' + (siteId || '0');
    }

    function jwtStorageKey(siteId) {
        return 'featvalue_embed_jwt_token_' + (siteId || '0');
    }

    function readStoredToken(siteId) {
        try {
            return localStorage.getItem(storageKey(siteId)) || '';
        } catch (e) {
            return '';
        }
    }

    function storeToken(siteId, token) {
        if (!token) {
            return;
        }

        try {
            localStorage.setItem(storageKey(siteId), token);
        } catch (e) {
            /* localStorage blockiert */
        }
    }

    function readStoredJwtToken(siteId) {
        try {
            return localStorage.getItem(jwtStorageKey(siteId)) || '';
        } catch (e) {
            return '';
        }
    }

    function storeJwtToken(siteId, jwt) {
        if (!jwt) {
            return;
        }

        try {
            localStorage.setItem(jwtStorageKey(siteId), jwt);
        } catch (e) {
            /* localStorage blockiert */
        }
    }

    function clearStoredToken(siteId) {
        try {
            localStorage.removeItem(storageKey(siteId));
        } catch (e) {
            /* localStorage blockiert */
        }

        try {
            localStorage.removeItem(jwtStorageKey(siteId));
        } catch (e) {
            /* localStorage blockiert */
        }
    }

    function silentLoginStorageKey(siteId) {
        return 'featvalue_embed_silent_login_' + (siteId || '0');
    }

    function silentLoginAttempted(siteId) {
        // Nur der Guard auf dieser Seitenladung verhindert eine Redirect-Schleife.
        // sessionStorage hat den Top-Level-Versuch nach fehlgeschlagenen Tests dauerhaft
        // blockiert — obwohl der Nutzer top-level auf login.featvalue.io eingeloggt ist.
        return silentLoginDeniedOnThisPage;
    }

    function markSilentLoginAttempted(siteId) {
        // Absichtlich leer: Schleifenschutz über silentLoginDeniedOnThisPage / fv_silent_auth.
    }

    function i18n() {
        var cfg = window.featvalueEmbedParent || {};
        return cfg.i18n || {};
    }

    /**
     * Ohne Premium (kein WordPress-SSO) hängt der Login im IFrame an Third-Party-Cookies. Das
     * einbettende Dokument darf dann einmalig selbst zum Login navigieren; mit Premium läuft der
     * SSO-Handoff und ein Top-Level-Redirect wäre unnötiger Seitenwechsel.
     */
    function topLevelLoginEnabled() {
        var cfg = window.featvalueEmbedParent || {};
        var flag = cfg.topLevelLogin;

        return flag !== false && flag !== '0' && flag !== 0;
    }

    function authHostSuffixes() {
        var cfg = window.featvalueEmbedParent || {};
        return Array.isArray(cfg.authHostSuffixes) ? cfg.authHostSuffixes : [];
    }

    function isAllowedFeatValueUrl(url) {
        try {
            var parsed = new URL(url, window.location.href);
            if (parsed.protocol !== 'https:' && parsed.protocol !== 'http:') {
                return false;
            }

            var host = parsed.hostname.toLowerCase();
            var suffixes = authHostSuffixes();
            for (var i = 0; i < suffixes.length; i++) {
                var suffix = String(suffixes[i] || '').toLowerCase();
                if (!suffix) {
                    continue;
                }
                if (host === suffix || host.slice(-(suffix.length + 1)) === '.' + suffix) {
                    return true;
                }
            }
        } catch (e) {
            return false;
        }

        return false;
    }

    function wrapForLoginGate(iframe) {
        var parent = iframe.parentNode;
        if (!parent) {
            return null;
        }

        if (parent.classList && parent.classList.contains('fv-embed-login-host')) {
            return parent;
        }

        // IFrame nicht ins DOM umhängen: appendChild/reparent lädt das Frame neu, startet erneut
        // den stillen IFrame-Login und kann mit dem Top-Level-Versuch kollidieren.
        try {
            var computed = window.getComputedStyle(parent);
            if (computed.position === 'static') {
                parent.style.position = 'relative';
            }
        } catch (e) {
            parent.style.position = 'relative';
        }
        parent.classList.add('fv-embed-login-host');
        return parent;
    }

    function hideLoginGate(iframe) {
        var host = iframe.parentNode;
        if (!host || !host.querySelector) {
            return;
        }

        var gate = host.querySelector('.fv-embed-login-gate');
        if (gate && gate.parentNode) {
            gate.parentNode.removeChild(gate);
        }
    }

    function embedLang() {
        var cfg = window.featvalueEmbedParent || {};
        var lang = typeof cfg.lang === 'string' ? cfg.lang.trim().toLowerCase() : '';

        return /^[a-z]{2}$/.test(lang) ? lang : '';
    }

    function applyEmbedLang(url, lang) {
        var code = lang || embedLang();
        if (!code) {
            return;
        }
        url.searchParams.set('lang', code);
    }

    function withEmbedLang(targetUrl) {
        if (!targetUrl) {
            return '';
        }

        var lang = embedLang();
        if (!lang) {
            return targetUrl;
        }

        try {
            var url = new URL(targetUrl, window.location.href);
            applyEmbedLang(url, lang);

            return url.toString();
        } catch (e) {
            return targetUrl;
        }
    }

    function buildRegisterUrl(registerUrl, currentUrl) {
        if (!registerUrl || !currentUrl) {
            return '';
        }

        try {
            var url = new URL(registerUrl, window.location.href);
            var parent = new URL(currentUrl, window.location.href);
            parent.searchParams.delete(SILENT_LOGIN_GUARD);
            parent.searchParams.delete('fv_jwt_token');
            parent.searchParams.delete('fv_access_token');
            url.searchParams.set('redirect', parent.toString());
            applyEmbedLang(url);

            return url.toString();
        } catch (e) {
            return '';
        }
    }

    function showLoginGate(iframe, authorizeUrl) {
        if (!iframe || !isAllowedFeatValueUrl(authorizeUrl)) {
            return;
        }

        injectEmbedStyles();
        var host = wrapForLoginGate(iframe);
        if (!host) {
            return;
        }

        hideLoginGate(iframe);

        var strings = i18n();
        var cfg = window.featvalueEmbedParent || {};
        var gate = document.createElement('div');
        gate.className = 'fv-embed-login-gate';

        var card = document.createElement('div');
        card.className = 'fv-embed-login-gate-card';

        var message = document.createElement('p');
        message.textContent = strings.loginRequired || 'Sign in to show this dashboard.';

        var hint = document.createElement('p');
        hint.className = 'fv-embed-login-hint';
        hint.textContent = strings.loginHint || 'A new window opens for sign-in. It closes automatically afterwards.';

        var actions = document.createElement('div');
        actions.className = 'fv-embed-login-gate-actions';

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'fv-embed-login-gate-button';
        button.textContent = strings.loginButton || 'Sign in';
        button.addEventListener('click', function () {
            try {
                sessionStorage.removeItem(silentLoginStorageKey(iframe.getAttribute('data-fv-site-id') || '0'));
            } catch (e) {
                /* ignore */
            }
            openParentLoginPopup(authorizeUrl, iframe, button);
        });
        actions.appendChild(button);

        var registerUrl = typeof cfg.registerUrl === 'string' ? cfg.registerUrl : '';
        var registerTarget = buildRegisterUrl(registerUrl, window.location.href);
        if (registerTarget && isAllowedFeatValueUrl(registerTarget)) {
            var registerButton = document.createElement('button');
            registerButton.type = 'button';
            registerButton.className = 'fv-embed-login-gate-button is-secondary';
            registerButton.textContent = strings.registerButton || 'Register';
            registerButton.addEventListener('click', function () {
                openParentLoginPopup(registerTarget, iframe, registerButton);
            });
            actions.appendChild(registerButton);
        }

        card.appendChild(message);
        card.appendChild(hint);
        card.appendChild(actions);
        gate.appendChild(card);
        host.appendChild(gate);
    }

    function decodeBase64State(state) {
        if (typeof state !== 'string' || state.indexOf('b64.') !== 0) {
            return '';
        }

        try {
            return decodeURIComponent(escape(atob(state.slice(4).replace(/-/g, '+').replace(/_/g, '/'))));
        } catch (e) {
            return '';
        }
    }

    function resolveAuthorizeStateTarget(state) {
        var decoded = decodeBase64State(state);
        if (decoded) {
            return decoded;
        }

        if (typeof state === 'string' && state.indexOf('/') === 0 && state.indexOf('//') !== 0 && state.indexOf('://') === -1) {
            return state;
        }

        return '';
    }

    function encodeBase64State(value) {
        return 'b64.' + btoa(unescape(encodeURIComponent(value)))
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/, '');
    }

    /**
     * Authorize-URL mit `fv_parent_return` im state. Mit `promptNone` für den stillen Top-Level-
     * Versuch; ohne für das interaktive Login-Popup (Callback → CMS mit JWT → Opener).
     */
    function buildAuthorizeLoginUrl(authorizeUrl, parentUrl, promptNone) {
        if (!authorizeUrl || !parentUrl) {
            return '';
        }

        try {
            var url = new URL(authorizeUrl, window.location.href);
            var stateTarget = resolveAuthorizeStateTarget(url.searchParams.get('state'));
            if (!stateTarget) {
                return '';
            }

            var parent = new URL(parentUrl, window.location.href);
            parent.searchParams.delete(SILENT_LOGIN_GUARD);

            var target = new URL(stateTarget, 'https://dashboard.featvalue.io');
            target.searchParams.set('fv_parent_return', parent.pathname + parent.search);

            url.searchParams.set('state', encodeBase64State(target.pathname + target.search));
            if (promptNone) {
                url.searchParams.set('prompt', 'none');
            } else {
                url.searchParams.delete('prompt');
            }
            applyEmbedLang(url);

            return url.toString();
        } catch (e) {
            return '';
        }
    }

    /**
     * Authorize-URL für den Top-Level-Versuch: `prompt=none` liefert bei bestehender Session sofort
     * einen Code, sonst einen Fehler. Über `fv_parent_return` im state weiß der OAuth-Callback, auf
     * welche Seite dieses Dokuments er zurückspringen muss.
     */
    function buildTopLevelLoginUrl(authorizeUrl, parentUrl) {
        return buildAuthorizeLoginUrl(authorizeUrl, parentUrl, true);
    }

    function buildPopupLoginUrl(authorizeUrl, parentUrl) {
        return buildAuthorizeLoginUrl(authorizeUrl, parentUrl, false);
    }

    /**
     * @returns {boolean} true, wenn die Navigation läuft und kein Login-Gate nötig ist.
     */
    function attemptTopLevelLogin(iframe, authorizeUrl) {
        if (!topLevelLoginEnabled() || !isAllowedFeatValueUrl(authorizeUrl)) {
            return false;
        }

        var siteId = iframe.getAttribute('data-fv-site-id') || '0';
        if (silentLoginAttempted(siteId)) {
            return false;
        }

        var target = buildTopLevelLoginUrl(authorizeUrl, window.location.href);
        if (!target || !isAllowedFeatValueUrl(target)) {
            return false;
        }

        markSilentLoginAttempted(siteId);
        // Bewusst location.href statt replace: schlägt der Rücksprung fehl (Origin live nicht
        // als Self-Hosted-Site bekannt), macht auth-popup-close history.back() — mit replace
        // wäre der vorherige Eintrag nicht mehr diese Seite, sondern deren Vorgänger.
        window.location.href = target;

        return true;
    }

    function openParentLoginPopup(authorizeUrl, iframe, button) {
        if (!isAllowedFeatValueUrl(authorizeUrl)) {
            return;
        }

        // Mit fv_parent_return springt der OAuth-Callback das Popup auf die CMS-Seite zurück
        // (JWT). Ohne das schließt auth-popup-close nur das Fenster — und postMessage klappt
        // nur, wenn known-origin die Parent-Origin kennt.
        var loginUrl = buildPopupLoginUrl(authorizeUrl, window.location.href);
        if (!loginUrl) {
            loginUrl = withEmbedLang(authorizeUrl);
        }
        if (!loginUrl || !isAllowedFeatValueUrl(loginUrl)) {
            return;
        }

        var popup = window.open(loginUrl, 'FeatValue Popup', 'width=600,height=700');
        if (!popup) {
            return;
        }

        if (button) {
            button.disabled = true;
        }

        var poll = window.setInterval(function () {
            if (!popup || popup.closed) {
                window.clearInterval(poll);
                if (button) {
                    button.disabled = false;
                }
            }
        }, 500);
    }

    function applyAccessTokenToIframe(iframe, token) {
        if (!iframe || !token) {
            return;
        }

        var siteId = iframe.getAttribute('data-fv-site-id') || '0';
        var previousToken = readStoredToken(siteId);
        storeToken(siteId, token);
        hideLoginGate(iframe);

        // layout-embed postet bei jedem Dashboard-Load `featvalue-embed-auth`. Ohne diesen
        // Guard würde jedes Mal iframe.src neu gesetzt → Reload → Auth-Message → Endlosschleife
        // (sichtbar als Dauerfeuer an HTTP-Requests und wackelnde Task-Sidebar bei fv-task).
        if (previousToken === token) {
            return;
        }

        var nextSrc = buildIframeSrc(iframe);
        var currentSrc = iframe.getAttribute('src') || '';
        if (nextSrc && nextSrc !== currentSrc) {
            iframe.src = nextSrc;
        }
    }

    function appendAccessToken(baseUrl, token) {
        if (!token) {
            return baseUrl;
        }

        try {
            var url = new URL(baseUrl, window.location.href);
            url.searchParams.set('access_token', token);
            return url.toString();
        } catch (e) {
            return baseUrl;
        }
    }

    function appendJwtToken(baseUrl, jwt) {
        if (!jwt) {
            return baseUrl;
        }

        try {
            var url = new URL(baseUrl, window.location.href);
            url.searchParams.set('jwt_token', jwt);
            return url.toString();
        } catch (e) {
            return baseUrl;
        }
    }

    /**
     * access_token bevorzugt (Livewire); jwt_token nur als Fallback nach Top-Level-Login.
     */
    function appendStoredEmbedAuth(baseUrl, accessToken, jwtToken) {
        var url = appendAccessToken(baseUrl, accessToken);
        if (!accessToken && jwtToken) {
            url = appendJwtToken(url, jwtToken);
        }
        return url;
    }

    function dashboardOriginFromIframe(iframe) {
        var origin = iframe.getAttribute('data-fv-dashboard-origin');
        if (origin) {
            return origin;
        }

        var base = iframe.getAttribute('data-fv-embed-base');
        if (!base) {
            return '';
        }

        try {
            return new URL(base, window.location.href).origin;
        } catch (e) {
            return '';
        }
    }

    // Erlaubte Absender-Origins: der Dashboard-Host (dort landet das Iframe nach dem
    // SSO-Redirect und von dort kommen Sidebar-/Location-Nachrichten) und der Host der
    // Embed-URL selbst (bei SSO external-auth.*, sonst identisch mit dem Dashboard-Host).
    function allowedOriginsForIframe(iframe) {
        var origins = {};

        ['data-fv-dashboard-origin', 'data-fv-embed-origin'].forEach(function (attribute) {
            var configured = iframe.getAttribute(attribute);
            if (configured) {
                origins[configured] = true;
            }
        });

        var base = iframe.getAttribute('data-fv-embed-base');
        if (base) {
            try {
                origins[new URL(base, window.location.href).origin] = true;
            } catch (e) {
                /* ignore */
            }
        }

        return origins;
    }

    function findIframeForMessageOrigin(eventOrigin) {
        var iframes = document.querySelectorAll('iframe[data-fv-embed-base]');
        for (var i = 0; i < iframes.length; i++) {
            var iframe = iframes[i];
            var allowed = allowedOriginsForIframe(iframe);
            if (allowed[eventOrigin]) {
                return iframe;
            }
        }

        return null;
    }

    function buildIframeSrc(iframe) {
        var baseUrl = iframe.getAttribute('data-fv-embed-base');
        if (!baseUrl) {
            return '';
        }

        var dashboardOrigin = dashboardOriginFromIframe(iframe);
        var dashboardId = iframe.getAttribute('data-fv-dashboard-id') || '';
        var fvTask = readFvTaskParam();
        var siteId = iframe.getAttribute('data-fv-site-id') || '0';
        var token = readStoredToken(siteId);
        var jwt = readStoredJwtToken(siteId);
        var embedUrl = baseUrl;

        if (fvTask) {
            var taskUrl = buildDashboardTaskUrl(baseUrl, dashboardOrigin, dashboardId, fvTask);
            if (taskUrl) {
                embedUrl = taskUrl;
            } else {
                pendingFvTask = fvTask;
            }
        }

        return appendStoredEmbedAuth(embedUrl, token, jwt);
    }

    function initEmbedIframe(iframe) {
        var src = buildIframeSrc(iframe);
        if (!src) {
            return;
        }

        iframe.src = src;
    }

    function initEmbedIframes() {
        var iframes = document.querySelectorAll('iframe[data-fv-embed-base]');
        for (var i = 0; i < iframes.length; i++) {
            initEmbedIframe(iframes[i]);
        }
    }

    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || typeof data !== 'object') {
            return;
        }

        // Nachrichten der geöffneten Modal-Seite (Review-Hinweis, Feedback).
        if (modal.iframe && event.source === modal.iframe.contentWindow) {
            if (data.type === 'fv-app-popup-resize') {
                applyModalHeight(data.height);
                return;
            }

            if (data.type === 'fv-app-popup-close') {
                closeModal(data.result);
                return;
            }

            if (data.type === 'task-changed') {
                notifyEmbeds({ type: 'task-changed' });
                return;
            }
        }

        // Modal-Anfrage aus einem eingebetteten FeatValue-Frame. Absender ist nicht zwingend
        // das Dashboard-IFrame selbst — die Task-Sidebar und deren eigene IFrames fragen
        // ebenfalls an —, deshalb prüfen wir hier Absender und Ziel-URL über die erlaubten
        // FeatValue-Hosts statt über die Dashboard-IFrame-Zuordnung.
        if (data.type === 'fv-app-frame-modal' && typeof data.href === 'string' && data.href !== '') {
            if (!isAllowedFeatValueUrl(event.origin) || !isAllowedFeatValueUrl(data.href)) {
                return;
            }

            openModal(data.href, event.source, event.origin);

            // Bestätigung: Ohne sie öffnet der Absender ersatzweise ein eigenes Modal
            // innerhalb seines IFrames.
            if (event.source && typeof data.requestId === 'string' && data.requestId !== '') {
                postToFrame(event.source, event.origin, {
                    type: 'fv-app-frame-modal-ack',
                    requestId: data.requestId,
                });
            }

            return;
        }

        if (data.type === 'fv-app-frame-lightbox-open' && typeof data.image === 'string' && data.image !== '') {
            if (!isAllowedFeatValueUrl(event.origin)) {
                return;
            }

            openLightbox(data, event.source, event.origin);
            return;
        }

        if (data.type === 'fv-app-frame-lightbox-close') {
            if (!isAllowedFeatValueUrl(event.origin)) {
                return;
            }
            closeLightbox();
            return;
        }

        if (data.type === 'fv-app-frame-lightbox-next') {
            if (!isAllowedFeatValueUrl(event.origin)) {
                return;
            }
            nextLightboxImage();
            return;
        }

        if (data.type === 'fv-app-frame-lightbox-previous') {
            if (!isAllowedFeatValueUrl(event.origin)) {
                return;
            }
            previousLightboxImage();
            return;
        }

        var matchedIframe = findIframeForMessageOrigin(event.origin);
        if (!matchedIframe) {
            return;
        }

        var dashboardOrigin = dashboardOriginFromIframe(matchedIframe);

        if (data.type === 'featvalue-embed-clear-auth') {
            var clearSiteId = matchedIframe.getAttribute('data-fv-site-id') || '0';
            clearStoredToken(clearSiteId);
            return;
        }

        if (data.type === 'featvalue-embed-top-level-login' && typeof data.authorize_url === 'string') {
            var topLoginSiteId = matchedIframe.getAttribute('data-fv-site-id') || '0';
            if (!readStoredToken(topLoginSiteId) && !readStoredJwtToken(topLoginSiteId)) {
                attemptTopLevelLogin(matchedIframe, data.authorize_url);
            } else {
                // Token liegt schon first-party — IFrame ggf. ohne Auth-Query neu mit Token laden.
                var topSrc = buildIframeSrc(matchedIframe);
                if (topSrc && matchedIframe.getAttribute('src') !== topSrc) {
                    matchedIframe.src = topSrc;
                }
            }
            return;
        }

        if (data.type === 'featvalue-embed-login-required' && typeof data.authorize_url === 'string') {
            var loginSiteId = matchedIframe.getAttribute('data-fv-site-id') || '0';
            if (readStoredToken(loginSiteId) || readStoredJwtToken(loginSiteId)) {
                var loginSrc = buildIframeSrc(matchedIframe);
                if (loginSrc) {
                    matchedIframe.src = loginSrc;
                }
                return;
            }
            if (attemptTopLevelLogin(matchedIframe, data.authorize_url)) {
                return;
            }
            showLoginGate(matchedIframe, data.authorize_url);
            return;
        }

        if (data.type === 'featvalue-embed-auth' && typeof data.access_token === 'string' && data.access_token !== '') {
            applyAccessTokenToIframe(matchedIframe, data.access_token);
            return;
        }

        if (data.type === 'featvalue-embed-request-auth') {
            var requestSiteId = matchedIframe.getAttribute('data-fv-site-id') || '0';
            var token = readStoredToken(requestSiteId);
            if (!token || !event.source) {
                return;
            }

            hideLoginGate(matchedIframe);
            event.source.postMessage(
                { type: 'featvalue-embed-auth', access_token: token },
                event.origin
            );
            return;
        }

        if (data.type === 'fv-app-frame-location' && typeof data.url === 'string' && data.url !== '') {
            if (pendingFvTask && tryNavigateIframeToPendingTaskFromLocation(matchedIframe, dashboardOrigin, data.url)) {
                // Navigation zur Task-URL läuft; die nächste fv-app-frame-location-Nachricht
                // (nach dem Laden der Task-Seite) aktualisiert dann fv-task in der Parent-URL.
                return;
            }

            updateParentUrl(data.url);
            return;
        }

        if (data.type === 'fv-app-frame-sidebar') {
            if (data.sidebar && typeof data.sidebar.url === 'string' && data.sidebar.url !== '') {
                openSidebar(data.sidebar.url, matchedIframe, dashboardOrigin);
            } else {
                closeSidebar();
            }
        }
    });

    /**
     * Nach Top-Level-OAuth: fv_jwt_token / fv_access_token aus der CMS-URL in localStorage
     * (first-party auf WordPress) legen — sonst ist beim nächsten Seitenaufruf wieder ein Redirect nötig.
     */
    function persistIncomingParentAuthFromUrl() {
        try {
            var url = new URL(window.location.href);
            var accessToken = url.searchParams.get('fv_access_token') || '';
            var jwtToken = url.searchParams.get('fv_jwt_token') || '';
            if (!accessToken && !jwtToken) {
                return;
            }

            var siteIds = { '0': true };
            var iframes = document.querySelectorAll('iframe[data-fv-embed-base]');
            for (var i = 0; i < iframes.length; i++) {
                siteIds[iframes[i].getAttribute('data-fv-site-id') || '0'] = true;
            }

            Object.keys(siteIds).forEach(function (siteId) {
                if (accessToken) {
                    storeToken(siteId, accessToken);
                }
                if (jwtToken) {
                    storeJwtToken(siteId, jwtToken);
                }
            });
        } catch (e) {
            /* ignore */
        }
    }

    function stripIncomingJwtFromParentUrl() {
        try {
            var url = new URL(window.location.href);
            var hadAuth = url.searchParams.has('fv_jwt_token') || url.searchParams.has('fv_access_token');
            if (!hadAuth) {
                return;
            }
            url.searchParams.delete('fv_jwt_token');
            url.searchParams.delete('fv_access_token');
            var next = url.pathname + (url.search ? url.search : '') + url.hash;
            window.history.replaceState({}, '', next);
        } catch (e) {
            /* ignore */
        }
    }

    /**
     * Der Guard des OAuth-Callbacks überlebt bewusst nur diesen Seitenaufruf: er verhindert einen
     * zweiten stillen Versuch und verschwindet dann aus der Adressleiste.
     */
    function consumeSilentLoginGuardFromParentUrl() {
        try {
            var url = new URL(window.location.href);
            if (!url.searchParams.has(SILENT_LOGIN_GUARD)) {
                return;
            }

            silentLoginDeniedOnThisPage = true;
            url.searchParams.delete(SILENT_LOGIN_GUARD);
            window.history.replaceState({}, '', url.pathname + (url.search ? url.search : '') + url.hash);
        } catch (e) {
            /* ignore */
        }
    }

    function boot() {
        if (handOffPopupJwtToOpener()) {
            return;
        }

        consumeSilentLoginGuardFromParentUrl();
        persistIncomingParentAuthFromUrl();
        stripIncomingJwtFromParentUrl();
        initEmbedIframes();
    }

    /**
     * Register/Login-Popup landet mit ?fv_jwt_token=… auf WordPress — JWT an Opener übergeben und schließen.
     */
    function handOffPopupJwtToOpener() {
        try {
            var url = new URL(window.location.href);
            var jwt = url.searchParams.get('fv_jwt_token');
            if (!jwt || !window.opener || window.opener.closed) {
                return false;
            }

            var target = new URL(window.opener.location.href);
            target.searchParams.set('fv_jwt_token', jwt);
            window.opener.location.assign(target.toString());
            window.close();

            return true;
        } catch (e) {
            return false;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
