#!/usr/bin/env bash
# Push the sample feeds to a store. Usage: ./push-samples.sh https://staging.j5rescue.com "$J5_HUB_DEFS_SECRET"
set -euo pipefail
BASE="${1:?base url}"; SECRET="${2:?secret}"
for c in shield plate helmet; do
  printf '%s: ' "$c"
  curl -sS -X PUT "$BASE/wp-json/j5/v1/armor-reference/$c" -H "X-J5-Hub-Secret: $SECRET" -H "Content-Type: application/json" --data-binary "@$(dirname "$0")/$c.json"
  echo
done
