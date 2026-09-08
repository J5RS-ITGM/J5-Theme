<?php
/**
 * J5 Fulfillment Status
 * ---------------------
 * Hub-owned presentation + purchasability override for the product stock
 * banner. The hub is the source of truth; it publishes a fulfillment status
 * as product meta and the theme renders the banner and gates the cart.
 *
 * We deliberately do NOT overload WC's inventory states (onbackorder, etc.)
 * for these promises — "made to order" is a fulfillment intent, not a stock
 * shortage. Purchasability for genuinely unavailable statuses (out /
 * discontinued) is enforced here in the theme AND should be enforced hub-side
 * by not publishing a buyable stock status; the cart gate below is a
 * presentation guard, not a substitute for correct inventory data.
 *
 * Meta contract (published by the hub to WC product meta):
 *   _j5_fulfillment            string  status slug ('' / absent = normal)
 *                                      Built-in slugs: in_stock, special,
 *                                      made_to_order, final_stock, out,
 *                                      discontinued.
 *                                      Custom slugs may be added via
 *                                      Settings → J5 Fulfillment.
 *   _j5_fulfillment_note       string  optional sublabel override
 *   _j5_fulfillment_replaces   string  optional comma/space list of product IDs
 *                                      that replace this item; rendered by any
 *                                      status with 'replacements' => true
 *                                      (discontinued, final_stock)
 *
 * Status labels, default notes, visual state, and buyability are editable in
 * wp-admin (Settings → J5 Fulfillment); see inc/j5-fulfillment-admin.php.
 * These are global label definitions, not per-product data, so editing them
 * does not conflict with the hub being the source of truth for product data.
 *
 * A set status OVERRIDES the inventory-derived banner for simple and variable
 * products. For variable products it also locks the banner so j5-shop.js does
 * not flip it on variation select (per-option availability dots still update).
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whitelist of visual states the theme knows how to render, with a label for
 * the admin UI. Every status (built-in or custom) maps to one of these so the
 * banner has a color and the swatch/card dots resolve. Adding a brand-new
 * visual state requires CSS, so custom statuses reuse these four.
 *
 * @return array slug => human label
 */
function j5_fulfillment_states() {
	return array(
		'in'           => 'Green (available)',
		'special'      => 'Gold (special / lead time)',
		'out'          => 'Red (unavailable)',
		'discontinued' => 'Muted (discontinued)',
	);
}

/**
 * Seed/default statuses. Used to initialize the editable option on first load
 * and as a fallback if the option is ever emptied. Keyed by slug (the value
 * the hub publishes to _j5_fulfillment).
 *
 * 'replacements' => true means the status renders the "see these instead"
 * block from _j5_fulfillment_replaces. This is decoupled from buyability so a
 * status can be BOTH purchasable and show successors (final_stock).
 *
 * 'banner_lock' => true pins the stock banner to this status on variable
 * products, so variation selection cannot overwrite it. Use it only when the
 * status asserts something the per-variation data cannot — a lead time, an
 * inbound shipment, a discontinuation. Leave it false for statuses that merely
 * restate availability ('in_stock', 'special'); locking those freezes the
 * banner on a stale headline while the option pills correctly show otherwise.
 *
 * @return array
 */
function j5_fulfillment_default_types() {
	return array(
		'in_stock' => array(
			'state'        => 'in',
			'label'        => 'IN STOCK',
			'note'         => 'ready to ship',
			'buyable'      => true,
			'replacements' => false,
			'banner_lock'  => false,
			'ack'          => '',
		),
		'special' => array(
			'state'        => 'special',
			'label'        => 'SPECIAL ORDER',
			'note'         => 'ships from manufacturer · allow extra time',
			'buyable'      => true,
			'replacements' => false,
			'banner_lock'  => false,
			'ack'          => 'special_order',
		),
		'made_to_order' => array(
			'state'        => 'special',
			'label'        => 'MADE TO ORDER',
			'note'         => 'built to spec · extended lead time',
			'buyable'      => true,
			'replacements' => false,
			'banner_lock'  => true,
			'ack'          => 'made_to_order',
		),
		'on_order' => array(
			'state'        => 'special',
			'label'        => 'ON ORDER',
			'note'         => 'inbound stock · contact us for ETA',
			'buyable'      => true,
			'replacements' => false,
			'banner_lock'  => true,
			'ack'          => '',
		),
		'final_stock' => array(
			'state'        => 'special',
			'label'        => 'FINAL STOCK',
			'note'         => 'discontinued · limited units remaining',
			'buyable'      => true,
			'replacements' => true,
			'banner_lock'  => true,
			'ack'          => '',
		),
		'out' => array(
			'state'        => 'out',
			'label'        => 'OUT OF STOCK',
			'note'         => 'contact us for ETA',
			'buyable'      => false,
			'replacements' => false,
			'banner_lock'  => true,
			'ack'          => '',
		),
		'discontinued' => array(
			'state'        => 'discontinued',
			'label'        => 'DISCONTINUED',
			'note'         => 'no longer available',
			'buyable'      => false,
			'replacements' => true,
			'banner_lock'  => true,
			'ack'          => '',
		),
	);
}

