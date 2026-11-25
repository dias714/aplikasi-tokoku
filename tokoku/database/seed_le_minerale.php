<?php
/**
 * Tambah produk Le Minerale + barcode 8996001600269
 * Jalankan: php database/seed_le_minerale.php
 */
require_once __DIR__ . '/../config/database.php';

// Pastikan kolom barcode ada
$colCheck = $pdo->query("SHOW COLUMNS FROM tbl_produk LIKE 'barcode'");
if (!$colCheck->fetch()) {
    $pdo->exec(
        'ALTER TABLE tbl_produk
         ADD COLUMN barcode VARCHAR(64) NULL DEFAULT NULL AFTER nama_produk'
    );
    // Index unik (abaikan jika gagal karena data lama)
    try {
        $pdo->exec('ALTER TABLE tbl_produk ADD UNIQUE KEY uq_produk_barcode (barcode)');
    } catch (Throwable $e) {
        // mungkin sudah ada / duplikat — lanjut
    }
    echo "Kolom barcode ditambahkan.\n";
}

$barcode = '8996001600269';
$name = 'Le Minerale';
$price = 5000;   // Rp 5.000 (bisa diubah di admin)
$stock = 100;
$description = 'Air mineral Le Minerale kemasan botol.';

// Cek apakah barcode sudah ada
$existing = $pdo->prepare('SELECT id_produk, nama_produk FROM tbl_produk WHERE barcode = :barcode LIMIT 1');
$existing->execute(['barcode' => $barcode]);
$row = $existing->fetch();

if ($row) {
    echo "Produk dengan barcode {$barcode} sudah ada: #{$row['id_produk']} — {$row['nama_produk']}\n";
    exit(0);
}

// Cek nama mirip
$byName = $pdo->prepare('SELECT id_produk FROM tbl_produk WHERE nama_produk LIKE :name LIMIT 1');
$byName->execute(['name' => '%Le Minerale%']);
$foundName = $byName->fetch();

if ($foundName) {
    $upd = $pdo->prepare(
        'UPDATE tbl_produk SET barcode = :barcode WHERE id_produk = :id'
    );
    $upd->execute([
        'barcode' => $barcode,
        'id' => (int) $foundName['id_produk'],
    ]);
    echo "Barcode {$barcode} dipasang ke produk Le Minerale yang sudah ada (#{$foundName['id_produk']}).\n";
    exit(0);
}

// Ambil kategori pertama (jika ada)
$catId = null;
try {
    $cat = $pdo->query('SELECT id_kategori FROM tbl_kategori ORDER BY id_kategori ASC LIMIT 1')->fetch();
    if ($cat) {
        $catId = (int) $cat['id_kategori'];
    }
} catch (Throwable $e) {
    $catId = null;
}

// Deteksi kolom yang tersedia
$cols = [];
foreach ($pdo->query('SHOW COLUMNS FROM tbl_produk')->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $cols[$c['Field']] = true;
}

$fields = ['nama_produk', 'harga', 'stok'];
$values = [':nama', ':harga', ':stok'];
$params = [
    'nama' => $name,
    'harga' => $price,
    'stok' => $stock,
];

if (isset($cols['barcode'])) {
    $fields[] = 'barcode';
    $values[] = ':barcode';
    $params['barcode'] = $barcode;
}
if (isset($cols['deskripsi'])) {
    $fields[] = 'deskripsi';
    $values[] = ':deskripsi';
    $params['deskripsi'] = $description;
}
if (isset($cols['id_kategori']) && $catId) {
    $fields[] = 'id_kategori';
    $values[] = ':id_kategori';
    $params['id_kategori'] = $catId;
}

$sql = 'INSERT INTO tbl_produk (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$id = (int) $pdo->lastInsertId();
echo "Produk berhasil ditambahkan.\n";
echo "  ID      : {$id}\n";
echo "  Nama    : {$name}\n";
echo "  Barcode : {$barcode}\n";
echo "  Harga   : Rp " . number_format($price, 0, ',', '.') . "\n";
echo "  Stok    : {$stock}\n";
echo "Selesai. Silakan cek di Kasir POS / Admin Produk.\n";
