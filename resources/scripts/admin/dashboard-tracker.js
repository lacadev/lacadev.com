/**
 * LacaDev Dashboard – Content Tracker Tab Switcher & Pagination
 * Pure JS, no external config needed.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // ── Tab switching ─────────────────────────────────────────────
    var select   = document.querySelector('.laca-report-select');
    var contents = document.querySelectorAll('.laca-tab-content');

    if (select) {
        select.addEventListener('change', function () {
            contents.forEach(function (c) { c.classList.remove('active'); });
            var target = document.getElementById(this.value);
            if (target) target.classList.add('active');
        });
    }

    // ── Per-tab pagination ────────────────────────────────────────
    document.querySelectorAll('.laca-tab-content').forEach(function (wrap) {
        var perPage  = parseInt(wrap.getAttribute('data-per-page') || '5', 10);
        var curPage  = 1;
        var nextBtn  = wrap.querySelector('.next');
        var prevBtn  = wrap.querySelector('.prev');
        var pageInfo = wrap.querySelector('.laca-page-info');
        var items    = wrap.querySelectorAll('.laca-list-item');

        function update() {
            var maxPage = Math.ceil(items.length / perPage);
            var start   = (curPage - 1) * perPage;
            var end     = start + perPage;

            items.forEach(function (item, idx) {
                item.style.setProperty('display', (idx >= start && idx < end) ? 'flex' : 'none', 'important');
            });

            if (prevBtn) prevBtn.disabled = (curPage <= 1);
            if (nextBtn) nextBtn.disabled = (curPage >= maxPage);
            if (pageInfo) pageInfo.innerText = 'Trang ' + curPage + ' / ' + (maxPage || 1);
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (curPage < Math.ceil(items.length / perPage)) { curPage++; update(); }
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (curPage > 1) { curPage--; update(); }
            });
        }

        update();
    });
});
