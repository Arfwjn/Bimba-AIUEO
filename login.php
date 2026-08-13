<?php
// login.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

$error = '';
$success = '';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Process Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Validasi keamanan (CSRF Token) gagal. Silakan coba lagi.';
    } else {
        $action = $_POST['action'] ?? 'login';

        if ($action === 'login') {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (!empty($username) && !empty($password)) {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id_admin'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama_lengkap'] = $user['nama'];
                    $_SESSION['role'] = 'admin';

                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Username atau Password salah. Silakan coba lagi.';
                }
            } else {
                $error = 'Harap isi semua kolom username dan password.';
            }
        } elseif ($action === 'register') {
            $username = trim($_POST['username'] ?? '');
            $nama = trim($_POST['nama_lengkap'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (!empty($username) && !empty($password) && !empty($nama)) {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin WHERE username = ?");
                $stmt->execute([$username]);
                $res = $stmt->fetch();

                if ($res['count'] > 0) {
                    $error = 'Username sudah terdaftar. Gunakan username lain.';
                } else {
                    $passHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmtIns = $pdo->prepare("INSERT INTO admin (nama, username, password) VALUES (?, ?, ?)");
                    $stmtIns->execute([$nama, $username, $passHash]);
                    $success = 'Akun Admin berhasil dibuat! Silakan login dengan akun Anda.';
                }
            } else {
                $error = 'Harap lengkapi semua field registrasi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - biMBA AIUEO Unit Kebanggan</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-card {
            background-color: var(--surface-color);
            border: 3px solid var(--border-color);
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
            padding: 40px 32px;
            box-shadow: 6px 6px 0 var(--border-color);
        }
        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-brand-icon {
            width: 64px;
            height: 64px;
            background-color: var(--border-color);
            color: #FFFFFF;
            font-family: var(--font-sans);
            font-size: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin: 0 auto 16px;
        }
        .tab-buttons {
            display: flex;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 24px;
        }
        .tab-btn {
            flex: 1;
            padding: 10px;
            background: none;
            border: none;
            font-family: var(--font-sans);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        .tab-btn.active {
            border-bottom-color: var(--border-color);
            color: var(--border-color);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="login-brand-icon">B</div>
        <h1 style="font-size: 24px; font-weight: 700;">biMBA AIUEO</h1>
        <div style="font-family: var(--font-sans); font-size: 13px; color: var(--text-muted);">
            Sistem Administrasi Unit Kebanggan
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="material-symbols-outlined">error</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <span class="material-symbols-outlined">check_circle</span>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <div class="tab-buttons">
        <button id="tabLoginBtn" class="tab-btn active" onclick="showTab('login')">Login Admin</button>
        <button id="tabRegBtn" class="tab-btn" onclick="showTab('register')">Daftar Admin Baru</button>
    </div>

    <!-- Login Form -->
    <form id="formLogin" method="POST" action="login.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">
        
        <div class="form-group">
            <label class="form-label">Username Admin</label>
            <input type="text" name="username" class="form-control" placeholder="Masukkan username" required value="admin">
        </div>

        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Masukkan password" required value="admin123">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 12px;">
            <span class="material-symbols-outlined">login</span>
            <span>Masuk ke Sistem Admin</span>
        </button>
    </form>

    <!-- Register Form -->
    <form id="formRegister" method="POST" action="login.php" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="register">
        
        <div class="form-group">
            <label class="form-label">Nama Lengkap Admin</label>
            <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama lengkap admin" required>
        </div>

        <div class="form-group">
            <label class="form-label">Username Baru</label>
            <input type="text" name="username" class="form-control" placeholder="Username baru" required>
        </div>

        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Password aman" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 12px;">
            <span class="material-symbols-outlined">person_add</span>
            <span>Daftar Akun Admin</span>
        </button>
    </form>
</div>

<script>
function showTab(tab) {
    const fLogin = document.getElementById('formLogin');
    const fReg = document.getElementById('formRegister');
    const bLogin = document.getElementById('tabLoginBtn');
    const bReg = document.getElementById('tabRegBtn');

    if (tab === 'login') {
        fLogin.style.display = 'block';
        fReg.style.display = 'none';
        bLogin.classList.add('active');
        bReg.classList.remove('active');
    } else {
        fLogin.style.display = 'none';
        fReg.style.display = 'block';
        bReg.classList.add('active');
        bLogin.classList.remove('active');
    }
}
</script>

</body>
</html>
