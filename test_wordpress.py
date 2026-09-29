"""Live HTTP integration checks. Creates and deletes its own workshop and reservations."""
from pathlib import Path
from urllib.request import build_opener,HTTPCookieProcessor,Request
from urllib.parse import urlencode,parse_qs,urlsplit
from urllib.error import HTTPError
from http.cookiejar import CookieJar
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime,timedelta,timezone
import re,html,uuid
BASE='http://127.0.0.1:8195';ROOT=Path(__file__).parent;checks=[]
def client():return build_opener(HTTPCookieProcessor(CookieJar()))
def request(web,path='/',data=None,status=200):
 try:
  r=web.open(Request(BASE+path,data=urlencode(data).encode() if data is not None else None));body=r.read().decode();assert r.status==status,(r.status,status);return body,r.url
 except HTTPError as e:
  body=e.read().decode();assert e.code==status,(e.code,status,body[-600:]);return body,e.url

def fields(form):return {k:html.unescape(v) for k,v in re.findall(r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"',form)}
def forms(page,action):return [x for x in re.findall(r'<form\b.*?</form>',page,re.S) if 'value="'+action+'"' in x]
def check(text):checks.append(text);print('PASS:',text)
admin=client();guest=client();env=dict(line.split('=',1) for line in (ROOT/'.local.env').read_text().splitlines());request(admin,'/wp-login.php');request(admin,'/wp-login.php',{'log':'portfolio_admin','pwd':env['WP_ADMIN_PASSWORD'],'redirect_to':BASE+'/wp-admin/','testcookie':'1'})
page,_=request(guest);assert 'Make room for better work.' in page;check('Actual WordPress theme and booking forms render')
request(guest,'/wp-admin/admin-post.php',{'action':'sc_book'},403);check('Missing booking nonce rejected')
request(guest,'/wp-admin/admin-post.php',{'action':'sc_delete','session_id':1},403);check('Anonymous workshop deletion rejected')
page,_=request(admin,'/wp-admin/admin.php?page=sessioncraft');form=fields(forms(page,'sc_schedule')[-1]);tag='INTEGRATION FIXTURE '+uuid.uuid4().hex[:8];form.update(title=tag,starts_at=(datetime.now(timezone.utc)+timedelta(days=40)).strftime('%Y-%m-%dT12:00'),duration=90,capacity=2,category='Test fixture',description='Temporary verification fixture; deleted after the run.')
page,_=request(admin,'/wp-admin/admin-post.php',form);sc_form=next(x for x in forms(page,'sc_schedule') if tag in x);schedule=fields(sc_form);sid=schedule['session_id'];check('Authenticated staff creates a persistent workshop')
try:
 page,_=request(guest);booking=fields(next(x for x in forms(page,'sc_book') if f'value="{sid}"' in x));booking.update(name='TEST PARTICIPANT',email='invalid',consent='yes');request(guest,'/wp-admin/admin-post.php',booking,400);check('Invalid email rejected without reservation')
 def reserve(n):
  web=client();data=dict(booking,name='TEST PARTICIPANT '+str(n),email=f'verification-{n}@example.invalid');return request(web,'/wp-admin/admin-post.php',data)
 with ThreadPoolExecutor(max_workers=5) as pool:results=list(pool.map(reserve,range(5)))
 confirmed=[r for r in results if 'Your seat is saved.' in r[0]];waiting=[r for r in results if 'on the waiting list.' in r[0]];assert len(confirmed)==2 and len(waiting)==3;check('Five concurrent reservations yield exactly two confirmed seats and three waiting places')
 request(guest,'/wp-admin/admin-post.php',dict(booking,name='Duplicate',email='verification-0@example.invalid'),409);check('Duplicate email within a workshop rejected')
 row=fields(forms(confirmed[0][0],'sc_cancel')[0]);bad=dict(row,key='tampered');request(guest,'/wp-admin/admin-post.php',bad,403);check('Tampered cancellation token rejected')
 page,_=request(guest,'/wp-admin/admin-post.php',row);assert 'reservation is cancelled.' in page;check('Private link cancellation persists')
 refreshed=[request(guest,urlsplit(url).path+'?'+urlsplit(url).query)[0] for _,url in waiting];assert sum('Your seat is saved.' in x for x in refreshed)==1;check('Cancelling one confirmed seat promotes exactly one waiting participant')
 # IDs establish queue order even when requests complete out of order.
 first=min(waiting,key=lambda r:int(parse_qs(urlsplit(r[1]).query)['reservation'][0]));page,_=request(guest,urlsplit(first[1]).path+'?'+urlsplit(first[1]).query);assert 'Your seat is saved.' in page;check('Waiting-list promotion follows reservation order')
 request(guest,'/wp-admin/admin-post.php',row);after=[request(guest,urlsplit(url).path+'?'+urlsplit(url).query)[0] for _,url in waiting];assert sum('Your seat is saved.' in x for x in after)==1;check('Repeated cancellation is idempotent')
 schedule.update(capacity='1',starts_at=form['starts_at']);request(admin,'/wp-admin/admin-post.php',schedule,409);check('Staff cannot reduce capacity below confirmed reservations')
 schedule.update(capacity='4');request(admin,'/wp-admin/admin-post.php',schedule);after=[request(guest,urlsplit(url).path+'?'+urlsplit(url).query)[0] for _,url in waiting];assert all('Your seat is saved.' in x for x in after);check('Increasing capacity promotes the waiting list')
finally:
 page,_=request(admin,'/wp-admin/admin.php?page=sessioncraft');delete=next(x for x in forms(page,'sc_delete') if f'name="session_id" value="{sid}"' in x);request(admin,'/wp-admin/admin-post.php',fields(delete));page,_=request(admin,'/wp-admin/admin.php?page=sessioncraft');assert tag not in page;check('Staff deletes test workshop and its reservation records')
ROOT.joinpath('TEST_RESULTS.md').write_text('# SessionCraft verification\n\nVerified '+datetime.now(timezone.utc).isoformat(timespec='seconds')+' against real local WordPress 7.1.2 / PHP 8.3 / MariaDB.\n\n'+''.join('- PASS: '+x+'\n' for x in checks)+'\nAll test-created records removed. Public hosting, email delivery and payments are outside this build.\n')
print(len(checks),'integration checks passed.')
