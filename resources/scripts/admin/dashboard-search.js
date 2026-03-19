/**
 * LacaDev Dashboard – Quick Search Widget
 * Config injected via wp_localize_script('lacadev-dashboard-search', 'lacadevSearch', { ajaxUrl, nonce })
 */
;(function () {
    'use strict';

    var cfg = window.lacadevSearch || {};
    var input   = document.querySelector('.laca-quick-search-input');
    var results = document.querySelector('.laca-quick-search-results');

    if (!input || !results) return;

    var timer    = null;
    var lastTerm = '';

    function renderItems(items) {
        results.innerHTML = '';
        if (!items || !items.length) {
            results.innerHTML = '<div class="laca-quick-search-empty">Không tìm thấy kết quả.</div>';
            return;
        }
        items.forEach(function (item) {
            var a = document.createElement('a');
            a.href = item.edit_url;
            a.className = 'laca-quick-search-item';
            a.target = '_blank';

            var t = document.createElement('span');
            t.className = 'item-title';
            t.textContent = item.title || '';

            var m = document.createElement('span');
            m.className = 'item-meta';
            m.textContent = (item.post_type || '') + (item.status || '') + ' · ' + (item.date || '');

            a.appendChild(t);
            a.appendChild(m);
            results.appendChild(a);
        });
    }

    function doSearch() {
        var term = input.value.trim();
        if (term === lastTerm) return;
        lastTerm = term;

        if (term.length < 2) {
            results.innerHTML = '<div class="laca-quick-search-empty">Nhập ít nhất 2 ký tự để tìm kiếm.</div>';
            return;
        }

        results.innerHTML = '<div class="laca-quick-search-loading">Đang tìm...</div>';

        var fd = new FormData();
        fd.append('action', 'lacadev_quick_search');
        fd.append('nonce', cfg.nonce || '');
        fd.append('search_keyword', term);

        fetch(cfg.ajaxUrl || ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.data.items) {
                    renderItems(data.data.items);
                } else {
                    results.innerHTML = '<div class="laca-quick-search-empty">' +
                        (data.data && data.data.message ? data.data.message : 'Không tìm thấy kết quả.') + '</div>';
                }
            })
            .catch(function () {
                results.innerHTML = '<div class="laca-quick-search-empty">Lỗi tìm kiếm. Vui lòng thử lại.</div>';
            });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        if (!input.value.trim()) {
            lastTerm = '';
            results.innerHTML = '<div class="laca-quick-search-empty">Nhập từ khóa theo tiêu đề để tìm...</div>';
            return;
        }
        timer = setTimeout(doSearch, 300);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { clearTimeout(timer); doSearch(); }
    });

    results.innerHTML = '<div class="laca-quick-search-empty">Nhập từ khóa theo tiêu đề để tìm...</div>';
})();
