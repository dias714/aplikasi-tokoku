<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('kasir');
require_once __DIR__ . '/../config/database.php';

$transactionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$receiptStmt = $pdo->prepare(
    'SELECT t.id_transaksi, t.tanggal, t.total_belanja, t.metode_pembayaran,
            t.uang_diterima, t.kembalian, p.nama_pelanggan,
            u.name AS nama_kasir
     FROM tbl_transaksi t
     LEFT JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan
     LEFT JOIN users u ON u.id = t.id_kasir
     WHERE t.id_transaksi = :id_transaksi AND t.id_kasir = :id_kasir
     LIMIT 1'
);
$receiptStmt->execute([
    'id_transaksi' => $transactionId && $transactionId > 0 ? $transactionId : 0,
    'id_kasir' => (int) $_SESSION['user_id'],
]);
$receipt = $receiptStmt->fetch();

if (!$receipt) {
    http_response_code(404);
}

$items = [];
if ($receipt) {
    $itemStmt = $pdo->prepare(
        'SELECT nama_produk, jumlah, harga,
                (COALESCE(jumlah, 0) * COALESCE(harga, 0)) AS subtotal
         FROM tbl_detail_transaksi
         WHERE id_transaksi = :id_transaksi
         ORDER BY id_detail'
    );
    $itemStmt->execute(['id_transaksi' => (int) $receipt['id_transaksi']]);
    $items = $itemStmt->fetchAll();
}

$trxCode = $receipt
    ? 'TRX-' . str_pad((string) $receipt['id_transaksi'], 3, '0', STR_PAD_LEFT)
    : '';

$pageTitle = $receipt
    ? 'Nota ' . $trxCode . ' - Tokoku'
    : 'Nota tidak ditemukan - Tokoku';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
/* Preview di layar */
.receipt-page {
    max-width: 420px;
    margin: 24px auto;
    padding: 0 16px 40px;
}
.receipt-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}
.receipt-print-help {
    font-size: 12px;
    color: #64748B;
    margin: 0 0 16px;
    line-height: 1.5;
}
.receipt-print-help strong {
    color: #0F172A;
}
.cashier-receipt {
    width: 54mm;                 /* cocok untuk roll 58mm (umum di Indonesia) */
    max-width: 100%;
    margin: 0 auto;
    padding: 2mm 1.8mm;
    background: #fff;
    color: #111;
    font-family: "Courier New", Courier, ui-monospace, monospace;
    font-size: 10.5px;
    line-height: 1.28;
    border: 1px dashed #CBD5E1;
    box-sizing: border-box;
    overflow: hidden;
}
.receipt-header {
    text-align: center;
    margin-bottom: 4px;
}
.receipt-header h1 {
    margin: 0;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 1px;
}
.receipt-header p {
    margin: 1px 0 0;
    font-size: 9.5px;
}
.receipt-rule {
    border: none;
    border-top: 1px dashed #333;
    margin: 4px 0;
}
.receipt-meta {
    font-size: 9.5px;
}
.receipt-meta div {
    display: flex;
    justify-content: space-between;
    gap: 4px;
    margin-bottom: 1px;
}
.receipt-meta span {
    color: #333;
}
.receipt-items {
    width: 100%;
    border-collapse: collapse;
    font-size: 9.5px;
    table-layout: fixed;
}
.receipt-items th,
.receipt-items td {
    padding: 1px 0;
    vertical-align: top;
    text-align: left;
    word-break: break-word;
}
.receipt-items th {
    border-bottom: 1px solid #333;
    font-weight: 700;
}
.receipt-items .col-qty { width: 11%; text-align: center; }
.receipt-items .col-price,
.receipt-items .col-sub { width: 22%; text-align: right; }
.receipt-item-name {
    display: block;
    word-break: break-word;
}
.receipt-totals {
    font-size: 10.5px;
}
.receipt-totals div {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1px;
}
.receipt-totals .receipt-change {
    font-weight: 800;
    font-size: 11.5px;
    margin-top: 3px;
}
.receipt-footer {
    text-align: center;
    font-size: 9.5px;
    margin-top: 3px;
}
.receipt-footer strong {
    display: block;
    margin-bottom: 1px;
}
.receipt-barcode-center {
    text-align: center;
    margin-top: 5px;
}
.receipt-barcode-center svg {
    max-width: 100%;
    height: 30px;
    display: block;
    margin: 0 auto;
}
.receipt-barcode-center .code-text {
    font-size: 8.5px;
    letter-spacing: 0.4px;
    margin-top: 1px;
}

/* Mode cetak thermal 50mm – sembunyikan semua kecuali nota */
body.cashier-print-mode,
body.cashier-print-mode * {
    visibility: hidden !important;
}
body.cashier-print-mode .receipt-page,
body.cashier-print-mode .receipt-page .cashier-receipt,
body.cashier-print-mode .receipt-page .cashier-receipt * {
    visibility: visible !important;
}
body.cashier-print-mode .receipt-page {
    position: absolute;
    left: 0;
    top: 0;
    margin: 0 !important;
    padding: 0 !important;
    max-width: none !important;
    width: 58mm !important;
}
body.cashier-print-mode .receipt-actions,
body.cashier-print-mode .receipt-print-help {
    display: none !important;
}
body.cashier-print-mode .cashier-receipt {
    width: 54mm !important;
    max-width: 54mm !important;
    border: none !important;
    margin: 0 !important;
    padding: 1.5mm 1.5mm !important;
    box-shadow: none !important;
    overflow: visible !important;
}

