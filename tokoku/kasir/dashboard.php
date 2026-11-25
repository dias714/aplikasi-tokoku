<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('kasir');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_images.php';

$pageTitle = 'Dashboard Kasir POS - Tokoku';
$todaySummary = ['transactions' => 0, 'turnover' => 0.0, 'units' => 0];
$inventorySummary = ['products' => 0, 'low_stock' => 0];
$recentTransactions = [];
$lowStockProducts = [];
$databaseError = false;

try {
    $summaryStmt = $pdo->prepare(
        'SELECT COUNT(*) AS transactions,
                COALESCE(SUM(total_belanja), 0) AS turnover
         FROM tbl_transaksi
         WHERE tanggal = :today AND id_kasir = :id_kasir'
    );
    $summaryStmt->execute([
        'today' => date('Y-m-d'),
        'id_kasir' => (int) $_SESSION['user_id'],
    ]);
    $todaySummary = array_merge($todaySummary, $summaryStmt->fetch());

    $unitsStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(d.jumlah), 0)
         FROM tbl_detail_transaksi d
         INNER JOIN tbl_transaksi t ON t.id_transaksi = d.id_transaksi
         WHERE t.tanggal = :today AND t.id_kasir = :id_kasir'
    );
    $unitsStmt->execute([
        'today' => date('Y-m-d'),
        'id_kasir' => (int) $_SESSION['user_id'],
    ]);
    $todaySummary['units'] = (int) $unitsStmt->fetchColumn();

    $inventorySummary = $pdo->query(
        'SELECT COUNT(*) AS products,
                COALESCE(SUM(CASE WHEN stok <= 5 THEN 1 ELSE 0 END), 0) AS low_stock
         FROM tbl_produk'
    )->fetch();

    $recentStmt = $pdo->prepare(
        'SELECT t.id_transaksi, t.tanggal, t.total_belanja, p.nama_pelanggan,
                COALESCE(SUM(d.jumlah), 0) AS jumlah_item
         FROM tbl_transaksi t
         LEFT JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan
         LEFT JOIN tbl_detail_transaksi d ON d.id_transaksi = t.id_transaksi
         WHERE t.id_kasir = :id_kasir
         GROUP BY t.id_transaksi, t.tanggal, t.total_belanja, p.nama_pelanggan
         ORDER BY t.tanggal DESC, t.id_transaksi DESC
         LIMIT 6'
    );
    $recentStmt->execute(['id_kasir' => (int) $_SESSION['user_id']]);
    $recentTransactions = $recentStmt->fetchAll();

    $lowStockProducts = $pdo->query(
        'SELECT id_produk, nama_produk, harga, stok, gambar
         FROM tbl_produk
         WHERE stok <= 10
         ORDER BY stok ASC, nama_produk ASC
         LIMIT 6'
    )->fetchAll();
} catch (Throwable $e) {
    error_log('Dashboard Kasir Modern: ' . $e->getMessage());
    $databaseError = true;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-shell-layout">
    <?php
    $kasirActive = 'dashboard';
    require_once __DIR__ . '/../includes/kasir_sidebar.php';
    ?>

    <!-- MAIN DASHBOARD CONTENT -->
    <main class="dashboard-shell-main">
        <header class="dashboard-command-topbar">
            <div class="topbar-left-col">
                <div class="topbar-breadcrumbs">
                    <span>Tokoku Kasir</span>
                    <span class="bc-divider">/</span>
                    <span class="bc-active">Terminal Operasional</span>
                </div>
                <h1 class="topbar-main-title">Dashboard Kasir &amp; POS</h1>
            </div>

            <div class="topbar-right-col">
                <div class="topbar-live-time">
                    <span class="live-dot-pulse"></span>
                    <span><?= date('l, d F Y') ?></span>
                </div>

                <div class="topbar-quick-actions">
                    <a href="<?= e(appUrl('kasir/checkout.php')) ?>" class="btn-topbar-action btn-primary-act" style="background: #059669; box-shadow: 0 3px 10px rgba(5, 150, 105, 0.3);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Transaksi Baru (F1)</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- WELCOME BANNER -->
        <section class="dashboard-hero-banner" style="background: linear-gradient(135deg, #064E3B 0%, #047857 60%, #0F172A 100%);">
            <div class="hero-banner-glass">
                <div class="hero-left-info">
                    <div class="hero-role-pill" style="color: #6EE7B7;">
                        <span>💳</span>
                        <span>TERMINAL KASIR AKTIF</span>
                    </div>
                    <h2>Selamat bertugas, <?= e($_SESSION['name'] ?? 'Kasir') ?>!</h2>
                    <p>
                        Terminal POS siap melayani transaksi tunai &amp; non-tunai. Seluruh transaksi kasir otomatis menghitung kembalian, memperbarui stok, dan dapat dicetak ke kertas thermal 50mm.
                    </p>
                </div>
                <div class="hero-right-meta">
                    <div class="hero-meta-pill">
                        <span class="meta-lbl">Kasir yang Bertugas</span>
                        <strong class="text-emerald"><?= e($_SESSION['name'] ?? 'Petugas') ?></strong>
                    </div>
                    <div class="hero-meta-pill">
                        <span class="meta-lbl">Printer Struk</span>
                        <strong>Ready (50 mm Roll)</strong>
                    </div>
                </div>
            </div>
        </section>

        <!-- KPI STATS -->
        <section class="dashboard-kpi-grid">
            <article class="kpi-card">
                <div class="kpi-card-header">
                    <span class="kpi-label">TRANSAKSI HARI INI</span>
                    <div class="kpi-icon-box icon-emerald">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <strong><?= number_format((int) $todaySummary['transactions']) ?></strong>
                    <span class="unit-suffix">Transaksi</span>
                </div>
                <div class="kpi-footer-trend">
                    <span class="trend-badge trend-up">Hari ini</span>
                    <span class="kpi-subtext">Diproses oleh Anda</span>
                </div>
            </article>

            <article class="kpi-card">
                <div class="kpi-card-header">
                    <span class="kpi-label">TOTAL OMZET KASIR</span>
                    <div class="kpi-icon-box icon-indigo">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <span class="curr-prefix">Rp</span>
                    <strong><?= number_format((float) $todaySummary['turnover'], 0, ',', '.') ?></strong>
                </div>
                <div class="kpi-footer-trend">
                    <span class="trend-badge trend-neutral">Uang Masuk</span>
                    <span class="kpi-subtext">Nilai bersih penjualan</span>
                </div>
            </article>

            <article class="kpi-card">
                <div class="kpi-card-header">
                    <span class="kpi-label">UNIT BARANG TERJUAL</span>
                    <div class="kpi-icon-box icon-sky">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <strong><?= number_format((int) $todaySummary['units']) ?></strong>
                    <span class="unit-suffix">Pcs</span>
                </div>
                <div class="kpi-footer-trend">
                    <span class="trend-badge trend-cat">Item keluar</span>
                    <span class="kpi-subtext">Hari ini</span>
                </div>
            </article>

            <article class="kpi-card">
                <div class="kpi-card-header">
                    <span class="kpi-label">PERINGATAN STOK</span>
                    <div class="kpi-icon-box icon-rose">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <strong><?= number_format((int) ($inventorySummary['low_stock'] ?? 0)) ?></strong>
                    <span class="unit-suffix">Produk</span>
                </div>
                <div class="kpi-footer-trend">
                    <span class="role-chip chip-ksr">Stok &le; 5</span>
                    <span class="kpi-subtext">Perlu perhatian</span>
                </div>
            </article>
        </section>

        <!-- COMMAND CENTER 2 COLUMNS -->
        <div class="dashboard-command-center-grid">
            <div class="cmd-col-main" id="transaksi-kasir">
                <section class="cmd-panel-card">
                    <div class="cmd-panel-header">
                        <div>
                            <h3>🧾 Transaksi Penjualan Kasir Terkini</h3>
                            <p>Catatan penjualan yang berhasil diproses oleh sesi kasir Anda</p>
                        </div>
                        <a href="<?= e(appUrl('kasir/checkout.php')) ?>" class="btn-topbar-action btn-primary-act" style="background: #059669; padding: 6px 12px; font-size: 12px;">+ Transaksi Baru</a>
                    </div>

                    <div class="cmd-table-responsive">
                        <table class="cmd-table">
                            <thead>
                                <tr>
                                    <th>Nomor TRX</th>
                                    <th>Pelanggan</th>
                                    <th>Waktu</th>
                                    <th>Jumlah Item</th>
                                    <th>Total Bayar</th>
                                    <th>Nota / Struk</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentTransactions)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Belum ada transaksi pada akun kasir Anda hari ini. Mulai dari tombol Transaksi Baru.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <span class="trx-pill-code">TRX-<?= str_pad((string) $transaction['id_transaksi'], 3, '0', STR_PAD_LEFT) ?></span>
                                            </td>
                                            <td><strong><?= e($transaction['nama_pelanggan'] ?? 'Pelanggan Umum') ?></strong></td>
                                            <td><?= $transaction['tanggal'] ? date('d/m/Y', strtotime($transaction['tanggal'])) : '-' ?></td>
                                            <td><?= number_format((int) $transaction['jumlah_item']) ?> item</td>
                                            <td><strong class="text-emerald">Rp <?= number_format((float) $transaction['total_belanja'], 0, ',', '.') ?></strong></td>
                                            <td>
                                                <a class="btn-stock-edit" href="<?= e(appUrl('kasir/receipt.php?id=' . (int) $transaction['id_transaksi'])) ?>">
                                                    🖨️ Cetak Struk
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="cmd-col-side" id="stok-kasir">
                <section class="cmd-panel-card">
                    <div class="cmd-panel-header">
                        <div>
                            <h3>📦 Pantau Stok Barang Toko</h3>
                            <p>Produk dengan persediaan terbatas</p>
                        </div>
                    </div>

                    <div class="critical-stock-list">
                        <?php if (empty($lowStockProducts)): ?>
                            <p class="text-muted">Semua stok produk dalam kondisi cukup.</p>
                        <?php else: ?>
                            <?php foreach ($lowStockProducts as $p): ?>
                                <?php
                                $stk = (int) $p['stok'];
                                $isCritical = $stk <= 5;
                                ?>
                                <div class="stock-item-card">
                                    <img src="<?= e(productImageUrl($p)) ?>" alt="" class="stock-item-img">
                                    <div class="stock-item-info">
                                        <strong class="stock-item-name"><?= e($p['nama_produk']) ?></strong>
                                        <span class="stock-item-price">Rp <?= number_format((float) $p['harga'], 0, ',', '.') ?></span>
                                    </div>
                                    <div class="stock-badge-col">
                                        <span class="stock-status-tag <?= $isCritical ? 'tag-danger' : 'tag-safe' ?>">
                                            <?= $stk ?> Unit
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>

        <footer class="dashboard-shell-footer">
            <span>© <?= date('Y') ?> <strong>Tokoku POS</strong> · Terminal Kasir Resmi.</span>
            <span>Mode Cetak Roll 50mm Aktif</span>
        </footer>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
