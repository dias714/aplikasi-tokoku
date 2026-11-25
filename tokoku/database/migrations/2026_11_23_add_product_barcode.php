<?php
/**
 * Tambah kolom barcode pada tbl_produk (opsional, unik bila diisi).
 * Jalankan sekali: php database/migrations/2026_11_23_add_product_barcode.php
 */
require_once __DIR__ . '/../../config/database.php';

$columnStatement = $pdo->query('SHOW COLUMNS FROM tbl_produk');
$columns = [];
foreach ($columnStatement->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $columns[$column['Field']] = true;
}

if (!isset($columns['barcode'])) {
    $pdo->exec(
        'ALTER TABLE tbl_produk
         ADD COLUMN barcode VARCHAR(64) NULL DEFAULT NULL AFTER nama_produk,
         ADD UNIQUE KEY uq_produk_barcode (barcode)'
    );
    echo "Kolom barcode ditambahkan ke tbl_produk.\n";
} else {
    echo "Kolom barcode sudah ada.\n";
}

// Isi barcode default untuk produk yang belum punya (format: TK + id 8 digit)
$products = $pdo->query(
    'SELECT id_produk FROM tbl_produk WHERE barcode IS NULL OR barcode = \'\''
)->fetchAll();

$update = $pdo->prepare(
    'UPDATE tbl_produk SET barcode = :barcode WHERE id_produk = :id_produk'
);

foreach ($products as $row) {
    $id = (int) $row['id_produk'];
    $code = 'TK' . str_pad((string) $id, 8, '0', STR_PAD_LEFT);
    $update->execute(['barcode' => $code, 'id_produk' => $id]);
}

echo 'Barcode default diisi untuk ' . count($products) . " produk.\n";
echo "Migrasi barcode selesai.\n";
