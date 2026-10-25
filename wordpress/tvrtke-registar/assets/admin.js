/* TVRTKE admin interactions: no remote scripts, tracking or global selectors. */
(function () {
    'use strict';
    function ready() {
        var root = document.querySelector('.tvrtke-admin');
        if (!root) return;
        var copy = root.querySelector('[data-tvrtke-copy]');
        if (copy) copy.addEventListener('click', function () {
            var content = root.querySelector('.tvrtke-shortcode code');
            var message = root.querySelector('[data-tvrtke-copy-status]');
            if (!content) return;
            var text = content.textContent.trim();
            var success = function () {
                if (message) message.textContent = 'Kratki kod je kopiran.';
            };
            var fallback = function () {
                if (message) message.textContent = 'Označite kratki kod i kopirajte ga ručno.';
                var selection = window.getSelection();
                if (selection) {
                    var range = document.createRange();
                    range.selectNodeContents(content);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(success, fallback);
            } else {
                fallback();
            }
        });

        // Target the actual section rather than leaving "API" highlighted
        // after keyboard or pointer navigation within the settings page.
        var tabs = root.querySelectorAll('.tvrtke-section-tabs a[href^="#tvrtke-"]');
        function updateActive() {
            var current = window.location.hash || '#tvrtke-api';
            tabs.forEach(function (link) {
                var selected = link.getAttribute('href') === current;
                link.classList.toggle('is-current', selected);
                if (selected) link.setAttribute('aria-current', 'location');
                else link.removeAttribute('aria-current');
            });
        }
        tabs.forEach(function (link) { link.addEventListener('click', function () {
            tabs.forEach(function (other) {
                other.classList.remove('is-current');
                other.removeAttribute('aria-current');
            });
            link.classList.add('is-current');
            link.setAttribute('aria-current', 'location');
        }); });
        window.addEventListener('hashchange', updateActive);
        updateActive();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
    else ready();
}());
