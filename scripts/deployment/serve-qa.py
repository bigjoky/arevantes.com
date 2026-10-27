#!/usr/bin/env python3
"""QA only: no inherited integration credentials, scheduler, worker or Cloud context."""
import os,subprocess
from pathlib import Path
root=Path.cwd()
text=(root/'.env').read_text()
required=['APP_ENV="local"','DB_HOST="127.0.0.1"','DB_PORT="5432"','DB_URL=""','APP_URL="http://127.0.0.1:8027"','DB_DATABASE="arevantes_deploy02_qa_test"','DB_USERNAME="deploy02_qa"','MAIL_MAILER="log"','QUEUE_CONNECTION="database"']
if (root/'.cloud/config.json').exists() or not all(x in text for x in required):raise SystemExit('Dedicated QA configuration required.')
env={k:os.environ[k] for k in ['PATH','HOME','TMPDIR','LANG','LC_ALL','SSL_CERT_FILE'] if k in os.environ}
subprocess.run(['php','scripts/deployment/qa-isolation.php'],env=env,stdout=subprocess.DEVNULL,check=True)
raise SystemExit(subprocess.run(['php','artisan','serve','--host=127.0.0.1','--port=8027','--tries=1'],env=env).returncode)
