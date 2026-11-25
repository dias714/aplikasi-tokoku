<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('kasir');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_images.php';

$pageTitle = 'Pantau Stok - Tokoku Kasir';
$lowStockProducts = [];
$allProducts = [];
$summary = ['total' => 0, 'low' => 0, 'out' => 0];
$databaseError = false;

try {
    $summaryRow = $pdo->query(
        'SELECT COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN stok <= 5 THEN 1 ELSE 0 END), 0) AS low,
                COALESCE(SUM(CASE WHEN stok = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock
         FROM tbl_produk'
    )->fetch();
    $summary['total'] = (int) ($summaryRow['total'] ?? 0);
    $summary['low'] = (int) ($summaryRow['low'] ?? 0);
    $summary['out'] = (int) ($summaryRow['out_of_stock'] ?? 0);

    $lowStockProducts = $pdo->query(
        'SELECT id_produk, nama_produk, harga, stok, gambar
         FROM tbl_produk
         WHERE stok <= 10
         ORDER BY stok ASC, nama_produk ASC'
    )->fetchAll();

    $allProducts = $pdo->query(
        'SELECT id_produk, nama_produk, harga, stok, gambar
         FROM tbl_produk
         ORDER BY stok ASC, nama_produk ASC
         LIMIT 50'
    )->fetchAll();
} catch (Throwable $e) {
    error_log('Kasir stock page: ' . $e->getMessage());
    $databaseError = true;
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell-layout">
    <?php
    $kasirActive = 'stock';
    require_once __DIR__ . '/../includes/kasir_sidebar.php';
    ?>

    <main class="dashboard-shell-main">
        <header class="dashboard-command-topbar">
            <div class="topbar-left-col">
                <div class="topbar-breadcrumbs">
                    <span>Tokoku Kasir</span>
                    <span class="bc-divider">/</span>
                    <span class="bc-active">Pantau Stok</span>
                </div>
                <h1 class="topbar-main-title">Pantau Stok Barang</h1>
            </div>
            <div class="topbar-right-col">
                <div class="topbar-live-time">
                    <span class="live-dot-pulse"></span>
                    <span><?= date('l, d F Y') ?></span>
                </div>
                <div class="topbar-quick-actions">
                    <a href="<?= e(appUrl('kasir/checkout.php')) ?>" class="btn-topbar-action btn-primary-act" style="background:#059669;box-shadow:0 3px 10px rgba(5,150,105,0.3);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Transaksi Baru</span>
                    </a>
                </div>
            </div>
        </header>

        <section class="dashboard-kpi-grid" style="grid-template-columns: repeat(3, 1fr);">
            <article class="kpi-card">
                <div class="kpi-card-header">
                    <span class="kpi-label">TOTAL PRODUK</span>
                    <div class="kpi-icon-box icon-sky">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <strong><?= number_format($summary['total']) ?></strong>
                    <span class="unit-suffix">Produk</span>
                </div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card-header">
                    <span class="kpi-label">STOK RENDAH (≤5)</span>
                    <div class="kpi-icon-box icon-rose">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <strong><?= number_format($summary['low']) ?></strong>
                    <span class="unit-suffix">Produk</span>
                </div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card-header">
                    <span class="kpi-label">HABIS (0)</span>
                    <div class="kpi-icon-box icon-rose">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <strong><?= number_format($summary['out']) ?></strong>
                    <span class="unit-suffix">Produk</span>
                </div>
            </article>
        </section>

        <section class="cmd-panel-card" style="margin-bottom:20px;">
            <div class="cmd-panel-header">
                <div>
                    <h3>Produk Stok Terbatas</h3>
                    <p>Produk dengan stok ≤ 10 unit — perlu perhatian</p>
                </div>
            </div>
            <div class="critical-stock-list" style="padding:8px 4px;">
                <?php if ($databaseError): ?>
                    <p class="text-muted">Gagal memuat data stok.</p>
                <?php elseif (empty($lowStockProducts)): ?>
                    <p class="text-muted" style="padding:16px;">Semua stok produk dalam kondisi cukup.</p>
                <?php else: ?>
                    <?php foreach ($lowStockProducts as $p): ?>
                        <?php
                        $stk = (int) $p['stok'];
                        $isCritical = $stk <= 5;
                        $isOut = $stk === 0;
                        ?>
                        <div class="stock-item-card">
                            <img src="<?= e(productImageUrl($p)) ?>" alt="" class="stock-item-img">
                            <div class="stock-item-info">
                                <strong class="stock-item-name"><?= e($p['nama_produk']) ?></strong>
                                <span class="stock-item-price">Rp <?= number_format((float) $p['harga'], 0, ',', '.') ?></span>
                            </div>
                            <div class="stock-badge-col">
                                <span class="stock-status-tag <?= $isOut || $isCritical ? 'tag-danger' : 'tag-safe' ?>">
                                    <?= $isOut ? 'Habis' : ($stk . ' Unit') ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="cmd-panel-card">
            <div class="cmd-panel-header">
                <div>
                    <h3>Semua Produk (urut stok terendah)</h3>
                    <p>Maksimal 50 produk ditampilkan</p>
                </div>
            </div>
            <div class="cmd-table-responsive">
                <table class="cmd-table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allProducts)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Belum ada produk di katalog.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allProducts as $p): ?>
                                <?php
                                $stk = (int) $p['stok'];
                                $statusClass = $stk === 0 ? 'tag-danger' : ($stk <= 5 ? 'tag-danger' : 'tag-safe');
                                $statusText = $stk === 0 ? 'Habis' : ($stk <= 5 ? 'Rendah' : 'Aman');
                                ?>
                                <tr>
                                    <td><strong><?= e($p['nama_produk']) ?></strong></td>
                                    <td>Rp <?= number_format((float) $p['harga'], 0, ',', '.') ?></td>
                                    <td><?= number_format($stk) ?></td>
                                    <td><span class="stock-status-tag <?= $statusClass ?>"><?= $statusText ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="dashboard-shell-footer">
            <span>© <?= date('Y') ?> <strong>Tokoku POS</strong> · Terminal Kasir Resmi.</span>
            <span>Mode Cetak Roll 50mm Aktif</span>
        </footer>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
