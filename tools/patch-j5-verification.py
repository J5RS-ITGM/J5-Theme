# Patches the j5-verification plugin (lives only on the server, not in git)
# to v0.5.0 for LE-only purchase verification:
#   - adds "Licensed Security" to the My Account claim type dropdown
#   - wraps the DP push record in apply_filters('j5v_portal_record') so the
#     theme can attach order context (inc/j5-le-verification.php)
# Idempotent: safe to re-run. Backups go to /tmp, never the web root.
# Usage: python3 tools/patch-j5-verification.py /path/to/wp-content/plugins/j5-verification
import sys, os, shutil, time
root = sys.argv[1].rstrip('/')
stamp = time.strftime('%Y%m%d-%H%M%S')
def patch(rel, old, new, marker):
    p = f"{root}/{rel}"; s = open(p, encoding='utf-8').read()
    if marker in s: print(f"  {rel}: already patched"); return
    if s.count(old) != 1: sys.exit(f"  {rel}: anchor not found ({s.count(old)}), aborting, nothing changed in this file")
    bk = f"/tmp/j5v-backup-{stamp}/{rel}"; os.makedirs(os.path.dirname(bk), exist_ok=True); shutil.copy2(p, bk)
    open(p, 'w', encoding='utf-8', newline='\n').write(s.replace(old, new)); print(f"  {rel}: patched (backup in /tmp/j5v-backup-{stamp}/)")
patch('includes/class-j5v-account.php',
      "\t\t\t\t\t\t<option>Law Enforcement</option>\n",
      "\t\t\t\t\t\t<option>Law Enforcement</option>\n\t\t\t\t\t\t<option>Licensed Security</option>\n",
      "<option>Licensed Security</option>")
patch('includes/class-j5v-portal.php',
      "\t\t$record = wp_json_encode( array(\n",
      "\t\t$record = wp_json_encode( apply_filters( 'j5v_portal_record', array(\n",
      "j5v_portal_record")
s = open(f"{root}/includes/class-j5v-portal.php", encoding='utf-8').read()
if "), $claim_id ) );\n\n\t\t$boundary" not in s:
    old = "\t\t) );\n\n\t\t$boundary"
    if s.count(old) != 1: sys.exit("  portal close anchor not found")
    open(f"{root}/includes/class-j5v-portal.php", 'w', encoding='utf-8', newline='\n').write(s.replace(old, "\t\t), $claim_id ) );\n\n\t\t$boundary"))
    print("  includes/class-j5v-portal.php: filter closed")
patch('j5-verification.php', "Version: 0.4.1", "Version: 0.5.0", "Version: 0.5.0")
patch('j5-verification.php', "define( 'J5V_VERSION', '0.4.1' );", "define( 'J5V_VERSION', '0.5.0' );", "'0.5.0' );")
print("done")
