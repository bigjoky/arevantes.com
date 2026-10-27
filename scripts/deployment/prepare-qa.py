#!/usr/bin/env python3
"""Prepare exclusively a synthetic, loopback-bound QA copy. No Cloud context."""
import base64,hashlib,json,os,secrets,subprocess,sys
from pathlib import Path
root=Path.cwd().resolve()
if not (root/'candidate-manifest.json').is_file() or (root/'.cloud/config.json').exists():raise SystemExit('Frozen source without Cloud context required.')
cli=['container','exec','apple-dev-postgres']
def sql(db,q):return subprocess.check_output(cli+['psql','-U','developer','-d',db,'-Atc',q],text=True).strip()
name='arevantes_deploy02_qa_test';role='deploy02_qa';marker='arevantes:deploy02:synthetic:v1'
if sql('postgres',"SELECT 1 FROM pg_database WHERE datname='"+name+"'") or sql('postgres',"SELECT 1 FROM pg_roles WHERE rolname='"+role+"'"):
 raise SystemExit('QA resource already exists. Reuse its private configuration; refusing to reset or alter it.')
password=secrets.token_hex(24)
sql('postgres',"CREATE ROLE "+role+" LOGIN PASSWORD '"+password+"'")
subprocess.run(cli+['createdb','-U','developer','-O',role,name],check=True)
sql('postgres',"COMMENT ON DATABASE "+name+" IS '"+marker+"'")
key='base64:'+base64.b64encode(secrets.token_bytes(32)).decode()
env={'NOMINATIM_URL':'http://127.0.0.1:9/disabled','GEONAMES_SPAIN_POSTAL_URL':'http://127.0.0.1:9/disabled','DEYE_API_URL':'http://127.0.0.1:9/disabled','HUAWEI_FUSIONSOLAR_URL':'http://127.0.0.1:9/disabled','APP_NAME':'AREVANTES-QA-DEPLOY02','APP_ENV':'local','APP_DEBUG':'false','APP_URL':'http://127.0.0.1:8027','APP_KEY':key,'APP_TIMEZONE':'Europe/Madrid','DB_CONNECTION':'pgsql','DB_HOST':'127.0.0.1','DB_PORT':'5432','DB_DATABASE':name,'DB_USERNAME':role,'DB_PASSWORD':password,'DB_SSLMODE':'disable','DB_URL':'','SESSION_DRIVER':'database','SESSION_COOKIE':'arevantes_deploy02_qa','SESSION_DOMAIN':'null','SESSION_SECURE_COOKIE':'false','CACHE_STORE':'database','CACHE_PREFIX':'deploy02_qa_','QUEUE_CONNECTION':'database','DB_QUEUE':'deploy02_qa','MAIL_MAILER':'log','BROADCAST_CONNECTION':'null','PRIVATE_FILESYSTEM_DISK':'local','FILESYSTEM_DISK':'local','INERTIA_SSR_ENABLED':'false','POSTGRES_BACKUP_ENABLED':'false','POSTGRES_RESTORE_VERIFICATION_ENABLED':'false'}
p=root/'.env';p.write_text('\n'.join(k+'="'+v+'"' for k,v in env.items())+'\n');p.chmod(0o600)
safe_env={k:os.environ[k] for k in ['PATH','HOME','TMPDIR','LANG','LC_ALL','SSL_CERT_FILE'] if k in os.environ}
subprocess.run(['php','scripts/deployment/qa-isolation.php'],check=True,env=safe_env,stdout=subprocess.DEVNULL)
subprocess.run(['php','artisan','migrate','--seed','--force'],check=True,env=safe_env)
credentials={'email':'admin@qa.invalid','password':secrets.token_urlsafe(24),'technician_password':secrets.token_urlsafe(24)}
p=root/'storage/qa-credentials.json';p.write_text(json.dumps(credentials));p.chmod(0o600)
subprocess.run(['php','scripts/deployment/seed-qa.php'],check=True,env=safe_env)
print(json.dumps({'url':env['APP_URL'],'database':name,'credentials_file':str(p),'production_access':False}))