/**
 * Option key holding the editable status definitions.
 */
const J5_FULFILLMENT_OPTION = 'j5_fulfillment_types';

/**
 * Active status definitions — editable via Settings → J5 Fulfillment.
 * Reads the option, seeding it from defaults the first time. Each entry is
 * validated so a malformed option can never break rendering.
 *
 * @return array
 */
function j5_fulfillment_types() {
	$stored = get_option( J5_FULFILLMENT_OPTION, null );

	if ( ! is_array( $stored ) || empty( $stored ) ) {
		return j5_fulfillment_default_types();
	}

	$valid_states = array_keys( j5_fulfillment_states() );
	// Back-compat seed for the 'ack' field (see below). Special order and made
	// to order promise different things — one is sourced for the buyer, the
	// other is built for them — so they carry separate acknowledgments rather
	// than sharing one generic lead-time notice.
	$ack_seed     = array(
		'special'       => 'special_order',
		'made_to_order' => 'made_to_order',
	);
	$clean        = array();

	foreach ( $stored as $slug => $def ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug || ! is_array( $def ) ) {
			continue;
		}
		$state = isset( $def['state'] ) && in_array( $def['state'], $valid_states, true )
			? $def['state']
			: 'in';
		$clean[ $slug ] = array(
			'state'        => $state,
			'label'        => isset( $def['label'] ) ? (string) $def['label'] : strtoupper( str_replace( '_', ' ', $slug ) ),
			'note'         => isset( $def['note'] ) ? (string) $def['note'] : '',
			'buyable'      => ! empty( $def['buyable'] ),
			// Back-compat: status lists saved before the 'replacements' flag
			// existed have no such key. 'discontinued' always showed the
			// replacement block, so default it on when the key is absent.
			'replacements' => array_key_exists( 'replacements', $def )
				? ! empty( $def['replacements'] )
				: ( 'discontinued' === $slug ),
			// Back-compat: status lists saved before 'banner_lock' existed have
			// no such key. Before this flag the banner locked whenever ANY
			// status was set, which froze correct per-variation availability on
			// variable products (a Small marked SPECIAL ORDER still showed a
			// green IN STOCK headline).
			//
			// The lock should hold only for statuses asserting a parent-level
			// truth the per-variation data cannot express: a lead time, an
			// inbound shipment, a discontinuation. Two statuses assert nothing
			// extra and must stay live — 'in_stock', and 'special', which is
			// precisely what a WC backorder already means and is the state the
			// JS derives per variation anyway. Locking either one would freeze
			// the signal that varies most.
			'banner_lock'  => array_key_exists( 'banner_lock', $def )
				? ! empty( $def['banner_lock'] )
				: ! in_array( $slug, array( 'in_stock', 'special' ), true ),
			// Acknowledgment implied by this status. Any product carrying the
			// status inherits this acknowledgment slug on top of its own
			// _j5_acknowledgments meta, so the buyer must affirm the lead-time
			// terms before checkout and the affirmation is recorded on the
			// order.
			//
			// Back-compat: status lists saved before this field existed have no
			// such key. Default it on for the two statuses that promise a lead
			// time rather than stock on hand — those are the orders where the
			// buyer is agreeing to wait, and where that agreement is worth
			// recording. Every other status defaults to none.
			//
			// The slug is only a reference; j5_ack_for_product() validates it
			// against j5_ack_definitions() and silently drops it if no such
			// acknowledgment exists. A dangling reference therefore degrades to
			// "no gate" rather than breaking checkout.
			'ack'          => array_key_exists( 'ack', $def )
				? sanitize_key( (string) $def['ack'] )
				: ( isset( $ack_seed[ $slug ] ) ? $ack_seed[ $slug ] : '' ),
		);
	}

	return ! empty( $clean ) ? $clean : j5_fulfillment_default_types();
}

