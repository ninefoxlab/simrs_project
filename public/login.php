<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/auth.php';
if (current_user($pdo)) redirect_to(APP_BASE_URL . '/index.php');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_check($_POST['csrf']??null)) $error='Sesi formulir tidak valid. Muat ulang halaman.';
    else {
        $username=trim((string)($_POST['username']??''));
        $password=(string)($_POST['password']??'');
        if ($username==='' || $password==='') $error='Username dan password wajib diisi.';
        elseif (login_user($pdo,$username,$password)) redirect_to(APP_BASE_URL . '/index.php');
        else $error='Username atau password tidak sesuai, atau akun tidak aktif.';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0E2A2F">
<title>Masuk | SIMRS RSUD NTB</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/simrs.css?v=2">
</head>
<body>
<main class="login-page">
  <section class="login-card">
    <div class="login-banner">
      <div>
        <div class="logo-mark" aria-hidden="true">✚</div>
        <h1>SIMRS RSUD<br>Nusa Tenggara Barat</h1>
        <p>Satu catatan pasien dari pendaftaran, pemeriksaan, penunjang, farmasi, sampai kasir.</p>
      </div>
      <svg class="login-ecg" viewBox="0 0 400 90" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 50 H90 L100 50 L108 38 L116 50 H150 L160 50 L168 12 L180 84 L192 4 L204 66 L212 50 H260 L272 50 L280 40 L290 50 H400"/>
        <circle cx="400" cy="50" r="4"/>
      </svg>
      <div class="banner-foot">Registrasi, rekam medis, laboratorium, radiologi, farmasi, billing, dan gudang</div>
    </div>
    <div class="login-body">
      <h2>Masuk</h2>
      <p class="muted">Gunakan akun yang diberikan IT Administrator rumah sakit.</p>
      <?php if($error): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="field"><label for="username">Username</label><input id="username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus value="<?= e($_POST['username'] ?? '') ?>"></div>
        <div class="field"><label for="password">Password</label><div class="pw-wrap"><input id="password" name="password" type="password" autocomplete="current-password" required><button class="pw-toggle" type="button" aria-pressed="false" aria-controls="password">Lihat</button></div></div>
        <button class="btn primary full" type="submit">Masuk</button>
      </form>
      <div class="login-help">Lupa password atau akun terkunci? Hubungi IT Administrator.</div>
    </div>
  </section>
</main>
<script src="<?= APP_BASE_URL ?>/assets/simrs.js?v=2" defer></script>
</body>
</html>
