<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/management.php';
try { upgrade_hub(db()); echo "อัปเดตโครงสร้างหลังบ้านสำเร็จ ข้อมูลเดิมอยู่ครบ\n"; }
catch(Throwable $error){fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1);}
