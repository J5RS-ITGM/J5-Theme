<?php
/**
 * J5 FAQ schema
 * -------------
 * Emits FAQPage JSON-LD for any singular post or page whose content has a
 * "questions" section: an H2 whose text contains "question" (e.g. "Common
 * questions about HESCO plates"), followed by H3 questions with their answer
 * paragraphs. The schema mirrors the visible text exactly; nothing is added
 * that isn't on the page, so it stays within Google's FAQ guidelines.
 *
 * Google only shows FAQ rich results for government and health sites now, but
 * the markup still helps search engines and AI answer engines read the Q&A.
 *
 * Opt a post out with the meta key `_j5_faq_schema` = "off".
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Pull question/answer pairs from rendered post content.
 *
 * @param string $html Rendered content (after the_content filters).
 * @return array[] [ [ 'q' => string, 'a' => string ], ... ]
 */
function j5_faq_extract( $html ) {
	if ( ! preg_match( '#<h2[^>]*>(.*?)</h2>#is', $html ) ) return array();

	// Find the "questions" H2 and take everything until the next H2 (or the end).
	if ( ! preg_match( '#<h2[^>]*>[^<]*question[^<]*</h2>(.*?)(?=<h2[^>]*>|$)#is', $html, $m ) ) return array();
	$section = $m[1];

	$pairs = array();
	if ( ! preg_match_all( '#<h3[^>]*>(.*?)</h3>(.*?)(?=<h3[^>]*>|$)#is', $section, $qs, PREG_SET_ORDER ) ) return array();

	foreach ( $qs as $q ) {
		$question = trim( wp_strip_all_tags( $q[1] ) );
		// Answer: the paragraphs and list items under the H3, as plain text.
		$answer_html = preg_replace( '#<!--.*?-->#s', '', $q[2] );
		$answer_html = preg_replace( '#</(p|li)>#i', "$0\n", $answer_html );
		$answer = trim( preg_replace( "/[ \t]*\n[ \t\n]*/", "\n", wp_strip_all_tags( $answer_html ) ) );
		if ( '' === $question || '' === $answer ) continue;
		$pairs[] = array( 'q' => $question, 'a' => $answer );
	}
	return $pairs;
}

add_action( 'wp_footer', function () {
	if ( ! is_singular( array( 'post', 'page' ) ) ) return;
	$post = get_post();
	if ( ! $post || 'off' === get_post_meta( $post->ID, '_j5_faq_schema', true ) ) return;

	// Cheap pre-check before rendering filters run.
	if ( false === stripos( $post->post_content, 'question' ) ) return;

	$html  = apply_filters( 'the_content', $post->post_content );
	$pairs = j5_faq_extract( $html );
	if ( count( $pairs ) < 2 ) return;

	$entities = array();
	foreach ( $pairs as $p ) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $p['q'],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $p['a'] ),
		);
	}
	$graph = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);
	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}, 20 );
