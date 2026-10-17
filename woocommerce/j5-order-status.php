<?php
/**
 * J5 order status view, shared by:
 *   - the order received page   (woocommerce/checkout/thankyou.php, $context = 'thankyou')
 *   - My Account > View order   (woocommerce/myaccount/view-order.php, $context = 'account')
 * Email links land on one of those two (inc/j5-order-view.php).
 *
 * Status hero + progress tracker follow the order's live state: LE ID review
 * (_j5le_status, inc/j5-le-verification.php), on hold, processing, completed,
 * canceled/refunded, failed, pending payment.
 *
 * @var WC_Order $order
 * @var string   $context  'thankyou' | 'account'
 */
defined( 'ABSPATH' ) || exit;
$context = isset( $context ) ? $context : 'thankyou';
$acct    = 'account' === $context;

$le        = (string) $order->get_meta( '_j5le_status' );   // pending | verified | rejected | ''
$le_ok     = in_array( $le, array( 'verified', 'approved' ), true );
$status    = $order->get_status();
$pickup    = false;
foreach ( $order->get_shipping_methods() as $m ) {
	if ( 'local_pickup' === $m->get_method_id() || false !== stripos( $m->get_method_title(), 'pickup' ) ) $pickup = true;
}
$first = $order->get_billing_first_name();
$cta   = array();

if ( 'failed' === $status ) {
	$state = 'failed';
	$title = 'Payment didn\'t go through';
	$lead  = 'Your bank declined the transaction, so this order wasn\'t placed. You can try again with the same or a different card.';
	$cta[] = array( $order->get_checkout_payment_url(), 'Try payment again', true );
} elseif ( 'pending' === $status ) {
	$state = 'hold';
	$title = 'Awaiting payment';
	$lead  = 'This order hasn\'t been paid yet. Complete payment and we\'ll get it moving.';
	$cta[] = array( $order->get_checkout_payment_url(), 'Pay now', true );
} elseif ( in_array( $status, array( 'cancelled', 'refunded' ), true ) ) {
	$state = 'failed';
	if ( 'rejected' === $le ) {
		$title = 'Order canceled';
		$lead  = 'We weren\'t able to verify the ID submitted with this order, so it was canceled and refunded in full to your original payment method. Refunds usually post within 5 to 10 business days.';
	} else {
		$title = 'refunded' === $status ? 'Order refunded' : 'Order canceled';
		$lead  = 'refunded' === $status ? 'This order was refunded to your original payment method. Refunds usually post within 5 to 10 business days.' : 'This order was canceled. If you have questions, reach out and we\'ll help.';
	}
} elseif ( 'rejected' === $le ) {
	// ID rejected, order not canceled yet (staff cancels and refunds by hand).
	$state = 'failed';
	$title = 'We couldn\'t verify your ID';
	$lead  = 'We weren\'t able to verify the ID submitted with this order, so it won\'t ship. It\'s being canceled and refunded in full to your original payment method. Refunds usually post within 5 to 10 business days. Check your email for details.';
} elseif ( 'pending' === $le ) {
	$state = 'review';
	$title = 'Verification in progress';
	$lead  = 'Your order includes items sold only to law enforcement and licensed security professionals. We\'re reviewing your ID now, typically within 1 to 2 business days, and we\'ll email you as soon as it\'s approved.';
} elseif ( 'on-hold' === $status ) {
	$state = 'hold';
	$title = 'Order on hold';
	$lead  = 'Your order is on hold while we confirm a few details. We\'ll email you as soon as it\'s moving.';
} elseif ( 'completed' === $status ) {
	$state = 'ok';
	$title = $pickup ? 'Ready for pickup' : 'Order shipped';
	$lead  = $pickup ? 'Your order is ready. Check your email for pickup details.' : 'Your order is on its way. Tracking details were sent to your email.';
} else {
	$state = 'ok';
	if ( $acct ) {
		$title = 'Order in progress';
	} else {
		$title = $first ? "Thanks, {$first}. Your order is in." : 'Thank you. Your order is in.';
	}
	$lead = $pickup ? 'We\'re getting it ready. We\'ll email you when it\'s ready for pickup.' : 'We\'re getting it ready to ship. You\'ll get tracking by email as soon as it leaves.';
	if ( $le_ok ) $lead = 'Your ID is verified. ' . $lead;
}

