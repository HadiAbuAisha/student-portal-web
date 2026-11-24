#!/usr/bin/env bash
set -euo pipefail
bash scripts/check-syntax.sh
php -d zend.assertions=1 -d assert.exception=1 scripts/check-core.php
node --check public/assets/app.js
