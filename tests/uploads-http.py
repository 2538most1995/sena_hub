"""Local HTTP upload checks. Uses one disposable system and cleans up its images."""
import http.cookiejar, urllib.request, urllib.parse, urllib.error, re, uuid, struct, zlib
from pathlib import Path
BASE='http://localhost:8888/'
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(path, data=None, file=None, anonymous=False):
    headers={}; body=None
    if file:
        boundary='HubTest'+uuid.uuid4().hex
        parts=[]
        for key,value in data.items():
            for v in value if isinstance(value,list) else [value]:
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{v}\r\n'.encode())
        filename,content,mime=file
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="icon_file"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+content+b'\r\n')
        parts.append(f'--{boundary}--\r\n'.encode());body=b''.join(parts)
        headers['Content-Type']='multipart/form-data; boundary='+boundary
    elif data is not None: body=urllib.parse.urlencode(data,doseq=True).encode()
    opener=urllib.request.build_opener() if anonymous else client
    try:
        r=opener.open(urllib.request.Request(BASE+path,data=body,headers=headers),timeout=15)
        return r.status,r.read(),r.headers
    except urllib.error.HTTPError as e:return e.code,e.read(),e.headers

def token(page):return re.search(rb'name="csrf" value="([a-f0-9]+)"',page)[1].decode()
def check(condition,label):assert condition,label;print('PASS',label)
def filename(page):return re.search(rb'system-icon.php\?id=\d+&amp;v=([a-f0-9]+\.png)',page)[1].decode()
_,page,_=request('admin/login.php')
password=re.search(r'^Password: (.+)$',Path('.local-credentials.txt').read_text(),re.M)[1]
_,page,_=request('admin/login.php',{'csrf':token(page),'username':'admin','password':password})
csrf=token(page)
item={'csrf':csrf,'action':'save','id':0,'name':'Upload integration test','description':'','url':'','icon':'apps','categories[]':['staff','reports'],'color':'#176b55','sort_order':999,'active':1,'icon_mode':'upload'}
_,page,_=request('admin/index.php',item)
check('กรุณาอัปโหลดรูปไอคอน' in page.decode(),'upload mode requires an image')
created=None
files=[]
try:
    png=Path('assets/images/sena-logo.png').read_bytes()
    missing=dict(item);missing.pop('csrf')
    status,_,_=request('admin/index.php',missing,('test.png',png,'image/png'))
    check(status==403,'uploads require CSRF')
    _,page,_=request('admin/index.php',item,('disguised.php',png,'image/png'))
    check('บันทึกเรียบร้อยแล้ว' in page.decode(),'upload creates system successfully')
    created=re.search(rb'Upload integration test.*?\?edit=(\d+)',page,re.S)[1].decode();item['id']=created
    _,page,_=request('admin/index.php?edit='+created)
    stored=filename(page);files.append(stored)
    check(re.fullmatch(r'[a-f0-9]{32}\.png',stored),'random PNG filename replaces original filename')
    status,image,headers=request('system-icon.php?id='+created)
    check(status==200 and headers['Content-Type']=='image/png' and image.startswith(b'\x89PNG'),'image served as PNG')
    width,height=struct.unpack('>II',image[16:24])
    check(max(width,height)==256,'image resized to at most 256 pixels')
    check(image!=png,'uploaded bytes re-encoded')
    _,public,_=request('?category=reports')
    check(('system-icon.php?id='+created).encode() in public,'uploaded icon renders without website URL')
    status,_,_=request('config/uploaded-icons/'+stored)
    check(status==403,'stored image cannot be accessed directly')
    bad_png=bytearray(png);bad_png[16:20]=struct.pack('>I',5000);bad_png[29:33]=struct.pack('>I',zlib.crc32(bad_png[12:29]))
    for bad in [('x.svg',b'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>','image/svg+xml'),('x.png',b'<?php echo 1; ?>','image/png'),('big.png',png+b'x'*(2*1024*1024),'image/png'),('wide.png',bytes(bad_png),'image/png')]:
        _,page,_=request('admin/index.php',item,bad)
        check('บันทึกเรียบร้อยแล้ว' not in page.decode() and 'role="alert"' in page.decode(),'unsafe, oversized or invalid image rejected: '+bad[0])
        _,page,_=request('admin/index.php?edit='+created)
        check(filename(page)==stored,'rejected upload preserves previous image')
    item['icon_mode']='icon';request('admin/index.php',item)
    _,public,_=request('?view=all')
    check(('system-icon.php?id='+created).encode() not in public,'switch to built-in icon')
    item['icon_mode']='upload';request('admin/index.php',item)
    _,page,_=request('admin/index.php?edit='+created)
    check(filename(page)==stored,'existing image retained without selecting file again')
    _,page,_=request('admin/index.php',item,('replacement.png',Path('assets/favicon/favicon-32.png').read_bytes(),'image/png'))
    _,page,_=request('admin/index.php?edit='+created)
    replacement=filename(page);files.append(replacement)
    check(replacement!=stored and not Path('config/uploaded-icons',stored).exists(),'replacement invalidates image URL and removes old file')
    item.pop('active');request('admin/index.php',item)
    status,_,_=request('system-icon.php?id='+created,anonymous=True)
    check(status==404,'hidden system image inaccessible to visitors')
    status,_,_=request('system-icon.php?id='+created)
    check(status==200,'admin can preview hidden system image')
finally:
    if created:
        request('admin/index.php',{'csrf':csrf,'action':'delete','id':created})
        check(all(not Path('config/uploaded-icons',f).exists() for f in files),'system deletion cleans uploaded files')
