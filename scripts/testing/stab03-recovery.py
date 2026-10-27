#!/usr/bin/env python3
"""Run the STAB-03 rehearsal only against two marked synthetic databases
in the already configured local apple-dev-postgres container. Never takes a DB URL.
"""
import os
from pathlib import Path
import shutil
import subprocess
import sys

root = Path(__file__).resolve().parents[2]
cli = shutil.which('container')
if not cli:
    raise SystemExit('The existing local container CLI is required; nothing has been changed.')
source = 'arevantes_stab03_recovery_test'
target = 'arevantes_stab03_restore_verify_test'

def psql(database, sql):
    return subprocess.check_output([cli, 'exec', 'apple-dev-postgres', 'psql', '-U', 'developer', '-d', database, '-Atc', sql], text=True).strip()

for database, marker in [(source, 'arevantes:synthetic-recovery-source:v1'), (target, 'arevantes:disposable-restore-verification:v1')]:
    existed = psql('postgres', "SELECT 1 FROM pg_database WHERE datname='" + database + "'") == '1'
    if not existed:
        subprocess.run([cli, 'exec', 'apple-dev-postgres', 'createdb', '-U', 'developer', database], check=True)
    current = psql('postgres', "SELECT coalesce(shobj_description(oid,'pg_database'),'') FROM pg_database WHERE datname='" + database + "'")
    if current != marker:
        if existed:
            raise SystemExit('An existing database lacks the required rehearsal marker. Refusing to tag or overwrite it.')
        if psql(database, "SELECT count(*) FROM pg_namespace WHERE nspname NOT IN ('public','information_schema') AND nspname NOT LIKE 'pg_%'") != '0' or psql(database, "SELECT count(*) FROM pg_tables WHERE schemaname='public'") != '0':
            raise SystemExit('The newly provisioned database is not empty; refusing to tag it.')
        psql('postgres', "COMMENT ON DATABASE " + database + " IS '" + marker + "'")

password = subprocess.check_output([cli, 'exec', 'apple-dev-postgres', 'printenv', 'POSTGRES_PASSWORD'], text=True).strip()
env = dict(os.environ, APP_ENV='testing', DB_CONNECTION='pgsql', DB_HOST='127.0.0.1', DB_PORT='5432', DB_DATABASE=source,
           DB_USERNAME='developer', DB_PASSWORD=password, DB_SSLMODE='disable', DB_URL='', APP_TIMEZONE='Europe/Madrid',
           SESSION_DRIVER='array', CACHE_STORE='array', QUEUE_CONNECTION='sync', MAIL_MAILER='array', STAB03_RECOVERY_REHEARSAL='1')
sys.exit(subprocess.run(['php', 'artisan', 'test', '--compact', 'tests/Feature/PostgresRecoveryRehearsalTest.php'], cwd=root, env=env).returncode)
