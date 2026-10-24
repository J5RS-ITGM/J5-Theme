#!/usr/bin/env bash
# Swap the sizing images in post 11262 for the [j5_plate_sizing] shortcode.
set -uo pipefail
SITE=/home/j5rescue/htdocs/j5rescue.com
ID=11262
W="sudo -u j5rescue -i -- bash -c"
cat > /tmp/hesco-sizing.py <<'EOF'
import re, sys
s = open(sys.argv[1], encoding='utf-8').read()
# the two sizing images sit in one wp:columns block; replace that whole block
pat = re.compile(r'<!-- wp:columns -->(?:(?!<!-- /wp:columns -->).)*HESCO-Sizing(?:(?!<!-- /wp:columns -->).)*<!-- /wp:columns -->', re.S)
new = '<!-- wp:shortcode -->\n[j5_plate_sizing brand="HESCO"]\n<!-- /wp:shortcode -->'
s2, n = pat.subn(new, s, count=1)
if n == 0:
    # fallback: any single image figure with HESCO-Sizing in it
    s2, n = re.subn(r'<!-- wp:image[^>]*-->\s*<figure[^>]*>(?:(?!</figure>).)*HESCO-Sizing(?:(?!</figure>).)*</figure>\s*<!-- /wp:image -->', new, s, count=1, flags=re.S)
print('replaced blocks:', n)
open(sys.argv[2], 'w', encoding='utf-8').write(s2)
EOF
chmod 644 /tmp/hesco-sizing.py
$W "cd $SITE && wp post get $ID --field=post_content 2>/dev/null" > /tmp/hesco-body-$ID.html
python3 -I /tmp/hesco-sizing.py /tmp/hesco-body-$ID.html /tmp/hesco-body-$ID-new.html
chmod 644 /tmp/hesco-body-$ID-new.html
grep -c 'j5_plate_sizing' /tmp/hesco-body-$ID-new.html
$W "cd $SITE && wp post update $ID /tmp/hesco-body-$ID-new.html 2>/dev/null && wp cache flush 2>/dev/null"
