<?php
$activeAdminPage = $activeAdminPage ?? '';
$adminNavigation = [
    [
        'section' => 'MENU UTAMA',
        'items' => [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'path' => 'admin/dashboard.php',
                'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>'
            ],
        ]
    ],
    [
        'section' => 'KATALOG & INVENTARIS',
        'items' => [
            [
                'key' => 'products',
                'label' => 'Manajemen Produk',
                'path' => 'admin/products.php',
                'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>'
            ],
            [
                'key' => 'categories',
                'label' => 'Kategori Produk',
                'path' => 'admin/categories.php',
                'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>'
            ],
        ]
    ],
    [
        'section' => 'TRANSAKSI & LAPORAN',
        'items' => [
            [
                'key' => 'transactions',
                'label' => 'Riwayat Transaksi',
                'path' => 'admin/transactions.php',
                'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>'
            ],
            [
                'key' => 'reports',
                'label' => 'Laporan Penjualan',
                'path' => 'admin/reports.php',
                'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>'
            ],
        ]
    ],
    [
        'section' => 'PENGATURAN & PENGGUNA',
        'items' => [
            [
                'key' => 'users',
                'label' => 'Kelola Pengguna',
                'path' => 'admin/users.php',
                'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>'
            ],
            [
                'key' => 'activities',
                'label' => 'Aktivitas Sistem',
                'path' => 'admin/activities.php',
                'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>'
            ],
        ]
    ]
];
?>

<aside class="modern-sidebar">
    <!-- BRAND LOGO -->
    <div class="sidebar-header">
        <a class="sidebar-brand-box" href="<?= e(appUrl('admin/dashboard.php')) ?>">
            <div class="sidebar-logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
            </div>
            <div class="sidebar-brand-text">
                <span class="brand-name">Tokoku</span>
                <span class="brand-badge">ADMIN CONSOLE</span>
            </div>
        </a>
    </div>

    <!-- QUICK STORE LINK -->
    <div class="sidebar-quick-store">
        <a href="<?= e(appUrl('customer/index.php')) ?>" target="_blank" class="btn-sidebar-view-shop">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="2" y1="12" x2="22" y2="12"></line>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
            </svg>
            <span>Buka Toko Online</span>
            <span class="ext-arrow">↗</span>
        </a>
    </div>

    <!-- NAV GROUPS -->
    <div class="sidebar-nav-container">
        <?php foreach ($adminNavigation as $navGroup): ?>
            <div class="nav-section-title"><?= e($navGroup['section']) ?></div>
            <nav class="sidebar-nav-list" aria-label="<?= e($navGroup['section']) ?>">
                <?php foreach ($navGroup['items'] as $item): ?>
                    <a
                        class="nav-link-modern <?= $activeAdminPage === $item['key'] ? 'is-active' : '' ?>"
                        href="<?= e(appUrl($item['path'])) ?>"
                        <?= $activeAdminPage === $item['key'] ? 'aria-current="page"' : '' ?>
                    >
                        <span class="nav-svg-icon"><?= $item['svg'] ?></span>
                        <span class="nav-text-label"><?= e($item['label']) ?></span>
                        <?php if ($activeAdminPage === $item['key']): ?>
                            <span class="nav-active-pill"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endforeach; ?>
    </div>

    <!-- SIDEBAR FOOTER & USER PROFILE -->
    <div class="sidebar-footer-profile">
        <div class="sidebar-profile-card">
            <div class="sidebar-user-avatar">
                <span><?= e(strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1))) ?></span>
            </div>
            <div class="sidebar-user-details">
                <strong class="sidebar-user-name"><?= e($_SESSION['name'] ?? 'Admin') ?></strong>
                <span class="sidebar-user-role">Super Admin</span>
            </div>
            <a href="<?= e(appUrl('index.php?logout=1')) ?>" class="btn-sidebar-logout" title="Keluar dari akun">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </a>
        </div>
    </div>
</aside>
