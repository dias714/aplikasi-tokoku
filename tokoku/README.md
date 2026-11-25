# Tokoku

## Gambar produk dan belanja pelanggan

Migrasi untuk menambahkan deskripsi/gambar produk dan menghubungkan pelanggan ke riwayat transaksinya:

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe database\migrations\2026_11_11_add_product_media_and_customer_link.php
```

Server PHP Laragon perlu mengaktifkan ekstensi `fileinfo` dan `gd` (termasuk dukungan WEBP). Admin mengelola foto melalui formulir **Admin → Produk**; file gambar tervalidasi disimpan di `uploads\products\` dan disajikan melalui `product_image.php`. Folder upload tidak dapat dieksekusi atau dijelajahi langsung.

Pelanggan dapat melihat katalog, detail produk, keranjang, checkout, dan riwayat pesanan setelah masuk dengan role `pelanggan`. Checkout membuat transaksi dan mengurangi stok dalam satu transaksi database. Skema saat ini belum memiliki status pesanan atau pembayaran online.

## Transaksi kasir dan nota

Jalankan migrasi berikut satu kali untuk menyimpan kasir, uang diterima, dan kembalian pada transaksi:

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe database\migrations\2026_11_11_add_cashier_payment_details.php
```

Setelah login dengan role `kasir`, buka **Transaksi Baru** untuk memilih produk, pelanggan (opsional), dan jumlah uang tunai. Sistem memeriksa stok/harga kembali dan menyimpan transaksi serta pengurangan stok secara atomik. Total, subtotal barang, dan kembalian diperbarui langsung saat jumlah produk atau pembayaran diubah.

**Cetak nota thermal 50 mm:** Aplikasi mengukur tinggi nota + buffer, lalu set `@page` 50 mm. Di dialog print:

1. Pilih printer thermal (contoh EPSON TM-T82X).
2. Paper size: **50 mm** / Custom (jangan Postcard atau A4).
3. Scale: **100% / Actual size**, Margin: **None / 0**.
4. Matikan centang **Headers and footers**.

Jika driver hanya menawarkan A4, ubah paper size di preferensi/driver printer.

**Barcode produk (Le Minerale 8996001600269):**

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe database\migrations\2026_11_23_add_product_barcode.php
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe database\seed_le_minerale.php
```

Produk bisa di-scan di kasir. Riwayat dashboard hanya menampilkan transaksi kasir yang sedang login.
