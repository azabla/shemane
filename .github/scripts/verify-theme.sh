#!/bin/sh
# Installs the fixture theme with Composer, builds it, and checks its classes reached its stylesheet.
set -eu

composer config repositories.theme-under-test '{"type":"path","url":"tests/Fixtures/theme-packages/fixture-theme","options":{"symlink":false}}'
composer require "liberusoftware/ecommerce-fixture-theme:*" --no-interaction --no-progress

test -f themes/fixture-theme/theme.json || { echo 'The theme did not install to themes/fixture-theme'; exit 1; }

php artisan theme:validate
php artisan theme:list
npm run build

python3 - <<'PY'
import json, pathlib, sys

entry = 'themes/fixture-theme/resources/css/app.css'
manifest = json.loads(pathlib.Path('public/build/manifest.json').read_text())
if entry not in manifest:
    sys.exit(f'FAIL: {entry} is not in the Vite manifest')
css = pathlib.Path('public/build', manifest[entry]['file']).read_text()
if '123456' not in css:
    sys.exit('FAIL: the theme stylesheet lost a class its own views use')
print('Theme built, with its classes.')
PY
