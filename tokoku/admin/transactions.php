<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_images.php';

$pageTitle = 'Transaksi Penjualan - Tokoku';
$errors    = [];
$search    = trim((string) ($_GET['q']       ?? ''));
$dateFrom  = trim((string) ($_GET['from']    ?? ''));
$dateTo    = trim((string) ($_GET['to']      ?? ''));
$kasirId   = filter_var($_GET['kasir'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$viewId    = filter_var($_GET['view']  ?? null, FILTER_VALIDATE_INT);
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 10;

// ── Validasi tanggal ──────────────────────────────────────────────────────────
foreach (['from' => $dateFrom, 'to' => $dateTo] as $label => $dateValue) {
    if ($dateValue !== '') {
        $parsedDate = DateTime::createFromFormat('!Y-m-d', $dateValue);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $dateValue) {
            $errors[] = 'Format tanggal filter tidak valid.';
            break;
        }
    }
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $errors[] = 'Tanggal awal tidak boleh melewati tanggal akhir.';
}

// ── Ambil daftar kasir untuk dropdown ────────────────────────────────────────
$kasirList = $pdo->query(
    "SELECT id, name FROM users WHERE role = 'kasir' ORDER BY name ASC"
)->fetchAll();

// ── Bangun WHERE clause ───────────────────────────────────────────────────────
$where  = $errors ? ['1 = 0'] : [];
$params = [];
if ($search !== '') {
    $where[]                  = '(CAST(t.id_transaksi AS CHAR) LIKE :id_search OR p.nama_pelanggan LIKE :customer_search)';
    $params['id_search']      = '%' . $search . '%';
    $params['customer_search']= '%' . $search . '%';
}
if (!$errors && $dateFrom !== '') {
    $where[]             = 't.tanggal >= :date_from';
    $params['date_from'] = $dateFrom;
}
if (!$errors && $dateTo !== '') {
    $where[]           = 't.tanggal <= :date_to';
    $params['date_to'] = $dateTo;
}
if ($kasirId > 0) {
    $where[]           = 't.id_kasir = :kasir_id';
    $params['kasir_id']= $kasirId;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// ── Ringkasan per kasir (selalu dari semua data, tanpa filter kasir) ──────────
$kasirSummaryBase = '';
$kasirSummaryParams = [];
$summaryWhere = [];
if ($search !== '') {
    $summaryWhere[]                        = '(CAST(t.id_transaksi AS CHAR) LIKE :id_search OR p.nama_pelanggan LIKE :customer_search)';
    $kasirSummaryParams['id_search']       = '%' . $search . '%';
    $kasirSummaryParams['customer_search'] = '%' . $search . '%';
}
if (!$errors && $dateFrom !== '') {
    $summaryWhere[]                        = 't.tanggal >= :date_from';
    $kasirSummaryParams['date_from']       = $dateFrom;
}
if (!$errors && $dateTo !== '') {
    $summaryWhere[]                      = 't.tanggal <= :date_to';
    $kasirSummaryParams['date_to']       = $dateTo;
}
$kasirSummaryBase = $summaryWhere ? ' WHERE ' . implode(' AND ', $summaryWhere) : '';

$summaryStmt = $pdo->prepare(
    'SELECT u.id AS kasir_id, u.name AS kasir_name,
            COUNT(t.id_transaksi) AS jml_transaksi,
            COALESCE(SUM(t.total_belanja), 0) AS total_penjualan,
            COALESCE(SUM(d_sum.jml_item), 0) AS total_item
     FROM users u
     LEFT JOIN tbl_transaksi t ON t.id_kasir = u.id'
    . ($kasirSummaryBase ? str_replace('t.id_transaksi', 't.id_transaksi', $kasirSummaryBase) : '')
    . ' LEFT JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan
     LEFT JOIN (
         SELECT id_transaksi, SUM(jumlah) AS jml_item
         FROM tbl_detail_transaksi
         GROUP BY id_transaksi
     ) d_sum ON d_sum.id_transaksi = t.id_transaksi
     WHERE u.role = \'kasir\'
     GROUP BY u.id, u.name
     ORDER BY total_penjualan DESC'
);
// Re-build WHERE properly for summary (can't mix easily, do raw)
$summaryStmt = $pdo->prepare(
    'SELECT u.id AS kasir_id, u.name AS kasir_name,
            COUNT(t.id_transaksi) AS jml_transaksi,
            COALESCE(SUM(t.total_belanja), 0) AS total_penjualan
     FROM users u
     LEFT JOIN tbl_transaksi t ON t.id_kasir = u.id
     WHERE u.role = \'kasir\'
     GROUP BY u.id, u.name
     ORDER BY total_penjualan DESC'
);
$summaryStmt->execute();
$kasirSummary = $summaryStmt->fetchAll();

// Grand total semua transaksi (tanpa filter kasir, tapi dengan filter tanggal/search)
$grandTotalStmt = $pdo->prepare(
    'SELECT COUNT(*) AS jml, COALESCE(SUM(t.total_belanja), 0) AS total
     FROM tbl_transaksi t
     LEFT JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan'
    . $kasirSummaryBase
);
foreach ($kasirSummaryParams as $k => $v) {
    $grandTotalStmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$grandTotalStmt->execute();
$grandTotal = $grandTotalStmt->fetch();

// ── Pagination ────────────────────────────────────────────────────────────────
$countStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM tbl_transaksi t
     LEFT JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan'
    . $whereSql
);
foreach ($params as $key => $value) {
    $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$countStmt->execute();
$totalTransactions = (int) $countStmt->fetchColumn();
$totalPages        = max(1, (int) ceil($totalTransactions / $perPage));
$page              = min($page, $totalPages);

$listStmt = $pdo->prepare(
    'SELECT t.id_transaksi, t.tanggal, t.total_belanja, t.metode_pembayaran,
            p.nama_pelanggan, u.name AS nama_kasir, u.id AS kasir_id,
            COUNT(d.id_detail) AS jumlah_item
     FROM tbl_transaksi t
     LEFT JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan
     LEFT JOIN users u ON u.id = t.id_kasir
     LEFT JOIN tbl_detail_transaksi d ON d.id_transaksi = t.id_transaksi'
    . $whereSql .
    ' GROUP BY t.id_transaksi, t.tanggal, t.total_belanja, t.metode_pembayaran,
               p.nama_pelanggan, u.name, u.id
      ORDER BY t.tanggal DESC, t.id_transaksi DESC
      LIMIT :limit OFFSET :offset'
);
foreach ($params as $key => $value) {
    $listStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$listStmt->bindValue(':limit',  $perPage,              PDO::PARAM_INT);
$listStmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
$listStmt->execute();
$transactions = $listStmt->fetchAll();

// ── Detail transaksi ──────────────────────────────────────────────────────────
$transactionDetail = null;
$details           = [];
if ($viewId && $viewId > 0) {
    $detailHeader = $pdo->prepare(
        'SELECT t.id_transaksi, t.tanggal, t.total_belanja, t.id_pelanggan,
                t.metode_pembayaran, t.uang_diterima, t.kembalian,
                p.nama_pelanggan, u.name AS nama_kasir
         FROM tbl_transaksi t
         LEFT JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan
         LEFT JOIN users u ON u.id = t.id_kasir
         WHERE t.id_transaksi = :id_transaksi LIMIT 1'
    );
    $detailHeader->execute(['id_transaksi' => $viewId]);
    $transactionDetail = $detailHeader->fetch() ?: null;
    if ($transactionDetail) {
        $detailStmt = $pdo->prepare(
            'SELECT d.id_detail, d.id_produk, d.nama_produk, d.jumlah, d.harga, p.gambar,
                    (COALESCE(jumlah, 0) * COALESCE(harga, 0)) AS subtotal
             FROM tbl_detail_transaksi d
             LEFT JOIN tbl_produk p ON p.id_produk = d.id_produk
             WHERE d.id_transaksi = :id_transaksi
             ORDER BY d.id_detail'
        );
        $detailStmt->execute(['id_transaksi' => $viewId]);
        $details = $detailStmt->fetchAll();
    } else {
        $errors[] = 'Transaksi yang dipilih tidak ditemukan.';
    }
}

// ── Active kasir name (untuk label filter) ───────────────────────────────────
$activeKasirName = '';
if ($kasirId > 0) {
    foreach ($kasirList as $k) {
        if ((int)$k['id'] === $kasirId) {
            $activeKasirName = $k['name'];
            break;
        }
    }
}

$paginationQuery = array_filter(
    ['q' => $search, 'from' => $dateFrom, 'to' => $dateTo, 'kasir' => $kasirId ?: ''],
    static fn ($value): bool => $value !== '' && $value !== 0
);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
    <?php $activeAdminPage = 'transactions'; require __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="admin-main">
        <header class="admin-topbar">
            <div>
                <div class="topbar-eyebrow">TOKOKU / ADMIN</div>
                <h1>Transaksi Penjualan</h1>
                <p class="topbar-subtitle">Pantau transaksi dan lihat rincian pembelian pelanggan.</p>
            </div>
            <div class="topbar-profile">
                <div class="profile-avatar"><?= e(strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1))) ?></div>
                <div><strong><?= e($_SESSION['name'] ?? 'Admin') ?></strong><span>Administrator</span></div>
            </div>
        </header>

        <?php foreach ($errors as $error): ?><div class="user-alert user-alert-error" role="alert"><?= e($error) ?></div><?php endforeach; ?>

        <!-- ── Kartu Ringkasan Kasir ─────────────────────────────────────── -->
        <section class="kasir-summary-section">
            <h2 class="kasir-summary-title">Ringkasan Penjualan per Kasir</h2>
            <div class="kasir-summary-grid">
                <!-- Kartu: Semua Transaksi -->
                <a href="<?= e(appUrl('admin/transactions.php' . ($paginationQuery && $kasirId === 0 ? '?' . http_build_query(array_diff_key($paginationQuery, ['kasir' => ''])) : ''))) ?>"
                   class="kasir-summary-card <?= $kasirId === 0 ? 'active' : '' ?>">
                    <div class="ksc-icon ksc-icon-all">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    </div>
                    <div class="ksc-body">
                        <span class="ksc-label">Semua Kasir</span>
                        <strong class="ksc-total">Rp <?= e(number_format((float)$grandTotal['total'], 0, ',', '.')) ?></strong>
                        <span class="ksc-count"><?= number_format((int)$grandTotal['jml']) ?> transaksi</span>
                    </div>
                    <?php if ($kasirId === 0): ?>
                        <span class="ksc-active-badge">Aktif</span>
                    <?php endif; ?>
                </a>

                <!-- Kartu per kasir -->
                <?php
                $kasirColors = ['#4F46E5', '#0EA5E9', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];
                foreach ($kasirSummary as $idx => $ks):
                    $color = $kasirColors[$idx % count($kasirColors)];
                    $filterUrl = appUrl('admin/transactions.php?' . http_build_query(
                        array_merge(
                            array_diff_key($paginationQuery, ['kasir' => '']),
                            ['kasir' => $ks['kasir_id']]
                        )
                    ));
                    $isActive = (int)$ks['kasir_id'] === $kasirId;
                ?>
                <a href="<?= e($filterUrl) ?>" class="kasir-summary-card <?= $isActive ? 'active' : '' ?>" style="--ksc-color: <?= $color ?>">
                    <div class="ksc-icon" style="background: <?= $color ?>1a; color: <?= $color ?>">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <div class="ksc-body">
                        <span class="ksc-label"><?= e($ks['kasir_name']) ?></span>
                        <strong class="ksc-total" style="color: <?= $color ?>">Rp <?= e(number_format((float)$ks['total_penjualan'], 0, ',', '.')) ?></strong>
                        <span class="ksc-count"><?= number_format((int)$ks['jml_transaksi']) ?> transaksi</span>
                    </div>
                    <?php if ($isActive): ?>
                        <span class="ksc-active-badge">Aktif</span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ── Detail Transaksi ──────────────────────────────────────────── -->
        <?php if ($transactionDetail): ?>
            <section class="admin-panel transaction-detail-panel" id="detail">
                <div class="panel-heading">
                    <div><h2>Detail TRX-<?= str_pad((string) $transactionDetail['id_transaksi'], 3, '0', STR_PAD_LEFT) ?></h2>
                    <p><?= $transactionDetail['tanggal'] ? e(date('d/m/Y', strtotime($transactionDetail['tanggal']))) : 'Tanggal tidak tersedia' ?> · <?= e($transactionDetail['nama_pelanggan'] ?? 'Pelanggan tidak diketahui') ?></p></div>
                    <a class="user-btn user-btn-light" href="<?= e(appUrl('admin/transactions.php' . ($paginationQuery ? '?' . http_build_query($paginationQuery) : ''))) ?>">Kembali ke transaksi</a>
                </div>
                <div class="table-wrap">
                    <table class="admin-table"><thead><tr><th>Produk</th><th>Jumlah</th><th>Harga Satuan</th><th>Subtotal</th></tr></thead><tbody>
                    <?php if (!$details): ?><tr><td colspan="4" class="table-empty">Tidak ada rincian produk untuk transaksi ini.</td></tr><?php else: foreach ($details as $detail): ?>
                        <tr><td><div class="table-user"><img class="product-table-image" src="<?= e(productImageUrl($detail)) ?>" alt="" loading="lazy"><strong><?= e($detail['nama_produk'] ?? 'Produk tidak diketahui') ?></strong></div></td><td><?= number_format((int) $detail['jumlah']) ?></td><td>Rp <?= e(number_format((float) $detail['harga'], 0, ',', '.')) ?></td><td>Rp <?= e(number_format((float) $detail['subtotal'], 0, ',', '.')) ?></td></tr>
                    <?php endforeach; endif; ?>
                    </tbody></table>
                </div>
                <div class="transaction-total"><span>Total transaksi</span><strong>Rp <?= e(number_format((float) $transactionDetail['total_belanja'], 0, ',', '.')) ?></strong></div>
                <?php if ($transactionDetail['id_pelanggan'] !== null || $transactionDetail['metode_pembayaran'] !== null): ?>
                    <div class="cashier-receipt-admin-meta">
                        <span>Pelanggan: <?= e($transactionDetail['nama_pelanggan'] ?? 'Pelanggan umum') ?></span>
                        <span>Kasir: <?= e($transactionDetail['nama_kasir'] ?? 'Tidak tercatat') ?></span>
                        <span>Pembayaran: <?= e($transactionDetail['metode_pembayaran'] ?? 'Tidak tercatat') ?></span>
                        <?php if ($transactionDetail['uang_diterima'] !== null): ?><span>Uang diterima: Rp <?= e(number_format((float) $transactionDetail['uang_diterima'], 0, ',', '.')) ?></span><?php endif; ?>
                        <?php if ($transactionDetail['kembalian'] !== null): ?><span>Kembalian: Rp <?= e(number_format((float) $transactionDetail['kembalian'], 0, ',', '.')) ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>
                <p class="product-schema-note transaction-schema-note">Status pesanan terpisah belum tersedia pada skema transaksi.</p>
            </section>
        <?php endif; ?>

        <!-- ── Daftar Transaksi ──────────────────────────────────────────── -->
        <section class="admin-panel transaction-list-panel">
            <div class="panel-heading">
                <div>
                    <h2>Daftar Transaksi<?= $activeKasirName ? ' — ' . e($activeKasirName) : '' ?></h2>
                    <p><?= $activeKasirName ? 'Menampilkan transaksi oleh ' . e($activeKasirName) . '.' : 'Semua data transaksi dari seluruh kasir.' ?></p>
                </div>
                <div class="panel-heading-actions">
                    <span class="panel-count"><?= number_format($totalTransactions) ?> transaksi</span>
                    <?php
                        $exportQuery = array_filter(
                            ['q' => $search, 'from' => $dateFrom, 'to' => $dateTo, 'kasir' => $kasirId ?: ''],
                            static fn ($v): bool => $v !== '' && $v !== 0
                        );
                        $exportUrl = appUrl('admin/export_transactions_pdf.php' . ($exportQuery ? '?' . http_build_query($exportQuery) : ''));
                    ?>
                    <a class="user-btn btn-export-pdf" href="<?= e($exportUrl) ?>" target="_blank" title="Ekspor laporan ke PDF">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        Ekspor PDF
                    </a>
                </div>
            </div>

            <!-- Filter form -->
            <form method="get" class="user-filter-form transaction-filter-form">
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari ID atau nama pelanggan..." aria-label="Cari ID atau nama pelanggan">
                <input type="date" name="from" value="<?= e($dateFrom) ?>" aria-label="Tanggal mulai">
                <input type="date" name="to" value="<?= e($dateTo) ?>" aria-label="Tanggal akhir">
                <select name="kasir" aria-label="Filter kasir">
                    <option value="">Semua kasir</option>
                    <?php foreach ($kasirList as $k): ?>
                        <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $kasirId ? 'selected' : '' ?>><?= e($k['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="user-btn user-btn-primary" type="submit">Filter</button>
                <?php if ($search !== '' || $dateFrom !== '' || $dateTo !== '' || $kasirId > 0): ?>
                    <a class="user-btn user-btn-light" href="<?= e(appUrl('admin/transactions.php')) ?>">Reset</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table class="admin-table"><thead><tr><th>ID</th><th>Tanggal</th><th>Pelanggan</th><th>Kasir</th><th>Jumlah Item</th><th>Total</th><th>Pembayaran</th><th>Aksi</th></tr></thead><tbody>
                <?php if (!$transactions): ?><tr><td colspan="8" class="table-empty">Tidak ada transaksi yang cocok dengan filter.</td></tr><?php else: foreach ($transactions as $transaction): ?>
                    <?php
                        $payMethod  = strtolower((string)($transaction['metode_pembayaran'] ?? ''));
                        $payBadge   = match(true) {
                            str_contains($payMethod, 'tunai') || str_contains($payMethod, 'cash') => 'pay-cash',
                            str_contains($payMethod, 'qris')     => 'pay-qris',
                            str_contains($payMethod, 'transfer') => 'pay-transfer',
                            default                              => 'pay-other',
                        };
                    ?>
                    <tr>
                        <td><strong>TRX-<?= str_pad((string) $transaction['id_transaksi'], 3, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><?= $transaction['tanggal'] ? e(date('d/m/Y', strtotime($transaction['tanggal']))) : '-' ?></td>
                        <td><?= e($transaction['nama_pelanggan'] ?? 'Pelanggan tidak diketahui') ?></td>
                        <td>
                            <?php if ($transaction['nama_kasir']): ?>
                                <a class="kasir-filter-link" href="<?= e(appUrl('admin/transactions.php?' . http_build_query(array_merge(array_diff_key($paginationQuery, ['kasir'=>'']), ['kasir' => (int)$transaction['kasir_id']])))) ?>">
                                    <?= e($transaction['nama_kasir']) ?>
                                </a>
                            <?php else: ?>
                                <span style="color:#94A3B8">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format((int) $transaction['jumlah_item']) ?></td>
                        <td>Rp <?= e(number_format((float) $transaction['total_belanja'], 0, ',', '.')) ?></td>
                        <td>
                            <?php if ($transaction['metode_pembayaran']): ?>
                                <span class="pay-badge <?= $payBadge ?>"><?= e($transaction['metode_pembayaran']) ?></span>
                            <?php else: ?>
                                <span style="color:#94A3B8">—</span>
                            <?php endif; ?>
                        </td>
                        <td><a class="user-action-edit" href="<?= e(appUrl('admin/transactions.php?' . http_build_query($paginationQuery + ['page' => $page, 'view' => (int) $transaction['id_transaksi']]) . '#detail')) ?>">Detail</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody></table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="product-pagination" aria-label="Pagination transaksi">
                    <span>Halaman <?= $page ?> dari <?= $totalPages ?></span>
                    <div>
                        <?php if ($page > 1): ?><a class="user-btn user-btn-light" href="<?= e(appUrl('admin/transactions.php?' . http_build_query($paginationQuery + ['page' => $page - 1]))) ?>">Sebelumnya</a><?php endif; ?>
                        <?php if ($page < $totalPages): ?><a class="user-btn user-btn-light" href="<?= e(appUrl('admin/transactions.php?' . http_build_query($paginationQuery + ['page' => $page + 1]))) ?>">Berikutnya</a><?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </section>

        <footer class="admin-footer"><span>© <?= date('Y') ?> Tokoku</span><span>Transaksi Penjualan</span></footer>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
