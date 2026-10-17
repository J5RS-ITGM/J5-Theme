#!/usr/bin/env bash
# ============================================================================
# J5 Self-Hosted Fonts — WOFF2 fetch script
#
# Downloads the exact font files referenced by assets/fonts/j5-fonts.css from
# Google Fonts and drops them in the theme's assets/fonts/ directory with the
# filenames the CSS expects ({family-slug}-{weight}.woff2).
#
# Run from the theme root (Git Bash on Windows works). It asks Google's CSS API (with a
# modern-browser UA so it returns WOFF2) for each family/weight, extracts the
# gstatic.com WOFF2 URL, and downloads it to the target filename.
#
# Safe to re-run; it overwrites existing files.
# ============================================================================
set -euo pipefail

DEST="${1:-$(cd "$(dirname "$0")" && pwd)/assets/fonts}"
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36"
API="https://fonts.googleapis.com/css2"

mkdir -p "$DEST"

# fetch <google-family> <weight> <output-filename>
fetch() {
  local family="$1" weight="$2" out="$3"
  local css url
  # Ask the CSS API for just this family+weight; UA forces WOFF2 responses.
  css=$(curl -s -A "$UA" "${API}?family=${family}:wght@${weight}&display=swap")
  # Google returns one @font-face per unicode subset (cyrillic, vietnamese,
  # latin-ext, latin...), each preceded by a /* subset */ comment. We need the
  # basic "latin" subset; grabbing the first URL gets cyrillic/vietnamese and
  # the site falls back to system fonts.
  url=$(printf '%s' "$css" | awk '/\/\* latin \*\//{f=1} f && /woff2/{match($0,/https:\/\/fonts\.gstatic\.com\/[^)]+\.woff2/); print substr($0,RSTART,RLENGTH); exit}')
  if [ -z "$url" ]; then
    echo "!! FAILED to resolve URL for ${family} ${weight}" >&2
    return 1
  fi
  curl -s -o "${DEST}/${out}" "$url"
  printf '  %-28s <- %s\n' "$out" "$url"
}

echo "Downloading WOFF2 files to: $DEST"
echo

echo "Bebas Neue:"
fetch "Bebas+Neue" 400 "bebas-neue-400.woff2"

echo "Barlow:"
for w in 300 400 500 600 700 800; do fetch "Barlow" "$w" "barlow-${w}.woff2"; done

echo "Barlow Condensed:"
for w in 400 500 600 700 800; do fetch "Barlow+Condensed" "$w" "barlow-condensed-${w}.woff2"; done

echo "JetBrains Mono:"
for w in 400 500 600; do fetch "JetBrains+Mono" "$w" "jetbrains-mono-${w}.woff2"; done

echo "Chakra Petch:"
for w in 400 500 600 700; do fetch "Chakra+Petch" "$w" "chakra-petch-${w}.woff2"; done

echo
echo "Done. Files (each should be several KB or more):"
ls -l "${DEST}"/*.woff2 | awk '{printf "  %8d bytes  %s\n", $5, $NF}' | sed 's|/.*/||'
echo
echo "Count: $(ls -1 "${DEST}"/*.woff2 | wc -l) files (expected 19)"
