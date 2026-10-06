"""Exercise new management features via MAMP with temporary fixtures only."""
import subprocess,json,re,urllib.request,urllib.parse,urllib.error,http.cookiejar,secrets
from pathlib import Path
PHP='/Applications/MAMP/bin/php/php8.3.14/bin/php'
prefix='qa_mgmt_'+secrets.token_hex(5)
def php(code):return subprocess.check_output([PHP,'-r',"require 'app/bootstrap.php'; require 'app/accounts.php'; "+code],text=True)
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(c,path,data=None,file=None):
 headers={};body=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None
 if file:
  boundary='ProfileQA'+secrets.token_hex(8);parts=[]
  for key,value in data.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
  name,content,mime=file
  parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="avatar"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+content+b'\r\n')
  parts.append(f'--{boundary}--\r\n'.encode());body=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
 try:
  r=c.open(urllib.request.Request('http://localhost:8888/'+path,data=body,headers=headers));return r.status,r.geturl(),r.read().decode()
 except urllib.error.HTTPError as e:return e.code,e.geturl(),e.read().decode()
def token(page):return re.search(r'name="csrf" value="([a-f0-9]+)"',page)[1]
def check(ok,label):assert ok,label;print('PASS',label)
def login(c,user):
 _,_,page=request(c,'admin/login.php');return request(c,'admin/login.php',{'csrf':token(page),'username':user,'password':'Test1234'})
