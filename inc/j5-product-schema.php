<?php
/**
 * J5 Product Schema
 * -----------------
 * Completes the Product structured data Rank Math prints on single product
 * pages, using the data the dealer portal (DP) already pushes to WooCommerce.
 *
 * Rank Math is the only source of Product JSON-LD on this site: the custom
 * woocommerce/single-product.php fires no WooCommerce hooks, so WC's own
 * structured data never prints. This module edits Rank Math's node in place
 * (rank_math/json_ld) rather than adding a second one.
 *
 * What it adds or corrects:
 *   - brand  from _j5_brand, then the pa_brand attribute, then a brand
 *            taxonomy. Never from product tags (the first tag is often a
 *            distributor, e.g. "RSR").
 *   - mpn    from _j5_mpn (variation first, then parent).
 *   - gtin   from WC's global_unique_id (UPC/EAN), digits only, 8/12/13/14.
 *   - availability from the product's own fulfillment status, so the schema
 *            matches the stock banner the shopper sees:
 *              state in → InStock, special → BackOrder,
 *              out → OutOfStock, discontinued → Discontinued.
 *            No status → WC stock_status. on_order with an ETA also gets
 *            availabilityStarts.
 *   - hasMerchantReturnPolicy per offer, following the published Returns &
 *            Refunds page: 30 days by mail, customer pays return shipping,
 *            restocking fee up to 15%. Returns NOT permitted for ballistic
 *            protection (armor / ballistic / shield categories), products
 *            carrying the non_returnable acknowledgment, special-order and
 *            made-to-order items, and final-stock closeouts.
 *   - Variable products become a ProductGroup with one Product per variation
 *            (own SKU, GTIN, MPN, price, availability, return policy), which
 *            is Google's supported markup for product variants.
 *
 * Shipping is deliberately NOT marked up here: set it once in Search Console
 * (Settings → Shipping and returns). Leave the RETURN policy blank there —
 * Search Console settings override page markup and cannot express the
 * per-product exceptions above.
 *
 * Audit from the server (read-only):
 *   wp eval 'j5_schema_return_audit();'
 *
 * Added 2026-10-18 (J5-PRODUCT-SCHEMA-1).
 *
 * @package j5-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================================
 *  Settings (filterable)
 * ========================================================================== */

/**
 * Return-policy terms from the published Returns & Refunds page.
 *
 * @return array
 */
function j5_schema_return_terms() {
	$page_id = 7126; // Returns & Refunds.
	$link    = get_post_status( $page_id ) === 'publish' ? get_permalink( $page_id ) : home_url( '/returns-refunds/' );

	return apply_filters(
		'j5_schema_return_terms',
		array(
			'country'      => 'US',
			'days'         => 30,
			'restocking'   => 15, // Percent; policy says "up to 15%".
			'link'         => $link,
		)
	);
}

/**
 * Category keywords that mark ballistic protection (not returnable). Matched
 * against the slug and name of each product category AND its ancestors, so a
 * product filed only under a child category is still caught.
 *
 * @return array
 */
function j5_schema_nonreturnable_keywords() {
	return apply_filters( 'j5_schema_nonreturnable_keywords', array( 'armor', 'ballistic', 'shield' ) );
}

/**
 * Fulfillment types that are final sale under the returns policy.
 *
 * @return array
 */
function j5_schema_nonreturnable_types() {
	return apply_filters( 'j5_schema_nonreturnable_types', array( 'special', 'made_to_order', 'final_stock' ) );
}

/* ============================================================================
 *  Data helpers
 * ========================================================================== */

/**
 * The parent product (or the product itself when it has no parent).
 *
 * @param WC_Product $product
 * @return WC_Product
 */
function j5_schema_parent( $product ) {
	if ( $product->is_type( 'variation' ) ) {
		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent instanceof WC_Product ) {
			return $parent;
		}
	}
	return $product;
}

/**
 * Brand name. DP's _j5_brand first, then pa_brand, then a brand taxonomy.
 *
 * @param WC_Product $product Parent product.
 * @return string
 */
function j5_schema_brand( $product ) {
	$brand = trim( wp_strip_all_tags( (string) $product->get_meta( '_j5_brand', true ) ) );
	if ( '' !== $brand ) {
		return $brand;
	}

	$attr = trim( (string) $product->get_attribute( 'pa_brand' ) );
	if ( '' !== $attr ) {
		return $attr;
	}

	foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand' ) as $tax ) {
		if ( taxonomy_exists( $tax ) ) {
			$terms = get_the_terms( $product->get_id(), $tax );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				return wp_specialchars_decode( $terms[0]->name );
			}
		}
	}

	return '';
}

