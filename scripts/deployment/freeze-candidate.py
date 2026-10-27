#!/usr/bin/env python3
"""Freeze Git-visible source without secrets, storage or repository mutations."""
import hashlib,json,os,shutil,subprocess,tempfile
from pathlib import Path
root=Path(__file__).resolve().parents[2]
paths=subprocess.check_output(['git','ls-files','--cached','--others','--exclude-standard','-z'],cwd=root).decode().split('\0')
snapshot=Path(tempfile.mkdtemp(prefix='arevantes-candidate-'))
entries=[]
for name in sorted(set(paths)):
 if name == 'docs/roadmap.md':continue  # Delivered and hashed as companion certification documentation.
 if name.startswith(('docs/deploy-01','docs/deploy-02')) and name != 'docs/deploy-02-i18n-review.json':continue
 if not name or name.startswith(('.env','.cloud/','storage/','public/build/','node_modules/','vendor/','.git/','.playwright-cli/','output/','certification-results/','bootstrap/cache/')) or name=='public/hot' or name.endswith(('.sqlite','.sqlite-wal','.sqlite-shm')):continue
 src=root/name
 if not src.is_file() or src.is_symlink():continue
 data=src.read_bytes(); dst=snapshot/name;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(src,dst)
 entries.append({'path':name,'sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data)})
manifest={'head':subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip(),'scope':'candidate source only; no credentials, storage or production','files':entries,'candidate_digest':hashlib.sha256(json.dumps(entries,sort_keys=True).encode()).hexdigest()}
(snapshot/'candidate-manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
for name in ['vendor','node_modules']:
 if (root/name).exists():
  if name=='vendor':shutil.copytree(root/name,snapshot/name)
  else:(snapshot/name).symlink_to(root/name,target_is_directory=True)
for name in ['storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','storage/app/private','bootstrap/cache']:(snapshot/name).mkdir(parents=True,exist_ok=True)
# Generated bootstrap caches were excluded; no existing files are deleted.
(snapshot/'.env').write_text('APP_ENV=testing\nAPP_URL=http://localhost:8000\nAPP_TIMEZONE=Europe/Madrid\nMAIL_MAILER=array\nBROADCAST_CONNECTION=null\nPOSTGRES_BACKUP_ENABLED=false\nPOSTGRES_RESTORE_VERIFICATION_ENABLED=false\n')
print(snapshot)
