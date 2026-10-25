<?php
/**
 * J5 guide tables: phone stacking support.
 * Copies each table's header text into data-label on the body cells so the
 * stacked layout in assets/css/j5-single.css can print the label beside each
 * value. Only runs on single posts/pages, only for .wp-block-table.
 *
 * @package astra-child
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_footer', function () {
	if ( ! is_singular( array( 'post', 'page' ) ) ) return;
	?>
	<script>
	(function(){
		document.querySelectorAll('.j5-single-body .wp-block-table table').forEach(function(t){
			var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function(th){ return th.textContent.trim(); });
			if (!heads.length) return;
			t.querySelectorAll('tbody tr').forEach(function(tr){
				Array.prototype.forEach.call(tr.children, function(td, i){
					if (!td.hasAttribute('data-label') && heads[i]) td.setAttribute('data-label', heads[i]);
				});
			});
		});
	})();
	</script>
	<?php
}, 30 );
