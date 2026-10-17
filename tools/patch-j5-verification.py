# Patches the j5-verification plugin (lives only on the server, not in git)
# to v0.5.2:
#   0.5.0  LE-only purchase verification
#     - adds "Licensed Security" to the My Account claim type dropdown
#     - wraps the DP push record in apply_filters('j5v_portal_record') so the
#       theme can attach order context (inc/j5-le-verification.php)
#   0.5.1  Branded ID decision emails (inc/j5-id-emails.php)
#     - plain-text coupon email gated by apply_filters('j5v_send_coupon_email')
#     - DP decision callback stores an optional `reason` as _j5v_reason
#   0.5.2  DP "Approve" vs "Approve & issue coupon"
#     - decision payload `issue_coupon` (true/false; missing = true for older DP builds)
#       is stored as _j5v_issue_coupon; the coupon is only issued when true
# Idempotent: safe to re-run. Backups go to /tmp, never the web root.
# Usage: python3 tools/patch-j5-verification.py /path/to/wp-content/plugins/j5-verification
import sys, os, re, shutil, time
root = sys.argv[1].rstrip('/')
stamp = time.strftime('%Y%m%d-%H%M%S')
def backup(rel):
    bk = f"/tmp/j5v-backup-{stamp}/{rel}"
    if not os.path.exists(bk):
        os.makedirs(os.path.dirname(bk), exist_ok=True); shutil.copy2(f"{root}/{rel}", bk)
def patch(rel, old, new, marker):
    p = f"{root}/{rel}"; s = open(p, encoding='utf-8').read()
    if marker in s: print(f"  {rel}: already patched ({marker[:40]})"); return
    if s.count(old) != 1: sys.exit(f"  {rel}: anchor not found ({s.count(old)}), aborting, nothing changed in this file")
    backup(rel)
    open(p, 'w', encoding='utf-8', newline='\n').write(s.replace(old, new)); print(f"  {rel}: patched (backup in /tmp/j5v-backup-{stamp}/)")

# 0.5.0
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
    backup('includes/class-j5v-portal.php')
    open(f"{root}/includes/class-j5v-portal.php", 'w', encoding='utf-8', newline='\n').write(s.replace(old, "\t\t), $claim_id ) );\n\n\t\t$boundary"))
    print("  includes/class-j5v-portal.php: filter closed")

# 0.5.1
patch('includes/class-j5v-discount.php',
      "\t\twp_mail(\n\t\t\t$user->user_email,\n\t\t\t'Your J5 Rescue service discount',",
      "\t\tif ( apply_filters( 'j5v_send_coupon_email', true, $claim_id ) ) wp_mail(\n\t\t\t$user->user_email,\n\t\t\t'Your J5 Rescue service discount',",
      "j5v_send_coupon_email")
patch('includes/class-j5v-portal.php',
      "\t\tupdate_post_meta( $claim_id, '_j5v_status', $decision );\n",
      "\t\tif ( ! empty( $p['reason'] ) ) {\n\t\t\tupdate_post_meta( $claim_id, '_j5v_reason', sanitize_textarea_field( $p['reason'] ) );\n\t\t}\n\t\tupdate_post_meta( $claim_id, '_j5v_status', $decision );\n",
      "_j5v_reason")

# 0.5.2
patch('includes/class-j5v-portal.php',
      "\t\tif ( 'approved' === $decision ) {\n\t\t\tJ5V_Discount::issue_coupon( $claim_id );\n",
      "\t\t// DP sends issue_coupon true/false (Approve vs Approve & issue coupon). Missing = true (older DP builds).\n"
      "\t\t$issue = array_key_exists( 'issue_coupon', (array) $p ) ? filter_var( $p['issue_coupon'], FILTER_VALIDATE_BOOLEAN ) : true;\n"
      "\t\tupdate_post_meta( $claim_id, '_j5v_issue_coupon', $issue ? 1 : 0 );\n"
      "\t\tif ( 'approved' === $decision && $issue ) {\n\t\t\tJ5V_Discount::issue_coupon( $claim_id );\n",
      "_j5v_issue_coupon")

# version -> 0.5.2 (from any earlier version)
p = f"{root}/j5-verification.php"; s = open(p, encoding='utf-8').read()
n = re.sub(r"Version: 0\.\d+\.\d+", "Version: 0.5.2", s, count=1)
n = re.sub(r"define\( 'J5V_VERSION', '0\.\d+\.\d+' \);", "define( 'J5V_VERSION', '0.5.2' );", n, count=1)
if n != s:
    backup('j5-verification.php'); open(p, 'w', encoding='utf-8', newline='\n').write(n); print("  j5-verification.php: version 0.5.2")
else: print("  j5-verification.php: already 0.5.2")
print("done")
