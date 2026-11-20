<?php
/**
 * One-off: adds the "What does In production mean?" answer (#in-production) to the live FAQ page,
 * right after the factory order answer (#factory-orders) in Orders & Shipping. Touches only that
 * page and only adds this block, so edits made to the FAQ in wp-admin are kept. Safe to re-run:
 * does nothing if it is already there. (J5IP-1, 2026-11-19)
 *
 * Run: wp eval-file wp-content/themes/j5-theme/content/pages/add-in-production-faq.php
 */
$id   = (int) apply_filters( 'j5_faq_page_id', 9041 );
$post = get_post( $id );
if ( ! $post ) { echo "FAQ page $id not found\n"; return; }
if ( false !== strpos( $post->post_content, 'id="in-production"' ) ) { echo "Already there, nothing to do\n"; return; }
$block = <<<'HTML'
<!-- wp:details {"anchor":"in-production"} -->
<details id="in-production" class="wp-block-details"><summary>What does "In production" mean?</summary><!-- wp:paragraph -->
<p>In production means the plates are on an open purchase order that the manufacturer has confirmed. The product page shows the estimated ship date, which comes from the manufacturer.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Ordering in production plates gets you in line ahead of a full factory lead time, and they cost less too. You save half the factory order discount, typically 2.5 to 5%. The discount comes off automatically in your cart. No code needed.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Ship dates are estimates from the manufacturer, not guarantees. Need it sooner? Pick an in-stock option or <a href="/contact-us/">contact us</a> before ordering.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->
HTML;
$c     = $post->post_content;
$where = '';
// 1. Right after the factory order answer.
$fo = strpos( $c, 'id="factory-orders"' );
$end = false === $fo ? false : strpos( $c, '<!-- /wp:details -->', $fo );
if ( false !== $end ) {
	$at    = $end + strlen( '<!-- /wp:details -->' );
	$new   = substr( $c, 0, $at ) . "\n\n" . $block . substr( $c, $at );
	$where = "Added after the factory order answer\n";
} else {
	// 2. Before the Discounts group heading, else 3. at the end.
	$pos = strpos( $c, '<!-- wp:heading {"className":"j5-faq-group"} -->' . "\n" . '<h2 class="wp-block-heading j5-faq-group">Discounts' );
	$new   = false === $pos ? rtrim( $c ) . "\n\n" . $block . "\n" : substr( $c, 0, $pos ) . $block . "\n" . substr( $c, $pos );
	$where = false === $pos ? "Added at the end of the FAQ\n" : "Added under Orders & Shipping\n";
}
kses_remove_filters(); // keep the details id (anchor)
$r = wp_update_post( array( 'ID' => $id, 'post_content' => $new ), true );
echo is_wp_error( $r ) ? 'ERROR ' . $r->get_error_message() . "\n" : $where;
