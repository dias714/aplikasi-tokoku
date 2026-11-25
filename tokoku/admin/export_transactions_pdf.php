<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/database.php';

// ── Filter params ─────────────────────────────────────────────────────────────
$search   = trim((string) ($_GET['q']    ?? ''));
$dateFrom = trim((string) ($_GET['from'] ?? ''));
$dateTo   = trim((string) ($_GET['to']   ?? ''));
$kasirId  = filter_var($_GET['kasir'] ?? null, FILTER_VALIDATE_INT) ?: 0;

// Ambil nama kasir untuk label
$kasirName = '';
if ($kasirId > 0) {
    $kasirStmt = $pdo->prepare('SELECT name FROM users WHERE id = :id AND role = :role LIMIT 1');
    $kasirStmt->execute(['id' => $kasirId, 'role' => 'kasir']);
    $kasirName = (string)($kasirStmt->fetchColumn() ?: '');
}

$errors = [];
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

$where  = $errors ? ['1 = 0'] : [];
$params = [];
if ($search !== '') {
    $where[]                  = '(CAST(t.id_transaksi AS CHAR) LIKE :id_search OR p.nama_pelanggan LIKE :customer_search)';
    $params['id_search']      = '%' . $search . '%';
    $params['customer_search']= '%' . $search . '%';
}
if (!$errors && $dateFrom !== '') {
    $where[]              = 't.tanggal >= :date_from';
    $params['date_from']  = $dateFrom;
}
if (!$errors && $dateTo !== '') {
    $where[]             = 't.tanggal <= :date_to';
    $params['date_to']   = $dateTo;
}
if ($kasirId > 0) {
    $where[]              = 't.id_kasir = :kasir_id';
    $params['kasir_id']   = $kasirId;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// ── Ambil SEMUA transaksi (tanpa paginasi) ────────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT t.id_transaksi, t.tanggal, t.total_belanja, t.metode_pembayaran,
            p.nama_pelanggan, u.name AS nama_kasir,
            COUNT(d.id_detail) AS jumlah_item
     FROM tbl_transaksi t
     LEFT JOIN tbl_pelanggan p ON p.id_pelanggan  = t.id_pelanggan
     LEFT JOIN users u          ON u.id            = t.id_kasir
     LEFT JOIN tbl_detail_transaksi d ON d.id_transaksi = t.id_transaksi'
    . $whereSql .
    ' GROUP BY t.id_transaksi, t.tanggal, t.total_belanja, t.metode_pembayaran,
               p.nama_pelanggan, u.name
      ORDER BY t.tanggal DESC, t.id_transaksi DESC'
);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$stmt->execute();
$transactions = $stmt->fetchAll();

// ── Hitung ringkasan ──────────────────────────────────────────────────────────
$totalAmount = array_sum(array_column($transactions, 'total_belanja'));
$totalCount  = count($transactions);

// ── Judul / periode label ─────────────────────────────────────────────────────
$periodLabel = 'Semua Periode';
if ($dateFrom !== '' && $dateTo !== '') {
    $periodLabel = date('d/m/Y', strtotime($dateFrom)) . ' – ' . date('d/m/Y', strtotime($dateTo));
} elseif ($dateFrom !== '') {
    $periodLabel = 'Mulai ' . date('d/m/Y', strtotime($dateFrom));
} elseif ($dateTo !== '') {
    $periodLabel = 'Sampai ' . date('d/m/Y', strtotime($dateTo));
}

