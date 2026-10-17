<?php
/**
 * J5 order received ("thank you") page.
 *
 * Replaces WooCommerce's default thankyou.php. Keeps the WC hooks payment
 * gateways rely on (woocommerce_before_thankyou, woocommerce_thankyou_{method},
 * woocommerce_thankyou) but renders its own layout: status hero with a
 * progress tracker, order summary strip, line items with images, totals,
 * addresses, and actions (invoice, view order).
 *
 * LE-only orders awaiting ID review (_j5le_status = pending, see
 * inc/j5-le-verification.php) get a "Verification" step in the tracker.
 *
 * @var WC_Order|false $order
 */

defined( 'ABSPATH' ) || exit;

if ( ! $order ) {
	echo '<div class="j5-ty"><div class="j5-ty__hero"><h1 class="j5-ty__title">Thank you</h1><p class="j5-ty__lead">Your order has been received.</p></div></div>';
	return;
}

do_action( 'woocommerce_before_thankyou', $order->get_id() );

$failed     = $order->has_status( 'failed' );
$le_pending = 'pending' === $order->get_meta( '_j5le_status' );
$on_hold    = $order->has_status( 'on-hold' );
$pickup     = false;
foreach ( $order->get_shipping_methods() as $m ) {
	if ( 'local_pickup' === $m->get_method_id() || false !== stripos( $m->get_method_title(), 'pickup' ) ) $pickup = true;
}
$first = $order->get_billing_first_name();

if ( $failed ) {
	$state = 'failed';
	$title = 'Payment didn\'t go through';
	$lead  = 'Your bank declined the transaction, so this order wasn\'t placed. You can try again with the same or a different card.';
} elseif ( $le_pending ) {
	$state = 'review';
	$title = 'Verification in progress';
	$lead  = 'Your order includes items sold only to law enforcement and licensed security professionals. We\'re reviewing your ID now, typically within 1 to 2 business days, and we\'ll email you as soon as it\'s approved.';
} elseif ( $on_hold ) {
	$state = 'hold';
	$title = 'Order received';
	$lead  = 'Your order is on hold while we confirm a few details. We\'ll email you as soon as it\'s moving.';
} else {
	$state = 'ok';
	$title = $first ? "Thanks, {$first}. Your order is in." : 'Thank you. Your order is in.';
	$lead  = $pickup ? 'We\'re getting it ready. We\'ll email you when it\'s ready for pickup.' : 'We\'re getting it ready to ship. You\'ll get tracking by email as soon as it leaves.';
}

