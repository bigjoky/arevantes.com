#!/usr/bin/env python3
"""Only synthetic local PostgreSQL; explicit modes; no business DB arguments."""
import os,subprocess,sys,secrets,tempfile,json
from pathlib import Path
root=Path.cwd()
if not (root/'candidate-manifest.json').exists() or (root/'.cloud/config.json').exists() or 'APP_ENV=testing' not in (root/'.env').read_text():raise SystemExit('Frozen testing source required; never run against the usual application or QA web copy.')
mode=sys.argv[1] if len(sys.argv)==2 else ''
groups={'diagnostics':('arevantes_deploy01_incremental_test',['DeploymentReadOnlyDiagnosticsTest']), 'api':('arevantes_deploy02_api_test',['Api/MobileV1Test','Api/MobilePushTest']), 'volume':('arevantes_deploy01_incremental_test',['DeploymentIncrementalTest']), 'health':('arevantes_deploy01_health_test',['StabilizationHealthTest']), 'security':('arevantes_deploy01_security_concurrency_test',['BackendTableSecurityTest']), 'fiscal-race':('arevantes_deploy01_fiscal_concurrency_test',['FiscalConcurrencyTest']), 'journey-race':('arevantes_deploy01_journey_concurrency_test',['ProjectJourneyConcurrencyTest']), 'preflight':('arevantes_deploy01_incremental_test',[]), 'incremental':('arevantes_deploy01_incremental_test',['DeploymentIncrementalTest']), 'postgres':('arevantes_deploy01_integrity_test',None), 'economic-race':('arevantes_deploy01_economic_concurrency_test',['EconomicConcurrencyTest']), 'identity-race':('arevantes_deploy01_eco02_concurrency_test',['ReceivedInvoiceConcurrencyTest']), 'temporal-race':('arevantes_deploy01_temporal_concurrency_test',['TemporalConcurrencyTest'])}
if mode not in groups:raise SystemExit('Choose one of: '+', '.join(sorted(groups)))
name,tests=groups[mode]
cli=['container','exec','apple-dev-postgres']
def sql(db,q):return subprocess.check_output(cli+['psql','-U','developer','-d',db,'-Atc',q],text=True).strip()
exists=sql('postgres',"SELECT 1 FROM pg_database WHERE datname='"+name+"'")=='1'
marker='arevantes:deploy01:synthetic:v1'
if not exists:
 subprocess.run(cli+['createdb','-U','developer',name],check=True)
 sql('postgres',"COMMENT ON DATABASE "+name+" IS '"+marker+"'")
if sql('postgres',"SELECT coalesce(shobj_description(oid,'pg_database'),'') FROM pg_database WHERE datname='"+name+"'")!=marker:
 raise SystemExit('Existing database not marked for DEPLOY01; refusing to use it')
password=subprocess.check_output(cli+['printenv','POSTGRES_PASSWORD'],text=True).strip()
base_env={k:os.environ[k] for k in ['PATH','HOME','TMPDIR','LANG','LC_ALL','SSL_CERT_FILE'] if k in os.environ}
env=dict(base_env,APP_ENV='testing',APP_TIMEZONE='Europe/Madrid',DB_CONNECTION='pgsql',DB_HOST='127.0.0.1',DB_PORT='5432',DB_DATABASE=name,DB_USERNAME='developer',DB_PASSWORD=password,DB_URL='',DB_SSLMODE='disable',SESSION_DRIVER='array',CACHE_STORE='array',QUEUE_CONNECTION='sync',MAIL_MAILER='array',DEPLOY01_LOCAL_TEST='1')
receipt=root/'storage/deploy-incremental-storage.json'
if mode=='preflight':
 if not receipt.exists():raise SystemExit('Run incremental or volume first to establish the matching synthetic file snapshot.')
 record=json.loads(receipt.read_text());storage=Path(record['root']).resolve()
 if record['database']!=name or not storage.is_dir() or storage.parent!=Path(tempfile.gettempdir()).resolve() or not storage.name.startswith(('arevantes-deploy01-volume-storage-','arevantes-deploy01-incremental-storage-')):raise SystemExit('Matching synthetic storage receipt required.')
else:
 storage=Path(tempfile.mkdtemp(prefix='arevantes-deploy01-'+mode+'-storage-'))
 if mode in ['volume','incremental']:
  receipt.write_text(json.dumps({'root':str(storage),'database':name}));receipt.chmod(0o600)
for directory in ['framework/cache/data','framework/sessions','framework/views','logs','app/private']:(storage/directory).mkdir(parents=True,exist_ok=True)
env['LARAVEL_STORAGE_PATH']=str(storage)
if mode=='volume':env['DEPLOY02_VOLUME']='1'
if mode=='diagnostics':env['DEPLOY02_DIAGNOSTICS']='1'
if tests is None:
 tests=[p.stem for p in Path('tests/Feature').glob('*.php') if any(x in p.stem for x in ['Temporal','Economic','Accounting','Invoice','Fiscal','Document','Security','Payment','Portal','Backup','Recovery','PurchaseOrderDate','Planning','WorkOrder','Availability','LeadResponse','Portfolio']) and not any(x in p.stem for x in ['Concurrency','Rehearsal'])]
if mode=='preflight':
 sys.exit(subprocess.run(['php','scripts/deployment/local-preflight.php'],env=env).returncode)
args=['php','artisan','test','--compact','--display-warnings','--log-junit',str(Path('/tmp')/('deploy01-'+mode+'-junit.xml')),*['tests/Feature/'+t+'.php' for t in sorted(tests)]]
sys.exit(subprocess.run(args,env=env).returncode)
