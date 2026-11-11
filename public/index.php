<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/permissions.php';
require_once dirname(__DIR__) . '/app/schema.php';
$user = require_login($pdo);
$menuData = require dirname(__DIR__) . '/app/menu.php';
$menus = $menuData['menus'];
$roles = $menuData['roles'];
$isITAdmin = $menuData['is_it_admin'];
$moduleDefinitions = require dirname(__DIR__) . '/app/modules_config.php';
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = array_merge(['dashboard','users','audit','master'], array_keys($moduleDefinitions));
if (!in_array($page, $allowedPages, true)) $page = 'dashboard';
if (!isset($menus[$page])) { http_response_code(403); $page = 'dashboard'; $denied = true; } else $denied = false;
$menuLabels = ['dashboard'=>'Dashboard','users'=>'Manajemen Pengguna','audit'=>'Audit Trail','master'=>'Master Data'];
foreach ($moduleDefinitions as $key=>$definition) $menuLabels[$key] = $definition['label'];
$pageTitle = $menuLabels[$page] ?? 'Dashboard';
$csrf = csrf_token();
$flash = flash_get();
$workflowPages = ['outpatient','inpatient','igd','emr','nursing','cpoe','laboratory','lab_results','radiology','pharmacy','billing','inventory','reporting'];
function nav_icon(string $route): string {
    $p = [
        'dashboard'=>'<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'users'=>'<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.8c2 .6 3.2 2.4 3.5 5.2"/>',
        'master'=>'<ellipse cx="12" cy="5.5" rx="8" ry="3"/><path d="M4 5.5v13c0 1.7 3.6 3 8 3s8-1.3 8-3v-13"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'audit'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'registration'=>'<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2.8h6V4M12 10v6M9 13h6"/>',
        'scheduling'=>'<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'outpatient'=>'<path d="M6 3v6a5 5 0 0 0 10 0V3"/><path d="M11 14v2a4 4 0 0 0 8 0v-2"/><circle cx="19" cy="12" r="2"/>',
        'inpatient'=>'<path d="M3 6v13M3 15h18v4M21 15v-3a3 3 0 0 0-3-3h-8v6"/><circle cx="6.5" cy="11" r="2"/>',
        'igd'=>'<path d="M12 3 2.5 20h19z"/><path d="M12 9.5v5M12 17.2v.3"/>',
        'emr'=>'<path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
        'nursing'=>'<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z"/><path d="M9 12h2l1-2 1.5 4 1-2h1.5"/>',
        'cpoe'=>'<path d="M21 3 10 14M21 3l-7 18-4-7-7-4z"/>',
        'lab_results'=>'<path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4"/><path d="m9 14 2 2 4-4"/>',
        'laboratory'=>'<path d="M9 3h6M10 3v6l-5.5 9.5A2 2 0 0 0 6.2 21h11.6a2 2 0 0 0 1.7-2.5L14 9V3"/><path d="M7 15h10"/>',
        'radiology'=>'<path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"/><circle cx="12" cy="12" r="3.5"/>',
        'pharmacy'=>'<rect x="3" y="8.5" width="18" height="7" rx="3.5" transform="rotate(-45 12 12)"/><path d="m9.5 9.5 5 5"/>',
        'billing'=>'<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'inventory'=>'<path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/>',
        'reporting'=>'<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($p[$route] ?? '<circle cx="12" cy="12" r="3"/>') . '</svg>';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> | SIMRS RSUD NTB</title>
<meta name="theme-color" content="#0E2A2F">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/simrs.css?v=2">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar" aria-label="Menu utama">
    <div class="brand"><div class="brand-icon" aria-hidden="true">✚</div><div><strong>SIMRS RSUD NTB</strong><span>Portal terpadu</span></div></div>
    <svg class="pulse" viewBox="0 0 220 26" preserveAspectRatio="none" aria-hidden="true"><path d="M0 14 H70 L78 14 L84 4 L92 24 L100 1 L108 20 L113 14 H220"/></svg>
    <nav class="nav">
      <?php foreach ($menus as $route=>$item): ?>
        <a href="<?= APP_BASE_URL ?>/index.php?page=<?= e($route) ?>" class="<?= $route===$page?'active':'' ?>" <?= $route===$page?'aria-current="page"':'' ?>><span class="ico"><?= nav_icon($route) ?></span><span><?= e($item['label']) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-foot"><div class="notice"><b><?= e($user['nama_tampilan']) ?></b><p><?= e(implode(', ', $roles)) ?></p></div></div>
  </aside>
  <div class="backdrop" aria-hidden="true"></div>
  <section class="main">
    <header class="topbar">
      <button class="nav-toggle" type="button" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false"><span></span></button>
      <div><div class="top-title"><?= e($pageTitle) ?></div><div class="top-sub">SIMRS RSUD NTB</div></div>
      <div class="clock" data-clock aria-live="off"><b>--:--:--</b><span>&nbsp;</span></div>
      <div class="user-chip"><div class="avatar" aria-hidden="true"><?= e(strtoupper(substr((string)$user['nama_tampilan'],0,1))) ?></div><div class="who"><b><?= e($user['nama_tampilan']) ?></b><div class="top-sub"><?= e($user['username']) ?></div></div><form method="post" action="<?= APP_BASE_URL ?>/logout.php"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="btn smallbtn" type="submit">Keluar</button></form></div>
    </header>
    <main class="content">
      <div class="crumb">SIMRS RSUD NTB / <b><?= e($pageTitle) ?></b></div>
      <?php if ($denied): ?><div class="flash error">Akses halaman ditolak untuk role akun ini.</div><?php endif; ?>
      <?php if ($flash): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
      <?php
        if ($page === 'dashboard') require dirname(__DIR__) . '/app/pages/dashboard.php';
        elseif ($page === 'users') require dirname(__DIR__) . '/app/pages/users.php';
        elseif ($page === 'audit') require dirname(__DIR__) . '/app/pages/audit.php';
        elseif (in_array($page, $workflowPages, true)) require dirname(__DIR__) . '/app/pages/workflow.php';
        else require dirname(__DIR__) . '/app/pages/module.php';
      ?>
    </main>
  </section>
</div>
<script src="<?= APP_BASE_URL ?>/assets/simrs.js?v=2" defer></script>
</body>
</html>
