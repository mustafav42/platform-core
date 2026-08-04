<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if (empty($_SESSION['admin_id'])) redirect('./');
if (!hash_equals((string)($_SESSION['csrf']??''),(string)($_GET['token']??''))) throw new RuntimeException('Geçersiz güvenlik anahtarı.');
$pdo=db();
$dir=BASE_PATH.'/storage/backups';
if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Yedek klasörü oluşturulamadı.');
if(!is_writable($dir)) throw new RuntimeException('Yedek klasörü yazılabilir değil.');
$stamp=date('Ymd-His');$base='database-'.$stamp.'.sql';$sqlFile=$dir.'/'.$base;
$fh=fopen($sqlFile,'wb');if(!$fh)throw new RuntimeException('Yedek dosyası oluşturulamadı.');
fwrite($fh,"-- Restaurant Management System database backup\n-- Created: ".date('c')."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
$tables=$pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
foreach($tables as $row){$table=(string)$row[0];$create=$pdo->query('SHOW CREATE TABLE `'.str_replace('`','``',$table).'`')->fetch(PDO::FETCH_NUM);fwrite($fh,"DROP TABLE IF EXISTS `{$table}`;\n".$create[1].";\n");$q=$pdo->query('SELECT * FROM `'.str_replace('`','``',$table).'`');while($data=$q->fetch(PDO::FETCH_ASSOC)){ $cols=array_map(fn($c)=>'`'.str_replace('`','``',$c).'`',array_keys($data));$vals=[];foreach($data as $v){$vals[]=$v===null?'NULL':$pdo->quote((string)$v);}fwrite($fh,'INSERT INTO `'.$table.'` ('.implode(',',$cols).') VALUES ('.implode(',',$vals).");\n");}fwrite($fh,"\n");}
fwrite($fh,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($fh);
$fileName=$base;$final=$sqlFile;
if(function_exists('gzencode')){$gz=$sqlFile.'.gz';file_put_contents($gz,gzencode((string)file_get_contents($sqlFile),9));unlink($sqlFile);$fileName=basename($gz);$final=$gz;}
$size=(int)filesize($final);$q=$pdo->prepare('INSERT INTO backup_history(file_name,file_size,status,created_by,created_at) VALUES(?,?,?,?,NOW())');$q->execute([$fileName,$size,'completed',(int)$_SESSION['admin_id']]);audit_log('database_backup_created','Veritabanı yedeği oluşturuldu.',['file'=>$fileName,'size'=>$size]);
header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.$fileName.'"');header('Content-Length: '.$size);header('Cache-Control: no-store');readfile($final);exit;
