# coding: utf-8
"""Exercise account authorization through MAMP; remove only temporary fixture users."""
import subprocess, json, re, urllib.request, urllib.parse, urllib.error, http.cookiejar, secrets
PHP='/Applications/MAMP/bin/php/php8.3.14/bin/php'
prefix='qa_'+secrets.token_hex(6)
def php(code):
    return subprocess.check_output([PHP,'-r',"require 'app/bootstrap.php'; require 'app/accounts.php'; "+code],text=True)
ids=json.loads(php("echo json_encode([save_hub_account(db(),0,'"+prefix+"_admin','Test1234','Test1234','admin'),save_hub_account(db(),0,'"+prefix+"_teacher','Test1234','Test1234','teacher')]);"))
def client(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(c,path,data=None):
    try:
        r=c.open('http://localhost:8888/admin/'+path,urllib.parse.urlencode(data).encode() if data is not None else None)
        return r.status,r.geturl(),r.read().decode()
    except urllib.error.HTTPError as e: return e.code,e.geturl(),e.read().decode()
def token(page): return re.search(r'name="csrf" value="([a-f0-9]+)"',page)[1]
def check(condition,label):
    assert condition,label
    print('PASS',label)
def login(c,username,password):
    _,_,page=request(c,'login.php')
    return request(c,'login.php',{'csrf':token(page),'username':username,'password':password})
try:
    admin=client(); teacher=client()
    status,url,page=login(admin,prefix+'_admin','Test1234'); check(status==200 and url.endswith('index.php'),'admin login and catalog')
    status,url,page=login(teacher,prefix+'_teacher','Test1234'); check(status==200 and url.endswith('account.php'),'teacher login lands on own account')
    status,url,page=request(teacher,'users.php'); check(url.endswith('account.php') and 'เพิ่มผู้ใช้ใหม่' not in page,'teacher denied user management')
    status,url,page=request(teacher,'index.php',{'action':'settings','public_url':'https://bad.example/'}); check(url.endswith('account.php'),'teacher cannot mutate admin settings')
    status,url,page=request(teacher,'account.php',{'action':'account'}); check(status==403,'account changes require CSRF')
    _,_,page=request(teacher,'account.php'); csrf=token(page)
    data={'csrf':csrf,'action':'account','username':prefix+'_renamed','current_password':'Test1234','new_password':'12345678','confirm_password':'12345678','role':'admin','id':ids[0]}
    status,url,page=request(teacher,'account.php',data); check('บันทึกบัญชีเรียบร้อยแล้ว' in page,'teacher renames self and changes eight character password')
    roles=json.loads(php('echo json_encode(db()->query("SELECT id,username,role FROM admins WHERE id IN ('+','.join(map(str,ids))+') ORDER BY id")->fetchAll());'))
    check(roles[0]['username']==prefix+'_admin' and roles[1]['role']=='teacher','forged ID and role do not change another user or promote teacher')
    status,url,page=login(client(),prefix+'_renamed','12345678'); check(url.endswith('account.php'),'renamed account authenticates with new password')
    _,_,page=request(admin,'users.php'); csrf=token(page)
    data={'csrf':csrf,'action':'save_user','id':ids[1],'username':prefix+'_renamed','password':'87654321','confirm_password':'87654321','role':'teacher'}
    status,url,page=request(admin,'users.php',data); check('บันทึกบัญชีเรียบร้อยแล้ว' in page,'admin resets teacher password')
    status,url,page=request(teacher,'account.php'); check(url.endswith('login.php'),'password reset invalidates old session')
    data['password']=data['confirm_password']='1234567'; status,url,page=request(admin,'users.php',data); check('อย่างน้อย 8 ตัวอักษร' in page,'HTTP rejects seven character password')
    data['password']=data['confirm_password']=''; data['username']=prefix+'_admin'; status,url,page=request(admin,'users.php',data); check('มีอยู่แล้ว' in page,'HTTP duplicate username gives friendly error')
    data['username']=prefix+'_renamed'; data['role']='admin'; request(admin,'users.php',data)
    promoted=client(); _,url,_=login(promoted,prefix+'_renamed','87654321'); check(url.endswith('index.php'),'admin can grant administrator role')
    data['role']='teacher'; request(admin,'users.php',data)
    _,url,_=request(promoted,'users.php'); check(url.endswith('account.php'),'role change applies to existing session immediately')
finally:
    php('db()->exec("DELETE FROM admins WHERE id IN ('+','.join(map(str,ids))+')");')
