<?php
declare(strict_types=1);
$base=dirname(__DIR__); $lock=$base.'/storage/installed.lock';
if (is_file($lock)) { header('Location: ../admin/'); exit; }
$req=[
 'PHP 8.1+'=>version_compare(PHP_VERSION,'8.1.0','>='),
 'PDO MySQL'=>extension_loaded('pdo_mysql'),
 'config yazılabilir'=>is_dir($base.'/config')&&is_writable($base.'/config'),
 'storage yazılabilir'=>is_dir($base.'/storage')&&is_writable($base.'/storage'),
];
$errors=[];$done=false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $host=trim((string)($_POST['db_host']??'localhost')); $port=(int)($_POST['db_port']??3306); $name=trim((string)($_POST['db_name']??''));
 $user=trim((string)($_POST['db_user']??'')); $pass=(string)($_POST['db_pass']??''); $business=trim((string)($_POST['business_name']??'Restoran'));
 $email=strtolower(trim((string)($_POST['admin_email']??''))); $password=(string)($_POST['admin_password']??'');
 if (in_array(false,$req,true)) $errors[]='Sunucu gereksinimleri tamamlanmadı.';
 if ($name===''||$user===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8) $errors[]='Veritabanı, geçerli e-posta ve en az 8 karakterlik şifre zorunludur.';
 if (!$errors) try {
   $pdo=new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
   $sql=(string)file_get_contents(__DIR__.'/schema.sql');
   foreach (array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[])) as $statement) $pdo->exec($statement);
   $pdo->beginTransaction();
   $q=$pdo->prepare('INSERT INTO admins(name,email,password_hash,role,is_active) VALUES(?,?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),password_hash=VALUES(password_hash),role=VALUES(role),is_active=1');
   $q->execute(['Yönetici',$email,password_hash($password,PASSWORD_DEFAULT),'admin']);
   $settings=['business_name'=>$business,'currency_symbol'=>'₺','locale'=>'tr-TR','menu_enabled'=>'1','pos_enabled'=>'1','reports_enabled'=>'1'];
   $q=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
   foreach($settings as $k=>$v)$q->execute([$k,$v]);
   $pdo->exec("INSERT INTO dining_areas(name,sort_order,is_active) SELECT 'Ana Salon',1,1 WHERE NOT EXISTS(SELECT 1 FROM dining_areas)");
   $pdo->commit();
   $config="<?php\ndeclare(strict_types=1);\nreturn ".var_export(['host'=>$host,'port'=>$port,'name'=>$name,'user'=>$user,'pass'=>$pass],true).";\n";
   if (file_put_contents($base.'/config/database.php',$config,LOCK_EX)===false) throw new RuntimeException('Veritabanı yapılandırması yazılamadı.');
   $env="APP_ENV=production\nAPP_DEBUG=false\nAPP_TIMEZONE=Europe/Istanbul\nAPP_KEY=".bin2hex(random_bytes(32))."\n";
   if (file_put_contents($base.'/.env',$env,LOCK_EX)===false) throw new RuntimeException('.env yazılamadı.');
   if (file_put_contents($lock,date(DATE_ATOM),LOCK_EX)===false) throw new RuntimeException('Kurulum kilidi yazılamadı.');
   $done=true;
 } catch(Throwable $e) { if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack(); $errors[]='Kurulum başarısız: '.$e->getMessage(); }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sistem Kurulumu</title><style>*{box-sizing:border-box}body{font-family:system-ui;background:#f3f4f6;margin:0;padding:32px}.box{max-width:760px;margin:auto;background:#fff;padding:30px;border-radius:18px;box-shadow:0 12px 32px #0001}label{display:block;font-weight:650;margin-top:13px}input{width:100%;padding:12px;margin-top:6px;border:1px solid #d1d5db;border-radius:10px}button{margin-top:20px;padding:13px 18px;border:0;border-radius:10px;background:#111827;color:#fff;font-weight:700}.ok{background:#ecfdf5;padding:15px;border-radius:10px}.err{background:#fef2f2;padding:12px;border-radius:10px;margin:8px 0}</style></head><body><div class="box"><h1>Restoran Yönetim Sistemi Kurulumu</h1><h2>Sunucu kontrolü</h2><ul><?php foreach($req as $k=>$v):?><li><?=$v?'✅':'❌'?> <?=htmlspecialchars($k)?></li><?php endforeach;?></ul><?php if($done):?><div class="ok"><strong>Kurulum tamamlandı.</strong><p><a href="../admin/">Yönetici paneline gir</a></p></div><?php else:?><?php foreach($errors as $x):?><div class="err"><?=htmlspecialchars($x)?></div><?php endforeach;?><form method="post"><label>Veritabanı sunucusu<input name="db_host" value="localhost" required></label><label>Port<input name="db_port" value="3306" type="number" required></label><label>Veritabanı adı<input name="db_name" required></label><label>Veritabanı kullanıcısı<input name="db_user" required></label><label>Veritabanı şifresi<input name="db_pass" type="password"></label><label>İşletme adı<input name="business_name" value="Restoran" required></label><label>Yönetici e-postası<input name="admin_email" type="email" required></label><label>Yönetici şifresi<input name="admin_password" type="password" minlength="8" required></label><button>Kurulumu Başlat</button></form><?php endif;?></div></body></html>