// Progress tracker: [label, done | active | todo].
$steps = array();
if ( in_array( $state, array( 'ok', 'review', 'hold' ), true ) && 'pending' !== $status ) {
	$done   = 'completed' === $status;
	$moving = in_array( $status, array( 'processing', 'completed' ), true );
	$steps[] = array( 'Order placed', 'done' );
	if ( $le ) $steps[] = array( 'ID verification', $le_ok ? 'done' : 'active' );
	$steps[] = array( $pickup ? 'Preparing' : 'Packing', $done ? 'done' : ( $moving ? 'active' : 'todo' ) );
	$steps[] = array( $pickup ? 'Ready for pickup' : 'Shipped', $done ? 'done' : 'todo' );
}
// Rejection reason from the ID claim (j5-verification stores the DP reviewer's text as _j5v_reason).
$reason = '';
if ( 'rejected' === $le && ( $cid = (int) $order->get_meta( '_j5le_claim' ) ) ) $reason = (string) get_post_meta( $cid, '_j5v_reason', true );

$eyebrow = 'Order #' . $order->get_order_number() . ( $acct ? ' · Placed ' . wc_format_datetime( $order->get_date_created() ) : ( 'failed' === $status ? '' : ' received' ) );
?>
<div class="j5-ty j5-ty--<?php echo esc_attr( $state ); ?> j5-ty--<?php echo esc_attr( $context ); ?>">

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
		<p class="j5-ty__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<h1 class="j5-ty__title"><?php echo esc_html( $title ); ?></h1>
		<p class="j5-ty__lead"><?php echo esc_html( $lead ); ?></p>
		<?php if ( $reason ) : ?>
			<div class="j5-ty__reason"><span>Reason</span><?php echo esc_html( $reason ); ?></div>
			<p class="j5-ty__muted">If you believe this was a mistake, call us at <a href="tel:+16304424938">(630) 442-4938</a> or email <a href="mailto:info@j5rescue.com">info@j5rescue.com</a> and we'll take another look.</p>
		<?php endif; ?>

		<?php if ( $cta || ( 'failed' === $state && ! $acct && is_user_logged_in() ) ) : ?>
			<p class="j5-ty__cta">
				<?php foreach ( $cta as $c ) : ?><a class="j5-ty__btn<?php echo $c[2] ? ' j5-ty__btn--primary' : ''; ?>" href="<?php echo esc_url( $c[0] ); ?>"><?php echo esc_html( $c[1] ); ?></a><?php endforeach; ?>
				<?php if ( 'failed' === $state && ! $acct && is_user_logged_in() ) : ?><a class="j5-ty__btn" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">My account</a><?php endif; ?>
			</p>
		<?php endif; ?>
		<?php if ( $steps ) : ?>
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
	// Payment gateways print their own instructions here (e.g. bank details). Thank-you page only.
	if ( ! $acct ) do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
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
			if ( $acct || ! is_user_logged_in() ) unset( $actions['view'] );
			if ( $actions ) : ?>
				<section class="j5-ty__card j5-ty__actions">
					<?php foreach ( $actions as $key => $a ) : ?>
						<a class="j5-ty__btn<?php echo 'view' === $key ? ' j5-ty__btn--primary' : ''; ?>" href="<?php echo esc_url( $a['url'] ); ?>"><?php echo esc_html( 'view' === $key ? 'View order' : $a['name'] ); ?></a>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>

			<?php $j5_notes = $acct ? $order->get_customer_order_notes() : array(); if ( $j5_notes ) : ?>
				<section class="j5-ty__card j5-ty__updates">
					<h2 class="j5-ty__h2">Updates</h2>
					<ul class="j5-ty__notes">
						<?php foreach ( $j5_notes as $n ) : ?>
							<li><span class="j5-ty__muted"><?php echo esc_html( date_i18n( wc_date_format(), strtotime( $n->comment_date ) ) ); ?></span><?php echo wp_kses_post( wpautop( wptexturize( $n->comment_content ) ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<section class="j5-ty__card j5-ty__help">
				<h2 class="j5-ty__h2">Questions?</h2>
				<p class="j5-ty__muted">Reference order #<?php echo esc_html( $order->get_order_number() ); ?> and we'll take it from there.</p>
				<p><a href="tel:+16304424938">(630) 442-4938</a><br><a href="mailto:info@j5rescue.com">info@j5rescue.com</a></p>
			</section>
		</aside>
	</div>

	<p class="j5-ty__continue">
		<?php if ( $acct ) : ?><a class="j5-ty__btn" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">← All orders</a><?php endif; ?>
		<a class="j5-ty__btn" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Continue shopping →</a>
	</p>
</div>