/**
 * GTIN (UPC/EAN) when it is a plausible barcode, else ''.
 *
 * @param WC_Product $product
 * @return string
 */
function j5_schema_gtin( $product ) {
	$raw = method_exists( $product, 'get_global_unique_id' )
		? (string) $product->get_global_unique_id()
		: (string) $product->get_meta( '_global_unique_id', true );
	$raw = preg_replace( '/\D/', '', $raw );

	return in_array( strlen( $raw ), array( 8, 12, 13, 14 ), true ) ? $raw : '';
}

/**
 * MPN: the product's own _j5_mpn, falling back to the parent's.
 *
 * @param WC_Product $product
 * @return string
 */
function j5_schema_mpn( $product ) {
	$mpn = trim( (string) $product->get_meta( '_j5_mpn', true ) );
	if ( '' === $mpn && $product->is_type( 'variation' ) ) {
		$mpn = trim( (string) j5_schema_parent( $product )->get_meta( '_j5_mpn', true ) );
	}
	return $mpn;
}

/**
 * The fulfillment status that applies to this product or variation.
 *
 * A variation uses ONLY its own status (DP publishes one per variation). It
 * does not fall back to the parent, because the parent carries the BEST
 * status across options and would overstate a single option. Without its
 * own status, a variation is described by its WC stock_status instead.
 *
 * @param WC_Product $product
 * @return array|null Result of j5_get_fulfillment(), or null.
 */
function j5_schema_fulfillment( $product ) {
	if ( ! function_exists( 'j5_get_fulfillment' ) ) {
		return null;
	}
	return j5_get_fulfillment( $product );
}

/**
 * schema.org availability for a simple product or a variation.
 *
 * @param WC_Product $product
 * @return array { url: string, starts: string }  starts = ISO date or ''.
 */
function j5_schema_availability( $product ) {
	$f = j5_schema_fulfillment( $product );

	if ( is_array( $f ) ) {
		$map = array(
			'in'           => 'InStock',
			'special'      => 'BackOrder',
			'out'          => 'OutOfStock',
			'discontinued' => 'Discontinued',
		);
		$val    = isset( $map[ $f['state'] ] ) ? $map[ $f['state'] ] : 'InStock';
		$starts = '';
		if ( 'BackOrder' === $val && ! empty( $f['eta'] ) ) {
			$ts = strtotime( $f['eta'] );
			if ( $ts && $ts > time() ) {
				$starts = gmdate( 'Y-m-d', $ts );
			}
		}
		return array( 'url' => 'https://schema.org/' . $val, 'starts' => $starts );
	}

	$stock = $product->get_stock_status();
	if ( 'onbackorder' === $stock ) {
		$val = 'BackOrder';
	} elseif ( 'outofstock' === $stock ) {
		$val = 'OutOfStock';
	} else {
		$val = 'InStock';
	}
	return array( 'url' => 'https://schema.org/' . $val, 'starts' => '' );
}

/**
 * Whether the parent product sits in a ballistic-protection category,
 * checking every assigned category and all of its ancestors.
 *
 * @param WC_Product $parent
 * @return string Matching category name, or ''.
 */
