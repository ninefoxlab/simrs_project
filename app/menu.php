<?php
// Tampilan dibatasi berdasarkan role; permission backend dicek terpisah.
$roleMenus = [
 'IT_ADMIN'=>['dashboard','users','master','audit'],
 'DOKTER'=>['dashboard','outpatient','inpatient','igd','emr','cpoe'],
 'ADMISI'=>['dashboard','registration','scheduling'],
 'PERAWAT'=>['dashboard','inpatient','igd','nursing','cpoe'],
 'APOTEKER'=>['dashboard','pharmacy'],
 'ATLM'=>['dashboard','laboratory'],
 'DOKTER_LAB'=>['dashboard','laboratory'],
 'VALIDATOR_LAB'=>['dashboard','laboratory'],
 'RADIOGRAFER'=>['dashboard','radiology'],
 'DOKTER_RAD'=>['dashboard','radiology'],
 'KASIR'=>['dashboard','billing'],
 'ASURANSI'=>['dashboard','billing'],
 'PETUGAS_GUDANG'=>['dashboard','inventory'],
 'KEPALA_GUDANG'=>['dashboard','inventory','reporting'],
 'PIMPINAN'=>['dashboard','reporting']
];
$all = require __DIR__ . '/modules_config.php';
$roles = user_roles($pdo, (int)$user['id_user']);
$isITAdmin = in_array('IT_ADMIN', $roles, true);
$routes = [];
if ($isITAdmin) {
    $routes = $roleMenus['IT_ADMIN'];
} else {
    foreach ($roles as $role) foreach ($roleMenus[$role] ?? [] as $route) $routes[$route] = $route;
    $routes = array_values($routes);
}
$menus = [];
$special = [
 'users'=>'user_management.access',
 'master'=>'master_data.access',
 'audit'=>'reporting.view'
];
foreach ($routes as $route) {
    if ($route === 'dashboard') { $menus[$route] = ['label'=>'Dashboard','icon'=>'⌂']; continue; }
    if ($route === 'users') { if (can($pdo,(int)$user['id_user'],$special[$route])) $menus[$route]=['label'=>'Manajemen Pengguna','icon'=>'♙']; continue; }
    if ($route === 'master') { if (can($pdo,(int)$user['id_user'],$special[$route])) $menus[$route]=['label'=>'Master Data','icon'=>'▦']; continue; }
    if ($route === 'audit') { if (can($pdo,(int)$user['id_user'],$special[$route])) $menus[$route]=['label'=>'Audit Trail','icon'=>'≡']; continue; }
    if (isset($all[$route]) && can($pdo,(int)$user['id_user'],$all[$route]['permission'].'.access')) {
        $menus[$route] = ['label'=>$all[$route]['label'],'icon'=>'•'];
    }
}
return ['menus'=>$menus,'roles'=>$roles,'is_it_admin'=>$isITAdmin];
