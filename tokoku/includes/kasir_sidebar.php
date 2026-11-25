<?php
/**
 * Modern Kasir Sidebar
 * Usage: $kasirActive = 'dashboard' | 'checkout' | 'transactions' | 'stock';
 *        require __DIR__ . '/kasir_sidebar.php';
 */
$kasirActive = $kasirActive ?? 'dashboard';
$userName = $_SESSION['name'] ?? 'Petugas Kasir';
$userInitial = strtoupper(substr($userName, 0, 1));
?>
<aside class="modern-sidebar">
    <div class="sidebar-header">
        <a class="sidebar-brand-box" href="<?= e(appUrl('kasir/dashboard.php')) ?>">
            <div class="sidebar-logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <line x1="2" y1="10" x2="22" y2="10"></line>
                </svg>
            </div>
            <div class="sidebar-brand-text">
                <span class="brand-name">Tokoku</span>
                <span class="brand-badge">TERMINAL KASIR (POS)</span>
            </div>
        </a>
    </div>

    <div class="sidebar-quick-store">
        <a href="<?= e(appUrl('kasir/checkout.php')) ?>" class="btn-sidebar-view-shop" style="background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3); color: #A7F3D0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #34D399;">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>+ Transaksi Baru</span>
        </a>
    </div>

    <div class="sidebar-nav-container">
        <div class="nav-section-title">OPERASIONAL KASIR</div>
        <nav class="sidebar-nav-list" aria-label="Operasional kasir">
            <a class="nav-link-modern<?= $kasirActive === 'dashboard' ? ' is-active' : '' ?>" href="<?= e(appUrl('kasir/dashboard.php')) ?>"<?= $kasirActive === 'dashboard' ? ' aria-current="page"' : '' ?>>
                <span class="nav-svg-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </span>
                <span class="nav-text-label">Dashboard Kasir</span>
                <?php if ($kasirActive === 'dashboard'): ?><span class="nav-active-pill"></span><?php endif; ?>
            </a>

            <a class="nav-link-modern<?= $kasirActive === 'checkout' ? ' is-active' : '' ?>" href="<?= e(appUrl('kasir/checkout.php')) ?>"<?= $kasirActive === 'checkout' ? ' aria-current="page"' : '' ?>>
                <span class="nav-svg-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                </span>
                <span class="nav-text-label">Kasir POS Baru</span>
                <?php if ($kasirActive === 'checkout'): ?><span class="nav-active-pill"></span><?php endif; ?>
            </a>

            <a class="nav-link-modern<?= $kasirActive === 'transactions' ? ' is-active' : '' ?>" href="<?= e(appUrl('kasir/transactions.php')) ?>"<?= $kasirActive === 'transactions' ? ' aria-current="page"' : '' ?>>
                <span class="nav-svg-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                </span>
                <span class="nav-text-label">Transaksi Hari Ini</span>
                <?php if ($kasirActive === 'transactions'): ?><span class="nav-active-pill"></span><?php endif; ?>
            </a>

            <a class="nav-link-modern<?= $kasirActive === 'stock' ? ' is-active' : '' ?>" href="<?= e(appUrl('kasir/stock.php')) ?>"<?= $kasirActive === 'stock' ? ' aria-current="page"' : '' ?>>
                <span class="nav-svg-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </span>
                <span class="nav-text-label">Pantau Stok</span>
                <?php if ($kasirActive === 'stock'): ?><span class="nav-active-pill"></span><?php endif; ?>
            </a>
        </nav>

        <div class="nav-section-title">PUBLIK</div>
        <nav class="sidebar-nav-list">
            <a class="nav-link-modern" href="<?= e(appUrl('customer/index.php')) ?>" target="_blank">
                <span class="nav-svg-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                </span>
                <span class="nav-text-label">Lihat Katalog Toko</span>
                <span class="ext-arrow">↗</span>
            </a>
        </nav>
    </div>

    <div class="sidebar-footer-profile">
        <div class="sidebar-profile-card">
            <div class="sidebar-user-avatar" style="background: linear-gradient(135deg, #059669 0%, #10B981 100%);">
                <span><?= e($userInitial) ?></span>
            </div>
            <div class="sidebar-user-details">
                <strong class="sidebar-user-name"><?= e($userName) ?></strong>
                <span class="sidebar-user-role">Petugas Operasional</span>
            </div>
            <a href="<?= e(appUrl('index.php?logout=1')) ?>" class="btn-sidebar-logout" title="Keluar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </a>
        </div>
    </div>
</aside>
