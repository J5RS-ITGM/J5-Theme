<?php
/**
 * J5-COMMENT-GUARD-1 (2026-11-10): spam guard for product reviews (and any
 * comment form), invisible to real customers. Anyone can still leave a review.
 *
 * A comment is rejected before it is saved when:
 *   1. the hidden honeypot field is filled in (bots fill every field);
 *   2. the "a person typed here" field is missing — the browser sets it the
 *      first time someone clicks or types in the form, so bots that post
 *      straight to wp-comments-post.php never send it;
 *   3. the text contains a link (spam's whole point; customers get a message
 *      asking them to remove it);
 *   4. the text is mostly Cyrillic (every spam wave on this site has been).
 * Staff who can moderate comments skip the checks. Moderation stays on, so
 * anything that gets through still waits for approval.
 * Filter j5_comment_guard_enabled (true) turns the whole guard off.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'J5_CG_TOKEN', 'j5-person' );

/** Hidden honeypot + person field, placed just before the submit button. */
add_filter( 'comment_form_submit_field', function ( $submit_field ) {
	if ( ! apply_filters( 'j5_comment_guard_enabled', true ) ) {
		return $submit_field;
	}
	$extra  = '<p class="j5-cg-hp" aria-hidden="true" style="position:absolute!important;left:-10000px!important;width:1px;height:1px;overflow:hidden;">';
	$extra .= '<label for="j5_cg_site">Leave this field empty</label>';
	$extra .= '<input type="text" name="j5_cg_site" id="j5_cg_site" value="" tabindex="-1" autocomplete="off"></p>';
	$extra .= '<input type="hidden" name="j5_cg_person" value="" class="j5-cg-person">';
	return $extra . $submit_field;
} );

/** Sets the person field on the first real interaction with a comment form. */
add_action( 'wp_footer', function () {
	if ( ! apply_filters( 'j5_comment_guard_enabled', true ) || ! is_singular() || ! comments_open() ) {
		return;
	}
	?>
	<script>
	(function () {
		var forms = document.querySelectorAll('#commentform, form.comment-form');
		forms.forEach(function (f) {
			var mark = function () {
				var el = f.querySelector('.j5-cg-person');
				if (el && !el.value) { el.value = <?php echo wp_json_encode( J5_CG_TOKEN ); ?>; }
			};
			['focusin', 'keydown', 'pointerdown', 'touchstart'].forEach(function (ev) { f.addEventListener(ev, mark, { once: true, passive: true }); });
		});
	})();
	</script>
	<?php
}, 99 );

/** Reject spam before WordPress saves it. */
add_filter( 'preprocess_comment', function ( $data ) {
	if ( ! apply_filters( 'j5_comment_guard_enabled', true ) ) {
		return $data;
	}
	// Staff replies and admin-side comments skip the checks.
	if ( is_admin() || current_user_can( 'moderate_comments' ) ) {
		return $data;
	}
	$type = isset( $data['comment_type'] ) ? (string) $data['comment_type'] : '';
	if ( in_array( $type, array( 'pingback', 'trackback' ), true ) ) {
		wp_die( esc_html__( 'Pingbacks are turned off.', 'j5' ), '', array( 'response' => 403 ) );
	}

	$text = (string) ( $data['comment_content'] ?? '' );
	$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$deny = function ( $msg, $code = 403 ) use ( $back ) {
		wp_die(
			'<p>' . esc_html( $msg ) . '</p><p><a href="' . esc_url( $back ) . '">Go back</a></p>',
			'Review not sent',
			array( 'response' => $code )
		);
	};

	// 1 + 2: bots.
	if ( ! empty( $_POST['j5_cg_site'] ) || J5_CG_TOKEN !== ( $_POST['j5_cg_person'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$deny( 'Your review could not be sent. Please go back, reload the page and try again.' );
	}
	// 3: links.
	if ( preg_match( '~(https?://|www\.|\[url|<a\s)~i', $text ) || ! empty( $data['comment_author_url'] ) && preg_match( '~\.(ru|biz|xyz|top)\b~i', (string) $data['comment_author_url'] ) ) {
		$deny( 'Please remove any links from your review and send it again.', 400 );
	}
	// 4: mostly Cyrillic.
	$letters  = preg_match_all( '/\p{L}/u', $text );
	$cyrillic = preg_match_all( '/\p{Cyrillic}/u', $text );
	if ( $letters > 0 && $cyrillic / $letters > 0.3 ) {
		$deny( 'Your review could not be sent.' );
	}
	return $data;
}, 1 );