/* CSS cetak – ketat, tanpa ruang kosong berlebih */
@media print {
    @page {
        size: 58mm auto;
        margin: 0 !important;
    }
    html, body {
        width: 58mm !important;
        min-width: 58mm !important;
        max-width: 58mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body * {
        visibility: hidden;
    }
    .receipt-page,
    .receipt-page .cashier-receipt,
    .receipt-page .cashier-receipt * {
        visibility: visible !important;
    }
    .receipt-actions,
    .receipt-print-help,
    .modern-sidebar,
    .dashboard-shell-layout,
    .no-print,
    header, nav, footer, aside {
        display: none !important;
        visibility: hidden !important;
    }
    .receipt-page {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        max-width: none !important;
        width: 58mm !important;
    }
    .cashier-receipt {
        width: 54mm !important;
        max-width: 54mm !important;
        border: none !important;
        margin: 0 !important;
        padding: 1.5mm 1.5mm !important;
        box-shadow: none !important;
        overflow: visible !important;
        page-break-inside: avoid;
    }
}
</style>

<main class="receipt-page" <?= $receipt ? 'data-printable="true"' : '' ?>>
    <div class="receipt-actions no-print">
        <a class="user-btn user-btn-light" href="<?= e(appUrl('kasir/dashboard.php')) ?>">Kembali ke Dashboard</a>
        <a class="user-btn user-btn-light" href="<?= e(appUrl('kasir/checkout.php')) ?>">Transaksi Baru</a>
        <?php if ($receipt): ?>
            <button class="user-btn user-btn-primary" type="button" data-print-receipt>Cetak Nota</button>
        <?php endif; ?>
    </div>

    <?php if ($receipt): ?>
        <p class="receipt-print-help no-print">
            <strong>Cara cetak:</strong> Klik <strong>Cetak Nota</strong> → jendela baru terbuka (izinkan pop-up).<br>
            Di dialog print: pilih printer thermal, Scale <strong>100%</strong>, Margins <strong>None</strong>,
            dan <strong>matikan Headers and footers</strong>.
        </p>
    <?php endif; ?>

    <?php if (!$receipt): ?>
        <section class="admin-panel cashier-receipt receipt-not-found" style="width:auto;padding:24px;text-align:center;">
            <h1>Nota tidak ditemukan</h1>
            <p>Nota tidak tersedia atau bukan transaksi yang dibuat oleh akun kasir ini.</p>
        </section>
    <?php else: ?>
        <article class="cashier-receipt">
            <header class="receipt-header">
                <h1>TOKOKU</h1>
                <p>Nota Pembayaran</p>
                <p>Terminal Kasir POS</p>
            </header>

            <div class="receipt-rule"></div>

            <div class="receipt-meta">
                <div><span>No</span><strong><?= e($trxCode) ?></strong></div>
                <div><span>Tgl</span><strong><?= $receipt['tanggal'] ? e(date('d/m/Y H:i', strtotime($receipt['tanggal'] . ' ' . date('H:i')))) : e(date('d/m/Y H:i')) ?></strong></div>
                <div><span>Kasir</span><strong><?= e($receipt['nama_kasir'] ?? $_SESSION['name'] ?? 'Kasir') ?></strong></div>
                <div><span>Pelanggan</span><strong><?= e($receipt['nama_pelanggan'] ?: 'Umum') ?></strong></div>
            </div>

            <div class="receipt-rule"></div>

            <table class="receipt-items">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-price">Harga</th>
                        <th class="col-sub">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><span class="receipt-item-name"><?= e($item['nama_produk'] ?? 'Produk') ?></span></td>
                        <td class="col-qty"><?= number_format((int) $item['jumlah']) ?></td>
                        <td class="col-price"><?= e(number_format((float) $item['harga'], 0, ',', '.')) ?></td>
                        <td class="col-sub"><?= e(number_format((float) $item['subtotal'], 0, ',', '.')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="receipt-rule"></div>

            <div class="receipt-totals">
                <div><span>Total</span><strong>Rp <?= e(number_format((float) $receipt['total_belanja'], 0, ',', '.')) ?></strong></div>
                <div><span>Bayar</span><strong>Rp <?= e(number_format((float) $receipt['uang_diterima'], 0, ',', '.')) ?></strong></div>
                <div class="receipt-change"><span>Kembali</span><strong>Rp <?= e(number_format((float) $receipt['kembalian'], 0, ',', '.')) ?></strong></div>
                <div><span>Metode</span><strong><?= e($receipt['metode_pembayaran'] ?? 'Tunai') ?></strong></div>
            </div>

            <div class="receipt-rule"></div>

            <footer class="receipt-footer">
                <strong>Terima kasih</strong>
                <span>Barang dapat ditukar sesuai kebijakan toko.</span>
            </footer>

            <div class="receipt-barcode-center">
                <svg id="receipt-trx-barcode" data-barcode-value="<?= e($trxCode) ?>"></svg>
                <div class="code-text"><?= e($trxCode) ?></div>
            </div>
        </article>
    <?php endif; ?>
</main>

<?php if ($receipt): ?>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var svg = document.getElementById('receipt-trx-barcode');
    if (svg && typeof JsBarcode === 'function') {
        try {
            JsBarcode(svg, svg.getAttribute('data-barcode-value') || '', {
                format: 'CODE128',
                width: 1.15,
                height: 28,
                displayValue: false,
                margin: 0,
                background: 'transparent'
            });
        } catch (e) {}
    }
});
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