$kasirLabel  = $kasirName !== '' ? e($kasirName) : 'Semua Kasir';
$generatedAt = date('d/m/Y H:i');
$adminName   = e($_SESSION['name'] ?? 'Admin');
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi – Tokoku</title>
    <style>
        /* ── Base ──────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            color: #1E293B;
            background: #F5F6FA;
            padding: 24px;
        }

        /* ── Print toolbar (tidak tampil saat cetak) ─ */
        .pdf-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 12px 16px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        .pdf-toolbar h1 { font-size: 15px; color: #1E293B; flex: 1; }
        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            background: #4F46E5;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-print:hover { background: #4338CA; }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            background: #fff;
            color: #4F46E5;
            border: 2px solid #4F46E5;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-back:hover { background: #EEF2FF; }

        /* ── Halaman laporan ─────────────────────────── */
        .report-page {
            background: #fff;
            border-radius: 12px;
            padding: 32px 36px;
            box-shadow: 0 4px 20px rgba(0,0,0,.07);
            max-width: 960px;
            margin: 0 auto;
        }

        /* Header laporan */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #4F46E5;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .report-brand { display: flex; align-items: center; gap: 12px; }
        .report-brand-icon {
            width: 44px; height: 44px;
            background: #4F46E5;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .report-brand-icon svg { width: 24px; height: 24px; color: #fff; stroke: #fff; }
        .report-brand-text .brand-name { font-size: 20px; font-weight: 800; color: #1E293B; }
        .report-brand-text .brand-sub  { font-size: 11px; color: #64748B; margin-top: 1px; }
        .report-meta { text-align: right; }
        .report-meta .report-title   { font-size: 16px; font-weight: 700; color: #4F46E5; }
        .report-meta .report-period  { font-size: 11px; color: #64748B; margin-top: 4px; }
        .report-meta .report-genby   { font-size: 11px; color: #94A3B8; margin-top: 2px; }

        /* Ringkasan */
        .summary-row {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }
        .summary-card {
            flex: 1;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 14px 18px;
            background: #F8FAFC;
        }
        .summary-card .sc-label { font-size: 11px; color: #64748B; text-transform: uppercase; letter-spacing: .5px; }
        .summary-card .sc-value { font-size: 20px; font-weight: 700; color: #1E293B; margin-top: 4px; }
        .summary-card.highlight { background: #EEF2FF; border-color: #A5B4FC; }
        .summary-card.highlight .sc-value { color: #4F46E5; }

        /* Filter info */
        .filter-info {
            font-size: 11px;
            color: #94A3B8;
            margin-bottom: 18px;
            padding: 8px 12px;
            background: #F8FAFC;
            border-radius: 6px;
            border-left: 3px solid #CBD5E1;
        }

        /* Tabel */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .report-table thead tr {
            background: #4F46E5;
            color: #fff;
        }
        .report-table thead th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            letter-spacing: .4px;
        }
        .report-table thead th:last-child,
        .report-table tbody td:last-child { text-align: right; }
        .report-table tbody tr { border-bottom: 1px solid #F1F5F9; }
        .report-table tbody tr:nth-child(even) { background: #F8FAFC; }
        .report-table tbody tr:hover { background: #EEF2FF; }
        .report-table tbody td { padding: 9px 12px; vertical-align: middle; }
        .report-table tfoot tr { border-top: 2px solid #4F46E5; }
        .report-table tfoot td {
            padding: 10px 12px;
            font-weight: 700;
            font-size: 13px;
            background: #EEF2FF;
        }

        .trx-id    { font-weight: 700; color: #4F46E5; }
        .badge-pay {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            background: #E0E7FF;
            color: #4338CA;
        }
        .badge-pay.cash  { background: #DCFCE7; color: #16A34A; }
        .badge-pay.qris  { background: #FEF3C7; color: #D97706; }
        .badge-pay.transfer { background: #E0F2FE; color: #0284C7; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94A3B8;
            font-size: 13px;
        }

        /* Footer laporan */
        .report-footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #94A3B8;
        }

        /* ── Print styles ────────────────────────────── */
        @media print {
            body { background: #fff; padding: 0; font-size: 11px; }
            .pdf-toolbar { display: none !important; }
            .report-page {
                box-shadow: none;
                border-radius: 0;
                padding: 20px;
                max-width: 100%;
            }
            .report-table tbody tr:hover { background: transparent; }
            .summary-card { break-inside: avoid; }
            @page {
                margin: 15mm;
                size: A4 landscape;
            }
        }
    </style>
</head>
<body>

    <!-- Toolbar (hanya tampil di browser, bukan saat print) -->
    <div class="pdf-toolbar">
        <h1>📄 Pratinjau Laporan Transaksi</h1>
        <a class="btn-back" href="<?= e(appUrl('admin/transactions.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : ''))) ?>">
            ← Kembali
        </a>
        <button class="btn-print" onclick="window.print()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Cetak / Simpan PDF
        </button>
    </div>

    <!-- Halaman laporan -->
    <div class="report-page">

        <!-- Header -->
        <div class="report-header">
            <div class="report-brand">
                <div class="report-brand-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                </div>
                <div class="report-brand-text">
                    <div class="brand-name">TOKOKU</div>
                    <div class="brand-sub">Sistem Manajemen Toko</div>
                </div>
            </div>
            <div class="report-meta">
                <div class="report-title">LAPORAN TRANSAKSI PENJUALAN</div>
                <div class="report-period">Periode: <?= e($periodLabel) ?></div>
                <div class="report-period">Kasir: <?= $kasirLabel ?></div>
                <?php if ($search !== ''): ?>
                    <div class="report-period">Pencarian: "<?= e($search) ?>"</div>
                <?php endif; ?>
                <div class="report-genby">Dibuat oleh: <?= $adminName ?> · <?= $generatedAt ?> WIB</div>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="summary-row">
            <div class="summary-card">
                <div class="sc-label">Total Transaksi</div>
                <div class="sc-value"><?= number_format($totalCount) ?></div>
            </div>
            <div class="summary-card highlight">
                <div class="sc-label">Total Pendapatan</div>
                <div class="sc-value">Rp <?= number_format($totalAmount, 0, ',', '.') ?></div>
            </div>
            <?php if ($totalCount > 0): ?>
            <div class="summary-card">
                <div class="sc-label">Rata-rata per Transaksi</div>
                <div class="sc-value">Rp <?= number_format($totalAmount / $totalCount, 0, ',', '.') ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Filter info -->
        <?php if ($search !== '' || $dateFrom !== '' || $dateTo !== '' || $kasirId > 0): ?>
        <div class="filter-info">
            Filter aktif:
            <?php if ($kasirId > 0): ?> Kasir: <strong><?= $kasirLabel ?></strong><?php endif; ?>
            <?php if ($search !== ''): ?> · Kata kunci "<strong><?= e($search) ?></strong>"<?php endif; ?>
            <?php if ($dateFrom !== ''): ?> · Dari <strong><?= e(date('d/m/Y', strtotime($dateFrom))) ?></strong><?php endif; ?>
            <?php if ($dateTo !== ''): ?> · Sampai <strong><?= e(date('d/m/Y', strtotime($dateTo))) ?></strong><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Tabel transaksi -->
        <?php if (!$transactions): ?>
            <div class="empty-state">Tidak ada data transaksi yang cocok dengan filter yang dipilih.</div>
        <?php else: ?>
        <table class="report-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>ID Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Kasir</th>
                    <th>Item</th>
                    <th>Pembayaran</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $i => $t): ?>
                <?php
                    $payMethod = strtolower((string)($t['metode_pembayaran'] ?? ''));
                    $badgeClass = match(true) {
                        str_contains($payMethod, 'tunai') || str_contains($payMethod, 'cash') => 'cash',
                        str_contains($payMethod, 'qris')  => 'qris',
                        str_contains($payMethod, 'transfer') => 'transfer',
                        default => ''
                    };
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="trx-id">TRX-<?= str_pad((string)$t['id_transaksi'], 3, '0', STR_PAD_LEFT) ?></td>
                    <td><?= $t['tanggal'] ? e(date('d/m/Y', strtotime($t['tanggal']))) : '-' ?></td>
                    <td><?= e($t['nama_pelanggan'] ?? 'Pelanggan Umum') ?></td>
                    <td><?= e($t['nama_kasir'] ?? '—') ?></td>
                    <td><?= number_format((int)$t['jumlah_item']) ?></td>
                    <td>
                        <?php if ($t['metode_pembayaran']): ?>
                            <span class="badge-pay <?= e($badgeClass) ?>"><?= e($t['metode_pembayaran']) ?></span>
                        <?php else: ?>
                            <span style="color:#94A3B8">—</span>
                        <?php endif; ?>
                    </td>
                    <td>Rp <?= e(number_format((float)$t['total_belanja'], 0, ',', '.')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" style="text-align:right; color:#4F46E5;">TOTAL KESELURUHAN</td>
                    <td>Rp <?= e(number_format($totalAmount, 0, ',', '.')) ?></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>

        <!-- Footer laporan -->
        <div class="report-footer">
            <span>© <?= date('Y') ?> Tokoku – Laporan dicetak secara otomatis oleh sistem</span>
            <span>Halaman laporan ini bersifat resmi dan dapat digunakan sebagai dokumen internal</span>
        </div>
    </div>

</body>
</html>
