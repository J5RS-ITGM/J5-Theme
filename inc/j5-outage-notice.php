<?php
/**
 * J5-OUTAGE-1: temporary service notice (e.g. our email provider is down).
 *
 * Off unless the option is set, so it can be switched without a deploy:
 *   on:  wp option update j5_outage_notice 1
 *   off: wp option delete j5_outage_notice
 *
 * Shows once per browser session as a small card in the corner (bottom on
 * phones); it does not block the page. Copy via filter `j5_outage_notice`.
 *
 * @package j5-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_footer', function () {
	if ( is_admin() || ! get_option( 'j5_outage_notice' ) ) return;
	$n = apply_filters( 'j5_outage_notice', array(
		'title' => 'Email is delayed right now',
		'body'  => 'Microsoft, our email provider, is having an outage, so emails to us may be delayed or not come through. For anything urgent, please call us. You can still place orders online; confirmation emails may arrive late.',
		'note'  => 'Make sure to leave a message',
		'why'   => 'Due to high call volume, we may not be able to pick up.',
		'phone' => '(630) 442-4938',
		'tel'   => 'tel:+16304424938',
	) );
	?>
	<div class="j5-outage" id="j5Outage" role="status" aria-live="polite" hidden>
		<button type="button" class="j5-outage__x" aria-label="Dismiss notice">&times;</button>
		<p class="j5-outage__t"><span class="j5-outage__i" aria-hidden="true">!</span><?php echo esc_html( $n['title'] ); ?></p>
		<p class="j5-outage__b"><?php echo esc_html( $n['body'] ); ?></p>
		<p class="j5-outage__n"><?php echo esc_html( $n['note'] ); ?><span><?php echo esc_html( $n['why'] ); ?></span></p>
		<a class="j5-outage__call" href="<?php echo esc_url( $n['tel'] ); ?>">Call <?php echo esc_html( $n['phone'] ); ?></a>
	</div>
	<style>
	.j5-outage{position:fixed;right:20px;bottom:20px;z-index:99990;width:360px;max-width:calc(100vw - 32px);box-sizing:border-box;padding:16px 44px 16px 18px;background:#15171b;border:1px solid rgba(245,241,232,.12);border-left:3px solid #d4af37;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,.5);color:#f5f1e8;font-family:Barlow,sans-serif}
	.j5-outage[hidden]{display:none}
	.j5-outage__t{display:flex;align-items:center;gap:10px;margin:0 0 6px;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:17px;letter-spacing:.06em;text-transform:uppercase}
	.j5-outage__i{flex:0 0 22px;height:22px;border-radius:50%;background:#d4af37;color:#0a0b0d;font-weight:800;font-size:14px;line-height:22px;text-align:center}
	.j5-outage__b{margin:0 0 12px;font-size:15px;line-height:1.45;color:#d6d2c9}
	.j5-outage__n{margin:0 0 12px;padding:10px 12px;background:rgba(212,175,55,.12);border-radius:4px;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:17px;letter-spacing:.06em;text-transform:uppercase;color:#d4af37}
	.j5-outage__n span{display:block;margin-top:2px;font-family:Barlow,sans-serif;font-weight:500;font-size:14px;letter-spacing:0;text-transform:none;color:#d6d2c9}
	.j5-outage__call{display:inline-flex;align-items:center;min-height:44px;padding:0 18px;border-radius:4px;background:#d4af37;color:#0a0b0d;font-weight:700;font-size:15px;text-decoration:none}
	.j5-outage__call:hover{background:#e6c450;color:#0a0b0d}
	.j5-outage__x{position:absolute;top:6px;right:6px;width:36px;height:36px;border:0;background:transparent;color:#a7a39a;font-size:24px;line-height:1;cursor:pointer}
	.j5-outage__x:hover{color:#f5f1e8}
	@media (max-width:640px){.j5-outage{left:16px;right:16px;bottom:16px;width:auto;max-width:none}.j5-outage__call{width:100%;justify-content:center}}
	</style>
	<script>
	(function () {
		var el = document.getElementById('j5Outage'), k = 'j5-outage-dismissed';
		if (!el) return;
		try { if (sessionStorage.getItem(k)) return; } catch (e) {}
		el.hidden = false;
		el.querySelector('.j5-outage__x').addEventListener('click', function () {
			el.hidden = true;
			try { sessionStorage.setItem(k, '1'); } catch (e) {}
		});
	})();
	</script>
	<?php
}, 50 );
