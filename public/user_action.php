<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/permissions.php';
$user=require_login($pdo);
if($_SERVER['REQUEST_METHOD']!=='POST' || !csrf_check($_POST['csrf']??null)){http_response_code(403);exit('Permintaan tidak valid.');}
$uid=(int)$user['id_user'];$action=$_POST['action']??'';
function user_flash_redirect(string $type,string $message): never { flash_set($type,$message); redirect_to(APP_BASE_URL.'/index.php?page=users'); }
if($action==='create'){
 require_permission($pdo,$uid,'user_management.create'); require_permission($pdo,$uid,'user_management.assign_role');
 $nama=trim($_POST['nama']??'');$username=trim($_POST['username']??'');$peran=trim($_POST['peran']??'');$spes=trim($_POST['spesialisasi']??'');$password=(string)($_POST['password']??'');$role=(int)($_POST['id_role']??0);
 if($nama===''||$username===''||$peran===''||$password===''||$role<1) user_flash_redirect('error','Lengkapi semua kolom wajib.');
 if(!preg_match('/^[A-Za-z0-9._-]{3,50}$/',$username)) user_flash_redirect('error','Format username tidak valid.');
 if(strlen($password)<12) user_flash_redirect('error','Password minimal 12 karakter.');
 $r=$pdo->prepare('SELECT id_role FROM roles WHERE id_role=? AND status_aktif=1');$r->execute([$role]);if(!$r->fetch())user_flash_redirect('error','Role tidak valid.');
 try{$pdo->beginTransaction();
  $s=$pdo->prepare('INSERT INTO pegawai (nama,peran,spesialisasi,username) VALUES (?,?,?,?)');$s->execute([$nama,$peran,$spes?:null,$username]);$pegawai=(int)$pdo->lastInsertId();
  $s=$pdo->prepare('INSERT INTO users (id_pegawai,username,password_hash,nama_tampilan,status_aktif,created_at,updated_at) VALUES (?,?,?,?,1,NOW(),NOW())');$s->execute([$pegawai,$username,password_hash($password,PASSWORD_DEFAULT),$nama]);$newId=(int)$pdo->lastInsertId();
  $s=$pdo->prepare('INSERT INTO user_roles (id_user,id_role,is_default,assigned_at) VALUES (?,?,1,NOW())');$s->execute([$newId,$role]);
  audit_log($pdo,$uid,'CREATE_USER','users','Membuat akun '.$username);$pdo->commit();user_flash_redirect('success','Akun pengguna berhasil dibuat.');
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());user_flash_redirect('error','Gagal membuat akun. Pastikan username belum dipakai dan struktur database sesuai.');}
}
if($action==='update'){
 require_permission($pdo,$uid,'user_management.update');require_permission($pdo,$uid,'user_management.assign_role');
 $id=(int)($_POST['id_user']??0);$nama=trim($_POST['nama']??'');$username=trim($_POST['username']??'');$peran=trim($_POST['peran']??'');$spes=trim($_POST['spesialisasi']??'');$password=(string)($_POST['password']??'');$role=(int)($_POST['id_role']??0);
 if($id<1||$nama===''||$username===''||$peran===''||$role<1)user_flash_redirect('error','Data pengguna tidak lengkap.');
 if(!preg_match('/^[A-Za-z0-9._-]{3,50}$/',$username))user_flash_redirect('error','Format username tidak valid.');if($password!==''&&strlen($password)<12)user_flash_redirect('error','Password baru minimal 12 karakter.');
 $find=$pdo->prepare('SELECT id_pegawai FROM users WHERE id_user=?');$find->execute([$id]);$pegawai=$find->fetchColumn();if(!$pegawai)user_flash_redirect('error','Pengguna tidak ditemukan.');
 try{$pdo->beginTransaction();
  $s=$pdo->prepare('UPDATE pegawai SET nama=?,peran=?,spesialisasi=?,username=? WHERE id_pegawai=?');$s->execute([$nama,$peran,$spes?:null,$username,$pegawai]);
  if($password!==''){$s=$pdo->prepare('UPDATE users SET username=?,nama_tampilan=?,password_hash=?,updated_at=NOW() WHERE id_user=?');$s->execute([$username,$nama,password_hash($password,PASSWORD_DEFAULT),$id]);}
  else{$s=$pdo->prepare('UPDATE users SET username=?,nama_tampilan=?,updated_at=NOW() WHERE id_user=?');$s->execute([$username,$nama,$id]);}
  $s=$pdo->prepare('UPDATE user_roles SET is_default=0 WHERE id_user=?');$s->execute([$id]);
  $s=$pdo->prepare('INSERT INTO user_roles (id_user,id_role,is_default,assigned_at) VALUES (?,?,1,NOW()) ON DUPLICATE KEY UPDATE is_default=1');$s->execute([$id,$role]);
  audit_log($pdo,$uid,'UPDATE_USER','users','Memperbarui akun '.$username);$pdo->commit();user_flash_redirect('success','Data pengguna berhasil diperbarui.');
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());user_flash_redirect('error','Gagal memperbarui akun.');}
}
if($action==='toggle'){
 require_permission($pdo,$uid,'user_management.activate');$id=(int)($_POST['id_user']??0);if($id<1||$id===$uid)user_flash_redirect('error','Akun sendiri tidak dapat dinonaktifkan.');
 $s=$pdo->prepare("SELECT u.status_aktif, r.kode_role FROM users u LEFT JOIN user_roles ur ON ur.id_user=u.id_user AND ur.is_default=1 LEFT JOIN roles r ON r.id_role=ur.id_role WHERE u.id_user=?");$s->execute([$id]);$target=$s->fetch();if(!$target)user_flash_redirect('error','Pengguna tidak ditemukan.');
 $new=(int)$target['status_aktif']===1?0:1;
 if($new===0&&$target['kode_role']==='IT_ADMIN'){$n=(int)$pdo->query("SELECT COUNT(DISTINCT u.id_user) FROM users u JOIN user_roles ur ON ur.id_user=u.id_user JOIN roles r ON r.id_role=ur.id_role WHERE u.status_aktif=1 AND r.kode_role='IT_ADMIN'")->fetchColumn();if($n<=1)user_flash_redirect('error','IT Administrator aktif terakhir tidak boleh dinonaktifkan.');}
 $s=$pdo->prepare('UPDATE users SET status_aktif=?,updated_at=NOW() WHERE id_user=?');$s->execute([$new,$id]);audit_log($pdo,$uid,$new?'ACTIVATE_USER':'DEACTIVATE_USER','users','Mengubah status akun ID '.$id);user_flash_redirect('success',$new?'Akun diaktifkan.':'Akun dinonaktifkan.');
}
http_response_code(400);exit('Tindakan tidak dikenal.');
