#!/usr/bin/env bash
set -euo pipefail
for file in app/*.php public/*.php; do php -l "$file"; done
