<?php
/**
 * One-off: adds the "What is a factory order?" answer (#factory-orders) to the live FAQ page,
 * at the end of Orders & Shipping. Touches only that page and only adds this block, so edits
 * made to the FAQ in wp-admin are kept. Safe to re-run: does nothing if it is already there.
 *
 * Run: wp eval-file wp-content/themes/j5-theme/content/pages/add-factory-order-faq.php
 */
$id   = (int) apply_filters( 'j5_faq_page_id', 9041 );
$post = get_post( $id );
if ( ! $post ) { echo "FAQ page $id not found\n"; return; }
if ( false !== strpos( $post->post_content, 'id="factory-orders"' ) ) { echo "Already there, nothing to do\n"; return; }
$block = <<<'HTML'
<!-- wp:details {"anchor":"factory-orders"} -->
<details id="factory-orders" class="wp-block-details"><summary>What is a factory order?</summary><!-- wp:paragraph -->
<p>When an item or size isn't on our shelf, you can still order it. We place the order with the manufacturer for you, and it ships to you as soon as it's ready.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Because a factory order takes longer than an in-stock order, it costs less. The product page shows how much you save, and the discount comes off automatically in your cart. No code needed.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Lead times are set by the manufacturer and are estimates, not guarantees. Where we have a current estimate, it's shown on the product page. Need it sooner? Pick an in-stock option or <a href="/contact-us/">contact us</a> before ordering.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

HTML;
$c   = $post->post_content;
$pos = strpos( $c, '<!-- wp:heading {"className":"j5-faq-group"} -->' . "\n" . '<h2 class="wp-block-heading j5-faq-group">Discounts' );
$new = false === $pos ? rtrim( $c ) . "\n\n" . $block : substr( $c, 0, $pos ) . $block . substr( $c, $pos );
kses_remove_filters(); // keep the details id (anchor)
$r = wp_update_post( array( 'ID' => $id, 'post_content' => $new ), true );
echo is_wp_error( $r ) ? 'ERROR ' . $r->get_error_message() . "\n" : ( false === $pos ? "Added at the end of the FAQ\n" : "Added under Orders & Shipping\n" );
