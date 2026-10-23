<?php
/**
 * J5 Cart Recovery: WooCommerce > Cart Recovery (Carts list + Settings).
 *
 * @package J5 Theme
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const J5CR_PAGE = 'j5-cart-recovery';

add_action( 'admin_menu', function () {
	add_submenu_page( 'woocommerce', 'Cart Recovery', 'Cart Recovery', 'manage_woocommerce', J5CR_PAGE, 'j5cr_admin_page' );
}, 60 );

function j5cr_admin_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => J5CR_PAGE ), $args ), admin_url( 'admin.php' ) );
}

function j5cr_action_url( $action, $args = array() ) {
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => $action ), $args ), admin_url( 'admin-post.php' ) ), $action );
}

/* ---------- Actions ---------- */

add_action( 'admin_post_j5cr_save', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed' );
	}
	check_admin_referer( 'j5cr_save' );
	$in = isset( $_POST['j5cr'] ) && is_array( $_POST['j5cr'] ) ? $_POST['j5cr'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in j5cr_sanitize()
	update_option( J5CR_OPTION, j5cr_sanitize( $in ), false );
	if ( isset( $_POST['j5cr_unsub'] ) ) {
		$list = array_filter( array_map( function ( $l ) { return strtolower( sanitize_email( trim( $l ) ) ); }, explode( "\n", wp_unslash( $_POST['j5cr_unsub'] ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		update_option( J5CR_UNSUB, array_values( array_unique( array_filter( $list, 'is_email' ) ) ), false );
	}
	wp_safe_redirect( j5cr_admin_url( array( 'tab' => 'settings', 'msg' => 'saved' ) ) );
	exit;
} );

add_action( 'admin_post_j5cr_send_now', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed' );
	}
	check_admin_referer( 'j5cr_send_now' );
	$row = j5cr_get( 'id', (string) absint( $_GET['id'] ?? 0 ) );
	$msg = 'none';
	if ( $row && 'cart' === $row['source'] && in_array( $row['status'], array( 'active', 'abandoned' ), true ) ) {
		$next = j5cr_next_step( $row, j5cr_settings() );
		if ( null !== $next ) {
			if ( 'active' === $row['status'] ) {
				j5cr_update( $row['id'], array( 'status' => 'abandoned' ) );
			}
			$msg = j5cr_send_step( $row, $next ) ? 'sent' : 'fail';
		}
	}
	wp_safe_redirect( j5cr_admin_url( array( 'msg' => $msg ) ) );
	exit;
} );

/* Stop / resume recovery emails for one customer (from the Carts list or an order). */
add_action( 'admin_post_j5cr_mute', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed' );
	}
	check_admin_referer( 'j5cr_mute' );
	$email = sanitize_email( wp_unslash( $_GET['email'] ?? '' ) );
	$on    = ! empty( $_GET['on'] );
	if ( $on ) {
		j5cr_mute( $email, 'Reminders stopped by ' . wp_get_current_user()->display_name );
	} else {
		j5cr_unmute( $email );
	}
	$back = wp_get_referer();
	wp_safe_redirect( add_query_arg( 'j5cr_msg', $on ? 'muted' : 'unmuted', $back ? $back : j5cr_admin_url() ) );
	exit;
} );

function j5cr_mute_url( $email, $on ) {
	return j5cr_action_url( 'j5cr_mute', array( 'email' => rawurlencode( $email ), 'on' => $on ? 1 : 0 ) );
}

/* Order screen box: stop reminders for this customer right from the order. */
add_action( 'add_meta_boxes', function () {
	$screens = array( 'shop_order' );
	if ( function_exists( 'wc_get_page_screen_id' ) ) {
		$screens[] = wc_get_page_screen_id( 'shop-order' );
	}
	foreach ( array_unique( $screens ) as $screen ) {
		add_meta_box( 'j5cr-order', 'Cart reminders', 'j5cr_order_box', $screen, 'side', 'default' );
	}
} );

