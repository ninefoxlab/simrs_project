<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers.php';
if ($_SERVER['REQUEST_METHOD']!=='POST' || !csrf_check($_POST['csrf']??null)) { http_response_code(403); exit('Permintaan logout tidak valid.'); }
if (!empty($_SESSION['user_id'])) audit_log($pdo,(int)$_SESSION['user_id'],'LOGOUT','users','Pengguna keluar dari sistem');
$_SESSION=[];
if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); }
session_destroy();
header('Location: ' . APP_BASE_URL . '/login.php'); exit;
