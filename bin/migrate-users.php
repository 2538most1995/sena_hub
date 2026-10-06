<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/accounts.php';
try { enable_account_roles(db()); echo "พร้อมใช้งานบัญชีครู เก็บบัญชีและข้อมูลเดิมไว้ครบ\n"; }
catch (Throwable $error) { fwrite(STDERR,"อัปเดตไม่สำเร็จ: ".$error->getMessage().PHP_EOL); exit(1); }