function j5cr_order_box( $post_or_order ) {
	$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
	if ( ! $order || ! is_email( $order->get_billing_email() ) ) {
		echo '<p>No customer email on this order.</p>';
		return;
	}
	global $wpdb;
	$email = $order->get_billing_email();
	$t     = j5cr_table();
	$open  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE email = %s AND status IN ('active','abandoned')", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$last  = j5cr_last_emailed( $email );
	$muted = j5cr_is_unsubscribed( $email );
	echo '<p style="margin-top:0">' . ( $muted ? '<strong style="color:#b32d2e">Stopped</strong> for this customer.' : '<strong>On.</strong> ' . ( $open ? $open . ' saved cart' . ( 1 === $open ? '' : 's' ) . ' may get reminders.' : 'No saved carts waiting.' ) ) . '</p>';
	if ( $last ) {
		echo '<p class="description">Last recovery email: ' . esc_html( wp_date( 'M j, g:i a', $last ) ) . '</p>';
	}
	echo $muted
		? '<a class="button" href="' . esc_url( j5cr_mute_url( $email, false ) ) . '">Resume reminders</a>'
		: '<a class="button" href="' . esc_url( j5cr_mute_url( $email, true ) ) . '">Stop reminders for this customer</a>';
	echo '<p class="description">Covers cart reminders and unpaid-checkout follow-ups. Order emails are not affected.</p>';
}

add_action( 'admin_notices', function () {
	$m = isset( $_GET['j5cr_msg'] ) ? sanitize_key( $_GET['j5cr_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'muted' === $m ) {
		echo '<div class="notice notice-success is-dismissible"><p>Cart reminders stopped for that customer.</p></div>';
	} elseif ( 'unmuted' === $m ) {
		echo '<div class="notice notice-success is-dismissible"><p>Cart reminders resumed for that customer. Carts that were already stopped stay stopped.</p></div>';
	}
} );

add_action( 'admin_post_j5cr_delete', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed' );
	}
	check_admin_referer( 'j5cr_delete' );
	global $wpdb;
	$wpdb->delete( j5cr_table(), array( 'id' => absint( $_GET['id'] ?? 0 ) ) );
	wp_safe_redirect( j5cr_admin_url( array( 'msg' => 'deleted' ) ) );
	exit;
} );

add_action( 'admin_post_j5cr_test', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed' );
	}
	check_admin_referer( 'j5cr_test' );
	$step = absint( $_GET['step'] ?? 0 );
	$me   = wp_get_current_user();
	$row  = j5cr_sample_row();
	$ok   = false;
	if ( $row ) {
		if ( 99 === $step ) {
			$s  = j5cr_settings();
			$ok = j5cr_mail( $me->user_email, '[TEST] ' . $s['failed_subject'], array(
				'kind' => 'failed', 'heading' => $s['failed_heading'], 'intro' => $s['failed_intro'], 'first' => $me->first_name,
				'button' => $s['failed_button'], 'url' => j5cr_restore_url( $row ), 'unsub_url' => '',
				'items' => j5cr_email_items( $row['cart'] ), 'total' => html_entity_decode( wp_strip_all_tags( wc_price( (float) $row['total'] ) ), ENT_QUOTES, 'UTF-8' ),
				'coupon' => array(), 'order' => '12345',
			) );
		} else {
			$row['first_name'] = $me->first_name;
			$ok                = j5cr_send_step( $row, min( 2, $step ), $me->user_email );
			$why               = $GLOBALS['j5cr_last_no_code'] ?? '';
			if ( $ok && $why ) {
				set_transient( 'j5cr_test_note_' . $me->ID, 'Reminder ' . ( min( 2, $step ) + 1 ) . ' test went out without a code. ' . $why . ( empty( $row['id'] ) ? '' : ' (Test used the most recent captured cart, #' . (int) $row['id'] . '.)' ), 300 );
			}
		}
	}
	wp_safe_redirect( j5cr_admin_url( array( 'tab' => 'settings', 'msg' => $ok ? 'test' : 'testfail' ) ) );
	exit;
} );

