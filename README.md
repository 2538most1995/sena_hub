# SENA Digital Hub — เวอร์ชัน 1

เว็บรวมลิงก์ HTML / CSS / JavaScript / PHP / MySQL สำหรับ สกร.ระดับอำเภอเสนา เว็บเดิมทำงานต่อได้ตามปกติ Hub เปิดแต่ละระบบในแท็บใหม่ ไม่ย้ายฐานข้อมูลหรือระบบเดิม

## เปิดบนเครื่องนี้

MAMP ตั้ง Document Root เป็นโฟลเดอร์นี้อยู่แล้ว:

- หน้า Hub: http://localhost:8888/
- หน้า Admin: http://localhost:8888/admin/
- บัญชีเริ่มต้น: `.local-credentials.txt` (ไฟล์ส่วนตัว ถูกปิดการเข้าถึงผ่านเว็บ)

ติดตั้งฐานข้อมูล `sena_digital_hub` แล้ว มีรายการเริ่มต้น 10 ระบบ **ยังไม่ใส่ URL จริง** จึงแสดง “รอเพิ่มลิงก์ระบบ” เข้า Admin → แก้ไข → ใส่ URL → บันทึก เพื่อเปิดใช้งาน

## ความสามารถ

หาก phpMyAdmin แจ้ง `#1046 No database selected` บน MAMP ให้นำเข้า `database/import-mamp.sql` แทน ไฟล์นี้สร้างและเลือกฐานข้อมูล `sena_digital_hub` ก่อนสร้างตาราง นำเข้าซ้ำได้โดยไม่ลบข้อมูลเดิม ส่วน `database/schema.sql` สำหรับโฮสติ้ง ต้องคลิกเลือกฐานข้อมูลที่สร้างไว้ทางซ้ายก่อนกด Import ไฟล์ SQL ทั้งสองสร้างเฉพาะตาราง ไม่สร้างบัญชี Admin หรือข้อมูลเริ่มต้น ให้ใช้ installer หรือขั้นตอนด้านล่าง

- เปิดหน้าเว็บโดยไม่ต้องเข้าสู่ระบบ ค้นหาชื่อและรายละเอียด แยก 6 หมวด พร้อม URL ตรงไปแต่ละหมวด
- Admin เข้าสู่ระบบ เพิ่ม/แก้ไข/ลบลิงก์ กำหนดลำดับ หมวด ไอคอน สี และซ่อนระบบ
- จัดการข้อความประชาสัมพันธ์และข้อมูลติดต่อ
- กำหนด URL สาธารณะและแสดงลิงก์สำหรับนำไปตั้ง LINE Rich Menu
- เปลี่ยนรหัสผ่าน Admin, hash รหัสผ่าน, prepared statements, CSRF token, จำกัดการลองรหัสผ่านผิด 5 ครั้งต่อ 15 นาที ต่อชื่อผู้ใช้และ IP
- ฟอนต์ภาษาไทยและไอคอนอยู่ในโปรเจกต์ ไม่ต้องพึ่ง CDN ขณะเปิดเว็บ

หมวดนักศึกษา/ครูเป็นการจัดกลุ่มเมนู ไม่ใช่การกำหนดสิทธิ์ ระบบปลายทางยังตรวจสอบสิทธิ์ผู้ใช้ของตนเอง LINE Login, สิทธิ์ผู้ใช้, Favorite และ Dashboard ยังไม่รวมในเวอร์ชันนี้

## ติดตั้งบนโฮสติ้ง Apache + PHP 8.1 ขึ้นไป + MySQL 5.7/8

