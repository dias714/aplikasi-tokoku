<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/auth.php';

// ========================================
// LOGOUT
// ========================================

if (isset($_GET['logout'])) {
    logoutUser();
}

// ========================================
// REDIRECT IF ALREADY LOGGED IN
// ========================================

if (isset($_SESSION['user_id'])) {
    redirectByRole();
}

// ========================================
// MESSAGE
// ========================================

$errorMessage = $_SESSION['access_error'] ?? '';
unset($_SESSION['access_error']);

$emailValue = '';

// ========================================
// LOGIN PROCESS
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $emailValue = strtolower(trim($_POST['email'] ?? ''));
    $passwordInput = $_POST['password'] ?? '';
    $csrfInput = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfInput) ||
        !hash_equals($_SESSION['csrf_token'], $csrfInput)
    ) {

        $errorMessage = 'Permintaan tidak valid. Silakan coba lagi.';
    } elseif (
        $emailValue === '' ||
        $passwordInput === '' ||
        !filter_var($emailValue, FILTER_VALIDATE_EMAIL)
    ) {

        $errorMessage = 'Email dan password harus diisi dengan benar.';
    } else {

        try {

            require_once __DIR__ . '/config/database.php';

            $stmt = $pdo->prepare(
                "SELECT id, name, email, password, role
                 FROM users
                 WHERE email = :email
                 LIMIT 1"
            );

            $stmt->execute([
                'email' => $emailValue
            ]);

            $user = $stmt->fetch();

            $allowedRoles = [
                'admin',
                'kasir',
                'pelanggan'
            ];

            if (
                $user &&
                password_verify(
                    $passwordInput,
                    $user['password']
                ) &&
                in_array($user['role'], $allowedRoles, true)
            ) {

                // Regenerate session ID
                session_regenerate_id(true);

                // Save authentication data
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Refresh CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // Redirect based on role
                redirectByRole();
            } else {

                $errorMessage = 'Email atau password salah. Silakan periksa kembali.';
            }
        } catch (Throwable $e) {

            error_log($e->getMessage());

            $errorMessage = 'Terjadi kesalahan saat memproses login.';
        }
    }
}

// ========================================
// PAGE
// ========================================

$pageTitle = 'Masuk ke Akun - Tokoku';

require_once __DIR__ . '/includes/header.php';

?>

