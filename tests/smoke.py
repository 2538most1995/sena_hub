"""Integration checks against a local running Hub; creates and removes one test row."""
import html, re, urllib.request, urllib.parse, urllib.error, http.cookiejar
from pathlib import Path
BASE = 'http://localhost:8888/'
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def request(path='', data=None):
    try:
        r = client.open(BASE + path, urllib.parse.urlencode(data).encode() if data is not None else None)
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
    item['icon_mode'] = 'favicon'
    request('admin/index.php', item)
    _, public = request('?view=all')
    check('favicon.php?id='+created in public, 'favicon preference is persisted and rendered')
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