/**
 * Format an expected-arrival date relative to today.
 *
 * Deliberately vague at distance and precise up close, which matches how
 * reliable a supplier ETA actually is: "arriving in about 3 weeks" two months
 * out, "arriving this week" when it's imminent. Avoids the staleness of a
 * hardcoded "ETA 2-3 weeks" that still says 2-3 weeks a month later.
 *
 * A date in the past means the shipment is late — say so plainly rather than
 * rendering a negative interval or silently showing nothing.
 *
 * @param string $iso Date string (ISO 'YYYY-MM-DD' preferred; anything
 *                    strtotime() understands is accepted).
 * @return string Human phrase, or '' if unparseable.
 */
function j5_format_eta( $iso ) {
	$ts = strtotime( $iso );
	if ( false === $ts ) {
		return '';
	}

	// Compare date-to-date in site time; ignore time-of-day.
	$today  = strtotime( current_time( 'Y-m-d' ) );
	$target = strtotime( gmdate( 'Y-m-d', $ts ) );
	$days   = (int) round( ( $target - $today ) / DAY_IN_SECONDS );

	if ( $days < 0 ) {
		$text = 'arrival delayed · contact us for status';
	} elseif ( 0 === $days ) {
		$text = 'arriving today';
	} elseif ( 1 === $days ) {
		$text = 'arriving tomorrow';
	} elseif ( $days <= 7 ) {
		$text = 'arriving this week';
	} elseif ( $days <= 14 ) {
		$text = 'arriving in about 2 weeks';
	} elseif ( $days <= 35 ) {
		$weeks = (int) max( 2, round( $days / 7 ) );
		$text  = sprintf( 'arriving in about %d weeks', $weeks );
	} elseif ( $days <= 75 ) {
		$text = 'arriving in about 2 months';
	} else {
		$months = (int) max( 2, round( $days / 30 ) );
		$text   = sprintf( 'arriving in about %d months', $months );
	}

	/**
	 * Filter the rendered ETA phrase.
	 *
	 * @param string $text Human phrase.
	 * @param int    $days Whole days until arrival (negative = overdue).
	 * @param string $iso  Original stored value.
	 */
	return apply_filters( 'j5_eta_text', $text, $days, $iso );
}

/**
 * Resolve the active fulfillment override for a product, if any.
 *
 * Returns an array (type, state, label, note, buyable, replacements,
 * banner_lock, replaces[]) or null
 * when there is no override and normal stock rendering should apply.
 *
 * @param WC_Product|int $product
 * @return array|null
 */
function j5_get_fulfillment( $product ) {
	if ( is_numeric( $product ) ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product instanceof WC_Product ) {
		return null;
	}

	$type = sanitize_key( (string) $product->get_meta( '_j5_fulfillment', true ) );

	$types = j5_fulfillment_types();
	if ( '' === $type || ! isset( $types[ $type ] ) ) {
		return null;
	}

	$def  = $types[ $type ];
	$note = trim( wp_strip_all_tags( (string) $product->get_meta( '_j5_fulfillment_note', true ) ) );

	// Parse replacement product IDs (discontinued successors).
	$replaces_raw = (string) $product->get_meta( '_j5_fulfillment_replaces', true );
	$replaces     = array();
	if ( '' !== $replaces_raw ) {
		foreach ( preg_split( '/[\s,]+/', $replaces_raw ) as $rid ) {
			$rid = absint( $rid );
			if ( $rid ) {
				$replaces[] = $rid;
			}
		}
		$replaces = array_values( array_unique( $replaces ) );
	}

	// Expected arrival date (_j5_fulfillment_eta, ISO YYYY-MM-DD). Rendered
	// relative to today so it stays accurate as the date approaches, instead
	// of a hardcoded "2-3 weeks" that silently rots. An explicit
	// _j5_fulfillment_note always wins — that's the escape hatch for nuance a
	// single date can't express (e.g. split batches arriving per size).
	$eta_raw  = trim( (string) $product->get_meta( '_j5_fulfillment_eta', true ) );
	$eta_text = ( '' !== $eta_raw ) ? j5_format_eta( $eta_raw ) : '';

	// Note precedence: explicit note → relative ETA → status default.
	if ( '' !== $note ) {
		$resolved_note = $note;
	} elseif ( '' !== $eta_text ) {
		$resolved_note = $eta_text;
	} else {
		$resolved_note = $def['note'];
	}

	return array(
		'type'         => $type,
		'state'        => $def['state'],
		'label'        => $def['label'],
		'note'         => $resolved_note,
		'eta'          => $eta_raw,
		'buyable'      => (bool) $def['buyable'],
		'replacements' => ! empty( $def['replacements'] ),
		'banner_lock'  => ! empty( $def['banner_lock'] ),
		'ack'          => isset( $def['ack'] ) ? (string) $def['ack'] : '',
		'replaces'     => $replaces,
	);
}

