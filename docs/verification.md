# ผลตรวจสอบเวอร์ชัน 1

ตรวจบน MAMP (Apache, PHP 8.3.14, MySQL) ที่ http://localhost:8888/

- PHP lint ทุกไฟล์ผ่าน และ JavaScript syntax ผ่าน
- HTTP integration: หน้าแรกดึง MySQL, บังคับเข้าสู่ระบบ Admin, login/logout, เพิ่ม/แก้ไข/ซ่อน/ลบระบบ ผ่าน
- URL `javascript:` ถูกปฏิเสธ, ข้อความ HTML ถูก escape, คำขอ POST ที่ไม่มี CSRF ถูกปฏิเสธ ผ่าน
- config, credentials, SQL และ installer ถูกปิดการอ่านผ่าน HTTP ผ่าน
- รัน installer ซ้ำไม่เปลี่ยนข้อมูลเดิม ผ่าน
- Playwright: ค้นหา “ครุภัณฑ์” พบ SENA Asset หนึ่งรายการ; คำค้นที่ไม่มีผลแสดง empty state
- URL หมวดนักศึกษาแสดง 5 รายการ
- desktop 1440 × 1000 และ mobile 390 × 844: ตรวจ screenshot แล้ว ไม่มี overflow แนวนอน และไม่มีไอคอนที่โหลดไม่สำเร็จ

รายการเริ่มต้นยังไม่มี URL จริง ไม่ได้ตรวจความพร้อมเว็บไซต์ปลายทาง ไม่ได้ deploy ไปโฮสติ้งสาธารณะ และไม่ได้เปลี่ยน Rich Menu ในบัญชี LINE OA

## ตรวจหลังปรับ UI ตามภาพอ้างอิง

- หน้าแรก 3 × 3 และหมวดนักศึกษา 2 คอลัมน์บน mobile 390 × 844 ตรวจ screenshot แล้ว
- ค้นหาจากหน้าแรกแสดงผลแทนเมนู ล้างคำค้นคืนเมนูหน้าแรก
- แบนเนอร์สลับได้ 2 ภาพ เมนูมุมบนปิดด้วย Escape ได้
- คำค้น N-NET พบ 1 ระบบ คำค้นที่ไม่มีผลแสดง empty state
- ทดสอบ HTTP Admin และ MySQL หลังปรับ UI ผ่าน
- รายการโปรดและโปรไฟล์เป็นหน้าเตรียมเปิดใช้งานตามขอบเขตเวอร์ชัน 1

## ติดตั้งและอัปเดตโดยรักษาข้อมูลบนเซิร์ฟเวอร์

ทดสอบด้วย `php tests/install.php` ในฐานข้อมูลชั่วคราวแยกจาก Hub จริง:

- ติดตั้งใหม่สร้างบัญชี Admin ด้วยรหัสสุ่ม และไม่ใส่รายการตัวอย่างโดยอัตโนมัติ
- รันซ้ำรักษาบัญชี รหัสผ่าน URL และข้อมูลระบบเดิมครบทุกตาราง
- `--seed-demo` ไม่เพิ่มรายการซ้ำเมื่อมีระบบอยู่แล้ว
- พบตารางของระบบอื่นหรือโครงสร้างตารางขัดแย้งจะหยุดก่อนเขียนข้อมูล
- หลังทดสอบลบเฉพาะฐานข้อมูลชั่วคราวของการทดสอบ

## Workspace administration (October 2026)

- Isolated database test: `php tests/management.php` checks repeatable upgrade, category backfill, profile validation, active-account protection, auditing and cascade cleanup.
- HTTP workflow: `python3 tests/management-http.py` checks admin/teacher boundaries, CSRF, profile/PNG upload, private avatar access, custom categories/navigation, invalid destinations, referenced-category protection, admin pagination across 31 records and public pagination/search across 231 records, bulk visibility, disabling/reactivating accounts and activity history.
- Existing regression suites: accounts, accounts-http, smoke, uploads-http, install and LINE menu checks.
- Browser QA: dashboard at 1440px; dashboard, menus, catalog and profile at 390px. Catalog/profile document width equals viewport width. Screenshots kept in ignored `output/playwright/`.
- Limits: no production deployment or high-concurrency load benchmark performed. Public catalog switches to server-side search and 24-item pagination above 200 active services. Use `admin/upgrade.php` or `bin/upgrade-hub.php` for existing hosting databases before enabling new controls.
