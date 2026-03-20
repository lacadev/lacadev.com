/**
 * LacaDev Dashboard Quick Search
 * Enqueued by DashboardWidgets::enqueueDashboardScripts()
 * Localized as `lacadevSearch` with {ajaxUrl, nonce}
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const input   = document.querySelector('.laca-quick-search-input');
        const results = document.querySelector('.laca-quick-search-results');

        if (!input || !results) return;

        let timer = null;

        input.addEventListener('input', function () {
            clearTimeout(timer);
            const q = input.value.trim();

            if (q.length < 2) {
                results.innerHTML = '';
                return;
            }

            results.innerHTML = '<div class="laca-quick-search-loading">⏳ Đang tìm...</div>';

            timer = setTimeout(function () {
                const data = new URLSearchParams({
                    action          : 'lacadev_quick_search',
                    nonce           : lacadevSearch.nonce,
                    search_keyword  : q,
                });

                fetch(lacadevSearch.ajaxUrl, {
                    method  : 'POST',
                    headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body    : data.toString(),
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success || !res.data.items.length) {
                        results.innerHTML = '<div class="laca-quick-search-empty">Không tìm thấy kết quả.</div>';
                        return;
                    }

                    results.innerHTML = res.data.items.map(function (item) {
                        return '<a href="' + item.edit_url + '" class="laca-quick-search-item">' +
                            '<span class="item-title">' + escHtml(item.title) + escHtml(item.status) + '</span>' +
                            '<span class="item-meta">' + escHtml(item.post_type) + ' · ' + escHtml(item.date) + '</span>' +
                        '</a>';
                    }).join('');
                })
                .catch(function () {
                    results.innerHTML = '<div class="laca-quick-search-empty">Lỗi kết nối.</div>';
                });
            }, 350);
        });

        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    });
}());