add_action( 'admin_post_j5cr_run', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed' );
	}
	check_admin_referer( 'j5cr_run' );
	j5cr_run();
	wp_safe_redirect( j5cr_admin_url( array( 'msg' => 'ran' ) ) );
	exit;
} );

/** A cart to use for test emails: the newest real one, else a sample from the catalog. */
function j5cr_sample_row() {
	global $wpdb;
	$t  = j5cr_table();
	$id = (int) $wpdb->get_var( "SELECT id FROM {$t} WHERE source = 'cart' ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $id ) {
		return j5cr_get( 'id', (string) $id );
	}
	$ids   = wc_get_products( array( 'status' => 'publish', 'limit' => 2, 'type' => array( 'simple' ), 'return' => 'ids', 'orderby' => 'popularity' ) );
	$items = array();
	$total = 0;
	foreach ( $ids as $pid ) {
		$p   = wc_get_product( $pid );
		$img = $p->get_image_id() ? wp_get_attachment_image_src( $p->get_image_id(), 'thumbnail' ) : false;
		$items[] = array( 'product_id' => $pid, 'variation_id' => 0, 'variation' => array(), 'qty' => 1, 'data' => array(), 'name' => $p->get_name(), 'meta' => array(), 'line' => (float) $p->get_price(), 'image' => $img ? $img[0] : '', 'url' => $p->get_permalink(), 'le' => 0 );
		$total  += (float) $p->get_price();
	}
	return $items ? array( 'id' => 0, 'token' => str_repeat( '0', 32 ), 'email' => wp_get_current_user()->user_email, 'first_name' => '', 'cart' => $items, 'total' => $total, 'user_id' => 0, 'has_le' => 0 ) : null;
}

/* ---------- Page ---------- */