/**
 * The acknowledgment slug implied by a product's fulfillment status, if any.
 *
 * Kept as a thin accessor so the acknowledgments module can ask the question
 * without reaching into the status definition array itself. Returns '' when
 * the product has no status, or its status implies no acknowledgment.
 *
 * Variations inherit the parent's status (the meta is parent-level), which
 * matches how j5_ack_for_product() resolves product-attached acknowledgments.
 *
 * @param WC_Product|int $product
 * @return string Acknowledgment slug, or '' for none.
 */
function j5_fulfillment_ack_slug( $product ) {
	$f = j5_get_fulfillment( $product );

	return ( is_array( $f ) && ! empty( $f['ack'] ) ) ? sanitize_key( $f['ack'] ) : '';
}

/**
 * Whether a fulfillment override forbids purchase.
 *
 * @param array|null $f Result of j5_get_fulfillment().
 * @return bool
 */
function j5_fulfillment_blocks_cart( $f ) {
	return is_array( $f ) && false === $f['buyable'];
}

/**
 * Render the "see these instead" replacement block. Shown for any status whose
 * definition sets 'replacements' => true (discontinued, final_stock, or a
 * custom status). Server-rendered (crawlable) so link equity flows to the
 * successors. Falls back to a contact line when no replacements are configured.
 *
 * Copy varies by buyability: a still-purchasable status (final stock) should
 * not claim the product is unavailable.
 *
 * @param array $f Result of j5_get_fulfillment().
 */
function j5_render_discontinued_block( $f ) {
	if ( ! is_array( $f ) || empty( $f['replacements'] ) ) {
		return;
	}

	$still_buyable = ! empty( $f['buyable'] );
	$heading       = $still_buyable
		? 'Discontinued — final units available'
		: 'This product has been discontinued';
	$subline       = $still_buyable
		? 'Once these sell out, consider:'
		: 'Consider these current alternatives:';

	$replacements = array();
	foreach ( $f['replaces'] as $rid ) {
		$rp = wc_get_product( $rid );
		if ( $rp && $rp->is_visible() && 'publish' === get_post_status( $rid ) ) {
			$replacements[] = $rp;
		}
	}

	echo '<div class="j5-discontinued-block">';

	if ( ! empty( $replacements ) ) {
		echo '<div class="j5-discontinued-head">' . esc_html( $heading ) . '</div>';
		echo '<p class="j5-discontinued-sub">' . esc_html( $subline ) . '</p>';
		echo '<div class="j5-discontinued-alts">';
		foreach ( $replacements as $rp ) {
			$img = $rp->get_image( 'woocommerce_thumbnail' );
			printf(
				'<a class="j5-alt-card" href="%1$s"><span class="j5-alt-thumb">%2$s</span><span class="j5-alt-info"><span class="j5-alt-name">%3$s</span><span class="j5-alt-price">%4$s</span></span></a>',
				esc_url( get_permalink( $rp->get_id() ) ),
				$img, // WC-generated <img>, already escaped by core.
				esc_html( $rp->get_name() ),
				wp_kses_post( $rp->get_price_html() )
			);
		}
		echo '</div>';
	} else {
		echo '<div class="j5-discontinued-head">' . esc_html( $heading ) . '</div>';
		echo '<p class="j5-discontinued-sub">Contact us and we\'ll help you find a comparable replacement.</p>';
		echo '<a class="j5-discontinued-contact" href="' . esc_url( home_url( '/contact/' ) ) . '">Contact Us →</a>';
	}

	echo '</div>';
}
