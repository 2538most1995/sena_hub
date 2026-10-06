<?php
require dirname(__DIR__).'/app/bootstrap.php';require_once dirname(__DIR__).'/app/accounts.php';
$c=require dirname(__DIR__).'/config/local.php';$pdo=new PDO(preg_replace('/;dbname=[^;]+/','',$c['dsn']),$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$name='hub_management_test_'.bin2hex(random_bytes(5));
function check_management(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
try {
 $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');$pdo->exec('USE `'.$name.'`');
 $pdo->exec(file_get_contents(dirname(__DIR__).'/database/schema.sql'));
 $pdo->exec("INSERT INTO systems(name,category) VALUES ('Legacy service','student')");
 $pdo->exec("INSERT INTO settings VALUES ('system_categories_1','[\"student\",\"staff\"]')");
 $hash=password_hash('Original1234',PASSWORD_DEFAULT);$stmt=$pdo->prepare('INSERT INTO admins(username,password_hash) VALUES (?,?)');$stmt->execute(['original',$hash]);
 upgrade_hub($pdo);upgrade_hub($pdo);
 check_management($pdo->query('SELECT password_hash FROM admins WHERE id=1')->fetchColumn()===$hash,'repeatable upgrade preserves password');
 check_management((int)$pdo->query('SELECT COUNT(*) FROM system_categories WHERE system_id=1')->fetchColumn()===2,'backfill preserves multiple categories without duplicates');
 $_SESSION=['admin_id'=>1];
 $teacher=save_hub_account($pdo,0,'teacher','Password1234','Password1234','teacher',profile_input(['display_name'=>'ครูทดสอบ','email'=>'teacher@example.org','bio'=>'ข้อมูลโปรไฟล์']));
 check_management($pdo->query('SELECT display_name FROM admins WHERE id='.$teacher)->fetchColumn()==='ครูทดสอบ','account and profile saved together');
 $blocked=false;try{profile_input(['email'=>'invalid-email']);}catch(InvalidArgumentException $error){$blocked=true;}check_management($blocked,'invalid email rejected');
 set_account_active($pdo,$teacher,false);check_management((int)$pdo->query('SELECT active FROM admins WHERE id='.$teacher)->fetchColumn()===0,'account can be disabled');
 $blocked=false;try{set_account_active($pdo,1,false);}catch(InvalidArgumentException $error){$blocked=true;}check_management($blocked,'last active administrator cannot be disabled');
 $second=save_hub_account($pdo,0,'second','Password1234','Password1234','admin');set_account_active($pdo,$second,false);
 $blocked=false;try{save_hub_account($pdo,1,'original','','','teacher');}catch(InvalidArgumentException $error){$blocked=true;}check_management($blocked,'disabled admins do not satisfy last administrator protection');
 check_management((int)$pdo->query('SELECT COUNT(*) FROM audit_log WHERE actor_id=1')->fetchColumn()>=3,'mutations record actor and action');
 $pdo->exec('DELETE FROM systems WHERE id=1');check_management((int)$pdo->query('SELECT COUNT(*) FROM system_categories')->fetchColumn()===0,'deleting service cascades normalized memberships');
}finally{$pdo->exec('DROP DATABASE IF EXISTS `'.$name.'`');}
