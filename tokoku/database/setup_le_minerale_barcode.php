<?php
/**
 * Satu langkah: migrasi kolom barcode + seed produk Le Minerale
 * Barcode: 8996001600269
 *
 * Jalankan:
 *   php database/setup_le_minerale_barcode.php
 */
require_once __DIR__ . '/../config/database.php';

echo "=== Setup barcode Le Minerale ===\n";

// 1) Pastikan kolom barcode ada
$colCheck = $pdo->query("SHOW COLUMNS FROM tbl_produk LIKE 'barcode'");
if (!$colCheck->fetch()) {
    $pdo->exec(
        'ALTER TABLE tbl_produk
         ADD COLUMN barcode VARCHAR(64) NULL DEFAULT NULL AFTER nama_produk'
    );
    echo "[OK] Kolom barcode ditambahkan.\n";
} else {
    echo "[OK] Kolom barcode sudah ada.\n";
}

// Index unik (abaikan jika gagal)
try {
    $pdo->exec('ALTER TABLE tbl_produk ADD UNIQUE KEY uq_produk_barcode (barcode)');
    echo "[OK] Index unik barcode dibuat.\n";
} catch (Throwable $e) {
    echo "[..] Index unik barcode sudah ada / dilewati.\n";
}

// 2) Isi barcode default untuk produk lama yang belum punya
$products = $pdo->query(
    "SELECT id_produk FROM tbl_produk WHERE barcode IS NULL OR barcode = ''"
)->fetchAll();
$update = $pdo->prepare(
    'UPDATE tbl_produk SET barcode = :barcode WHERE id_produk = :id_produk'
);
foreach ($products as $row) {
    $id = (int) $row['id_produk'];
    $code = 'TK' . str_pad((string) $id, 8, '0', STR_PAD_LEFT);
    $update->execute(['barcode' => $code, 'id_produk' => $id]);
}
if (count($products) > 0) {
    echo '[OK] Barcode default diisi untuk ' . count($products) . " produk lama.\n";
}

// 3) Seed / update Le Minerale
$barcode = '8996001600269';
$name = 'Le Minerale';
$price = 5000;
$stock = 100;
$description = 'Air mineral Le Minerale kemasan botol.';

$existing = $pdo->prepare('SELECT id_produk, nama_produk FROM tbl_produk WHERE barcode = :barcode LIMIT 1');
$existing->execute(['barcode' => $barcode]);
$row = $existing->fetch();

if ($row) {
    echo "[OK] Produk dengan barcode {$barcode} sudah ada: #{$row['id_produk']} — {$row['nama_produk']}\n";
    echo "Selesai. Siap di-scan di Kasir POS.\n";
    exit(0);
}

$byName = $pdo->prepare('SELECT id_produk FROM tbl_produk WHERE nama_produk LIKE :name LIMIT 1');
$byName->execute(['name' => '%Le Minerale%']);
$foundName = $byName->fetch();

if ($foundName) {
    $upd = $pdo->prepare('UPDATE tbl_produk SET barcode = :barcode WHERE id_produk = :id');
    $upd->execute(['barcode' => $barcode, 'id' => (int) $foundName['id_produk']]);
    echo "[OK] Barcode {$barcode} dipasang ke produk Le Minerale yang sudah ada (#{$foundName['id_produk']}).\n";
    echo "Selesai. Siap di-scan di Kasir POS.\n";
    exit(0);
}

// Deteksi kolom
$cols = [];
foreach ($pdo->query('SHOW COLUMNS FROM tbl_produk')->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $cols[$c['Field']] = true;
}

$catId = null;
try {
    $cat = $pdo->query('SELECT id_kategori FROM tbl_kategori ORDER BY id_kategori ASC LIMIT 1')->fetch();
    if ($cat) {
        $catId = (int) $cat['id_kategori'];
    }
} catch (Throwable $e) {
    $catId = null;
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
echo "Selesai. Siap di-scan di Kasir POS.\n";
