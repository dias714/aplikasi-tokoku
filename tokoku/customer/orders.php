<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('pelanggan');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_images.php';
require_once __DIR__ . '/../includes/store.php';

$pageTitle = 'Pesanan Saya - Tokoku';

$stmt = $pdo->prepare(
    'SELECT t.id_transaksi, t.tanggal, t.total_belanja,
            COUNT(d.id_detail) AS jumlah_baris,
            COALESCE(SUM(d.jumlah), 0) AS jumlah_item
     FROM tbl_transaksi t
     INNER JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan
     LEFT JOIN tbl_detail_transaksi d ON d.id_transaksi = t.id_transaksi
     WHERE p.id_user = :id_user
     GROUP BY t.id_transaksi, t.tanggal, t.total_belanja
     ORDER BY t.tanggal DESC, t.id_transaksi DESC'
);
$stmt->execute(['id_user' => (int) $_SESSION['user_id']]);
$orders = $stmt->fetchAll();

$success = $_SESSION['order_flash'] ?? '';
unset($_SESSION['order_flash']);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="store-page-orders">
    <?php customerStoreHeader('orders'); ?>

    <div class="orders-page-container">
        <!-- HEADER -->
        <div class="orders-page-header">
            <div>
                <span class="orders-header-pill">RIWAYAT TRANSAKSI</span>
                <h1>Pesanan Saya</h1>
                <p>Pantau seluruh riwayat transaksi dan rincian barang yang pernah Anda pesan di Tokoku.</p>
            </div>
            <a href="<?= e(appUrl('customer/index.php')) ?>" class="btn-orders-new-shop">
                <span>+ Belanja Lagi</span>
            </a>
        </div>

        <!-- FLASH NOTIFICATION -->
        <?php if ($success): ?>
            <div class="user-alert user-alert-success" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span><?= e($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="orders-empty-state">
                <div class="orders-empty-icon">📋</div>
                <h2>Belum Ada Riwayat Pesanan</h2>
                <p>Anda belum pernah melakukan pemesanan di toko kami. Mulai temukan berbagai produk menarik di katalog kami sekarang!</p>
                <a href="<?= e(appUrl('customer/index.php')) ?>" class="btn-empty-start-shop">
                    <span>Mulai Belanja di Katalog</span>
                </a>
            </div>
        <?php else: ?>
            <div class="orders-list-grid">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $trxCode = 'TRX-' . str_pad((string) $order['id_transaksi'], 3, '0', STR_PAD_LEFT);
                    $dateStr = $order['tanggal'] ? date('d M Y', strtotime($order['tanggal'])) : '-';
                    $totalFormatted = number_format((float) $order['total_belanja'], 0, ',', '.');
                    $detailUrl = appUrl('customer/order.php?id=' . (int) $order['id_transaksi']);
                    ?>
                    <article class="order-summary-card">
                        <div class="order-card-header">
                            <div class="order-meta-info">
                                <span class="order-code-badge"><?= e($trxCode) ?></span>
                                <span class="order-date-text">📅 <?= e($dateStr) ?></span>
                            </div>
                            <span class="order-status-pill status-recorded">✓ Tercatat Berhasil</span>
                        </div>

                        <div class="order-card-body">
                            <div class="order-stat-box">
                                <span class="stat-lbl">Jumlah Barang</span>
                                <strong class="stat-num"><?= number_format((int) $order['jumlah_item']) ?> Unit</strong>
                                <span class="stat-sub">(<?= number_format((int) $order['jumlah_baris']) ?> varian produk)</span>
                            </div>

                            <div class="order-stat-box">
                                <span class="stat-lbl">Total Belanja</span>
                                <strong class="stat-price">Rp <?= e($totalFormatted) ?></strong>
                                <span class="stat-sub">Pembayaran terverifikasi</span>
                            </div>

                            <div class="order-card-actions">
                                <a href="<?= e($detailUrl) ?>" class="btn-order-view-detail">
                                    <span>Lihat Detail &amp; Struk</span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php customerStoreFooter(); ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
