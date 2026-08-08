<?php
/**
 * lib/layout_footer.php
 * -----------------------------------------------------------------
 * Penutup struktur HTML dari layout_header.php.
 *
 * Sidebar Toggle — State-Driven via CSS Class pada .app-shell:
 *   Desktop (≥ 992px) : toggle class .sidebar-collapsed  → CSS collapse via width
 *   Mobile  (< 992px) : toggle class .sidebar-open       → CSS slide via transform
 *
 * ID yang wajib ada di layout_header.php:
 *   id="btnSidebarToggle"  — tombol hamburger
 *   id="mainSidebar"       — <aside class="sidebar">
 *   id="appShell"          — <div class="app-shell">
 *   id="sidebarOverlay"    — div backdrop
 * -----------------------------------------------------------------
 */
?>
    </main>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    var toggleBtn = document.getElementById('btnSidebarToggle');
    var appShell  = document.getElementById('appShell');
    var overlay   = document.getElementById('sidebarOverlay');
    var LS_KEY    = 'simklinik_sidebar_collapsed';

    if (!toggleBtn || !appShell) {
        console.warn('[SIMKlinik] Sidebar toggle: elemen tidak ditemukan (#btnSidebarToggle / #appShell).');
        return;
    }

    function isMobile() {
        return window.innerWidth < 992;
    }

    // ── Desktop: collapse sidebar lewat CSS class .sidebar-collapsed ──────
    function toggleDesktop() {
        var collapsed = appShell.classList.toggle('sidebar-collapsed');
        localStorage.setItem(LS_KEY, collapsed ? 'true' : 'false');
    }

    // ── Mobile: slide sidebar in/out lewat CSS class .sidebar-open ────────
    function openMobile() {
        appShell.classList.add('sidebar-open');
        document.body.style.overflow = 'hidden';
    }

    function closeMobile() {
        appShell.classList.remove('sidebar-open');
        document.body.style.overflow = '';
    }

    // ── Restore state desktop dari localStorage ───────────────────────────
    function restoreDesktopState() {
        if (localStorage.getItem(LS_KEY) === 'true') {
            appShell.classList.add('sidebar-collapsed');
        } else {
            appShell.classList.remove('sidebar-collapsed');
        }
    }

    // ── Sinkronisasi saat resize melewati breakpoint ──────────────────────
    function onResize() {
        if (!isMobile()) {
            // Masuk mode desktop: hapus state mobile, restore state desktop
            closeMobile();
            restoreDesktopState();
        } else {
            // Masuk mode mobile: hapus state desktop
            appShell.classList.remove('sidebar-collapsed');
        }
    }

    // ── Event: Klik tombol hamburger ☰ ───────────────────────────────────
    toggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (isMobile()) {
            if (appShell.classList.contains('sidebar-open')) {
                closeMobile();
            } else {
                openMobile();
            }
        } else {
            toggleDesktop();
        }
    });

    // ── Event: Klik overlay backdrop → tutup sidebar ─────────────────────
    if (overlay) {
        overlay.addEventListener('click', function () {
            closeMobile();
        });
    }

    // ── Event: Klik di luar sidebar area (mobile) → tutup ────────────────
    document.addEventListener('click', function (e) {
        if (!isMobile()) return;
        if (!appShell.classList.contains('sidebar-open')) return;

        var sidebar = document.getElementById('mainSidebar');
        if (sidebar && !sidebar.contains(e.target) &&
            !toggleBtn.contains(e.target) &&
            e.target !== toggleBtn) {
            closeMobile();
        }
    });

    // ── Resize listener & init ────────────────────────────────────────────
    window.addEventListener('resize', onResize);
    onResize();
});
</script>
</body>
</html>
