/**
 * init.js — Application initialization scripts.
 *
 * Extracted from inline <script> blocks to enable strict CSP with nonces.
 * This file handles: dark mode, font size, cookie consent, service worker,
 * jQuery/Bootstrap conflict resolution, and tooltip initialization.
 */

// ── Dark Mode (system preference + manual toggle + localStorage) ──

function toggleDarkMode() {
    var isDark = document.documentElement.classList.toggle('dark');
    try { localStorage.setItem('darkMode', isDark ? '1' : '0'); } catch(e) {}
    jQuery('#darkModeIcon').toggleClass('fa-moon fa-sun');
}

(function() {
    try {
        var stored = localStorage.getItem('darkMode');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var shouldDark = stored !== null ? stored === '1' : prefersDark;
        if (shouldDark) {
            document.documentElement.classList.add('dark');
            setTimeout(function(){ jQuery('#darkModeIcon').removeClass('fa-moon').addClass('fa-sun'); }, 0);
        }
    } catch(e) {}
    try {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (localStorage.getItem('darkMode') !== null) return;
            if (e.matches) { document.documentElement.classList.add('dark'); jQuery('#darkModeIcon').removeClass('fa-moon').addClass('fa-sun'); }
            else { document.documentElement.classList.remove('dark'); jQuery('#darkModeIcon').removeClass('fa-sun').addClass('fa-moon'); }
        });
    } catch(e) {}
})();

// ── Font Size (75%–150%, persisted in localStorage) ──

var fontSize = parseInt(localStorage.getItem('fontSize') || '100');
if (fontSize !== 100) document.documentElement.style.fontSize = fontSize + '%';

function changeFontSize(dir) {
    fontSize = Math.max(75, Math.min(150, fontSize + dir * 10));
    document.documentElement.style.fontSize = fontSize + '%';
    try { localStorage.setItem('fontSize', fontSize); } catch(e) {}
}

// ── Cookie Consent (accept → hide, reject → about:blank) ──

(function() {
    var banner = document.getElementById('cookieBanner');
    if (!banner) return;
    try {
        var consent = localStorage.getItem('cookieConsent');
        if (consent === null) banner.style.display = 'block';
        else if (consent === 'rejected') window.location.href = 'about:blank';
    } catch(e) { banner.style.display = 'block'; }
})();

function acceptCookies() {
    try { localStorage.setItem('cookieConsent', 'accepted'); } catch(e) {}
    document.getElementById('cookieBanner').style.display = 'none';
}

function rejectCookies() {
    try { localStorage.setItem('cookieConsent', 'rejected'); } catch(e) {}
    window.location.href = 'about:blank';
}

// ── Service Worker Registration (PWA offline support) ──

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function() {});
}

// ── jQuery Ready: Bootstrap/jQuery UI conflicts + tooltips ──

jQuery(document).ready(function() {
    if ($.fn.button && $.fn.button.noConflict) { var bb = $.fn.button.noConflict(); $.fn.bootstrapBtn = bb; }
    if ($.fn.tooltip && $.fn.tooltip.noConflict) { var bt = $.fn.tooltip.noConflict(); $.fn.bsTooltip = bt; }
    try { jQuery('[title]').not('[title=""]').not('.ui-dialog-titlebar *').tooltip(); } catch(e) {}
});