1. สร้างฐานข้อมูลและผู้ใช้ MySQL เฉพาะ Hub ด้วยสิทธิ์บนฐานข้อมูลนั้น เปิด PHP extensions `pdo_mysql` และ `mbstring`
2. อัปโหลด `index.php`, `admin/`, `app/`, `assets/`, `config/` และ `.htaccess` รวม `.htaccess` ในโฟลเดอร์ย่อย อย่าอัปโหลด `config/local.php` ของ MAMP, `.local-credentials.txt`, `tests/`, `.playwright-cli/` หรือไฟล์ผลทดสอบ
3. คัดลอก `config/example.php` เป็น `config/local.php` แล้วตั้ง DSN, username และ password ของโฮสติ้ง
4. ใน phpMyAdmin คลิกเลือกฐานข้อมูลของ Hub ทางซ้ายก่อน แล้วใช้ Import นำเข้า `database/schema.sql` เพื่อสร้างตาราง หรือใช้ SSH รัน `php bin/install.php` เพื่อสร้างตารางและบัญชี Admin (ต้องอัปโหลด `bin/` และ `database/` ชั่วคราว) ตัวติดตั้งไม่เพิ่มระบบตัวอย่างโดยอัตโนมัติ หากต้องการรายการเริ่มต้นให้ใช้ `php bin/install.php --seed-demo` ซึ่งจะเพิ่มเฉพาะเมื่อ `systems` ยังว่าง
5. หากใช้ phpMyAdmin และไม่มี SSH: สร้าง password hash ด้วย PHP CLI ที่เครื่องตัวเอง `php -r 'echo password_hash(readline("Password: "), PASSWORD_DEFAULT), PHP_EOL;'` แล้วใช้ SQL parameter/หน้าฟอร์ม phpMyAdmin เพิ่มแถวใน `admins` ใส่ `username` และ `password_hash` ที่ได้ เข้าสู่ Admin เพื่อเพิ่มระบบเอง
6. หากใช้ installer จะสร้างรหัสผ่านสุ่มใน `.local-credentials.txt` ดาวน์โหลดอ่านผ่านช่องทางโฮสติ้งส่วนตัว แล้วลบไฟล์ออกจากโฮสติ้ง เปลี่ยนรหัสผ่านใน Admin ก่อนเปิดบริการ ลบ `bin/` และ `database/` หลังติดตั้งได้
7. เปิด HTTPS ทดสอบหน้าแรกและหน้า Admin ตรวจว่าการเข้าถึง `/config/local.php` และไฟล์รหัสผ่านได้ 403 หรือ 404 ก่อนเปิดบริการ `.htaccess` ต้องได้รับอนุญาตผ่าน `AllowOverride` ถ้าใช้ Nginx ต้องตั้ง deny สำหรับ `app`, `config`, `database`, `bin`, `tests`, `docs`, dotfiles และไฟล์ credentials เอง
8. เข้า Admin → ใส่ URL ระบบจริง → ตั้ง URL สาธารณะของ Hub → บันทึก

ไม่อัปโหลดบัญชีหรือรหัสผ่าน MySQL `root` ของ MAMP ไปโฮสติ้ง ไม่มีการติดตั้งบัญชี Admin ค่าเริ่มต้นที่ทุกคนรู้รหัสผ่าน

## อัปเดตจาก GitHub โดยรักษาฐานข้อมูลบนเซิร์ฟเวอร์

Repository: https://github.com/2538most1995/sena_hub

- วาง Hub ในโฟลเดอร์ใหม่ เช่น `public_html/portal/` และใช้ฐานข้อมูลแยก เช่น `ชื่อบัญชี_sena_hub` พร้อมผู้ใช้ MySQL ที่เข้าถึงได้เฉพาะฐานข้อมูลนี้ หลีกเลี่ยงการตั้ง DSN ไปที่ฐานข้อมูลระบบรับสมัครหรือระบบอื่น
- `config/local.php` และ `.local-credentials.txt` ไม่อยู่ใน Git ต้องสร้าง `config/local.php` บนเซิร์ฟเวอร์ครั้งแรกจาก `config/example.php` และเก็บค่าของเซิร์ฟเวอร์ไว้เมื่ออัปเดต อย่าคัดลอกไฟล์ MAMP ไปทับ
- การเปิดเว็บหรืออัปเดต source code ไม่รันตัวติดตั้ง ไม่ import SQL และไม่แก้ไขฐานข้อมูลโดยอัตโนมัติ การอัปเดตตามปกติใช้ `git pull --ff-only` หรืออัปโหลดเฉพาะไฟล์ source ที่เปลี่ยน โดยไม่ลบไฟล์ตั้งค่าของเซิร์ฟเวอร์
- ตัวติดตั้งใช้ `CREATE TABLE IF NOT EXISTS` ไม่มี `DROP`, `TRUNCATE` หรือ `REPLACE` เก็บบัญชีและรหัสผ่านเดิม เติมเฉพาะ setting ที่ขาด และไม่เพิ่มข้อมูลตัวอย่างซ้ำ หากพบตารางอื่นหรือโครงสร้างไม่ใช่ Hub จะหยุดก่อนเขียนข้อมูล
- `database/import-mamp.sql` ใช้บน MAMP เท่านั้น สำหรับ shared hosting ที่ชื่อฐานข้อมูลมี prefix ให้สร้างฐานข้อมูลเฉพาะ Hub และเลือกฐานข้อมูลนั้นก่อน import `database/schema.sql`
- ตอนติดตั้งครั้งแรกด้วย CLI รหัสผ่าน Admin จะสร้างใหม่เฉพาะเซิร์ฟเวอร์ ไม่ใช่รหัสผ่านของเครื่องทดสอบ