<main class="auth-modern-container">

    <div class="auth-modern-card">
        <!-- LEFT: BRANDING & HIGHLIGHTS -->
        <div class="auth-side-brand">
            <div class="auth-brand-header">
                <div class="brand-logo-modern">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                </div>
                <h2>Tokoku</h2>
                <span class="auth-brand-tag">Katalog Lengkap &amp; Terstruktur</span>
            </div>

            <div class="auth-feature-list">
                <div class="auth-feat-item">
                    <span class="auth-feat-icon">📦</span>
                    <div>
                        <strong>Katalog Terpadu &amp; Rinci</strong>
                        <span>Navigasi kategori lengkap, filter harga, dan stok realtime.</span>
                    </div>
                </div>
                <div class="auth-feat-item">
                    <span class="auth-feat-icon">💳</span>
                    <div>
                        <strong>Multi-Peran &amp; Transaksi</strong>
                        <span>Mendukung belanja pelanggan, kasir POS, dan administrasi toko.</span>
                    </div>
                </div>
                <div class="auth-feat-item">
                    <span class="auth-feat-icon">🛡️</span>
                    <div>
                        <strong>Aman &amp; Terpercaya</strong>
                        <span>Sistem keamanan sesi, CSRF token, dan hash password terlindungi.</span>
                    </div>
                </div>
            </div>

            <div class="auth-guest-cta">
                <p>Hanya ingin melihat produk tanpa login?</p>
                <a href="<?= e(appUrl('customer/index.php')) ?>" class="btn-guest-explore">
                    <span>Jelajahi Katalog Toko Sekarang →</span>
                </a>
            </div>
        </div>

        <!-- RIGHT: LOGIN FORM -->
        <div class="auth-form-panel">
            <div class="auth-form-heading">
                <h1>Selamat Datang</h1>
                <p>Silakan masuk untuk mengakses fitur toko sesuai hak akses Anda.</p>
            </div>

            <!-- QUICK DEMO LOGINS -->
            <div class="demo-logins-box">
                <span class="demo-box-lbl">⚡ Akun Demo / Uji Coba Cepat:</span>
                <div class="demo-pills-row">
                    <button type="button" class="demo-pill pill-cust" onclick="fillDemo('pelanggan@tokoku.com', 'password123')">
                        <span>👤 Pelanggan</span>
                    </button>
                    <button type="button" class="demo-pill pill-cashier" onclick="fillDemo('kasir@tokoku.com', 'password123')">
                        <span>💳 Kasir</span>
                    </button>
                    <button type="button" class="demo-pill pill-admin" onclick="fillDemo('admin123@gmail.com', 'password123')">
                        <span>⚙️ Admin</span>
                    </button>
                </div>
            </div>

            <!-- ERROR MESSAGE -->
            <?php if ($errorMessage !== ''): ?>
                <div class="user-alert user-alert-error" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                    </svg>
                    <span><?= e($errorMessage) ?></span>
                </div>
            <?php endif; ?>

            <!-- LOGIN FORM -->
            <form method="POST" action="<?= e(appUrl('index.php')) ?>" id="loginForm" class="auth-form-body">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                <!-- EMAIL -->
                <div class="form-group-modern">
                    <label for="email">Alamat Email</label>
                    <div class="input-modern-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="nama@email.com"
                            value="<?= e($emailValue) ?>"
                            maxlength="255"
                            autocomplete="username"
                            required>
                    </div>
                </div>

                <!-- PASSWORD -->
                <div class="form-group-modern">
                    <label for="password">Kata Sandi</label>
                    <div class="input-modern-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Minimal 8 karakter"
                            autocomplete="current-password"
                            required>
                        <button
                            type="button"
                            class="password-toggle-btn"
                            id="togglePassword"
                            aria-label="Tampilkan password"
                            aria-pressed="false">
                            Tampilkan
                        </button>
                    </div>
                </div>

                <div id="rfid-login-box" style="margin-bottom:18px;padding:14px;background:#F8FAFC;border:1px dashed #6366F1;border-radius:12px;">
                    <label for="rfid-card-input" style="display:block;font-size:12px;font-weight:700;color:#312E81;margin-bottom:6px;">
                        Tap Kartu RFID (Admin / Kasir / Pengguna)
                    </label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input
                            id="rfid-card-input"
                            type="password"
                            autocomplete="off"
                            placeholder="Tempelkan kartu RFID ke reader..."
                            style="flex:1;padding:10px 12px;border:1.5px solid #6366F1;border-radius:10px;font-size:14px;background:#fff;">
                        <button
                            type="button"
                            id="rfid-toggle-visibility"
                            style="padding:10px 12px;border:1px solid #CBD5E1;border-radius:10px;background:#fff;font-size:12px;cursor:pointer;">
                            Lihat
                        </button>
                    </div>
                    <p id="rfid-login-status" style="margin:6px 0 0;font-size:12px;color:#64748B;">
                        Siap membaca UID kartu (otomatis masuk setelah Enter).
                    </p>
                </div>

                <!-- LOGIN BUTTON -->
                <button
                    type="submit"
                    class="btn-modern-login"
                    id="loginButton">
                    <span class="button-text">Masuk ke Sistem</span>
                    <span class="button-loading">Memproses...</span>
                </button>
            </form>

            <div class="auth-card-footer">
                <a href="<?= e(appUrl('customer/index.php')) ?>" class="footer-catalog-link">
                    Kembali ke Beranda Katalog Produk →
                </a>
            </div>
        </div>
    </div>

</main>

<script>
    function fillDemo(email, pass) {
        const emailInput = document.getElementById('email');
        const passInput = document.getElementById('password');
        if (emailInput && passInput) {
            emailInput.value = email;
            passInput.value = pass;
            passInput.focus();
        }
    }
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var rfidInput = document.getElementById('rfid-card-input');
    var rfidStatus = document.getElementById('rfid-login-status');
    var toggleBtn = document.getElementById('rfid-toggle-visibility');
    var csrfInput = document.querySelector('input[name="csrf_token"]');

    if (toggleBtn && rfidInput) {
        toggleBtn.addEventListener('click', function () {
            var isPass = rfidInput.type === 'password';
            rfidInput.type = isPass ? 'text' : 'password';
            toggleBtn.textContent = isPass ? 'Sembunyi' : 'Lihat';
        });
    }

    if (rfidInput) {
        rfidInput.addEventListener('keydown', async function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();

            var uid = (rfidInput.value || '').trim();
            rfidInput.value = '';
            if (!uid) return;

            if (rfidStatus) {
                rfidStatus.textContent = 'Memverifikasi kartu RFID...';
                rfidStatus.style.color = '#4338CA';
            }

            try {
                var res = await fetch('<?= e(appUrl('rfid_login.php')) ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        rfid_uid: uid,
                        csrf_token: csrfInput ? csrfInput.value : ''
                    })
                });
                var data = await res.json();

                if (!res.ok || !data.ok || !data.redirect_url) {
                    if (rfidStatus) {
                        rfidStatus.textContent = data.message || 'Kartu RFID tidak dikenali.';
                        rfidStatus.style.color = '#E11D48';
                    }
                    return;
                }

                if (rfidStatus) {
                    rfidStatus.textContent = data.message + ' Mengalihkan...';
                    rfidStatus.style.color = '#059669';
                }
                window.location.href = data.redirect_url;
            } catch (err) {
                if (rfidStatus) {
                    rfidStatus.textContent = 'Gagal menghubungi server untuk login RFID.';
                    rfidStatus.style.color = '#E11D48';
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>