function j5cr_admin_page() {
	$tab = isset( $_GET['tab'] ) && 'settings' === $_GET['tab'] ? 'settings' : 'carts'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$s   = j5cr_settings();
	$msg = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$msgs = array(
		'saved'    => array( 'success', 'Settings saved.' ),
		'sent'     => array( 'success', 'Reminder sent.' ),
		'fail'     => array( 'error', 'The reminder could not be sent. Check the mail log.' ),
		'none'     => array( 'warning', 'Nothing to send for that cart.' ),
		'deleted'  => array( 'success', 'Cart deleted.' ),
		'test'     => array( 'success', 'Test email sent to you.' ),
		'testfail' => array( 'error', 'The test email could not be sent (no sample cart or mail error).' ),
		'ran'      => array( 'success', 'Checked all carts now.' ),
	);
	?>
	<div class="wrap j5cr">
		<style>
		.j5cr h1{display:flex;align-items:center;gap:10px}
		.j5cr .j5cr-off{background:#fcf0f1;border-left:4px solid #d63638;padding:10px 14px;margin:12px 0}
		.j5cr-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:16px 0 20px}
		.j5cr-stat{background:#fff;border:1px solid #dcdcde;border-top:3px solid #b8963f;padding:12px 14px}
		.j5cr-stat b{display:block;font-size:22px;line-height:1.2;color:#1d2327}
		.j5cr-stat span{color:#646970;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
		.j5cr-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;background:#f0f0f1;color:#50575e}
		.j5cr-badge.active{background:#e7f0f8;color:#135e96}.j5cr-badge.abandoned{background:#fcf3e6;color:#8a5a00}
		.j5cr-badge.recovered{background:#e7f5ec;color:#1a7f37}.j5cr-badge.ordered{background:#eef6f0;color:#2e7d4f}
		.j5cr-badge.skipped,.j5cr-badge.unsubscribed,.j5cr-badge.emptied{background:#f0f0f1;color:#787c82}
		.j5cr-badge.failed{background:#fcf0f1;color:#b32d2e}
		.j5cr table.widefat td{vertical-align:middle}
		.j5cr .items{color:#50575e;font-size:12px;line-height:1.5}
		.j5cr-card{background:#fff;border:1px solid #dcdcde;padding:4px 18px 14px;margin:0 0 16px;max-width:980px}
		.j5cr-card h2{margin:14px 0 4px}
		.j5cr-card .desc{color:#646970;margin:0 0 8px}
		.j5cr-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
		.j5cr-step{border:1px solid #dcdcde;padding:10px 12px;background:#fcfcfc}
		.j5cr-step.off{opacity:.6}
		.j5cr-step h3{margin:4px 0 8px;display:flex;justify-content:space-between;align-items:center}
		.j5cr-step label.blk{display:block;font-weight:600;margin:8px 0 2px}
		.j5cr-step input[type=text],.j5cr-step textarea{width:100%}
		.j5cr-brands{columns:3;max-width:760px}
		.j5cr-brands label{display:block;break-inside:avoid;margin:2px 0}
		@media (max-width:1200px){.j5cr-steps{grid-template-columns:1fr}.j5cr-stats{grid-template-columns:repeat(2,1fr)}}
		</style>
		<h1>Cart Recovery</h1>
		<?php
		$j5cr_note = 'test' === $msg ? get_transient( 'j5cr_test_note_' . get_current_user_id() ) : '';
		if ( $j5cr_note ) :
			delete_transient( 'j5cr_test_note_' . get_current_user_id() );
			?>
			<div class="notice notice-warning is-dismissible"><p><?php echo esc_html( $j5cr_note ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $msgs[ $msg ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $msgs[ $msg ][0] ); ?> is-dismissible"><p><?php echo esc_html( $msgs[ $msg ][1] ); ?></p></div>
		<?php endif; ?>
		<?php if ( empty( $s['enabled'] ) ) : ?>
			<div class="j5cr-off"><strong>Cart Recovery is off.</strong> Nothing is captured or sent until you turn it on under <a href="<?php echo esc_url( j5cr_admin_url( array( 'tab' => 'settings' ) ) ); ?>">Settings</a>.</div>
		<?php endif; ?>
		<nav class="nav-tab-wrapper">
			<a class="nav-tab <?php echo 'carts' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( j5cr_admin_url() ); ?>">Carts</a>
			<a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( j5cr_admin_url( array( 'tab' => 'settings' ) ) ); ?>">Settings</a>
		</nav>
		<?php 'settings' === $tab ? j5cr_admin_settings( $s ) : j5cr_admin_carts( $s ); ?>
	</div>
	<?php
}

function j5cr_admin_carts( $s ) {
	global $wpdb;
	$t      = j5cr_table();
	$since  = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
	$filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.NonceVerification.Recommended
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$stats = $wpdb->get_row( $wpdb->prepare( "SELECT
		SUM(source='cart') captured,
		SUM(source='cart' AND status IN ('abandoned','skipped','unsubscribed','recovered') ) abandoned,
		SUM(CASE WHEN source='cart' AND status IN ('active','abandoned') THEN total ELSE 0 END) open_value,
		SUM(status='recovered') recovered,
		SUM(source='failed') failed
		FROM {$t} WHERE created >= %s", $since ), ARRAY_A );
	$rec_ids   = $wpdb->get_col( $wpdb->prepare( "SELECT order_id FROM {$t} WHERE status = 'recovered' AND order_id > 0 AND created >= %s", $since ) );
	$where     = $filter ? $wpdb->prepare( 'WHERE status = %s', $filter ) : ( isset( $_GET['source'] ) && 'failed' === $_GET['source'] ? "WHERE source = 'failed'" : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$rows      = $wpdb->get_results( "SELECT * FROM {$t} {$where} ORDER BY updated DESC LIMIT 200", ARRAY_A );
	// phpcs:enable
	$rec_value = 0.0;
	foreach ( array_unique( array_map( 'intval', $rec_ids ) ) as $oid ) {
		$o = wc_get_order( $oid );
		if ( $o && $o->is_paid() ) {
			$rec_value += (float) $o->get_total();
		}
	}
	$money = function ( $v ) { return wp_strip_all_tags( wc_price( (float) $v ) ); };
	?>
	<div class="j5cr-stats">
		<div class="j5cr-stat"><span>Carts captured · 30 days</span><b><?php echo (int) $stats['captured']; ?></b></div>
		<div class="j5cr-stat"><span>Abandoned</span><b><?php echo (int) $stats['abandoned']; ?></b></div>
		<div class="j5cr-stat"><span>Still open (value)</span><b><?php echo esc_html( $money( $stats['open_value'] ) ); ?></b></div>
		<div class="j5cr-stat"><span>Recovered</span><b><?php echo (int) $stats['recovered']; ?> · <?php echo esc_html( $money( $rec_value ) ); ?></b></div>
		<div class="j5cr-stat"><span>Unpaid checkouts followed up</span><b><?php echo (int) $stats['failed']; ?></b></div>
	</div>
	<p>
		<?php foreach ( array( '' => 'All', 'active' => 'Active', 'abandoned' => 'Abandoned', 'recovered' => 'Recovered', 'ordered' => 'Ordered', 'skipped' => 'Skipped' ) as $k => $label ) : ?>
			<a href="<?php echo esc_url( j5cr_admin_url( $k ? array( 'status' => $k ) : array() ) ); ?>" class="button <?php echo $filter === $k && ! isset( $_GET['source'] ) ? 'button-primary' : ''; // phpcs:ignore ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
		<a href="<?php echo esc_url( j5cr_admin_url( array( 'source' => 'failed' ) ) ); ?>" class="button <?php echo isset( $_GET['source'] ) ? 'button-primary' : ''; // phpcs:ignore ?>">Unpaid checkouts</a>
		<a href="<?php echo esc_url( j5cr_action_url( 'j5cr_run' ) ); ?>" class="button" style="float:right">Check now</a>
	</p>
	<table class="widefat striped">
		<thead><tr><th>Last activity</th><th>Customer</th><th>Items</th><th>Value</th><th>Status</th><th>Reminders</th><th>Order</th><th></th></tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?>
			<tr><td colspan="8">No carts yet. Carts appear here once a shopper enters their email at checkout or a signed-in customer adds to their cart.</td></tr>
		<?php endif; ?>
		<?php foreach ( $rows as $r ) :
			$cart  = json_decode( $r['cart'], true ) ?: array();
			$names = array();
			foreach ( $cart as $it ) {
				$names[] = esc_html( $it['name'] ) . ' &times;' . (int) $it['qty'];
			}
			$badge = 'failed' === $r['source'] && ! in_array( $r['status'], array( 'recovered' ), true ) ? 'failed' : $r['status'];
			$label = 'failed' === $r['source'] && 'recovered' !== $r['status'] ? 'Unpaid checkout' : ucfirst( $r['status'] );
			?>
			<tr>
				<td><?php echo esc_html( wp_date( 'M j, g:i a', strtotime( $r['updated'] . ' UTC' ) ) ); ?></td>
				<td><strong><?php echo esc_html( $r['first_name'] ?: '(no name)' ); ?></strong><br><a href="mailto:<?php echo esc_attr( $r['email'] ); ?>"><?php echo esc_html( $r['email'] ); ?></a><?php echo $r['user_id'] ? ' <span class="j5cr-badge">account</span>' : ''; ?><?php echo $r['has_le'] ? ' <span class="j5cr-badge">LE items</span>' : ''; ?><?php echo $r['email'] && j5cr_is_unsubscribed( $r['email'] ) ? ' <span class="j5cr-badge failed">reminders stopped</span>' : ''; ?></td>
				<td class="items"><?php echo implode( '<br>', $names ); // escaped above ?></td>
				<td><strong><?php echo esc_html( $money( $r['total'] ) ); ?></strong></td>
				<td><span class="j5cr-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $label ); ?></span><?php echo $r['note'] ? '<br><small>' . esc_html( $r['note'] ) . '</small>' : ''; ?></td>
				<td><?php echo (int) $r['steps_sent']; ?> sent<?php echo $r['clicked'] ? '<br><small>Link clicked</small>' : ''; ?><?php echo $r['coupon'] ? '<br><small>Code ' . esc_html( $r['coupon'] ) . '</small>' : ''; ?></td>
				<td><?php
					$o = $r['order_id'] ? wc_get_order( (int) $r['order_id'] ) : false;
					echo $o ? '<a href="' . esc_url( $o->get_edit_order_url() ) . '">#' . esc_html( $o->get_order_number() ) . '</a>' : '&mdash;';
				?></td>
				<td style="white-space:nowrap">
					<?php if ( 'cart' === $r['source'] && in_array( $r['status'], array( 'active', 'abandoned' ), true ) && null !== j5cr_next_step( $r, $s ) ) : ?>
						<a class="button button-small" href="<?php echo esc_url( j5cr_action_url( 'j5cr_send_now', array( 'id' => $r['id'] ) ) ); ?>">Send reminder now</a>
					<?php endif; ?>
					<?php if ( $r['email'] ) : $muted = j5cr_is_unsubscribed( $r['email'] ); ?>
						<a class="button button-small" href="<?php echo esc_url( j5cr_mute_url( $r['email'], ! $muted ) ); ?>" <?php echo $muted ? '' : 'onclick="return confirm(\'Stop all cart reminders and payment follow-ups to ' . esc_js( $r['email'] ) . '?\')"'; ?>><?php echo $muted ? 'Resume reminders' : 'Stop reminders'; ?></a>
					<?php endif; ?>
					<a class="button button-small" href="<?php echo esc_url( j5cr_restore_url( $r ) ); ?>" target="_blank" title="Opens the saved cart in your own browser session">Open cart</a>
					<a class="button button-small button-link-delete" href="<?php echo esc_url( j5cr_action_url( 'j5cr_delete', array( 'id' => $r['id'] ) ) ); ?>" onclick="return confirm('Delete this cart record?')">Delete</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

function j5cr_admin_settings( $s ) {
	$f      = function ( $name ) { return 'j5cr[' . $name . ']'; };
	$chk    = function ( $name, $on, $label ) use ( $f ) {
		return '<label><input type="checkbox" name="' . esc_attr( $f( $name ) ) . '" value="1" ' . checked( $on, 1, false ) . '> ' . esc_html( $label ) . '</label>';
	};
	$brands = taxonomy_exists( 'product_brand' ) ? get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => false, 'orderby' => 'name' ) ) : array();
	$unsub  = get_option( J5CR_UNSUB, array() );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
		<input type="hidden" name="action" value="j5cr_save">
		<?php wp_nonce_field( 'j5cr_save' ); ?>

		<div class="j5cr-card">
			<h2>General</h2>
			<table class="form-table" role="presentation">
				<tr><th>Cart Recovery</th><td><?php echo $chk( 'enabled', $s['enabled'], 'On: capture carts and send reminders' ); // phpcs:ignore ?></td></tr>
				<tr><th>Capture</th><td><?php echo $chk( 'capture_guests', $s['capture_guests'], 'Guests (once they type an email at checkout)' ); // phpcs:ignore ?><br><?php echo $chk( 'capture_users', $s['capture_users'], 'Signed-in customers (as soon as they add to cart)' ); // phpcs:ignore ?></td></tr>
				<tr><th>Email spacing</th><td>At most one recovery email per customer every <input type="number" min="1" max="168" name="<?php echo esc_attr( $f( 'email_gap' ) ); ?>" value="<?php echo (int) $s['email_gap']; ?>" class="small-text"> hours <p class="description">Counts cart reminders and unpaid-checkout follow-ups together. If a customer has several carts, only the newest one gets reminders.</p></td></tr>
				<tr><th>Open orders</th><td><label><input type="checkbox" name="<?php echo esc_attr( $f( 'stop_open_order' ) ); ?>" value="1" <?php checked( $s['stop_open_order'], 1 ); ?>> Stop reminders when the customer has a pending or on-hold order</label><p class="description">For example, an order you recreated and sent a pay link for.</p></td></tr>
				<tr><th>Abandoned after</th><td><input type="number" min="15" max="1440" name="<?php echo esc_attr( $f( 'abandon_after' ) ); ?>" value="<?php echo (int) $s['abandon_after']; ?>" class="small-text"> minutes with no cart activity</td></tr>
				<tr><th>Minimum cart value</th><td><input type="number" min="0" step="1" name="<?php echo esc_attr( $f( 'min_total' ) ); ?>" value="<?php echo esc_attr( $s['min_total'] ); ?>" class="small-text"> $ (smaller carts get no reminders)</td></tr>
				<tr><th>Carts with LE-only items</th><td>
					<?php foreach ( array( 'verified' => 'Remind only buyers who are already LE verified', 'all' => 'Remind everyone', 'none' => 'Never remind' ) as $k => $label ) : ?>
						<label style="display:block"><input type="radio" name="<?php echo esc_attr( $f( 'le_audience' ) ); ?>" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['le_audience'], $k ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</td></tr>
			</table>
		</div>

		<div class="j5cr-card">
			<h2>Reminder emails</h2>
			<p class="desc">Timed from the shopper's last cart activity. Turn a step off to skip it. Each email has a button that rebuilds the exact cart (options included) and opens checkout.</p>
			<div class="j5cr-steps">
				<?php foreach ( $s['steps'] as $i => $st ) :
					$n = 'j5cr[steps][' . $i . ']'; ?>
					<div class="j5cr-step <?php echo $st['enabled'] ? '' : 'off'; ?>">
						<h3>Reminder <?php echo (int) ( $i + 1 ); ?>
							<label style="font-weight:400"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enabled]" value="1" <?php checked( $st['enabled'], 1 ); ?>> On</label>
						</h3>
						<label class="blk">Send after</label>
						<input type="number" min="0.25" max="720" step="0.25" name="<?php echo esc_attr( $n ); ?>[delay]" value="<?php echo esc_attr( $st['delay'] ); ?>" class="small-text"> hours
						<label class="blk">Subject</label><input type="text" name="<?php echo esc_attr( $n ); ?>[subject]" value="<?php echo esc_attr( $st['subject'] ); ?>">
						<label class="blk">Heading</label><input type="text" name="<?php echo esc_attr( $n ); ?>[heading]" value="<?php echo esc_attr( $st['heading'] ); ?>">
						<label class="blk">Message</label><textarea rows="4" name="<?php echo esc_attr( $n ); ?>[intro]"><?php echo esc_textarea( $st['intro'] ); ?></textarea>
						<label class="blk">Button</label><input type="text" name="<?php echo esc_attr( $n ); ?>[button]" value="<?php echo esc_attr( $st['button'] ); ?>">
						<label class="blk"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[discount]" value="1" <?php checked( $st['discount'], 1 ); ?>> Include a discount code</label>
						<input type="number" min="1" max="50" step="0.5" name="<?php echo esc_attr( $n ); ?>[pct]" value="<?php echo esc_attr( $st['pct'] ); ?>" class="small-text"> % off, valid
						<input type="number" min="1" max="60" name="<?php echo esc_attr( $n ); ?>[expiry]" value="<?php echo (int) $st['expiry']; ?>" class="small-text"> days
						<p style="margin:10px 0 0"><a class="button button-small" href="<?php echo esc_url( j5cr_action_url( 'j5cr_test', array( 'step' => $i ) ) ); ?>">Email me a test</a> <small>(uses saved settings)</small></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="j5cr-card">
			<h2>Discount rules</h2>
			<p class="desc">Codes are single use, locked to the shopper's email, and only apply to eligible items. If nothing in the cart qualifies, the email goes out without a code.</p>
			<table class="form-table" role="presentation">
				<tr><th>Never discount these brands<br><small style="font-weight:400">MAP brands</small></th><td>
					<div class="j5cr-brands">
						<?php if ( ! $brands || is_wp_error( $brands ) ) : ?><em>No brands found.</em><?php endif; ?>
						<?php foreach ( (array) $brands as $b ) : if ( ! $b instanceof WP_Term ) { continue; } ?>
							<label><input type="checkbox" name="j5cr[exclude_brands][]" value="<?php echo (int) $b->term_id; ?>" <?php checked( in_array( (int) $b->term_id, array_map( 'intval', $s['exclude_brands'] ), true ) ); ?>> <?php echo esc_html( $b->name ); ?></label>
						<?php endforeach; ?>
					</div>
				</td></tr>
				<tr><th>Also exclude</th><td><?php echo $chk( 'exclude_le', $s['exclude_le'], 'Law enforcement only items' ); // phpcs:ignore ?><br><?php echo $chk( 'exclude_sale', $s['exclude_sale'], 'Items already on sale' ); // phpcs:ignore ?></td></tr>
				<tr><th>Excluded products</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( $f( 'exclude_products' ) ); ?>" value="<?php echo esc_attr( $s['exclude_products'] ); ?>"><p class="description">Product IDs or SKUs, comma separated.</p></td></tr>
			</table>
		</div>

		<div class="j5cr-card">
			<h2>Unpaid checkouts</h2>
			<p class="desc">Orders placed at checkout that never got paid (declined card, closed PayPal window, gateway error). Sent once per order.</p>
			<table class="form-table" role="presentation">
				<tr><th>Follow up</th><td><?php echo $chk( 'failed_enabled', $s['failed_enabled'], 'On' ); // phpcs:ignore ?></td></tr>
				<tr><th>After</th><td><input type="number" min="5" max="55" name="<?php echo esc_attr( $f( 'failed_delay' ) ); ?>" value="<?php echo (int) $s['failed_delay']; ?>" class="small-text"> minutes <span class="description">Keep under WooCommerce's hold-stock time (<?php echo (int) get_option( 'woocommerce_hold_stock_minutes', 60 ); ?> min) so the pay link still works.</span></td></tr>
				<tr><th>Send</th><td><?php echo $chk( 'failed_customer', $s['failed_customer'], 'Email the customer a link to finish' ); // phpcs:ignore ?><br><?php echo $chk( 'failed_admin', $s['failed_admin'], 'Alert staff with the gateway error' ); // phpcs:ignore ?></td></tr>
				<tr><th>Staff email</th><td><input type="email" class="regular-text" name="<?php echo esc_attr( $f( 'failed_admin_email' ) ); ?>" value="<?php echo esc_attr( $s['failed_admin_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"></td></tr>
				<tr><th>Subject</th><td><input type="text" class="large-text" name="<?php echo esc_attr( $f( 'failed_subject' ) ); ?>" value="<?php echo esc_attr( $s['failed_subject'] ); ?>"></td></tr>
				<tr><th>Heading</th><td><input type="text" class="large-text" name="<?php echo esc_attr( $f( 'failed_heading' ) ); ?>" value="<?php echo esc_attr( $s['failed_heading'] ); ?>"></td></tr>
				<tr><th>Message</th><td><textarea class="large-text" rows="3" name="<?php echo esc_attr( $f( 'failed_intro' ) ); ?>"><?php echo esc_textarea( $s['failed_intro'] ); ?></textarea></td></tr>
				<tr><th>Button</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( $f( 'failed_button' ) ); ?>" value="<?php echo esc_attr( $s['failed_button'] ); ?>">
					<a class="button button-small" href="<?php echo esc_url( j5cr_action_url( 'j5cr_test', array( 'step' => 99 ) ) ); ?>">Email me a test</a></td></tr>
			</table>
		</div>

		<div class="j5cr-card">
			<h2>Data</h2>
			<table class="form-table" role="presentation">
				<tr><th>Keep cart records</th><td><input type="number" min="7" max="365" name="<?php echo esc_attr( $f( 'retention' ) ); ?>" value="<?php echo (int) $s['retention']; ?>" class="small-text"> days</td></tr>
				<tr><th>Do not remind</th><td><textarea name="j5cr_unsub" rows="4" class="large-text code"><?php echo esc_textarea( implode( "\n", (array) $unsub ) ); ?></textarea><p class="description">One email per line. These customers never get cart reminders or unpaid-checkout follow-ups (added here by the Stop reminders buttons and the unsubscribe link). Order emails are not affected.</p></td></tr>
			</table>
		</div>

		<?php submit_button( 'Save settings' ); ?>
	</form>
	<?php
}
