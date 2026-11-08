#!/usr/bin/env bash
# Package all 6 Lush View Hotel operational modules into releases/<module>.zip
# ready for QloApps Back Office -> Modules and Services -> Add a new module.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
OUT_DIR="${REPO_ROOT}/releases"
mkdir -p "$OUT_DIR"

python3 - "$REPO_ROOT" "$OUT_DIR" <<'PY'
import os, sys, zipfile

repo_root, out_dir = sys.argv[1], sys.argv[2]
modules = [
    'lvhadminclean',
    'lvhaudit',
    'lvhupgrader',
    'lvhauditlog',
    'lvhpayments',
    'lvhhousekeeping',
    'lvhfrontdesk',
    'lvhconference',
    'lvhreports',
]

for mod in modules:
    src_dir = os.path.join(repo_root, 'modules', mod)
    if not os.path.isdir(src_dir):
        raise SystemExit(f"error: {src_dir} does not exist")
    if mod == 'lvhupgrader':
        sql_path = os.path.join(src_dir, 'sql', 'upgrade_170_to_180.sql')
        if not os.path.isfile(sql_path) or os.path.getsize(sql_path) < 50000:
            raise SystemExit(f"error: {sql_path} missing or truncated")
    out_zip = os.path.join(out_dir, f"{mod}.zip")
    with zipfile.ZipFile(out_zip, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for dp, dns, fns in os.walk(src_dir):
            dns[:] = sorted([d for d in dns if not d.startswith('.') and d not in ('translations', 'cache')])
            for fn in sorted(fns):
                if fn.startswith('.') or fn == 'config.xml':
                    continue
                abs_p = os.path.join(dp, fn)
                rel_p = os.path.relpath(abs_p, os.path.join(repo_root, 'modules')).replace('\\', '/')
                zf.write(abs_p, rel_p)
    print(f"Built {out_zip} ({os.path.getsize(out_zip):,} bytes)")
PY