การสำรองข้อมูลบนเซิร์ฟเวอร์ให้เก็บแยกจาก Git ส่วนคำสั่งเปลี่ยนแปลงข้อมูลผ่าน Admin ยังคงทำงานตามที่ผู้ดูแลเลือก

## เชื่อม LINE Rich Menu

ต้องมีโดเมนสาธารณะและสิทธิ์จัดการ LINE OA ของคุณก่อน URL `localhost` เปิดจากมือถือผู้ใช้คนอื่นไม่ได้

เข้า LINE Official Account Manager → Rich Menu → กำหนดพื้นที่ปุ่มและ Action แบบ Link / URI:

- รวมระบบ: `https://yourdomain.com/portal/`
- นักศึกษา: `https://yourdomain.com/portal/?category=student`
- ครู: `https://yourdomain.com/portal/?category=staff`
- แหล่งเรียนรู้: `https://yourdomain.com/portal/?category=learning`
- ติดต่อเรา: `https://yourdomain.com/portal/#contact`

ใช้ลิงก์จริงที่สร้างในหน้า Admin แทน URL ตัวอย่าง ตั้งค่าการแสดง Rich Menu แล้วทดสอบจาก LINE บนโทรศัพท์ การตั้งค่า LINE OA ยังไม่ได้ดำเนินการในโปรเจกต์นี้ เพราะยังไม่มี URL สาธารณะและบัญชี OA ที่เชื่อมต่อ

คู่มือทางการ: https://developers.line.biz/en/docs/messaging-api/using-rich-menus/

## ตรวจสอบ

- PHP lint ทุกไฟล์
- `python3 tests/smoke.py` ทดสอบ HTTP กับ MAMP: หน้าแรก, login/logout, เพิ่ม/แก้ไข/ซ่อน/ลบ, การ escape HTML, URL validation, CSRF และการปิดไฟล์ส่วนตัว สร้างแถวทดสอบแล้วลบคืน ต้องมีบัญชีเริ่มต้นใน `.local-credentials.txt`
- ตรวจ UI ด้วย Playwright บน desktop และ mobile

## ใบอนุญาต assets

Noto Sans Thai: SIL Open Font License (`assets/fonts/OFL.txt`)
Tabler Icons: MIT (`assets/icons/LICENSE.txt`)

## UI ตามภาพอ้างอิง

หน้าแรกมีเมนูสี 3 × 3 พร้อมค้นหา แบนเนอร์ 2 ภาพ และแถบนำทาง 5 เมนู หน้าหมวดนักศึกษาใช้หัวสีม่วงและการ์ด 2 คอลัมน์บนมือถือ / 4 คอลัมน์บน desktop ข่าวสารและติดต่อแสดงข้อความจาก Admin รายการโปรดและโปรไฟล์ระบุว่าเตรียมเปิดใช้งานในขั้นถัดไป

ตราหน่วยงานคัดลอกจาก `sena_certicate/assets/logo.png` แบนเนอร์คัดลอกจาก `sena_web/uploads/banners/` บนเครื่องผู้ใช้และย่อเป็น JPEG สำหรับเว็บ ใช้แบนเนอร์หน่วยงานเดิมแทนภาพอาคารใน reference
