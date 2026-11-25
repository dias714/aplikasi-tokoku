<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('kasir');
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Transaksi Hari Ini - Tokoku Kasir';
$today = date('Y-m-d');
$transactions = [];
$summary = ['count' => 0, 'total' => 0.0, 'units' => 0];
$databaseError = false;

try {
    $stmt = $pdo->prepare(
        'SELECT t.id_transaksi, t.tanggal, t.total_belanja, t.metode_pembayaran,
                t.uang_diterima, t.kembalian, p.nama_pelanggan,
                COALESCE(SUM(d.jumlah), 0) AS jumlah_item
         FROM tbl_transaksi t
         LEFT JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan
         LEFT JOIN tbl_detail_transaksi d ON d.id_transaksi = t.id_transaksi
         WHERE t.tanggal = :today AND t.id_kasir = :id_kasir
         GROUP BY t.id_transaksi, t.tanggal, t.total_belanja, t.metode_pembayaran,
                  t.uang_diterima, t.kembalian, p.nama_pelanggan
         ORDER BY t.id_transaksi DESC'
    );
    $stmt->execute([
        'today' => $today,
        'id_kasir' => (int) $_SESSION['user_id'],
    ]);
    $transactions = $stmt->fetchAll();

    $summary['count'] = count($transactions);
    foreach ($transactions as $row) {
        $summary['total'] += (float) $row['total_belanja'];
        $summary['units'] += (int) $row['jumlah_item'];
    }
} catch (Throwable $e) {
    error_log('Kasir transactions page: ' . $e->getMessage());
    $databaseError = true;
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell-layout">
    <?php
    $kasirActive = 'transactions';
    require_once __DIR__ . '/../includes/kasir_sidebar.php';
    ?>

    <main class="dashboard-shell-main">
        <header class="dashboard-command-topbar">
            <div class="topbar-left-col">
                <div class="topbar-breadcrumbs">
                    <span>Tokoku Kasir</span>
                    <span class="bc-divider">/</span>
                    <span class="bc-active">Transaksi Hari Ini</span>
                </div>
                <h1 class="topbar-main-title">Transaksi Hari Ini</h1>
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
                    <span class="kpi-label">JUMLAH TRANSAKSI</span>
                    <div class="kpi-icon-box icon-emerald">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <strong><?= number_format($summary['count']) ?></strong>
                    <span class="unit-suffix">Transaksi</span>
                </div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card-header">
                    <span class="kpi-label">TOTAL OMZET</span>
                    <div class="kpi-icon-box icon-indigo">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <span class="curr-prefix">Rp</span>
                    <strong><?= number_format($summary['total'], 0, ',', '.') ?></strong>
                </div>
            </article>
            <article class="kpi-card">
                <div class="kpi-card-header">
                    <span class="kpi-label">UNIT TERJUAL</span>
                    <div class="kpi-icon-box icon-sky">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        </svg>
                    </div>
                </div>
                <div class="kpi-main-number">
                    <strong><?= number_format($summary['units']) ?></strong>
                    <span class="unit-suffix">Pcs</span>
                </div>
            </article>
        </section>

        <section class="cmd-panel-card">
            <div class="cmd-panel-header">
                <div>
                    <h3>Daftar Transaksi <?= date('d/m/Y') ?></h3>
                    <p>Semua transaksi yang Anda proses hari ini</p>
                </div>
            </div>
            <div class="cmd-table-responsive">
                <table class="cmd-table">
                    <thead>
                        <tr>
                            <th>Nomor TRX</th>
                            <th>Pelanggan</th>
                            <th>Jumlah Item</th>
                            <th>Total Bayar</th>
                            <th>Uang Diterima</th>
                            <th>Kembalian</th>
                            <th>Nota / Struk</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($databaseError): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Gagal memuat data. Coba muat ulang halaman.</td>
                            </tr>
                        <?php elseif (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada transaksi hari ini. Mulai dari tombol Transaksi Baru.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $trx): ?>
                                <tr>
                                    <td>
                                        <span class="trx-pill-code">TRX-<?= str_pad((string) $trx['id_transaksi'], 3, '0', STR_PAD_LEFT) ?></span>
                                    </td>
                                    <td><strong><?= e($trx['nama_pelanggan'] ?? 'Pelanggan Umum') ?></strong></td>
                                    <td><?= number_format((int) $trx['jumlah_item']) ?> item</td>
                                    <td><strong class="text-emerald">Rp <?= number_format((float) $trx['total_belanja'], 0, ',', '.') ?></strong></td>
                                    <td>Rp <?= number_format((float) ($trx['uang_diterima'] ?? 0), 0, ',', '.') ?></td>
                                    <td>Rp <?= number_format((float) ($trx['kembalian'] ?? 0), 0, ',', '.') ?></td>
                                    <td>
                                        <a class="btn-stock-edit" href="<?= e(appUrl('kasir/receipt.php?id=' . (int) $trx['id_transaksi'])) ?>">
                                            Cetak Struk
                                        </a>
                                    </td>
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
