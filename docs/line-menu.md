# เมนู LINE ด้วย Reply API (แพ็กเกจฟรี)

`line-webhook.php` ส่งการ์ด Flex เมื่อบอตเข้ากลุ่ม (`join`) หรือมีคนพิมพ์ เมนู / menu / รวมระบบ / hub / sena / เว็บ / ระบบ / รวมเว็บ / เสนา ใช้เฉพาะ Reply API ไม่มี Push หรือ Broadcast ไม่แตะฐานข้อมูล Hub ไม่เก็บข้อความหรือโปรไฟล์สมาชิก

## ตั้งค่าบนโฮสติ้ง

1. อัปโหลด `line-webhook.php` ที่โฟลเดอร์ Hub เดิม
2. คัดลอก `config/line.example.php` เป็น `config/line.php` บนเซิร์ฟเวอร์ ใส่ Channel secret และ Channel access token ของ Messaging API บัญชี SENA DIGITAL HUB เก็บไฟล์นี้เฉพาะเซิร์ฟเวอร์ ห้ามใส่ Git
3. ใช้ PHP ที่เปิด cURL และออก HTTPS ไป api.line.me ได้ ให้ PHP เขียนโฟลเดอร์ `config/line-events` ได้ (สร้างเองด้วยสิทธิ์ 0700) ทั้งโฟลเดอร์ config ต้องถูกปิดการเข้าถึงผ่านเว็บด้วย `.htaccess` เดิม
4. ตั้ง Webhook URL เป็น `https://krumost.com/sena_hub/line-webhook.php` แล้วกด Verify ให้สำเร็จ จากนั้นเปิด Use webhook
5. ตรวจว่าการอนุญาตเข้ากลุ่มเปิดอยู่ หากมี Webhook เดิมอยู่แล้วต้องรวม handler นี้เข้าโค้ดเดิมก่อน ห้ามแทน URL เดิมโดยไม่ตรวจ
6. เมื่อ Webhook ผ่านการทดสอบ ให้ปิดคำตอบเมนูเดิมเพื่อไม่ตอบซ้ำ หากมีคำตอบเริ่มต้นที่ตอบทุกข้อความด้วย ให้ใช้โหมดแชทแบบแมนนวลโดยเปิด Webhook และปิดเวลาตอบข้อความ เพื่อให้บอตทำงานตลอดวัน ข้อความเดิมยังเก็บไว้ใน OA Manager แต่จะไม่ถูกส่งอัตโนมัติในโหมดนี้ ข้อความทักทายเพื่อนใหม่เปิดไว้ได้
7. ทดลองจากกลุ่มทดสอบที่ไม่มี OA อื่น: เชิญบอตเข้ากลุ่ม จะได้การ์ดเมนูทันที จากนั้นพิมพ์ “เมนู” และคลิกปุ่มต่างๆ ตรวจบนคอมและมือถือ

กลุ่มที่มีบอตอยู่ก่อนเปิด Webhook จะไม่เกิด join ใหม่ ให้พิมพ์ “เมนู” แทน สมาชิกต้องกดปุ่มเพื่อเปิดหน้าเว็บ การ์ดไม่ได้เปิดหน้าเว็บบนเครื่องทุกคนโดยอัตโนมัติ

การกด Verify ส่ง events ว่าง จึงยืนยันการเชื่อมต่อและลายเซ็น แต่ยังไม่ยืนยันว่า access token ส่งการ์ดได้ ต้องทดสอบแชตจริงด้วย

HTTP: 405 เมื่อเปิดผ่าน browser (endpoint รับ POST เท่านั้น), 503 เมื่อยังไม่ตั้ง credentials/cURL/cache, 403 เมื่อลายเซ็นไม่ถูกต้อง, 400 เมื่อ JSON ผิด, 502 เมื่อ LINE ปฏิเสธการส่ง ตรวจ server log ซึ่งแสดงเฉพาะ HTTP status

config/line-events เก็บเฉพาะ hash ของ event ID และสถานะส่งสำเร็จเพื่อป้องกันส่งซ้ำ เก็บไว้อย่างน้อยตลอดช่วง webhook redelivery สามารถลบไฟล์เก่ากว่า 7 วันตามรอบดูแลโฮสติ้ง

ทดสอบในเครื่อง: `/Applications/MAMP/bin/php/php8.3.14/bin/php tests/line.php`

Reply messages ไม่ถูกนับในโควตาข้อความรายเดือน: https://developers.line.biz/en/docs/messaging-api/pricing/
Join event รองรับการตอบกลับ: https://developers.line.biz/en/reference/messaging-api/#join-event