// Progress tracker steps: [label, state] where state = done | active | todo.
$steps = array( array( 'Order placed', 'done' ) );
if ( $le_pending ) $steps[] = array( 'ID verification', 'active' );
$steps[] = array( $pickup ? 'Preparing' : 'Packing', $le_pending || $on_hold ? 'todo' : 'active' );
$steps[] = array( $pickup ? 'Ready for pickup' : 'Shipped', 'todo' );
?>
<div class="j5-ty j5-ty--<?php echo esc_attr( $state ); ?>">

	<section class="j5-ty__hero">
		<div class="j5-ty__badge" aria-hidden="true">
			<?php if ( 'failed' === $state ) : ?>
				<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
			<?php elseif ( 'review' === $state || 'hold' === $state ) : ?>
				<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.5 4 5.5v6c0 5 3.4 8.6 8 10 4.6-1.4 8-5 8-10v-6l-8-3Z"/><path d="M12 8v4l2.5 2"/></svg>
			<?php else : ?>
				<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
			<?php endif; ?>
		</div>
		<p class="j5-ty__eyebrow">Order #<?php echo esc_html( $order->get_order_number() ); ?><?php echo $failed ? '' : ' received'; ?></p>
		<h1 class="j5-ty__title"><?php echo esc_html( $title ); ?></h1>
		<p class="j5-ty__lead"><?php echo esc_html( $lead ); ?></p>

		<?php if ( $failed ) : ?>
			<p class="j5-ty__cta">
				<a class="j5-ty__btn j5-ty__btn--primary" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>">Try payment again</a>
				<?php if ( is_user_logged_in() ) : ?><a class="j5-ty__btn" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">My account</a><?php endif; ?>
			</p>
		<?php else : ?>
			<ol class="j5-ty__steps" aria-label="Order progress">
				<?php foreach ( $steps as $i => $s ) : ?>
					<li class="j5-ty__step is-<?php echo esc_attr( $s[1] ); ?>"<?php echo 'active' === $s[1] ? ' aria-current="step"' : ''; ?>>
						<span class="j5-ty__dot"><?php echo 'done' === $s[1] ? '✓' : (int) ( $i + 1 ); ?></span>
						<span class="j5-ty__step-label"><?php echo esc_html( $s[0] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</section>

	<dl class="j5-ty__meta">
		<div><dt>Order</dt><dd>#<?php echo esc_html( $order->get_order_number() ); ?></dd></div>
		<div><dt>Date</dt><dd><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></dd></div>
		<div><dt>Total</dt><dd><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd></div>
		<?php if ( $order->get_payment_method_title() ) : ?><div><dt>Payment</dt><dd><?php echo esc_html( $order->get_payment_method_title() ); ?></dd></div><?php endif; ?>
		<?php if ( $order->get_billing_email() ) : ?><div><dt>Emailed to</dt><dd><?php echo esc_html( $order->get_billing_email() ); ?></dd></div><?php endif; ?>
	</dl>

	<?php
	// Payment gateways print their own instructions here (e.g. bank details).
	do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
	?>

	<div class="j5-ty__grid">
		<section class="j5-ty__card j5-ty__items" aria-labelledby="j5-ty-items">
			<h2 class="j5-ty__h2" id="j5-ty-items">Order summary</h2>
			<ul class="j5-ty__lines">
				<?php foreach ( $order->get_items() as $item_id => $item ) :
					$product = $item->get_product();
					$link    = $product && $product->is_visible() ? $product->get_permalink( $item ) : '';
					?>
					<li class="j5-ty__line">
						<div class="j5-ty__thumb"><?php echo $product ? $product->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) : ''; ?></div>
						<div class="j5-ty__line-main">
							<?php if ( $link ) : ?>
								<a class="j5-ty__line-name" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $item->get_name() ); ?></a>
							<?php else : ?>
								<span class="j5-ty__line-name"><?php echo esc_html( $item->get_name() ); ?></span>
							<?php endif; ?>
							<?php if ( $product && $product->get_sku() ) : ?><span class="j5-ty__sku">SKU <?php echo esc_html( $product->get_sku() ); ?></span><?php endif; ?>
							<div class="j5-ty__line-meta"><?php wc_display_item_meta( $item ); ?></div>
							<span class="j5-ty__qty">Qty <?php echo esc_html( $item->get_quantity() ); ?></span>
						</div>
						<div class="j5-ty__line-total"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></div>
					</li>
				<?php endforeach; ?>
			</ul>
			<table class="j5-ty__totals">
				<?php foreach ( $order->get_order_item_totals() as $key => $row ) :
					if ( 'payment_method' === $key ) continue; ?>
					<tr class="j5-ty__total-row j5-ty__total-row--<?php echo esc_attr( $key ); ?>">
						<th scope="row"><?php echo esc_html( rtrim( $row['label'], ':' ) ); ?></th>
						<td><?php echo wp_kses_post( $row['value'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php if ( $order->get_customer_note() ) : ?>
				<p class="j5-ty__note"><strong>Your note:</strong> <?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></p>
			<?php endif; ?>
		</section>

		<aside class="j5-ty__side">
			<section class="j5-ty__card">
				<h2 class="j5-ty__h2"><?php echo $pickup ? 'Pickup' : 'Shipping to'; ?></h2>
				<?php if ( $pickup ) : ?>
					<p class="j5-ty__addr"><?php echo esc_html( $order->get_shipping_method() ); ?></p>
					<p class="j5-ty__muted">We'll email you when it's ready, with pickup details.</p>
				<?php else : ?>
					<address class="j5-ty__addr"><?php echo wp_kses_post( $order->get_formatted_shipping_address( $order->get_formatted_billing_address() ) ); ?></address>
					<?php if ( $order->get_shipping_method() ) : ?><p class="j5-ty__muted"><?php echo esc_html( $order->get_shipping_method() ); ?></p><?php endif; ?>
				<?php endif; ?>
			</section>

			<section class="j5-ty__card">
				<h2 class="j5-ty__h2">Billing</h2>
				<address class="j5-ty__addr"><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?></address>
				<?php if ( $order->get_billing_phone() ) : ?><p class="j5-ty__muted"><?php echo esc_html( $order->get_billing_phone() ); ?></p><?php endif; ?>
			</section>

			<?php
			$actions = function_exists( 'wc_get_account_orders_actions' ) ? wc_get_account_orders_actions( $order ) : array();
			unset( $actions['pay'], $actions['cancel'] );
			if ( ! is_user_logged_in() ) unset( $actions['view'] );
			if ( $actions ) : ?>
				<section class="j5-ty__card j5-ty__actions">
					<?php foreach ( $actions as $key => $a ) : ?>
						<a class="j5-ty__btn<?php echo 'view' === $key ? ' j5-ty__btn--primary' : ''; ?>" href="<?php echo esc_url( $a['url'] ); ?>"><?php echo esc_html( 'view' === $key ? 'View order' : $a['name'] ); ?></a>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>

			<section class="j5-ty__card j5-ty__help">
				<h2 class="j5-ty__h2">Questions?</h2>
				<p class="j5-ty__muted">Reference order #<?php echo esc_html( $order->get_order_number() ); ?> and we'll take it from there.</p>
				<p><a href="tel:+16304424938">(630) 442-4938</a><br><a href="mailto:info@j5rescue.com">info@j5rescue.com</a></p>
			</section>
		</aside>
	</div>

	<p class="j5-ty__continue"><a class="j5-ty__btn" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Continue shopping →</a></p>

	<?php
	// Other plugins (analytics, conversion tracking) hook here. The default
	// order details table is removed below so it doesn't render twice.
	do_action( 'woocommerce_thankyou', $order->get_id() );
	?>
</div>
