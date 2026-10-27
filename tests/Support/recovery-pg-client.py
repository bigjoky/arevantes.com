#!/usr/bin/env python3
"""Test-only PG18 client bridge into the existing local Apple container.
Never accepts a business database, arbitrary SQL, a remote host, or credentials in argv.
"""
import os
import pathlib
import shutil
import subprocess
import sys

SOURCE = 'arevantes_stab03_recovery_test'
TARGET = 'arevantes_stab03_restore_verify_test'
operation = pathlib.Path(sys.argv[0]).name
arguments = sys.argv[1:]

def option(name):
    values = [arg.split('=', 1)[1] for arg in arguments if arg.startswith(name + '=')]
    if len(values) != 1:
        raise RuntimeError('Expected one explicit local PostgreSQL parameter')
    return values[0]

if os.environ.get('STAB03_RECOVERY_REHEARSAL') != '1':
    raise RuntimeError('Explicit disposable recovery rehearsal required')
if option('--host') not in ['127.0.0.1', 'localhost', '::1'] or option('--port') != '5432' or option('--username') != 'developer':
    raise RuntimeError('Local container PostgreSQL parameters required')
database = option('--dbname')
if operation not in ['pg_dump', 'pg_restore', 'psql'] or database != (SOURCE if operation == 'pg_dump' else TARGET):
    raise RuntimeError('Operation/database outside fixed synthetic rehearsal namespace')
cli = shutil.which('container')
if not cli:
    raise RuntimeError('Existing local container CLI is unavailable')
command = [cli, 'exec']
if operation == 'pg_restore':
    command.append('-i')
command += ['apple-dev-postgres', operation, '-U', 'developer', '-d', database]
if operation == 'pg_dump':
    destination = option('--file')
    with open(destination, 'wb') as output:
        result = subprocess.run(command + ['--format=custom', '--compress=6', '--no-owner', '--no-privileges'], stdout=output)
elif operation == 'pg_restore':
    dump = arguments[-1]
    with open(dump, 'rb') as source:
        result = subprocess.run(command + ['--exit-on-error', '--no-owner', '--no-privileges'], stdin=source)
else:
    sql = option('--command')
    if sql != 'DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;':
        raise RuntimeError('Only the fixed disposable public-schema reset is allowed')
    result = subprocess.run(command + ['--set=ON_ERROR_STOP=1', '--command=' + sql])
sys.exit(result.returncode)