function j5_schema_ballistic_category( $parent ) {
	static $cache = array();
	$pid = $parent->get_id();
	if ( isset( $cache[ $pid ] ) ) {
		return $cache[ $pid ];
	}

	$hit      = '';
	$keywords = j5_schema_nonreturnable_keywords();
	$term_ids = $parent->get_category_ids();

	foreach ( $term_ids as $tid ) {
		$chain = array_merge( array( (int) $tid ), array_map( 'intval', get_ancestors( (int) $tid, 'product_cat', 'taxonomy' ) ) );
		foreach ( $chain as $cid ) {
			$term = get_term( $cid, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$hay = strtolower( $term->slug . ' ' . wp_specialchars_decode( $term->name ) );
			foreach ( $keywords as $kw ) {
				if ( '' !== $kw && false !== strpos( $hay, strtolower( $kw ) ) ) {
					$hit = wp_specialchars_decode( $term->name );
					break 3;
				}
			}
		}
	}

	$cache[ $pid ] = $hit;
	return $hit;
}

/**
 * Why a product (or variation) is not returnable, or '' when it is.
 *
 * @param WC_Product $product
 * @return string Reason code.
 */
function j5_schema_nonreturnable_reason( $product ) {
	$parent = j5_schema_parent( $product );

	// Product-attached acknowledgment (read raw, so it works even if the
	// acknowledgment's definition was renamed or removed in wp-admin).
	$acks = strtolower( (string) get_post_meta( $parent->get_id(), '_j5_acknowledgments', true ) );
	if ( '' !== $acks && in_array( 'non_returnable', preg_split( '/[\s,]+/', $acks ), true ) ) {
		return 'ack:non_returnable';
	}

	$cat = j5_schema_ballistic_category( $parent );
	if ( '' !== $cat ) {
		return 'category:' . $cat;
	}

	$f = j5_schema_fulfillment( $product );
	if ( ! is_array( $f ) && 'onbackorder' === $product->get_stock_status() ) {
		// No fulfillment status, but WC says backorder: that is the special-
		// order pipeline, which the policy makes final sale.
		return 'status:special';
	}
	if ( is_array( $f ) && in_array( $f['type'], j5_schema_nonreturnable_types(), true ) ) {
		return 'status:' . $f['type'];
	}

	return '';
}

/**
 * MerchantReturnPolicy node for a product or variation.
 *
 * @param WC_Product $product
 * @return array
 */
function j5_schema_return_policy( $product ) {
	$t = j5_schema_return_terms();

	$policy = array(
		'@type'               => 'MerchantReturnPolicy',
		'applicableCountry'   => $t['country'],
		'returnPolicyCountry' => $t['country'],
		'merchantReturnLink'  => $t['link'],
	);

	if ( '' !== j5_schema_nonreturnable_reason( $product ) ) {
		$policy['returnPolicyCategory'] = 'https://schema.org/MerchantReturnNotPermitted';
		return $policy;
	}

	$policy['returnPolicyCategory'] = 'https://schema.org/MerchantReturnFiniteReturnWindow';
	$policy['merchantReturnDays']   = (int) $t['days'];
	$policy['returnMethod']         = 'https://schema.org/ReturnByMail';
	$policy['returnFees']           = 'https://schema.org/ReturnFeesCustomerResponsibility';
	if ( ! empty( $t['restocking'] ) ) {
		$policy['restockingFee'] = (float) $t['restocking'];
	}

	return $policy;
}

/**
 * Formatted price string, or '' when the product has no price.
 *
 * @param WC_Product $product
 * @return string
 */
function j5_schema_price( $product ) {
	$price = $product->get_price();
	if ( '' === $price || null === $price ) {
		return '';
	}
	return wc_format_decimal( $price, wc_get_price_decimals() );
}

/**
 * Offer node for a simple product or a variation.
 *
 * @param WC_Product $product
 * @param string     $url
 * @param array      $base Existing Rank Math offer to keep fields from.
 * @return array|null Null when there is no price.
 */
function j5_schema_offer( $product, $url, $base = array() ) {
	$price = j5_schema_price( $product );
	if ( '' === $price ) {
		return null;
	}

	$avail = j5_schema_availability( $product );

	$offer = array(
		'@type'                   => 'Offer',
		'url'                     => $url,
		'price'                   => $price,
		'priceCurrency'           => get_woocommerce_currency(),
		'availability'            => $avail['url'],
		'itemCondition'           => 'https://schema.org/NewCondition',
		'hasMerchantReturnPolicy' => j5_schema_return_policy( $product ),
	);
	if ( '' !== $avail['starts'] ) {
		$offer['availabilityStarts'] = $avail['starts'];
	}

	// Keep what Rank Math already knows that we don't override.
	foreach ( array( 'seller', 'priceValidUntil', 'priceSpecification' ) as $k ) {
		if ( isset( $base[ $k ] ) ) {
			$offer[ $k ] = $base[ $k ];
		}
	}

	return $offer;
}

/**
 * Add sku / gtin / mpn / brand to a Product-like node.
 *
 * @param array      $node
 * @param WC_Product $product
 * @param string     $brand
 * @return array
 */
function j5_schema_identifiers( $node, $product, $brand ) {
	$sku = trim( (string) $product->get_sku() );
	if ( '' !== $sku ) {
		$node['sku'] = $sku;
	} else {
		unset( $node['sku'] );
	}

	$gtin = j5_schema_gtin( $product );
	if ( '' !== $gtin ) {
		$node['gtin'] = $gtin;
	}

	$mpn = j5_schema_mpn( $product );
	if ( '' !== $mpn ) {
		$node['mpn'] = $mpn;
	}

	if ( '' !== $brand ) {
		$node['brand'] = array( '@type' => 'Brand', 'name' => $brand );
	}

	return $node;
}

/* ============================================================================
 *  Variable products → ProductGroup
 * ========================================================================== */

/**
 * schema.org property for a WC attribute, when Google supports varying by it.
 *
 * @param string $attr Attribute key, e.g. 'pa_size' or 'size'.
 * @return string Property name ('size', 'color', ...) or ''.
 */
function j5_schema_vary_prop( $attr ) {
	$key = strtolower( preg_replace( '/^(attribute_)?(pa_)?/', '', (string) $attr ) );
	$map = apply_filters(
		'j5_schema_vary_map',
		array(
			'size'     => 'size',
			'color'    => 'color',
			'colour'   => 'color',
			'material' => 'material',
			'pattern'  => 'pattern',
		)
	);
	return isset( $map[ $key ] ) ? $map[ $key ] : '';
}

/**
 * Human value of a variation attribute (term name, not slug).
 *
 * @param string $attr  Attribute key without the 'attribute_' prefix.
 * @param string $value Stored value (slug for taxonomies).
 * @return string
 */
function j5_schema_attr_label( $attr, $value ) {
	if ( taxonomy_exists( $attr ) ) {
		$term = get_term_by( 'slug', $value, $attr );
		if ( $term && ! is_wp_error( $term ) ) {
			return wp_specialchars_decode( $term->name );
		}
	}
	return (string) $value;
}

/**
 * Turn Rank Math's variable-product node into a ProductGroup with variants.
 *
 * @param array               $node
 * @param WC_Product_Variable $product
 * @return array
 */
function j5_schema_product_group( $node, $product ) {
	$brand     = j5_schema_brand( $product );
	$parent_nm = isset( $node['name'] ) ? $node['name'] : $product->get_name();
	$permalink = get_permalink( $product->get_id() );
	$base      = isset( $node['offers'] ) && is_array( $node['offers'] ) ? $node['offers'] : array();
	$variants  = array();
	$varies    = array();
	$cap       = (int) apply_filters( 'j5_schema_variant_cap', 100 );

	foreach ( $product->get_children() as $vid ) {
		if ( count( $variants ) >= $cap ) {
			break;
		}
		$v = wc_get_product( $vid );
		if ( ! $v instanceof WC_Product_Variation || 'publish' !== get_post_status( $vid ) ) {
			continue;
		}
		if ( method_exists( $v, 'variation_is_visible' ) && ! $v->variation_is_visible() ) {
			continue;
		}

		$labels = array();
		$query  = array();
		$props  = array();
		foreach ( $v->get_variation_attributes() as $key => $value ) {
			if ( '' === $value ) {
				continue; // "Any" — nothing to name or preselect.
			}
			$attr          = preg_replace( '/^attribute_/', '', $key );
			$label         = j5_schema_attr_label( $attr, $value );
			$labels[]      = $label;
			$query[ $key ] = $value;
			$prop          = j5_schema_vary_prop( $attr );
			if ( '' !== $prop ) {
				$props[ $prop ]  = $label;
				$varies[ $prop ] = 'https://schema.org/' . $prop;
			}
		}

		$url   = add_query_arg( array_map( 'rawurlencode', $query ), $permalink );
		$offer = j5_schema_offer( $v, $url, $base );
		if ( null === $offer ) {
			continue;
		}

		$item = array(
			'@type' => 'Product',
			'name'  => $labels ? $parent_nm . ' - ' . implode( ' / ', $labels ) : $parent_nm,
			'url'   => $url,
		);
		$img_id = $v->get_image_id();
		$img    = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : '';
		if ( $img ) {
			$item['image'] = $img;
		} elseif ( isset( $node['image'] ) ) {
			$item['image'] = $node['image'];
		}
		$item           = j5_schema_identifiers( $item, $v, $brand );
		$item           = array_merge( $item, $props );
		$item['offers'] = $offer;

		$variants[] = $item;
	}

	if ( empty( $variants ) ) {
		// Nothing priced to describe; keep Rank Math's node, just enriched.
		return j5_schema_identifiers( $node, $product, $brand );
	}

	$node['@type'] = 'ProductGroup';
	unset( $node['offers'], $node['sku'], $node['gtin'], $node['gtin8'], $node['gtin12'], $node['gtin13'], $node['gtin14'], $node['mpn'] );

	$group_id = trim( (string) $product->get_sku() );
	$node['productGroupID'] = '' !== $group_id ? $group_id : (string) $product->get_id();
	if ( '' !== $brand ) {
		$node['brand'] = array( '@type' => 'Brand', 'name' => $brand );
	}
	if ( $varies ) {
		$node['variesBy'] = array_values( $varies );
	}
	$node['hasVariant'] = $variants;

	return $node;
}

/* ============================================================================
 *  Rank Math hook
 * ========================================================================== */

/**
 * Whether a JSON-LD node is Rank Math's Product node.
 *
 * @param mixed $node
 * @return bool
 */
function j5_schema_is_product_node( $node ) {
	if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
		return false;
	}
	$types = (array) $node['@type'];
	return in_array( 'Product', $types, true );
}

