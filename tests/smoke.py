"""Integration checks against a local running Hub; creates and removes one test row."""
import html, re, urllib.request, urllib.parse, urllib.error, http.cookiejar
from pathlib import Path
BASE = 'http://localhost:8888/'
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def request(path='', data=None):
    try:
        r = client.open(BASE + path, urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None)
        return r.status, r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode()
def token(page):
    return re.search(r'name="csrf" value="([a-f0-9]+)"', page)[1]
def check(condition, name):
    assert condition, name
    print('PASS', name)
status, page = request()
check(status == 200 and 'SENA Asset' in page, 'public catalog renders from database')
status, page = request('admin/')
check('สำหรับผู้ดูแลระบบและครู' in page, 'unauthenticated admin redirects to login')
status, _ = request('admin/login.php', {'username': 'admin', 'password':'invalid'})
check(status == 403, 'login requires CSRF token')
_, login = request('admin/login.php')
password = re.search(r'^Password: (.+)$', Path('.local-credentials.txt').read_text(), re.M)[1]
_, page = request('admin/login.php', {'csrf': token(login), 'username':'admin', 'password':password})
check('รายการระบบ' in page, 'admin authenticates')
csrf = token(page)
item = {'csrf':csrf,'action':'save','id':0,'name':'Smoke <script>alert(1)</script>','description':'Integration check','url':'javascript:alert(1)','icon':'apps','category':'forms','color':'#176b55','sort_order':999,'active':1,'icon_mode':'icon'}
_, page = request('admin/index.php', item)
check('URL ต้องขึ้นต้น' in page, 'unsafe URL rejected')
item['url'] = 'https://example.org/'
created = None
try:
    _, page = request('admin/index.php', item)
    check('Smoke &lt;script&gt;' in page and 'Smoke <script>' not in page, 'stored HTML escaped')
    row = re.search(r'Smoke &lt;script&gt;.*?\?edit=(\d+)', page, re.S)
    created = row[1]
    _, public = request('?view=all')
    check('favicon.php?id='+created not in public, 'manual icon preference suppresses remote favicon')
    item['id'] = created
    item.pop('category')
    item['categories[]'] = ['forms', 'staff', 'forms']
    _, page = request('admin/index.php', item)
    check('ครูและบุคลากร · แบบฟอร์ม' in page, 'multiple categories save on one system')
    _, edit = request('admin/index.php?edit='+created)
    for key in ['forms', 'staff']:
        check(re.search(r'name="categories\[\]" value="'+key+r'" checked',edit), 'edit retains category: '+key)
    for key in ['forms', 'staff']:
        _, public = request('?category='+key)
        card = re.search(r'<article[^>]*data-category="staff forms"[^>]*>.*?Smoke &lt;script&gt;.*?</article>',public,re.S)
        check(card and ' hidden' not in card[0].split('>')[0], 'system visible in category: '+key)
    _, public = request('?view=all')
    check(public.count('href="https://example.org/"') == 1, 'all categories list has no duplicate website')
    for bad in [[], ['invalid'], 'forms', [['forms']]]:
        item['categories[]'] = bad
        # For non-array payloads exercise a malformed categories field directly.
        malformed = dict(item)
        if not isinstance(bad, list):
            malformed.pop('categories[]'); malformed['categories'] = bad
        _, page = request('admin/index.php', malformed)
        check('กรุณาเลือกอย่างน้อย 1 หมวดหมู่' in page, 'invalid or empty categories rejected')
    _, edit = request('admin/index.php?edit='+created)
    check(all(re.search(r'name="categories\[\]" value="'+key+r'" checked',edit) for key in ['forms','staff']), 'rejected submissions preserve existing memberships')
    item['categories[]'] = ['staff']
    request('admin/index.php', item)
    _, public = request('?category=forms')
    card = re.search(r'<article[^>]*data-category="staff"[^>]*>.*?Smoke &lt;script&gt;.*?</article>',public,re.S)
    check(card and ' hidden' in card[0].split('>')[0], 'unchecked category removes membership')
    _, edit = request('admin/index.php?edit='+created)
    check(re.search(r'name="categories\[\]" value="staff" checked',edit) and not re.search(r'name="categories\[\]" value="forms" checked',edit), 'single remaining category retained after reload')
    item['icon_mode'] = 'favicon'
    request('admin/index.php', item)
    _, public = request('?view=all')
    check('favicon.php?id='+created in public, 'favicon preference is persisted and rendered')
    before_revision=re.search(r'favicon.php\?id='+created+r'&amp;v=([^"]+)',public)[1]
    status,_=request('admin/index.php',{'action':'refresh_favicon','id':created})
    check(status==403,'favicon refresh requires CSRF')
    _,page=request('admin/index.php',{'csrf':csrf,'action':'refresh_favicon','id':created})
    check('ล้างแคชแล้ว' in page,'admin can request favicon refresh')
    _,public=request('?view=all')
    after_revision=re.search(r'favicon.php\?id='+created+r'&amp;v=([^"]+)',public)[1]
    check(before_revision!=after_revision,'favicon refresh invalidates browser URL')
    _, public = request()
    check('href="https://example.org/"' in public and 'rel="noopener noreferrer"' in public, 'saved link appears publicly with safe new tab')
    status, _ = request('admin/index.php', {'action':'delete','id':created})
    check(status == 403, 'admin mutations require CSRF token')
    item['id'] = created
    item['name'] = 'Smoke updated'
    item.pop('active')
    _, page = request('admin/index.php', item)
    _, public = request()
    check('Smoke updated' in page and 'Smoke updated' not in public, 'edit and hide work')
finally:
    if created:
        _, page = request('admin/index.php', {'csrf':csrf,'action':'delete','id':created})
        check('Smoke updated' not in page, 'delete removes test record')
for path in ['config/local.php','.local-credentials.txt','database/schema.sql','bin/install.php']:
    status, _ = request(path)
    check(status in (403,404), 'sensitive path blocked: '+path)
_, page = request('admin/index.php', {'csrf':csrf,'action':'logout'})
check('สำหรับผู้ดูแลระบบและครู' in page, 'logout works')
