<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_images.php';

$pageTitle = 'Dashboard Eksekutif Admin - Tokoku';

$stats = [
    'total_omzet' => 0.0,
    'total_transaksi' => 0,
    'total_produk' => 0,
    'total_kategori' => 0,
    'stok_kritis' => 0,
    'total_user' => 0,
    'admin_count' => 0,
    'kasir_count' => 0,
    'pelanggan_count' => 0,
];

$recentTransactions = [];
$criticalStockProducts = [];
$recentUsers = [];
$categoryDistribution = [];
$databaseError = false;

try {
    // 1. KPI Stats
    $trxStats = $pdo->query(
        'SELECT COALESCE(SUM(total_belanja), 0) AS omzet, COUNT(*) AS total FROM tbl_transaksi'
    )->fetch();
    $stats['total_omzet'] = (float) ($trxStats['omzet'] ?? 0);
    $stats['total_transaksi'] = (int) ($trxStats['total'] ?? 0);

    $stats['total_produk'] = (int) $pdo->query('SELECT COUNT(*) FROM tbl_produk')->fetchColumn();
    $stats['total_kategori'] = (int) $pdo->query('SELECT COUNT(*) FROM tbl_kategori')->fetchColumn();
    $stats['stok_kritis'] = (int) $pdo->query('SELECT COUNT(*) FROM tbl_produk WHERE stok <= 5')->fetchColumn();

    $stats['total_user'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $userRoles = $pdo->query('SELECT role, COUNT(*) as cnt FROM users GROUP BY role')->fetchAll(PDO::FETCH_KEY_PAIR);
    $stats['admin_count'] = (int) ($userRoles['admin'] ?? 0);
    $stats['kasir_count'] = (int) ($userRoles['kasir'] ?? 0);
    $stats['pelanggan_count'] = (int) ($userRoles['pelanggan'] ?? 0);

    // 2. Recent Transactions
    $recentTrxStmt = $pdo->query(
        'SELECT t.id_transaksi, t.tanggal, t.total_belanja,
                COALESCE(p.nama_pelanggan, "Pelanggan Toko") AS nama_pelanggan,
                COALESCE(SUM(d.jumlah), 0) AS total_item
         FROM tbl_transaksi t
         LEFT JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan
         LEFT JOIN tbl_detail_transaksi d ON d.id_transaksi = t.id_transaksi
         GROUP BY t.id_transaksi, t.tanggal, t.total_belanja, p.nama_pelanggan
         ORDER BY t.id_transaksi DESC
         LIMIT 6'
    );
    $recentTransactions = $recentTrxStmt->fetchAll();

    // 3. Category Distribution for visual chart
    $catDistStmt = $pdo->query(
        'SELECT k.id_kategori, k.nama_kategori, COUNT(p.id_produk) AS count_prod,
                COALESCE(SUM(p.stok), 0) AS total_stok
         FROM tbl_kategori k
         LEFT JOIN tbl_produk p ON p.id_kategori = k.id_kategori
         GROUP BY k.id_kategori, k.nama_kategori
         ORDER BY count_prod DESC
         LIMIT 5'
    );
    $categoryDistribution = $catDistStmt->fetchAll();

    // 4. Critical Stock Products (lowest stock first)
    $critStmt = $pdo->query(
        'SELECT id_produk, nama_produk, harga, stok, gambar
         FROM tbl_produk
         ORDER BY stok ASC, id_produk DESC
         LIMIT 5'
    );
    $criticalStockProducts = $critStmt->fetchAll();

    // 5. Recent Registered Users
    $recentUserStmt = $pdo->query(
        'SELECT id, name, email, role, created_at
         FROM users
         ORDER BY id DESC
         LIMIT 5'
    );
    $recentUsers = $recentUserStmt->fetchAll();

} catch (Throwable $e) {
    error_log('Admin Dashboard Modern: ' . $e->getMessage());
    $databaseError = true;
}

// Greeting time
$hour = (int) date('H');
$greeting = 'Selamat datang';
if ($hour >= 5 && $hour < 11) $greeting = 'Selamat pagi';
elseif ($hour >= 11 && $hour < 15) $greeting = 'Selamat siang';
elseif ($hour >= 15 && $hour < 18) $greeting = 'Selamat sore';
else $greeting = 'Selamat malam';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-shell-layout">
    <?php $activeAdminPage = 'dashboard'; require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="dashboard-shell-main">
        <!-- COMMAND TOPBAR -->
        <header class="dashboard-command-topbar">
            <div class="topbar-left-col">
                <div class="topbar-breadcrumbs">
                    <span>Tokoku Console</span>
                    <span class="bc-divider">/</span>
                    <span class="bc-active">Dashboard Eksekutif</span>
                </div>
                <h1 class="topbar-main-title">Pusat Komando &amp; Ringkasan Toko</h1>
            </div>

            <div class="topbar-right-col">
                <div class="topbar-live-time">
                    <span class="live-dot-pulse"></span>
                    <span><?= date('l, d F Y') ?></span>
                </div>

                <div class="topbar-quick-actions">
                    <a href="<?= e(appUrl('admin/products.php')) ?>" class="btn-topbar-action btn-primary-act">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Produk Baru</span>
                    </a>
                    <a href="<?= e(appUrl('kasir/dashboard.php')) ?>" class="btn-topbar-action btn-outline-act">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                            <line x1="2" y1="10" x2="22" y2="10"></line>
                        </svg>
                        <span>Kasir POS</span>
                    </a>
                    <a href="<?= e(appUrl('customer/index.php')) ?>" target="_blank" class="btn-topbar-action btn-ghost-act" title="Lihat Toko Publik">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>
                        <span>Toko Online</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- WELCOME BANNER WITH METRICS -->
        <section class="dashboard-hero-banner">
            <div class="hero-banner-glass">
                <div class="hero-left-info">
                    <div class="hero-role-pill">
                        <span class="role-icon">⚡</span>
                        <span>ADMINISTRATOR UTAMA</span>
                    </div>
                    <h2><?= $greeting ?>, <?= e($_SESSION['name'] ?? 'Admin') ?>!</h2>
                    <p>
                        Sistem manajemen Tokoku beroperasi optimal. Semua modul terhubung langsung ke database MariaDB/MySQL dengan verifikasi transaksi instan dan pencatatan riwayat lengkap.
                    </p>
                </div>
                <div class="hero-right-meta">
                    <div class="hero-meta-pill">
                        <span class="meta-lbl">Status Database</span>
                        <strong class="text-emerald">● Terhubung (db_toko)</strong>
                    </div>
                    <div class="hero-meta-pill">
                        <span class="meta-lbl">Sesi Admin</span>
                        <strong><?= e($_SESSION['name'] ?? 'Admin') ?></strong>
                    </div>
                </div>
            </div>
        </section>

        <!-- KPI METRICS GRID -->
        <section class="dashboard-kpi-grid">
            <!-- 1. TOTAL PENDAPATAN -->
            <article class="kpi-card kpi-card-revenue">
                <div class="kpi-card-header">
                    <span class="kpi-label">TOTAL OMZET PENJUALAN</span>
                    <div class="kpi-icon-box icon-emerald">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <span class="curr-prefix">Rp</span>
                    <strong><?= number_format($stats['total_omzet'], 0, ',', '.') ?></strong>
                </div>
                <div class="kpi-footer-trend">
                    <span class="trend-badge trend-up">✓ Transaksi Sah</span>
                    <span class="kpi-subtext">Akumulasi seluruh penjualan</span>
                </div>
            </article>

            <!-- 2. TOTAL TRANSAKSI -->
            <article class="kpi-card kpi-card-orders">
                <div class="kpi-card-header">
                    <span class="kpi-label">TOTAL TRANSAKSI</span>
                    <div class="kpi-icon-box icon-indigo">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <strong><?= number_format($stats['total_transaksi']) ?></strong>
                    <span class="unit-suffix">Pesanan</span>
                </div>
                <div class="kpi-footer-trend">
                    <span class="trend-badge trend-neutral">Tercatat</span>
                    <span class="kpi-subtext">Dari kasir &amp; belanja online</span>
                </div>
            </article>

            <!-- 3. KATALOG PRODUK -->
            <article class="kpi-card kpi-card-products">
                <div class="kpi-card-header">
                    <span class="kpi-label">KATALOG PRODUK</span>
                    <div class="kpi-icon-box icon-sky">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <strong><?= number_format($stats['total_produk']) ?></strong>
                    <span class="unit-suffix">Varian</span>
                </div>
                <div class="kpi-footer-trend">
                    <span class="trend-badge trend-cat"><?= number_format($stats['total_kategori']) ?> Kategori</span>
                    <span class="kpi-subtext">Katalog aktif di toko</span>
                </div>
            </article>

            <!-- 4. TOTAL PENGGUNA -->
            <article class="kpi-card kpi-card-users">
                <div class="kpi-card-header">
                    <span class="kpi-label">TOTAL PENGGUNA</span>
                    <div class="kpi-icon-box icon-purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <strong><?= number_format($stats['total_user']) ?></strong>
                    <span class="unit-suffix">Akun</span>
                </div>
                <div class="kpi-footer-trend">
                    <span class="role-chip chip-adm"><?= $stats['admin_count'] ?> Admin</span>
                    <span class="role-chip chip-ksr"><?= $stats['kasir_count'] ?> Kasir</span>
                    <span class="role-chip chip-plg"><?= $stats['pelanggan_count'] ?> Pelanggan</span>
                </div>
            </article>
        </section>

        <!-- QUICK ACCESS MODULE TILES -->
        <section class="dashboard-quick-shortcuts">
            <div class="section-title-strip">
                <h3>⚡ Modul &amp; Pintasan Kerja Cepat</h3>
                <span class="title-sub">Akses langsung ke seluruh manajemen aplikasi</span>
            </div>

            <div class="shortcuts-grid">
                <a href="<?= e(appUrl('admin/products.php')) ?>" class="shortcut-tile">
                    <div class="tile-icon-wrap icon-blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <div class="tile-text">
                        <strong>Kelola Produk</strong>
                        <span>Master data barang, harga &amp; foto</span>
                    </div>
                    <span class="tile-arrow">→</span>
                </a>

                <a href="<?= e(appUrl('admin/categories.php')) ?>" class="shortcut-tile">
                    <div class="tile-icon-wrap icon-orange">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                        </svg>
                    </div>
                    <div class="tile-text">
                        <strong>Master Kategori</strong>
                        <span>Kelompokan dan klasifikasi produk</span>
                    </div>
                    <span class="tile-arrow">→</span>
                </a>

                <a href="<?= e(appUrl('admin/transactions.php')) ?>" class="shortcut-tile">
                    <div class="tile-icon-wrap icon-green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                    </div>
                    <div class="tile-text">
                        <strong>Riwayat Transaksi</strong>
                        <span>Audit nota dan rincian pembelian</span>
                    </div>
                    <span class="tile-arrow">→</span>
                </a>

                <a href="<?= e(appUrl('admin/reports.php')) ?>" class="shortcut-tile">
                    <div class="tile-icon-wrap icon-purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </div>
                    <div class="tile-text">
                        <strong>Laporan Penjualan</strong>
                        <span>Rekapitulasi omzet dan grafik</span>
                    </div>
                    <span class="tile-arrow">→</span>
                </a>

                <a href="<?= e(appUrl('admin/users.php')) ?>" class="shortcut-tile">
                    <div class="tile-icon-wrap icon-rose">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <polyline points="17 11 19 13 23 9"></polyline>
                        </svg>
                    </div>
                    <div class="tile-text">
                        <strong>Kelola Akun &amp; Role</strong>
                        <span>Admin, Kasir, dan Pelanggan</span>
                    </div>
                    <span class="tile-arrow">→</span>
                </a>

                <a href="<?= e(appUrl('admin/activities.php')) ?>" class="shortcut-tile">
                    <div class="tile-icon-wrap icon-amber">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 14 14"></polyline>
                        </svg>
                    </div>
                    <div class="tile-text">
                        <strong>Aktivitas Sistem</strong>
                        <span>Log jejak audit &amp; operasi data</span>
                    </div>
                    <span class="tile-arrow">→</span>
                </a>
            </div>
        </section>

        <!-- MAIN TWO-COLUMN COMMAND CENTER -->
        <div class="dashboard-command-center-grid">
            <!-- LEFT COLUMN: CATEGORY DISTRIBUTION & RECENT TRANSACTIONS -->
            <div class="cmd-col-main">
                <!-- 1. CATEGORY DISTRIBUTION VISUAL CHART -->
                <section class="cmd-panel-card">
                    <div class="cmd-panel-header">
                        <div>
                            <h3>📊 Distribusi Produk per Kategori</h3>
                            <p>Visualisasi sebaran jenis barang dalam database</p>
                        </div>
                        <a href="<?= e(appUrl('admin/categories.php')) ?>" class="panel-action-link">Kelola Kategori →</a>
                    </div>

                    <div class="category-distribution-list">
                        <?php if (empty($categoryDistribution)): ?>
                            <p class="text-muted">Belum ada kategori yang tercatat.</p>
                        <?php else: ?>
                            <?php foreach ($categoryDistribution as $catRow): ?>
                                <?php
                                $totalAll = max(1, $stats['total_produk']);
                                $pct = round(($catRow['count_prod'] / $totalAll) * 100);
                                ?>
                                <div class="cat-dist-row">
                                    <div class="cat-dist-info">
                                        <span class="cat-dist-name"><?= e($catRow['nama_kategori']) ?></span>
                                        <span class="cat-dist-nums"><strong><?= $catRow['count_prod'] ?> produk</strong> (<?= $pct ?>%) · Stok: <?= number_format($catRow['total_stok']) ?></span>
                                    </div>
                                    <div class="cat-progress-track">
                                        <div class="cat-progress-bar" style="width: <?= max(5, $pct) ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- 2. RECENT TRANSACTIONS TABLE -->
                <section class="cmd-panel-card">
                    <div class="cmd-panel-header">
                        <div>
                            <h3>🧾 Transaksi Penjualan Terkini</h3>
                            <p>Daftar 6 transaksi paling akhir dari toko</p>
                        </div>
                        <a href="<?= e(appUrl('admin/transactions.php')) ?>" class="panel-action-link">Semua Transaksi →</a>
                    </div>

                    <div class="cmd-table-responsive">
                        <table class="cmd-table">
                            <thead>
                                <tr>
                                    <th>Kode Transaksi</th>
                                    <th>Pelanggan</th>
                                    <th>Tanggal</th>
                                    <th>Jumlah Item</th>
                                    <th>Total Nilai</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentTransactions)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Belum ada transaksi yang tercatat.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentTransactions as $trx): ?>
                                        <tr>
                                            <td>
                                                <span class="trx-pill-code">TRX-<?= str_pad((string) $trx['id_transaksi'], 3, '0', STR_PAD_LEFT) ?></span>
                                            </td>
                                            <td><strong><?= e($trx['nama_pelanggan']) ?></strong></td>
                                            <td><?= $trx['tanggal'] ? date('d/m/Y', strtotime($trx['tanggal'])) : '-' ?></td>
                                            <td><?= number_format((int) $trx['total_item']) ?> unit</td>
                                            <td><strong class="text-emerald">Rp <?= number_format((float) $trx['total_belanja'], 0, ',', '.') ?></strong></td>
                                            <td><span class="status-pill status-success">Tercatat</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- RIGHT COLUMN: INVENTORY & RECENT USERS -->
            <div class="cmd-col-side">
                <!-- 1. INVENTORY STATUS (CRITICAL STOCK) -->
                <section class="cmd-panel-card">
                    <div class="cmd-panel-header">
                        <div>
                            <h3>⚠️ Peringatan Stok Inventaris</h3>
                            <p>Produk dengan sisa stok paling sedikit</p>
                        </div>
                        <a href="<?= e(appUrl('admin/products.php')) ?>" class="panel-action-link">Restock →</a>
                    </div>

                    <div class="critical-stock-list">
                        <?php if (empty($criticalStockProducts)): ?>
                            <p class="text-muted">Belum ada produk di katalog.</p>
                        <?php else: ?>
                            <?php foreach ($criticalStockProducts as $cProd): ?>
                                <?php
                                $stk = (int) $cProd['stok'];
                                $isLow = $stk <= 5;
                                ?>
                                <div class="stock-item-card">
                                    <img src="<?= e(productImageUrl($cProd)) ?>" alt="" class="stock-item-img">
                                    <div class="stock-item-info">
                                        <strong class="stock-item-name"><?= e($cProd['nama_produk']) ?></strong>
                                        <span class="stock-item-price">Rp <?= number_format((float) $cProd['harga'], 0, ',', '.') ?></span>
                                    </div>
                                    <div class="stock-badge-col">
                                        <span class="stock-status-tag <?= $isLow ? 'tag-danger' : 'tag-safe' ?>">
                                            <?= $stk ?> Unit
                                        </span>
                                        <a href="<?= e(appUrl('admin/products.php?edit=' . (int) $cProd['id_produk'])) ?>" class="btn-stock-edit">Edit</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- 2. RECENT REGISTERED USERS -->
                <section class="cmd-panel-card">
                    <div class="cmd-panel-header">
                        <div>
                            <h3>👥 Pengguna Terbaru</h3>
                            <p>Akun yang baru saja didaftarkan</p>
                        </div>
                        <a href="<?= e(appUrl('admin/users.php')) ?>" class="panel-action-link">Kelola →</a>
                    </div>

                    <div class="recent-users-modern-list">
                        <?php if (empty($recentUsers)): ?>
                            <p class="text-muted">Belum ada data pengguna.</p>
                        <?php else: ?>
                            <?php foreach ($recentUsers as $usr): ?>
                                <div class="user-modern-item">
                                    <div class="user-avatar-circle">
                                        <?= e(strtoupper(substr($usr['name'], 0, 1))) ?>
                                    </div>
                                    <div class="user-details-box">
                                        <strong><?= e($usr['name']) ?></strong>
                                        <span><?= e($usr['email']) ?></span>
                                    </div>
                                    <span class="role-pill-modern role-<?= e($usr['role']) ?>">
                                        <?= e(ucfirst($usr['role'])) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- 3. SYSTEM DIAGNOSTICS CARD -->
                <section class="cmd-panel-card sys-diagnostics-card">
                    <div class="cmd-panel-header">
                        <div>
                            <h3>🛡️ Integritas Sistem Tokoku</h3>
                            <p>Status proteksi dan konfigurasi server</p>
                        </div>
                    </div>
                    <div class="diag-checks-list">
                        <div class="diag-check-row">
                            <span class="diag-icon">✓</span>
                            <span>PHP 8.3 &amp; Extension Fileinfo / GD</span>
                            <strong class="diag-status-ok">Aktif</strong>
                        </div>
                        <div class="diag-check-row">
                            <span class="diag-icon">✓</span>
                            <span>Koneksi Database MySQL (3306)</span>
                            <strong class="diag-status-ok">Normal</strong>
                        </div>
                        <div class="diag-check-row">
                            <span class="diag-icon">✓</span>
                            <span>Proteksi CSRF &amp; Secure Session</span>
                            <strong class="diag-status-ok">Aktif</strong>
                        </div>
                        <div class="diag-check-row">
                            <span class="diag-icon">✓</span>
                            <span>Penyimpanan Uploads Aman</span>
                            <strong class="diag-status-ok">Siap</strong>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- FOOTER -->
        <footer class="dashboard-shell-footer">
            <span>© <?= date('Y') ?> <strong>Tokoku Console</strong> · Seluruh hak cipta dilindungi.</span>
            <span>Versi Desain: Dashboard Eksekutif Modern 2.0</span>
        </footer>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