/**
 * Rewrite one Product node for the current product.
 *
 * @param array      $node
 * @param WC_Product $product
 * @return array
 */
function j5_schema_rewrite_node( $node, $product ) {
	if ( $product->is_type( 'variable' ) ) {
		return j5_schema_product_group( $node, $product );
	}

	$brand = j5_schema_brand( $product );
	$node  = j5_schema_identifiers( $node, $product, $brand );
	$base  = isset( $node['offers'] ) && is_array( $node['offers'] ) ? $node['offers'] : array();
	$offer = j5_schema_offer( $product, get_permalink( $product->get_id() ), $base );
	if ( null !== $offer ) {
		$node['offers'] = $offer;
	}
	return $node;
}

add_filter(
	'rank_math/json_ld',
	function ( $data, $jsonld = null ) {
		if ( ! is_array( $data ) || ! function_exists( 'is_product' ) || ! is_product() ) {
			return $data;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product instanceof WC_Product ) {
			return $data;
		}

		try {
			foreach ( $data as $key => $node ) {
				if ( j5_schema_is_product_node( $node ) ) {
					$data[ $key ] = j5_schema_rewrite_node( $node, $product );
				} elseif ( '@graph' === $key && is_array( $node ) ) {
					foreach ( $node as $gk => $gnode ) {
						if ( j5_schema_is_product_node( $gnode ) ) {
							$data['@graph'][ $gk ] = j5_schema_rewrite_node( $gnode, $product );
						}
					}
				}
			}
		} catch ( \Throwable $e ) {
			// Never let schema break a product page; fall back to Rank Math's.
			return $data;
		}

		return $data;
	},
	99,
	2
);

