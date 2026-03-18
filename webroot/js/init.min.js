/**
 * Guidevera Init — Runs before pages.js.
 * Handles: dark mode, font size, cookie consent, service worker.
 */

// ── Dark Mode (immediate — no flash) ──
(function(){try{var t=localStorage.getItem('theme');if(t==='dark'||(t!=='light'&&matchMedia('(prefers-color-scheme:dark)').matches))document.documentElement.classList.add('dark')}catch(e){}})();

// ── Font Size (immediate) ──
(function(){try{var s=localStorage.getItem('fontSize');if(s){document.documentElement.style.fontSize=s}}catch(e){}})();

function toggleDarkMode() {
    var isDark = document.documentElement.classList.toggle('dark');
    try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    var icon = document.getElementById('darkModeIcon');
    if (icon) { icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon'; }
}

function changeFontSize(delta) {
    var current = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
    var newSize = Math.max(12, Math.min(24, current + delta));
    document.documentElement.style.fontSize = newSize + 'px';
    try { localStorage.setItem('fontSize', newSize + 'px'); } catch(e) {}
}

function toggleSidebar() {
    document.querySelector('.app-sidebar')?.classList.toggle('mobile-open');
    document.querySelector('.sidebar-backdrop')?.classList.toggle('open');
}

function closeSidebar() {
    document.querySelector('.app-sidebar')?.classList.remove('mobile-open');
    document.querySelector('.sidebar-backdrop')?.classList.remove('open');
}

function load_module(url, target) {
    if (target === 'blank') { window.open(url, '_blank'); return; }
    window.location.href = url;
}

function add_text_direction(dir) {
    document.documentElement.setAttribute('dir', dir);
}

// ── Cookie Consent ──
(function() {
    var banner = document.getElementById('cookieBanner');
    if (!banner) return;

    var cookieIcon = document.getElementById('cookieSettingsIcon');
    var consent;
    try { consent = localStorage.getItem('cookieConsent'); } catch(e) { consent = null; }

    if (consent === 'accepted') {
        // Already accepted — show cookie icon, hide banner
        banner.style.display = 'none';
        if (cookieIcon) cookieIcon.style.display = 'flex';
    } else {
        // Not accepted (null or 'rejected') — show banner, hide icon
        banner.style.display = 'block';
        if (cookieIcon) cookieIcon.style.display = 'none';
    }
})();

function acceptCookies() {
    try { localStorage.setItem('cookieConsent', 'accepted'); } catch(e) {}
    var banner = document.getElementById('cookieBanner');
    var icon = document.getElementById('cookieSettingsIcon');
    if (banner) banner.style.display = 'none';
    if (icon) icon.style.display = 'flex';
}

function rejectCookies() {
    try { localStorage.removeItem('cookieConsent'); } catch(e) {}
    window.location.href = 'about:blank';
}

function showCookieBanner() {
    var banner = document.getElementById('cookieBanner');
    var icon = document.getElementById('cookieSettingsIcon');
    if (banner) banner.style.display = 'block';
    if (icon) icon.style.display = 'none';
}

// ── Service Worker ──
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function(){});
}

// ── Sidebar Resize ──
(function() {
    // Restore saved width from cookie
    var saved = document.cookie.replace(/(?:(?:^|.*;\s*)sidebarWidth\s*=\s*([^;]*).*$)|^.*$/, '$1');
    if (saved) {
        document.documentElement.style.setProperty('--sidebar-width', saved);
    }

    document.addEventListener('DOMContentLoaded', function() {
        var handle = document.getElementById('sidebarResizeHandle');
        var sidebar = document.getElementById('sidebar');
        if (!handle || !sidebar) return;

        var startX, startW;
        handle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            startX = e.clientX;
            startW = sidebar.offsetWidth;
            handle.classList.add('active');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            function onMove(e) {
                var w = Math.max(192, Math.min(640, startW + (e.clientX - startX)));
                sidebar.style.width = w + 'px';
                document.documentElement.style.setProperty('--sidebar-width', w + 'px');
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                handle.classList.remove('active');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                // Save to cookie (1 year)
                var w = sidebar.offsetWidth + 'px';
                document.cookie = 'sidebarWidth=' + w + ';path=/;max-age=31536000;SameSite=Lax';
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    });
})();
