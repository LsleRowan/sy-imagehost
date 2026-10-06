/**
 * ImageHost Admin 交互
 * Toast / 确认框 / 侧边栏 / 主题 / 菜单 / 筛选 / 批量操作 / 上传 / 预览
 */
(function () {
    'use strict';

    var CFG = window.IH || {};

    /* ------------------------------------------------------------------ */
    /* 基础工具                                                            */
    /* ------------------------------------------------------------------ */
    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function el(html) {
        var t = document.createElement('template');
        t.innerHTML = html.trim();
        return t.content.firstElementChild;
    }

    function esc(text) {
        var d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);
        return d.innerHTML;
    }

    // 属性值转义（额外处理引号）
    function attr(text) {
        return esc(text).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function formatSize(bytes) {
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = 0;
        var n = Number(bytes) || 0;
        while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
        return (Math.round(n * 100) / 100) + ' ' + units[i];
    }

    function normExt(ext) {
        ext = (ext || '').toLowerCase();
        return ext === 'jpeg' ? 'jpg' : ext;
    }

    function timeValue(str) {
        var t = Date.parse(String(str).replace(' ', 'T'));
        return isNaN(t) ? 0 : t;
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).catch(function () { return fallbackCopy(text); });
        }
        return fallbackCopy(text);
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        return Promise.resolve();
    }

    var SVG = {
        more: '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/><circle cx="5" cy="12" r="1.6"/></svg>',
        imageOff: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.41 10.41a2 2 0 1 1-2.83-2.83"/><line x1="13.5" x2="6" y1="13.5" y2="21"/><line x1="18" x2="21" y1="12" y2="15"/><path d="M3.59 3.59A1.99 1.99 0 0 0 3 5v14a2 2 0 0 0 2 2h14a2 2 0 0 0 1.41-.59"/><line x1="1" x2="23" y1="1" y2="23"/></svg>',
        eye: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
        copy: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>',
        download: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>',
        trash: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>'
    };

    /* ------------------------------------------------------------------ */
    /* Toast                                                               */
    /* ------------------------------------------------------------------ */
    var toastWrap = null;

    function toast(text, type) {
        type = type || 'success';
        if (!toastWrap) toastWrap = $('#toast-wrap');
        if (!toastWrap) return;

        var iconHtml = type === 'error'
            ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>'
            : type === 'info'
                ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>'
                : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';

        var node = el(
            '<div class="toast toast--' + type + '" role="status">' +
                '<span class="toast__icon">' + iconHtml + '</span>' +
                '<span>' + esc(text) + '</span>' +
            '</div>'
        );
        toastWrap.appendChild(node);
        requestAnimationFrame(function () { node.classList.add('show'); });

        setTimeout(function () {
            node.classList.remove('show');
            setTimeout(function () { node.remove(); }, 260);
        }, 2600);
    }

    /* ------------------------------------------------------------------ */
    /* 确认框 / 输入框                                                      */
    /* ------------------------------------------------------------------ */
    function openLayer(innerHtml) {
        var overlay = el('<div class="modal-overlay">' + innerHtml + '</div>');
        document.body.appendChild(overlay);
        overlay.classList.add('active');
        requestAnimationFrame(function () { overlay.classList.add('shown'); });
        return overlay;
    }

    function closeLayer(overlay) {
        if (!overlay) return;
        overlay.classList.remove('shown');
        setTimeout(function () { overlay.remove(); }, 200);
    }

    function confirmDialog(opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            var overlay = openLayer(
                '<div class="modal" role="dialog" aria-modal="true" aria-label="' + attr(opts.title || '确认') + '">' +
                    '<div class="modal-head"><h3>' + esc(opts.title || '确认操作') + '</h3></div>' +
                    '<div class="modal-body"><p>' + esc(opts.text || '确定要执行此操作吗？') + '</p></div>' +
                    '<div class="modal-foot">' +
                        '<button type="button" class="btn btn-outline" data-no>取消</button>' +
                        '<button type="button" class="btn ' + (opts.danger === false ? 'btn-primary' : 'btn-danger') + '" data-yes>' + esc(opts.okText || '确定') + '</button>' +
                    '</div>' +
                '</div>'
            );

            function done(val) {
                overlay.removeEventListener('keydown', onKey);
                closeLayer(overlay);
                resolve(val);
            }
            function onKey(e) { if (e.key === 'Escape') { e.stopPropagation(); done(false); } }

            overlay.__ihCancel = function () { done(false); };

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) done(false);
                if (e.target.closest('[data-no]')) done(false);
                if (e.target.closest('[data-yes]')) done(true);
            });
            overlay.addEventListener('keydown', onKey);
            overlay.querySelector('[data-yes]').focus();
        });
    }

    function promptDialog(opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            var overlay = openLayer(
                '<div class="modal" role="dialog" aria-modal="true" aria-label="' + attr(opts.title || '输入') + '">' +
                    '<div class="modal-head"><h3>' + esc(opts.title || '请输入') + '</h3></div>' +
                    '<div class="modal-body">' +
                        (opts.label ? '<div class="form-group"><label for="ih-prompt-input">' + esc(opts.label) + '</label>' : '<div class="form-group">') +
                        '<input type="text" id="ih-prompt-input" value="' + attr(opts.value || '') + '" ' +
                            'placeholder="' + attr(opts.placeholder || '') + '" maxlength="50" autocomplete="off">' +
                        (opts.hint ? '<div class="form-hint">' + esc(opts.hint) + '</div>' : '') +
                        '</div>' +
                    '</div>' +
                    '<div class="modal-foot">' +
                        '<button type="button" class="btn btn-outline" data-no>取消</button>' +
                        '<button type="button" class="btn btn-primary" data-yes>' + esc(opts.okText || '确定') + '</button>' +
                    '</div>' +
                '</div>'
            );

            var input = overlay.querySelector('#ih-prompt-input');

            function done(val) {
                document.removeEventListener('keydown', onDocKey, true);
                closeLayer(overlay);
                resolve(val);
            }
            function trySubmit() {
                var v = input.value.trim();
                if (!v && opts.required !== false) { input.focus(); return; }
                done(v);
            }
            function onDocKey(e) {
                if (e.key === 'Escape') { e.stopPropagation(); done(null); }
                if (e.key === 'Enter') { e.stopPropagation(); trySubmit(); }
            }

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) done(null);
                if (e.target.closest('[data-no]')) done(null);
                if (e.target.closest('[data-yes]')) trySubmit();
            });
            document.addEventListener('keydown', onDocKey, true);
            setTimeout(function () { input.focus(); input.select(); }, 60);
        });
    }

    /* ------------------------------------------------------------------ */
    /* 请求                                                                */
    /* ------------------------------------------------------------------ */
    function rawPost(url, data) {
        var body = new URLSearchParams();
        Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
        if (CFG.csrf) body.append('csrf_token', CFG.csrf);

        return fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: body.toString()
        }).then(function (res) {
            return res.json().catch(function () { return { error: '响应解析失败' }; }).then(function (j) {
                return { ok: res.ok, status: res.status, body: j };
            });
        });
    }

    function apiPost(url, data) {
        return rawPost(url, data).then(function (res) {
            var j = res.body || {};
            if (res.ok && j.success) return j.data;
            throw new Error(j.error || '请求失败');
        });
    }

    /* ------------------------------------------------------------------ */
    /* 主题 / 侧边栏 / 菜单                                                 */
    /* ------------------------------------------------------------------ */
    function initTheme() {
        var btn = $('#theme-toggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (dark) {
                document.documentElement.removeAttribute('data-theme');
                try { localStorage.setItem('ih-theme', 'light'); } catch (e) {}
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                try { localStorage.setItem('ih-theme', 'dark'); } catch (e) {}
            }
        });
    }

    function initSidebar() {
        var toggle = $('#sidebar-toggle');
        var overlay = $('#sidebar-overlay');
        if (!toggle) return;

        function setOpen(open) {
            document.body.classList.toggle('sidebar-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        toggle.addEventListener('click', function () {
            setOpen(!document.body.classList.contains('sidebar-open'));
        });
        if (overlay) overlay.addEventListener('click', function () { setOpen(false); });
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1024) setOpen(false);
        });
    }

    function closeMenus(except) {
        $$('.menu.open').forEach(function (m) {
            if (m !== except) m.classList.remove('open');
        });
    }

    function initMenus() {
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-menu-btn], #account-btn');
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                var menu = trigger.parentElement.querySelector(':scope > .menu');
                if (!menu) return;
                var willOpen = !menu.classList.contains('open');
                closeMenus(menu);
                menu.classList.toggle('open', willOpen);
                if (trigger.id === 'account-btn') {
                    trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                }
                return;
            }
            if (!e.target.closest('.menu')) closeMenus();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMenus();
        });
    }

    /* ------------------------------------------------------------------ */
    /* 图片网格：筛选 / 排序 / 批量                                         */
    /* ------------------------------------------------------------------ */
    var grid = null;
    var allCards = [];

    function visibleCards() {
        return allCards.filter(function (c) { return c.style.display !== 'none'; });
    }

    function applyFilters() {
        if (!grid) return;
        var qEl = $('#img-search');
        var fEl = $('#filter-folder');
        var tEl = $('#filter-type');
        var sEl = $('#sort-by');

        var q = qEl ? qEl.value.trim().toLowerCase() : '';
        var folder = fEl ? fEl.value : '';
        var type = tEl ? normExt(tEl.value) : '';
        var sort = sEl ? sEl.value : 'newest';

        var sorted = allCards.slice();
        sorted.sort(function (a, b) {
            if (sort === 'name') return a.dataset.name.localeCompare(b.dataset.name, 'zh-Hans-CN');
            if (sort === 'size') return Number(b.dataset.size) - Number(a.dataset.size);
            if (sort === 'oldest') return timeValue(a.dataset.time) - timeValue(b.dataset.time);
            return timeValue(b.dataset.time) - timeValue(a.dataset.time);
        });
        sorted.forEach(function (c) { grid.appendChild(c); });

        var shown = 0;
        sorted.forEach(function (c) {
            var ok = true;
            if (folder && c.dataset.folder !== folder) ok = false;
            if (ok && type && normExt(c.dataset.ext) !== type) ok = false;
            if (ok && q) {
                var hay = (c.dataset.name + ' ' + c.dataset.file + ' ' + c.dataset.folder).toLowerCase();
                if (hay.indexOf(q) === -1) ok = false;
            }
            c.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });

        var countEl = $('#filter-count');
        if (countEl) {
            countEl.textContent = shown === allCards.length
                ? '共 ' + allCards.length + ' 张'
                : '显示 ' + shown + ' / ' + allCards.length;
        }

        var emptyFilter = $('#empty-filter');
        if (emptyFilter) emptyFilter.style.display = (shown === 0 && allCards.length > 0) ? '' : 'none';
        if (grid) grid.style.display = shown === 0 ? 'none' : '';
    }

    function updateSelection() {
        var checked = $$('.js-select:checked');
        var bar = $('#batch-bar');
        var count = $('#sel-count');
        if (count) count.textContent = checked.length;
        if (bar) bar.classList.toggle('show', checked.length > 0);

        allCards.forEach(function (card) {
            var box = card.querySelector('.js-select');
            card.classList.toggle('selected', !!(box && box.checked));
        });
    }

    function removeCards(cards) {
        cards.forEach(function (c) { c.remove(); });
        allCards = allCards.filter(function (c) { return c.parentNode; });
        updateSelection();
        applyFilters();
        var emptyAll = $('#empty-all');
        if (emptyAll && allCards.length === 0) emptyAll.style.display = '';
    }

    function deleteImages(ids) {
        return apiPost('images.php', { action: 'delete_batch', ids: ids.join(',') })
            .then(function (data) {
                var n = (data && data.deleted) || 0;
                toast(n > 0 ? '已删除 ' + n + ' 张图片' : '删除失败', n > 0 ? 'success' : 'error');
                return n;
            })
            .catch(function (err) {
                toast(err.message || '删除失败', 'error');
                return 0;
            });
    }

    function initImageGrid() {
        grid = $('#image-grid');
        if (!grid) return;
        allCards = $$('.img-card', grid);

        ['#img-search', '#filter-folder', '#filter-type', '#sort-by'].forEach(function (sel) {
            var node = $(sel);
            if (!node) return;
            node.addEventListener('input', applyFilters);
            node.addEventListener('change', applyFilters);
        });

        var clearBtn = $('#clear-filters');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                var s = $('#img-search'); if (s) s.value = '';
                var f = $('#filter-folder'); if (f) f.value = '';
                var t = $('#filter-type'); if (t) t.value = '';
                applyFilters();
            });
        }

        grid.addEventListener('change', function (e) {
            if (e.target.classList.contains('js-select')) updateSelection();
        });

        var selAll = $('#select-all');
        if (selAll) selAll.addEventListener('click', function () {
            visibleCards().forEach(function (c) {
                var box = c.querySelector('.js-select');
                if (box) box.checked = true;
            });
            updateSelection();
        });

        var selNone = $('#select-none');
        if (selNone) selNone.addEventListener('click', function () {
            $$('.js-select:checked').forEach(function (b) { b.checked = false; });
            updateSelection();
        });

        var batchDel = $('#batch-delete');
        if (batchDel) batchDel.addEventListener('click', function () {
            var ids = $$('.js-select:checked').map(function (b) { return b.value; });
            if (!ids.length) return;
            confirmDialog({
                title: '批量删除',
                text: '确定删除选中的 ' + ids.length + ' 张图片吗？此操作不可恢复。',
                okText: '删除 ' + ids.length + ' 张'
            }).then(function (yes) {
                if (!yes) return;
                var boxes = $$('.js-select:checked');
                var cards = boxes.map(function (b) { return b.closest('.img-card'); });
                deleteImages(ids).then(function (n) {
                    if (n > 0) removeCards(cards);
                });
            });
        });

        /* 卡片点击 → 预览；菜单按钮 / 复选框除外 */
        grid.addEventListener('click', function (e) {
            if (e.target.closest('.img-check, .img-card__more, .menu, button, a, input')) return;
            var card = e.target.closest('.img-card');
            if (card) openPreviewFromCard(card);
        });

        /* 卡片菜单动作 */
        document.addEventListener('click', function (e) {
            var actBtn = e.target.closest('.img-card__menu [data-act]');
            if (!actBtn) return;
            var card = actBtn.closest('.img-card');
            if (!card) return;
            closeMenus();
            var act = actBtn.dataset.act;

            if (act === 'preview') openPreviewFromCard(card);
            if (act === 'copy') {
                copyText(card.dataset.url).then(function () { toast('链接已复制'); });
            }
            if (act === 'download') {
                var a = document.createElement('a');
                a.href = card.dataset.url;
                a.download = card.dataset.file || '';
                a.target = '_blank';
                a.rel = 'noopener';
                document.body.appendChild(a);
                a.click();
                a.remove();
            }
            if (act === 'delete') {
                confirmDialog({
                    title: '删除图片',
                    text: '确定删除「' + card.dataset.name + '」吗？此操作不可恢复。',
                    okText: '删除'
                }).then(function (yes) {
                    if (!yes) return;
                    deleteImages([card.dataset.id]).then(function (n) {
                        if (n > 0) removeCards([card]);
                    });
                });
            }
        });

        /* 右栏最近上传 → 预览 */
        document.addEventListener('click', function (e) {
            var item = e.target.closest('[data-goto]');
            if (!item) return;
            var id = item.dataset.goto;
            var target = allCards.filter(function (c) { return c.dataset.id === id; })[0];
            if (!target) return;
            var vis = visibleCards();
            var idx = vis.indexOf(target);
            if (idx >= 0) showPreview(vis, idx);
            else showPreview([target], 0);
        });

        applyFilters();
        updateSelection();
    }

    /* ------------------------------------------------------------------ */
    /* 图片预览 Lightbox                                                    */
    /* ------------------------------------------------------------------ */
    var lb = null;
    var lbList = [];
    var lbIndex = 0;
    var lbScale = 1;

    function buildLightbox() {
        if (lb) return lb;
        lb = el(
            '<div class="lightbox" role="dialog" aria-modal="true" aria-label="图片预览">' +
                '<div class="lb-stage">' +
                    '<div class="lb-counter"></div>' +
                    '<button class="lb-btn lb-prev" type="button" aria-label="上一张">' +
                        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>' +
                    '<img class="lb-img" alt="预览图片">' +
                    '<button class="lb-btn lb-next" type="button" aria-label="下一张">' +
                        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>' +
                    '<button class="lb-close" type="button" aria-label="关闭">' +
                        '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>' +
                '</div>' +
                '<aside class="lb-info">' +
                    '<h4 data-lb="name"></h4>' +
                    '<div class="lb-folder" data-lb="folder"></div>' +
                    '<dl class="lb-props">' +
                        '<dt>尺寸</dt><dd data-lb="dim"></dd>' +
                        '<dt>大小</dt><dd data-lb="size"></dd>' +
                        '<dt>格式</dt><dd data-lb="ext"></dd>' +
                        '<dt>MIME</dt><dd data-lb="mime"></dd>' +
                        '<dt>上传时间</dt><dd data-lb="time"></dd>' +
                    '</dl>' +
                    '<div class="lb-url">' +
                        '<div class="lb-url-label">图片 URL</div>' +
                        '<div class="lb-url-box"><code data-lb="url"></code></div>' +
                    '</div>' +
                    '<div class="lb-actions">' +
                        '<button class="lb-btn-solid" type="button" data-lb-copy>' + SVG.copy + ' 复制链接</button>' +
                        '<button class="lb-btn-line" type="button" data-lb-download>' + SVG.download + ' 下载</button>' +
                        '<button class="lb-btn-danger" type="button" data-lb-delete>' + SVG.trash + ' 删除</button>' +
                    '</div>' +
                '</aside>' +
            '</div>'
        );
        document.body.appendChild(lb);

        lb.querySelector('.lb-prev').addEventListener('click', function () { stepPreview(-1); });
        lb.querySelector('.lb-next').addEventListener('click', function () { stepPreview(1); });
        lb.querySelector('.lb-close').addEventListener('click', closePreview);
        lb.querySelector('.lb-stage').addEventListener('click', function (e) {
            if (e.target === e.currentTarget) closePreview();
        });

        var img = lb.querySelector('.lb-img');
        img.addEventListener('dblclick', function () {
            lbScale = lbScale > 1 ? 1 : 2.5;
            applyScale();
        });
        lb.querySelector('.lb-stage').addEventListener('wheel', function (e) {
            if (!lbList.length) return;
            e.preventDefault();
            lbScale += e.deltaY < 0 ? 0.2 : -0.2;
            lbScale = Math.max(1, Math.min(4, Math.round(lbScale * 10) / 10));
            if (lbScale === 1) lbScale = 1;
            applyScale();
        }, { passive: false });

        lb.querySelector('[data-lb-copy]').addEventListener('click', function () {
            var card = lbList[lbIndex];
            if (!card) return;
            copyText(card.dataset.url).then(function () { toast('链接已复制'); });
        });
        lb.querySelector('[data-lb-download]').addEventListener('click', function () {
            var card = lbList[lbIndex];
            if (!card) return;
            var a = document.createElement('a');
            a.href = card.dataset.url;
            a.download = card.dataset.file || '';
            a.target = '_blank';
            a.rel = 'noopener';
            document.body.appendChild(a);
            a.click();
            a.remove();
        });
        lb.querySelector('[data-lb-delete]').addEventListener('click', function () {
            var card = lbList[lbIndex];
            if (!card) return;
            confirmDialog({
                title: '删除图片',
                text: '确定删除「' + card.dataset.name + '」吗？此操作不可恢复。',
                okText: '删除'
            }).then(function (yes) {
                if (!yes) return;
                deleteImages([card.dataset.id]).then(function (n) {
                    if (n <= 0) return;
                    var wasGrid = card.parentNode;
                    var nextIndex = lbIndex;
                    lbList = lbList.filter(function (c) { return c !== card; });
                    if (wasGrid) removeCards([card]);
                    if (!lbList.length) { closePreview(); return; }
                    if (nextIndex >= lbList.length) nextIndex = lbList.length - 1;
                    renderPreview(nextIndex);
                });
            });
        });

        return lb;
    }

    function applyScale() {
        if (!lb) return;
        var img = lb.querySelector('.lb-img');
        img.style.transform = 'scale(' + lbScale + ')';
        img.classList.toggle('zoomed', lbScale > 1);
    }

    function openPreviewFromCard(card) {
        var list = visibleCards();
        var idx = list.indexOf(card);
        if (idx < 0) { list = [card]; idx = 0; }
        showPreview(list, idx);
    }

    function showPreview(list, index) {
        if (!list || !list.length) return;
        buildLightbox();
        lbList = list;
        renderPreview(index || 0);
        lb.classList.add('active');
        document.body.style.overflow = 'hidden';
        lb.querySelector('.lb-close').focus();
    }

    function renderPreview(index) {
        if (!lb || !lbList.length) return;
        lbIndex = Math.max(0, Math.min(index, lbList.length - 1));
        var card = lbList[lbIndex];
        var d = card.dataset;

        lbScale = 1;
        applyScale();

        var img = lb.querySelector('.lb-img');
        img.onerror = function () { toast('图片加载失败', 'error'); };
        img.src = d.url;

        lb.querySelector('.lb-counter').textContent = (lbIndex + 1) + ' / ' + lbList.length;
        lb.querySelector('[data-lb="name"]').textContent = d.name;
        lb.querySelector('[data-lb="folder"]').textContent = '文件夹 · ' + d.folder;
        lb.querySelector('[data-lb="dim"]').textContent = d.w + ' × ' + d.h;
        lb.querySelector('[data-lb="size"]').textContent = formatSize(d.size);
        lb.querySelector('[data-lb="ext"]').textContent = normExt(d.ext).toUpperCase();
        lb.querySelector('[data-lb="mime"]').textContent = d.mime || '—';
        lb.querySelector('[data-lb="time"]').textContent = d.time || '—';
        lb.querySelector('[data-lb="url"]').textContent = d.url;

        var multi = lbList.length > 1;
        lb.querySelector('.lb-prev').style.display = multi ? '' : 'none';
        lb.querySelector('.lb-next').style.display = multi ? '' : 'none';
    }

    function stepPreview(delta) {
        if (lbList.length < 2) return;
        var next = (lbIndex + delta + lbList.length) % lbList.length;
        renderPreview(next);
    }

    function closePreview() {
        if (!lb) return;
        lb.classList.remove('active');
        document.body.style.overflow = '';
    }

    function initPreviewKeys() {
        document.addEventListener('keydown', function (e) {
            if (!lb || !lb.classList.contains('active')) return;
            if (document.querySelector('.modal-overlay.shown')) return; // 确认框优先
            if (e.key === 'Escape') { closePreview(); return; }
            if (e.key === 'ArrowLeft') { e.preventDefault(); stepPreview(-1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); stepPreview(1); }
        });
    }

    /* ------------------------------------------------------------------ */
    /* 上传 Modal                                                          */
    /* ------------------------------------------------------------------ */
    var uploadOverlay = null;
    var uQueue = [];
    var uIndex = 0;
    var uRunning = false;
    var uSeq = 0;
    var uSummary = { ok: 0, fail: 0 };
    var uUploaded = [];

    function uploadState() {
        return { total: uQueue.length, done: uQueue.filter(function (i) { return i.status === 'done'; }).length + uQueue.filter(function (i) { return i.status === 'error'; }).length, running: uRunning };
    }

    function updatePill() {
        var pill = $('#upload-pill');
        if (!pill) return;
        var state = uploadState();
        var active = uRunning && uQueue.length > 0;
        pill.classList.toggle('show', active);
        var count = $('#pill-count');
        if (count) count.textContent = Math.min(state.done + (uRunning ? 1 : 0), state.total) + ' / ' + state.total;
        var icon = $('#pill-icon');
        if (icon) {
            icon.innerHTML = active
                ? '<svg class="spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg>'
                : '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>';
        }
    }

    function buildUploadModal() {
        if (uploadOverlay) return uploadOverlay;

        var folderOptions = (CFG.folders || []).map(function (f) {
            return '<option value="' + attr(f.name) + '">' + esc(f.name) + ' (' + f.count + ' 张)</option>';
        }).join('');

        uploadOverlay = el(
            '<div class="modal-overlay" id="upload-modal">' +
                '<div class="modal modal--upload" role="dialog" aria-modal="true" aria-label="上传图片">' +
                    '<div class="modal-head">' +
                        '<h3>上传图片</h3>' +
                        '<button class="btn btn-icon" type="button" data-close aria-label="关闭">' +
                            '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
                        '</button>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        '<div class="upload-drop" id="upload-drop">' +
                            '<span class="ud-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg></span>' +
                            '<div class="ud-title">拖拽图片到这里，或点击选择</div>' +
                            '<div class="ud-sub">支持批量上传 · 单个最大 ' + (CFG.maxMb || 10) + ' MB</div>' +
                            '<input type="file" id="upload-file" multiple accept="image/jpeg,image/png,image/gif,image/webp,image/avif">' +
                        '</div>' +
                        '<div class="upload-folder">' +
                            '<label for="upload-folder">上传到</label>' +
                            '<select id="upload-folder">' +
                                '<option value="">选择文件夹</option>' + folderOptions +
                            '</select>' +
                        '</div>' +
                        '<label class="upload-opt" for="upload-webp">' +
                            '<input type="checkbox" id="upload-webp">' +
                            '<span class="uo-text">转换为 WebP<small>压缩体积 · JPG / PNG / 静态 GIF 自动转换，动画 GIF 保持原样</small></span>' +
                        '</label>' +
                        '<div class="upload-queue" id="upload-queue"></div>' +
                        '<div class="upload-summary" id="upload-summary"></div>' +
                    '</div>' +
                    '<div class="modal-foot">' +
                        '<button class="btn btn-ghost" type="button" data-close>取消</button>' +
                        '<button class="btn btn-primary" type="button" id="upload-start" disabled>开始上传</button>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );
        document.body.appendChild(uploadOverlay);

        var drop = $('#upload-drop', uploadOverlay);
        var fileInput = $('#upload-file', uploadOverlay);

        drop.addEventListener('click', function () { fileInput.click(); });
        drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('dragover'); });
        drop.addEventListener('dragleave', function () { drop.classList.remove('dragover'); });
        drop.addEventListener('drop', function (e) {
            e.preventDefault();
            drop.classList.remove('dragover');
            addFiles(e.dataTransfer.files);
        });
        fileInput.addEventListener('change', function (e) {
            addFiles(e.target.files);
            fileInput.value = '';
        });

        var folderSel = $('#upload-folder', uploadOverlay);
        if (folderSel && !folderSel.value && CFG.folders && CFG.folders.length === 1) {
            folderSel.value = CFG.folders[0].name;
        }

        var webpBox = $('#upload-webp', uploadOverlay);
        if (webpBox) {
            try { webpBox.checked = localStorage.getItem('ih-upload-webp') === '1'; } catch (e) {}
            webpBox.addEventListener('change', function () {
                try { localStorage.setItem('ih-upload-webp', webpBox.checked ? '1' : '0'); } catch (e) {}
            });
        }

        $$('#upload-modal [data-close]', document).forEach(function (btn) {
            btn.addEventListener('click', function () { closeUploadModal(); });
        });
        uploadOverlay.addEventListener('click', function (e) {
            if (e.target === uploadOverlay) closeUploadModal();
        });
        $('#upload-start', uploadOverlay).addEventListener('click', startUpload);
        $('#upload-queue', uploadOverlay).addEventListener('click', function (e) {
            var rm = e.target.closest('[data-uq-remove]');
            if (rm) removeQueueItem(Number(rm.dataset.uqRemove));
        });

        return uploadOverlay;
    }

    function openUploadModal() {
        buildUploadModal();
        if (!uQueue.length) {
            var sum = $('#upload-summary', uploadOverlay);
            if (sum) { sum.classList.remove('show'); sum.innerHTML = ''; }
        }
        uploadOverlay.classList.add('active');
        requestAnimationFrame(function () { uploadOverlay.classList.add('shown'); });
        document.body.style.overflow = 'hidden';
        renderQueue();
    }

    function closeUploadModal() {
        if (!uploadOverlay) return;
        uploadOverlay.classList.remove('shown');
        setTimeout(function () { uploadOverlay.classList.remove('active'); }, 180);
        if (!lb || !lb.classList.contains('active')) document.body.style.overflow = '';
        updatePill();
    }

    function addFiles(fileList) {
        var added = 0;
        Array.prototype.forEach.call(fileList, function (file) {
            if (!file.type || file.type.indexOf('image/') !== 0) return;
            var dup = uQueue.some(function (i) {
                return i.file.name === file.name && i.file.size === file.size && i.status !== 'error';
            });
            if (dup) return;
            if (file.size > (CFG.maxMb || 10) * 1048576) {
                toast('「' + file.name + '」超过大小限制', 'error');
                return;
            }
            uQueue.push({
                key: ++uSeq,
                file: file,
                status: 'pending',
                progress: 0,
                xhr: null,
                result: null,
                thumb: URL.createObjectURL(file)
            });
            added++;
        });
        if (added) renderQueue();
    }

    function removeQueueItem(key) {
        if (uRunning) {
            var item = uQueue.filter(function (i) { return i.key === key; })[0];
            if (item && item.xhr) item.xhr.abort();
        }
        uQueue = uQueue.filter(function (i) { return i.key !== key; });
        renderQueue();
    }

    function statusText(item) {
        if (item.status === 'pending') return '等待上传';
        if (item.status === 'uploading') return item.progress + '%';
        if (item.status === 'done') return (item.result && item.result.converted) ? '已完成 · WebP' : '已完成';
        if (item.status === 'error') return item.error || '上传失败';
        if (item.status === 'canceled') return '已取消';
        return '';
    }

    function renderQueue() {
        var wrap = $('#upload-queue');
        if (!wrap) return;
        wrap.classList.toggle('show', uQueue.length > 0);

        wrap.innerHTML = uQueue.map(function (item) {
            var cls = item.status === 'done' ? ' is-done' : (item.status === 'error' ? ' is-error' : '');
            var pct = item.status === 'done' ? 100 : item.progress;
            return '' +
                '<div class="uq-item' + cls + '" data-uq="' + item.key + '">' +
                    '<img class="uq-thumb" src="' + item.thumb + '" alt="">' +
                    '<div class="uq-main">' +
                        '<div class="uq-name" title="' + attr(item.file.name) + '">' + esc(item.file.name) + '</div>' +
                        '<div class="uq-sub"><span>' + formatSize(item.file.size) + '</span><span class="st">' + esc(statusText(item)) + '</span></div>' +
                        '<div class="uq-progress"><span style="width:' + pct + '%"></span></div>' +
                    '</div>' +
                    '<div class="uq-actions">' +
                        '<button type="button" data-uq-remove="' + item.key + '" aria-label="移除">' +
                            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
                        '</button>' +
                    '</div>' +
                '</div>';
        }).join('');

        var startBtn = $('#upload-start');
        if (startBtn) {
            var pending = uQueue.some(function (i) { return i.status === 'pending'; });
            startBtn.disabled = uRunning || !pending;
            startBtn.textContent = uRunning ? '上传中…' : (uQueue.length ? '开始上传 (' + uQueue.length + ')' : '开始上传');
        }
        updatePill();
    }

    function uploadOne(item, folder) {
        return new Promise(function (resolve) {
            var fd = new FormData();
            fd.append('folder', folder);
            fd.append('csrf_token', CFG.csrf || '');
            var webpBox = $('#upload-webp');
            if (webpBox && webpBox.checked) fd.append('webp', '1');
            fd.append('images[]', item.file);

            var xhr = new XMLHttpRequest();
            item.xhr = xhr;
            item.status = 'uploading';
            renderQueue();

            xhr.open('POST', CFG.endpoint || 'upload.php');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.addEventListener('progress', function (e) {
                if (!e.lengthComputable) return;
                item.progress = Math.min(99, Math.round(e.loaded / e.total * 100));
                var node = document.querySelector('[data-uq="' + item.key + '"] .uq-progress > span');
                var txt = document.querySelector('[data-uq="' + item.key + '"] .uq-sub .st');
                if (node) node.style.width = item.progress + '%';
                if (txt) txt.textContent = item.progress + '%';
                updatePill();
            });

            xhr.addEventListener('load', function () {
                var body = {};
                try { body = JSON.parse(xhr.responseText); } catch (e) {}
                if (xhr.status >= 200 && xhr.status < 300 && Array.isArray(body.success) && body.success.length) {
                    item.status = 'done';
                    item.result = body.success[0];
                    item.progress = 100;
                } else {
                    item.status = 'error';
                    var reason = '上传失败';
                    if (Array.isArray(body.failed) && body.failed.length) reason = body.failed[0].reason || reason;
                    else if (body.error) reason = body.error;
                    else if (xhr.status === 0) reason = '网络错误';
                    item.error = reason;
                }
                item.xhr = null;
                resolve();
            });

            xhr.addEventListener('error', function () {
                item.status = 'error';
                item.error = '网络错误';
                item.xhr = null;
                resolve();
            });

            xhr.addEventListener('abort', function () {
                item.status = 'canceled';
                item.xhr = null;
                resolve();
            });

            xhr.send(fd);
        });
    }

    function startUpload() {
        var folderSel = $('#upload-folder');
        var folder = folderSel ? folderSel.value : '';
        if (!folder) { toast('请选择上传到的文件夹', 'error'); if (folderSel) folderSel.focus(); return; }
        if (uRunning) return;

        var pending = uQueue.filter(function (i) { return i.status === 'pending'; });
        if (!pending.length) return;

        uRunning = true;
        uSummary = { ok: 0, fail: 0 };
        var sumEl = $('#upload-summary');
        if (sumEl) { sumEl.classList.remove('show'); sumEl.innerHTML = ''; }
        renderQueue();
        updatePill();

        function next() {
            if (uIndex >= pending.length) {
                uRunning = false;
                uIndex = 0;
                renderQueue();
                finishUpload();
                return;
            }
            var item = pending[uIndex++];
            if (uQueue.indexOf(item) === -1) { next(); return; }  // 已被移除
            uploadOne(item, folder).then(function () {
                if (item.status === 'done') uSummary.ok++;
                else if (item.status === 'error') uSummary.fail++;
                renderQueue();
                next();
            });
        }
        next();
    }

    function finishUpload() {
        var sumEl = $('#upload-summary');
        var total = uSummary.ok + uSummary.fail;
        uUploaded = uQueue.filter(function (i) { return i.status === 'done' && i.result; })
            .map(function (i) { return i.result; });

        if (sumEl && total > 0) {
            var failed = uQueue.filter(function (i) { return i.status === 'error'; });
            var html = '本次完成：成功 ' + uSummary.ok + ' 张' + (uSummary.fail ? '，失败 ' + uSummary.fail + ' 张' : '');
            if (failed.length) {
                html += '<br>' + failed.map(function (i) {
                    return '· ' + esc(i.file.name) + ' — ' + esc(i.error || '失败');
                }).join('<br>');
            }
            sumEl.innerHTML = html;
            sumEl.classList.add('show');
        }

        if (uSummary.ok > 0) {
            toast('上传完成，成功 ' + uSummary.ok + ' 张图片', 'success');
            insertUploadedCards();
        }
        if (uSummary.fail > 0) toast(uSummary.fail + ' 张图片上传失败', 'error');

        // 已完成的从队列清理，失败的保留以便重试
        uQueue = uQueue.filter(function (i) { return i.status === 'error'; });
        renderQueue();
        updatePill();
    }

    function insertUploadedCards() {
        if (!grid) return;
        var results = uUploaded;
        if (!results.length) return;

        var now = new Date();
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var stamp = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) +
            ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());

        var badgeMap = { jpg: 'badge-blue', png: 'badge-green', gif: 'badge-orange', webp: 'badge-blue', avif: 'badge-green' };

        results.forEach(function (r) {
            var rawExt = r.ext
                || (String(r.original_name || '').indexOf('.') > -1 ? r.original_name.split('.').pop() : '');
            var ext = normExt(String(rawExt).toLowerCase());
            if (!/^[a-z0-9]+$/.test(ext)) ext = 'img';
            var card = el(
                '<article class="img-card"' +
                    ' data-id="' + attr(r.id) + '"' +
                    ' data-name="' + attr(r.original_name) + '"' +
                    ' data-file="' + attr(r.name) + '"' +
                    ' data-folder="' + attr(r.folder) + '"' +
                    ' data-ext="' + attr(ext) + '"' +
                    ' data-size="' + attr(r.size) + '"' +
                    ' data-time="' + attr(stamp) + '"' +
                    ' data-url="' + attr(r.url) + '"' +
                    ' data-mime=""' +
                    ' data-w="' + attr(r.width || 0) + '"' +
                    ' data-h="' + attr(r.height || 0) + '">' +
                    '<div class="img-card__thumb">' +
                        '<span class="img-check"><input type="checkbox" class="js-select" value="' + attr(r.id) + '" aria-label="选择"></span>' +
                        '<button class="img-card__more" type="button" data-menu-btn aria-label="更多操作">' + SVG.more + '</button>' +
                        '<div class="menu img-card__menu">' +
                            '<button type="button" data-act="preview">' + SVG.eye + ' 预览</button>' +
                            '<button type="button" data-act="copy">' + SVG.copy + ' 复制链接</button>' +
                            '<button type="button" data-act="download">' + SVG.download + ' 下载</button>' +
                            '<div class="menu-sep"></div>' +
                            '<button type="button" data-act="delete" class="menu-danger">' + SVG.trash + ' 删除</button>' +
                        '</div>' +
                        '<img src="' + attr(r.url) + '" alt="' + attr(r.original_name) + '" loading="lazy"' +
                            ' onerror="this.closest(\'.img-card__thumb\').classList.add(\'img-error\')">' +
                        '<div class="thumb-error">' + SVG.imageOff + '<span>图片加载失败</span></div>' +
                    '</div>' +
                    '<div class="img-card__body">' +
                        '<div class="img-card__name" title="' + attr(r.original_name) + '">' + esc(r.original_name) + '</div>' +
                        '<div class="img-card__meta">' +
                            '<span>' + esc(r.sizeFormatted || formatSize(r.size)) + '</span>' +
                            '<span class="sep">·</span><span>' + stamp.slice(0, 10) + '</span>' +
                            '<span class="badge ' + (badgeMap[ext] || '') + '">' + esc(ext.toUpperCase()) + '</span>' +
                        '</div>' +
                    '</div>' +
                '</article>'
            );
            grid.appendChild(card);
            allCards.push(card);
        });

        var emptyAll = $('#empty-all');
        if (emptyAll) emptyAll.style.display = 'none';
        applyFilters();
    }

    function initUpload() {
        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-upload-open]')) openUploadModal();
        });
        var pill = $('#upload-pill');
        if (pill) {
            pill.addEventListener('click', openUploadModal);
            pill.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openUploadModal(); }
            });
        }
        updatePill();
    }

    /* ------------------------------------------------------------------ */
    /* 文件夹页                                                            */
    /* ------------------------------------------------------------------ */
    function initFolders() {
        var createBtns = $$('[data-folder-create]');
        if (createBtns.length) {
            createBtns.forEach(function (btn) {
                btn.addEventListener('click', function () { askCreateFolder(); });
            });
            if (/[?&]new=1/.test(location.search)) askCreateFolder();
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-fact]');
            if (!btn) return;
            var name = btn.dataset.folder || '';
            var fact = btn.dataset.fact;
            closeMenus();

            if (fact === 'open') {
                location.href = 'images.php?folder=' + encodeURIComponent(name);
                return;
            }
            if (fact === 'rename') {
                promptDialog({
                    title: '重命名文件夹',
                    label: '新的文件夹名称',
                    value: name,
                    hint: '只允许字母、数字、下划线和短横线',
                    okText: '保存'
                }).then(function (val) {
                    if (val === null || val === '' || val === name) return;
                    apiPost('folders.php', { action: 'rename', old_name: name, name: val })
                        .then(function () { toast('文件夹已重命名'); setTimeout(function () { location.reload(); }, 450); })
                        .catch(function (err) { toast(err.message || '重命名失败', 'error'); });
                });
                return;
            }
            if (fact === 'delete') {
                confirmDialog({
                    title: '删除文件夹',
                    text: '确定删除文件夹「' + name + '」吗？仅空文件夹可删除。',
                    okText: '删除'
                }).then(function (yes) {
                    if (!yes) return;
                    apiPost('folders.php', { action: 'delete', name: name })
                        .then(function () { toast('文件夹已删除'); setTimeout(function () { location.reload(); }, 450); })
                        .catch(function (err) { toast(err.message || '删除失败，文件夹可能不为空', 'error'); });
                });
            }
        });
    }

    function askCreateFolder() {
        promptDialog({
            title: '新建文件夹',
            label: '文件夹名称',
            placeholder: '例如 wallpaper',
            hint: '只允许字母、数字、下划线和短横线，最长 50 字符',
            okText: '创建'
        }).then(function (val) {
            if (val === null || val === '') return;
            apiPost('folders.php', { action: 'create', name: val })
                .then(function () { toast('文件夹已创建'); setTimeout(function () { location.reload(); }, 450); })
                .catch(function (err) { toast(err.message || '创建失败，名称可能已存在', 'error'); });
        });
    }

    /* ------------------------------------------------------------------ */
    /* 设置页                                                              */
    /* ------------------------------------------------------------------ */
    function initSettings() {
        $$('[data-regen-token]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                confirmDialog({
                    title: '重新生成 Token',
                    text: '重新生成后，旧 Token 将立即失效，使用旧 Token 的接口调用会失败。',
                    okText: '重新生成',
                    danger: false
                }).then(function (yes) {
                    if (!yes) return;
                    apiPost('settings.php', { action: 'generate_token' })
                        .then(function () { toast('API Token 已重新生成'); setTimeout(function () { location.reload(); }, 500); })
                        .catch(function (err) { toast(err.message || '生成失败', 'error'); });
                });
            });
        });
    }

    /* ------------------------------------------------------------------ */
    /* 通用：复制按钮 / 页面消息 / 图片错误兜底                             */
    /* ------------------------------------------------------------------ */
    function initCopyButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-copy]');
            if (!btn) return;
            var text = btn.dataset.copy;
            if (btn.dataset.copyTarget) {
                var target = $(btn.dataset.copyTarget);
                if (target) text = target.value || target.textContent;
            }
            copyText(text).then(function () { toast('已复制到剪贴板'); });
        });
    }

    function initPageMessage() {
        var msg = $('#page-msg');
        if (!msg) return;
        var text = msg.dataset.text || '';
        if (text) toast(text, msg.dataset.type || 'success');
    }

    function sweepBrokenImages() {
        $$('img').forEach(function (img) {
            if (img.complete && img.naturalWidth === 0) {
                var thumb = img.closest('.img-card__thumb');
                if (thumb) thumb.classList.add('img-error');
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* 全局 ESC                                                            */
    /* ------------------------------------------------------------------ */
    function initGlobalKeys() {
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;

            // 上传弹窗优先
            var upModal = document.querySelector('#upload-modal.shown, #upload-modal.active');
            if (upModal && !(lb && lb.classList.contains('active'))) {
                closeUploadModal();
                return;
            }

            if (lb && lb.classList.contains('active')) return; // 由预览自行处理

            var openConfirm = document.querySelector('.modal-overlay.shown');
            if (openConfirm) {
                if (typeof openConfirm.__ihCancel === 'function') openConfirm.__ihCancel();
                else closeLayer(openConfirm);
                return;
            }
            if (document.body.classList.contains('sidebar-open')) {
                document.body.classList.remove('sidebar-open');
            }
            closeMenus();
        });
    }

    /* ------------------------------------------------------------------ */
    /* 启动                                                                */
    /* ------------------------------------------------------------------ */
    function boot() {
        initTheme();
        initSidebar();
        initMenus();
        initImageGrid();
        initPreviewKeys();
        initUpload();
        initFolders();
        initSettings();
        initCopyButtons();
        initPageMessage();
        initGlobalKeys();
        sweepBrokenImages();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