/* ============================================================================
 *  Audit (WP-CLI)
 * ========================================================================== */

/**
 * Print every published product's return classification and the gaps in its
 * identifiers. Read-only. Run on the server:
 *   wp eval 'j5_schema_return_audit();'
 */
function j5_schema_return_audit() {
	$ids = get_posts(
		array(
			'post_type'        => 'product',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);

	$reasons = array();
	$missing = array( 'brand' => 0, 'gtin' => 0, 'mpn' => 0 );
	$total   = 0;

	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$total++;
		$r = j5_schema_nonreturnable_reason( $p );
		$k = '' === $r ? 'RETURNABLE (30 days)' : 'NOT RETURNABLE ' . $r;
		$reasons[ $k ] = isset( $reasons[ $k ] ) ? $reasons[ $k ] + 1 : 1;

		if ( '' === j5_schema_brand( $p ) ) {
			$missing['brand']++;
		}
		$probe = $p;
		if ( $p->is_type( 'variable' ) ) {
			$kids  = $p->get_children();
			$probe = $kids ? wc_get_product( $kids[0] ) : $p;
		}
		if ( $probe && '' === j5_schema_gtin( $probe ) ) {
			$missing['gtin']++;
		}
		if ( $probe && '' === j5_schema_mpn( $probe ) ) {
			$missing['mpn']++;
		}
	}

	ksort( $reasons );
	echo "Published products: {$total}\n\nReturn classification:\n";
	foreach ( $reasons as $k => $n ) {
		printf( "  %5d  %s\n", $n, $k );
	}
	echo "\nMissing identifiers (variable products checked on first variation):\n";
	foreach ( $missing as $k => $n ) {
		printf( "  %5d  no %s\n", $n, $k );
	}
}
