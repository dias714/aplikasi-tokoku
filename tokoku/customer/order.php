<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('pelanggan');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_images.php';
require_once __DIR__ . '/../includes/store.php';

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$orderStmt = $pdo->prepare(
    'SELECT t.id_transaksi, t.tanggal, t.total_belanja,
            p.nama_pelanggan, p.kota
     FROM tbl_transaksi t
     INNER JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan
     WHERE t.id_transaksi = :id_transaksi AND p.id_user = :id_user
     LIMIT 1'
);
$orderStmt->execute([
    'id_transaksi' => $orderId && $orderId > 0 ? $orderId : 0,
    'id_user' => (int) $_SESSION['user_id'],
]);
$order = $orderStmt->fetch();

if (!$order) {
    http_response_code(404);
}

$items = [];
if ($order) {
    $itemStmt = $pdo->prepare(
        'SELECT d.nama_produk, d.jumlah, d.harga, p.gambar,
                (COALESCE(d.jumlah, 0) * COALESCE(d.harga, 0)) AS subtotal,
                d.id_produk
         FROM tbl_detail_transaksi d
         LEFT JOIN tbl_produk p ON p.id_produk = d.id_produk
         WHERE d.id_transaksi = :id_transaksi
         ORDER BY d.id_detail'
    );
    $itemStmt->execute(['id_transaksi' => (int) $order['id_transaksi']]);
    $items = $itemStmt->fetchAll();
}

$pageTitle = $order
    ? 'Nota Pesanan TRX-' . str_pad((string) $order['id_transaksi'], 3, '0', STR_PAD_LEFT) . ' - Tokoku'
    : 'Pesanan tidak ditemukan - Tokoku';

$success = $_SESSION['order_flash'] ?? '';
unset($_SESSION['order_flash']);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="store-page-order-detail">
    <div class="no-print">
        <?php customerStoreHeader('orders'); ?>
    </div>

    <div class="order-detail-page-container">
        <!-- TOP ACTIONS & BREADCRUMB (NO PRINT) -->
        <div class="detail-nav-actions no-print">
            <a href="<?= e(appUrl('customer/orders.php')) ?>" class="btn-back-link">
                ← Kembali ke Pesanan Saya
            </a>
            <div class="print-actions-right">
                <button type="button" class="btn-print-invoice" onclick="window.print()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    <span>Cetak Nota / Invoice</span>
                </button>
                <a href="<?= e(appUrl('customer/index.php')) ?>" class="btn-shop-more">Belanja Lagi</a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="user-alert user-alert-success no-print" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span><?= e($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($order): ?>
            <?php
            $trxCode = 'TRX-' . str_pad((string) $order['id_transaksi'], 3, '0', STR_PAD_LEFT);
            $orderDate = $order['tanggal'] ? date('d F Y', strtotime($order['tanggal'])) : '-';
            $totalUnits = array_sum(array_column($items, 'jumlah'));
            ?>

            <!-- INVOICE SHEET -->
            <article class="invoice-sheet-card">
                <!-- INVOICE HEADER -->
                <div class="invoice-header">
                    <div class="invoice-brand">
                        <div class="store-brand-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                            </svg>
                        </div>
                        <div>
                            <h2 class="invoice-brand-title">Tokoku</h2>
                            <span class="invoice-brand-sub">Katalog Lengkap &amp; Terstruktur</span>
                        </div>
                    </div>

                    <div class="invoice-meta-right">
                        <span class="invoice-pill-title">BUKTI PEMESANAN / NOTA</span>
                        <h1 class="invoice-code"><?= e($trxCode) ?></h1>
                        <span class="invoice-date">Tanggal: <?= e($orderDate) ?></span>
                    </div>
                </div>

                <div class="invoice-divider"></div>

                <!-- CUSTOMER & ORDER DETAILS -->
                <div class="invoice-info-grid">
                    <div class="info-block">
                        <span class="info-block-lbl">Diterbitkan Untuk:</span>
                        <strong class="info-customer-name"><?= e($order['nama_pelanggan']) ?></strong>
                        <span class="info-customer-city">📍 <?= e($order['kota'] ?: 'Toko Pusat') ?></span>
                        <span class="info-customer-status">Role Akun: Pelanggan Terdaftar</span>
                    </div>

                    <div class="info-block">
                        <span class="info-block-lbl">Status Transaksi:</span>
                        <div class="status-badge-inline">
                            <span class="badge-dot-green"></span>
                            <strong>Tercatat di Database</strong>
                        </div>
                        <span class="info-note">Stok barang telah berhasil diperbarui dan dialokasikan untuk pesanan Anda.</span>
                    </div>
                </div>

                <!-- ITEMS TABLE -->
                <div class="invoice-table-wrap">
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th class="col-num">No</th>
                                <th class="col-item">Rincian Barang</th>
                                <th class="col-qty text-center">Jumlah</th>
                                <th class="col-price text-right">Harga Satuan</th>
                                <th class="col-subtotal text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($items as $item): ?>
                                <tr>
                                    <td class="col-num text-center"><?= $no++ ?></td>
                                    <td class="col-item">
                                        <div class="item-name-cell">
                                            <img src="<?= e(productImageUrl($item)) ?>" alt="" class="item-thumb-small">
                                            <strong><?= e($item['nama_produk'] ?? 'Produk') ?></strong>
                                        </div>
                                    </td>
                                    <td class="col-qty text-center"><?= number_format((int) $item['jumlah']) ?> unit</td>
                                    <td class="col-price text-right">Rp <?= number_format((float) $item['harga'], 0, ',', '.') ?></td>
                                    <td class="col-subtotal text-right">
                                        <strong>Rp <?= number_format((float) $item['subtotal'], 0, ',', '.') ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-muted">Total Keseluruhan Barang (<?= number_format($totalUnits) ?> Unit)</td>
                                <td class="text-right text-bold">Total Pembayaran:</td>
                                <td class="text-right total-final-cell">
                                    <strong>Rp <?= number_format((float) $order['total_belanja'], 0, ',', '.') ?></strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- INVOICE FOOTER NOTE -->
                <div class="invoice-footer-note">
                    <p>Terima kasih telah berbelanja di <strong>Tokoku</strong>. Simpan nota ini sebagai tanda bukti pemesanan yang sah.</p>
                </div>
            </article>
        <?php else: ?>
            <div class="catalog-empty-box">
                <h3>Pesanan Tidak Ditemukan</h3>
                <p>Pesanan yang Anda minta tidak terdaftar pada akun Anda atau telah dihapus.</p>
                <div class="empty-actions">
                    <a href="<?= e(appUrl('customer/orders.php')) ?>" class="btn-empty-reset">Kembali ke Riwayat Pesanan</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="no-print">
        <?php customerStoreFooter(); ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