ids=json.loads(php("echo json_encode([save_hub_account(db(),0,'"+prefix+"_admin','Test1234','Test1234','admin'),save_hub_account(db(),0,'"+prefix+"_teacher','Test1234','Test1234','teacher')]);"))
snapshot=json.loads(php("echo json_encode(db()->query(\"SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('hub_categories','hub_navigation')\")->fetchAll(PDO::FETCH_KEY_PAIR));"))
system_ids=[]
try:
 a=client();t=client();_,_,page=login(a,prefix+'_admin');csrf=token(page);login(t,prefix+'_teacher')
 for path in ['menus.php','settings.php','users.php','dashboard.php','upgrade.php']:
  _,url,_=request(t,'admin/'+path,{'action':'save'});check(url.endswith('account.php'),'teacher denied '+path)
 for path in ['menus.php','settings.php','users.php','index.php']:
  status,_,_=request(a,'admin/'+path,{'action':'save'});check(status==403,'CSRF protected '+path)
 _,_,page=request(t,'admin/account.php');data={'csrf':token(page),'action':'profile','display_name':'ครู <script>test</script>','email':'qa@example.org','phone':'0123456789','position':'งานทะเบียน','bio':'ข้อมูลทดสอบ','id':ids[0],'role':'admin'}
 _,_,page=request(t,'admin/account.php',data);check('บันทึกโปรไฟล์เรียบร้อยแล้ว' in page and '&lt;script&gt;' in page,'self profile persists and escapes HTML')
 stored=json.loads(php('echo json_encode(db()->query("SELECT id,display_name,role FROM admins WHERE id IN ('+','.join(map(str,ids))+') ORDER BY id")->fetchAll());'))
 check(stored[0]['display_name']=='' and stored[1]['role']=='teacher','profile ignores forged account ID and role')
 _,_,page=request(t,'admin/account.php',data,('avatar.png',Path('assets/line/student.png').read_bytes(),'image/png'))
 avatar=php('echo db()->query("SELECT avatar_file FROM admins WHERE id='+str(ids[1])+'")->fetchColumn();')
 check(re.fullmatch('[a-f0-9]{32}\.png',avatar) is not None,'profile upload stores random re-encoded PNG')
 own=t.open('http://localhost:8888/profile-photo.php?id='+str(ids[1]));check(own.headers.get_content_type()=='image/png','authenticated owner can read avatar')
 status,_,_=request(client(),'profile-photo.php?id='+str(ids[1]));check(status==404,'avatar is inaccessible to anonymous visitors')
 status,_,_=request(t,'profile-photo.php?id='+str(ids[0]));check(status==403,'teacher cannot read another profile photo')
 _,_,page=request(t,'admin/account.php',data,('bad.svg',b'<svg></svg>','image/svg+xml'))
 check('รองรับเฉพาะรูป PNG' in page,'profile rejects SVG upload')
 data['remove_avatar']='1';request(t,'admin/account.php',data)
 check(not Path('config/uploaded-icons/'+avatar).exists(),'removing profile photo cleans stored file')
 data.pop('remove_avatar')
 _,_,page=request(a,'admin/menus.php');csrf=token(page)
 category={'csrf':csrf,'action':'save','kind':'categories','id':'','key':prefix,'label':'หมวดทดสอบ','icon':'book','tone':'green'}
 _,_,page=request(a,'admin/menus.php',category);check('บันทึกเมนูและหมวดหมู่แล้ว' in page,'create custom category')
 _,_,public=request(a,'');check('?category='+prefix in public and 'หมวดทดสอบ' in public,'custom category renders on public hub')
 menu={'csrf':csrf,'action':'save','kind':'menus','id':'','label':'ลิงก์ทดสอบ','target':'javascript:alert(1)','icon':'book','location':'top','active':'1'}
 _,_,page=request(a,'admin/menus.php',menu);check('ลิงก์หรือตำแหน่งเมนูไม่ถูกต้อง' in page,'reject executable menu URL')
 menu['target']='?category='+prefix;_,_,page=request(a,'admin/menus.php',menu);check('บันทึกเมนูและหมวดหมู่แล้ว' in page,'create category navigation link')
 _,_,public=request(a,'');check('ลิงก์ทดสอบ' in public,'custom navigation renders publicly')
 _,_,page=request(a,'admin/menus.php',{'csrf':csrf,'action':'delete','kind':'categories','id':prefix});check('มีเมนูลิงก์ไปหมวดนี้' in page,'category deletion protects navigation references')
 # Create enough records to check pagination across more than one page.
 system_ids=json.loads(php("$stmt=db()->prepare('INSERT INTO systems(name,category,sort_order) VALUES (?,?,?)');$ids=[];for($i=0;$i<31;$i++){$stmt->execute(['"+prefix+"_'.$i,'"+prefix+"',10000+$i]);$id=(int)db()->lastInsertId();$ids[]=$id;db()->prepare('INSERT INTO system_categories VALUES (?,?)')->execute([$id,'"+prefix+"']);}echo json_encode($ids);"))
 _,_,page=request(a,'admin/index.php?q='+prefix);check(page.count('class="system-selection"')==25 and '31 รายการ' in page,'catalog search paginates 25 of 31 matches')
 _,_,page=request(a,'admin/index.php?q='+prefix+'&page=2');check(page.count('class="system-selection"')==6,'catalog second page has remaining six matches')
 _,_,page=request(a,'admin/index.php?category='+prefix);check(page.count('class="system-selection"')==25,'custom category uses normalized membership index')
 more_ids=json.loads(php("$stmt=db()->prepare('INSERT INTO systems(name,category,sort_order) VALUES (?,?,?)');$ids=[];for($i=31;$i<231;$i++){$stmt->execute(['"+prefix+"_'.$i,'"+prefix+"',10000+$i]);$id=(int)db()->lastInsertId();$ids[]=$id;db()->prepare('INSERT INTO system_categories VALUES (?,?)')->execute([$id,'"+prefix+"']);}echo json_encode($ids);"))
 system_ids.extend(more_ids)
 _,_,page=request(a,'?view=all&q='+prefix);check('data-server-search="true"' in page and page.count('<article class="system-card')==24 and '231 ระบบ' in page,'large public catalog switches to server-side pagination')
 _,_,page=request(a,'?view=all&q='+prefix+'&page=2');check(page.count('<article class="system-card')==24 and 'หน้า 2 /' in page,'public second page preserves query')
 _,_,page=request(a,'?view=all&q='+prefix+'_230');check(page.count('<article class="system-card')==1,'public search finds records beyond first page')
 _,_,page=request(a,'admin/index.php',{'csrf':csrf,'action':'bulk','ids[]':system_ids[:2],'operation':'hide'});check('บันทึกเรียบร้อยแล้ว' in page,'bulk hide succeeds')
 check(int(php('echo db()->query("SELECT SUM(active=0) FROM systems WHERE id IN ('+','.join(map(str,system_ids[:2]))+')")->fetchColumn();'))==2,'bulk hide changes only selected records')
 _,_,page=request(a,'admin/users.php',{'csrf':csrf,'action':'set_active','id':ids[1],'active':'0'});check('เปลี่ยนสถานะบัญชีแล้ว' in page,'administrator can disable teacher')
 _,url,_=request(t,'admin/account.php');check(url.endswith('login.php'),'disabled account loses existing session')
 _,url,page=login(client(),prefix+'_teacher');check(url.endswith('login.php') and 'ไม่ถูกต้อง' in page,'disabled account cannot log in')
 request(a,'admin/users.php',{'csrf':csrf,'action':'set_active','id':ids[1],'active':'1'});_,url,_=login(client(),prefix+'_teacher');check(url.endswith('account.php'),'reactivated account can log in')
 _,_,page=request(a,'admin/activity.php');check('แก้ไขโปรไฟล์' in page and 'เปลี่ยนสถานะหลายระบบ' in page,'activity lists actual management actions')
finally:
 if system_ids:php('db()->exec("DELETE FROM systems WHERE id IN ('+','.join(map(str,system_ids))+')");')
 Path('/tmp/sena-menus-restore.json').write_text(json.dumps(snapshot))
 php("$old=json_decode(file_get_contents('/tmp/sena-menus-restore.json'),true);foreach(['hub_categories','hub_navigation'] as $key){if(isset($old[$key]))save_setting(db(),$key,$old[$key]);else db()->prepare('DELETE FROM settings WHERE setting_key=?')->execute([$key]);}")
 php('db()->exec("DELETE FROM audit_log WHERE actor_id IN ('+','.join(map(str,ids))+')");db()->exec("DELETE FROM admins WHERE id IN ('+','.join(map(str,ids))+')");')
 Path('/tmp/sena-menus-restore.json').unlink(missing_ok=True)
