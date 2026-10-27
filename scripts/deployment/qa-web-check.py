#!/usr/bin/env python3
"""Read-only web checks, except synthetic login/session creation. Never sends documents."""
import http.cookiejar,json,urllib.request,urllib.parse,urllib.error
from pathlib import Path
base='http://127.0.0.1:8027'
root=Path.cwd()
credentials=json.loads((root/'storage/qa-credentials.json').read_text())
fixtures=json.loads((root/'storage/qa-fixtures.json').read_text())
def login(email,password):
 jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
 opener.open(base+'/login',timeout=15).read()
 token=next(c.value for c in jar if c.name=='XSRF-TOKEN')
 req=urllib.request.Request(base+'/login',data=json.dumps({'email':email,'password':password}).encode(),headers={'Content-Type':'application/json','Accept':'application/json','X-XSRF-TOKEN':urllib.parse.unquote(token)},method='POST')
 opener.open(req,timeout=15).read()
 return opener,jar
def get(opener,path):
 try:
  with opener.open(base+path,timeout=25) as r:return r.status,r.geturl(),r.headers.get('Content-Type',''),r.read()
 except urllib.error.HTTPError as e:return e.code,e.geturl(),e.headers.get('Content-Type',''),e.read()
admin,jar=login(credentials['email'],credentials['password']);results=[]
paths=['/dashboard','/quotes','/quotes/'+str(fixtures['quote_id']),'/sales/'+str(fixtures['invoice_id']),'/sales/'+str(fixtures['invoice_id'])+'/pdf','/purchases','/expenses','/operations/planning']
for path in paths:
 status,url,kind,data=get(admin,path);results.append({'path':path,'status':status,'authenticated':not url.endswith('/login'),'pdf_valid':data.startswith(b'%PDF') if path.endswith('/pdf') else None});assert status==200 and not url.endswith('/login'),(path,status)
 if path.endswith('/pdf'):assert data.startswith(b'%PDF')
tech,_=login('technician@qa.invalid',credentials['technician_password'])
for path in ['/expenses','/quotes','/company-fiscal','/sales/'+str(fixtures['invoice_id'])+'/pdf']:
 status,url,_,_=get(tech,path);results.append({'path':path,'actor':'technician','status':status});assert status==403,(path,status)
try:
 login('test@example.com','password')
 raise AssertionError('Factory password must not authenticate in QA')
except urllib.error.HTTPError as e:
 assert e.code==422,e.code
 results.append({'actor':'retired_factory_user','known_password_rejected':True,'status':e.code})
status,url,_,_=get(urllib.request.build_opener(),'/expenses');assert url.endswith('/login');results.append({'path':'/expenses','actor':'anonymous','redirected_to_login':True})
cookies=[{'name':c.name,'value':c.value,'domain':c.domain,'path':c.path,'expires':c.expires or -1,'httpOnly':any(k.lower()=='httponly' for k in c._rest),'secure':c.secure,'sameSite':'Lax'} for c in jar]
p=root/'storage/qa-browser-state.json';p.write_text(json.dumps({'cookies':cookies,'origins':[]}));p.chmod(0o600)
print(json.dumps({'url':base,'checks':results,'communications_sent':False,'fiscal_documents_issued':False},indent=2))
