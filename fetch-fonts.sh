#!/usr/bin/env bash
# ============================================================================
# J5 Self-Hosted Fonts — WOFF2 fetch script
#
# Downloads the exact font files referenced by assets/fonts/j5-fonts.css from
# Google Fonts and drops them in the theme's assets/fonts/ directory with the
# filenames the CSS expects ({family-slug}-{weight}.woff2).
#
# Run ON THE VULTR BOX as the site user. It asks Google's CSS API (with a
# modern-browser UA so it returns WOFF2) for each family/weight, extracts the
# gstatic.com WOFF2 URL, and downloads it to the target filename.
#
# Safe to re-run; it overwrites existing files.
# ============================================================================
set -euo pipefail

DEST="/home/j5rescue/htdocs/j5rescue.com/wp-content/themes/astra-child/assets/fonts"
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36"
API="https://fonts.googleapis.com/css2"

mkdir -p "$DEST"

# fetch <google-family> <weight> <output-filename>
fetch() {
  local family="$1" weight="$2" out="$3"
  local css url
  # Ask the CSS API for just this family+weight; UA forces WOFF2 responses.
  css=$(curl -s -A "$UA" "${API}?family=${family}:wght@${weight}&display=swap")
  # Pull the first woff2 URL out of the returned @font-face block.
  url=$(printf '%s' "$css" | grep -oE 'https://fonts.gstatic.com/[^)]+\.woff2' | head -1)
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
echo "Done. Verifying files are real WOFF2 (should all say: Web Open Font Format):"
file "${DEST}"/*.woff2 | sed 's|.*/||'
echo
echo "Count: $(ls -1 "${DEST}"/*.woff2 | wc -l) files (expected 19)"
