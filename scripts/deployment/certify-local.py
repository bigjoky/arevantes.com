#!/usr/bin/env python3
"""Reproduce the local checks on a frozen TEST copy; serialize PostgreSQL DDL."""
import json,os,subprocess,sys,time
from pathlib import Path
root=Path.cwd()
if not (root/'candidate-manifest.json').exists() or (root/'.cloud/config.json').exists() or 'APP_ENV=testing' not in (root/'.env').read_text():raise SystemExit('Frozen testing copy without Cloud context required; never use the QA web database.')
logs=root/'certification-results';logs.mkdir(exist_ok=True)
env={k:os.environ[k] for k in ['PATH','HOME','TMPDIR','LANG','LC_ALL','SSL_CERT_FILE'] if k in os.environ}
checks=[('build',['npm','run','build']),('types',['npm','run','types:check']),('lint',['npm','run','lint:check']),('i18n',['npm','run','i18n:check']),('frontend',['npm','run','test:frontend']),('full',['php','artisan','test','--compact','--log-junit',str(logs/'full.xml')])]
checks += [(mode,['python3','scripts/deployment/local-checks.py',mode]) for mode in ['volume','diagnostics','postgres','economic-race','identity-race','temporal-race','fiscal-race','journey-race','security','health','api']]
checks += [('recovery',['python3','scripts/testing/stab03-recovery.py']),('preflight',['python3','scripts/deployment/local-checks.py','preflight'])]
results=[]
for name,command in checks:
 start=time.monotonic()
 with (logs/(name+'.log')).open('w') as out:result=subprocess.run(command,env=env,stdout=out,stderr=subprocess.STDOUT)
 review=name=='preflight' and result.returncode==1
 results.append({'check':name,'exit':result.returncode,'review_required':review,'seconds':round(time.monotonic()-start,3)})
 (logs/'results.json').write_text(json.dumps(results,indent=2)+'\n')
 print(name,result.returncode,'review required' if review else '',flush=True)
 if result.returncode and not review:raise SystemExit(result.returncode)
