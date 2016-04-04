<?php
/*
 * Plugin Name: In Stock Layered Nav for WooCommerce
 * Plugin URI:  http://wordpress.org/plugins/in-stock-layered-nav-for-woocommerce
 * Description: Hides products that are out of stock for the size attribute when viewing that size's catalog page.
 * Version:     1.0
 * Author:      Linchpin Agency - Jonathan Desrosiers
 * Author URI:  http://linchpin.agency/?utm_source=in-stock-layered-nav-for-woocommerce&utm_medium=plugin-admin-page&utm_campaign=wp-plugin
 * License:     GPL 2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
class WooCommerce_In_Stock_Layered_Nav {

	/**
	 * WooCommerce_In_Stock_Layered_Nav constructor.
	 */
	function __construct() {
		add_action( 'init', array( $this, 'init' ) );

		add_filter( 'woocommerce_layered_nav_query_post_ids', array( $this, 'woocommerce_layered_nav_query_post_ids' ), 10, 4 );
		add_action( 'woocommerce_product_set_stock', array( $this, 'woocommerce_product_set_stock' ) );
		add_action( 'woocommerce_reduce_order_stock', array( $this, 'woocommerce_reduce_order_stock' ) );
		add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'woocommerce_ajax_save_product_variations' ) );
		add_action( 'save_post_product', array( $this, 'save_post_product' ) );
	}

	/**
	 * Make sure WooCommerce is active. If not, deactivate and display a notice.
	 */
	function init() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}
	}

	/**
	 * Deactivate the plugin.
	 */
	function admin_init() {
		deactivate_plugins( plugin_basename( __FILE__ ) );
	}

	/**
	 * Display a notice when the plugin is deactivated.
	 */
	function admin_notices() {
		?>
		<div class="updated">
			<p>
				<strong>WooCommerce In Stock Layered Nav</strong> requires <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>. The plugin was <strong>deactivated</strong>.
			</p>
		</div>
		<?php
	}

	/**
	 * Filter layered nav query post ID lists to exclude any out of stock items.
	 *
	 * @param $posts
	 * @param $args
	 * @param $attribute
	 * @param $value
	 *
	 * @return mixed
	 */
	function woocommerce_layered_nav_query_post_ids( $posts, $args, $attribute, $value ) {
		// Look for a cached list of posts first.
		$transient = get_transient( 'wc_layered_nav_query_post_ids_' . $value );
		if ( false !== $transient ) {
			return array_map( 'intval', $transient );
		}

		// Grab the attribute term. If we can't let's just bail.
		$attribute_term = get_term( $value, $attribute );
		if ( empty( $attribute_term ) || is_wp_error( $attribute_term ) ) {
			return $posts;
		}

		$original_posts = $posts;
		$in_stock_variations = array();

		$first_product = new WC_Product_Variable( $posts[0] );
		$attributes = $first_product->get_attributes();

		// Check for manual and taxonomy attributes
		if ( empty( $attributes[ $attribute ] ) || empty( $attributes[ $attribute ]['is_variation'] ) ) {
			return $posts;
		}

		$in_stock_variation_args = array(
			'post_type' => 'product_variation',
			'post_parent__in' => $posts,
			'posts_per_page' => 500,
			'offset' => 0,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'relation' => 'AND',
					array(
						'key' => '_manage_stock',
						'value' => 'yes',
					),
					array(
						'key' => '_stock',
						'value' => get_option( 'woocommerce_notify_no_stock_amount' ),
						'compare' => '>',
						'type' => 'NUMERIC',
					),
				),
				array(
					'relation' => 'OR',
					array(
						'key' => 'attribute_' . $attribute,
						'value' => $attribute_term->slug,
					),
					array(
						'key' => 'attribute_' . $attribute,
						'value' => $attribute_term->name,
					),
				),
			),
		);

		$in_stock_query = new WP_Query( $in_stock_variation_args );

		while ( $in_stock_query->have_posts() ) {
			$in_stock_variations = array_merge( $in_stock_variations, $in_stock_query->posts );

			$in_stock_variation_args['offset'] = $in_stock_variation_args['offset'] + $in_stock_variation_args['posts_per_page'];
			$in_stock_query = new WP_Query( $in_stock_variation_args );
		}

		$product_variations = array();

		// Amass a list of in stock variations.
		foreach ( $in_stock_variations as $variation ) {
			$product_variations[ $variation->post_parent ][] = $variation;
		}

		// Remove products that are not in stock.
		foreach ( $posts as $key => $product ) {
			if ( empty( $product_variations[ $product ] ) ) {
				unset( $posts[ $key ] );
			}
		}

		set_transient( 'wc_layered_nav_query_post_ids_' . $value, $posts, WEEK_IN_SECONDS );

		return $posts;
	}

	/**
	 * When a product's stock is adjusted, clear any size transients associated.
	 *
	 * @param $product
	 */
	function woocommerce_product_set_stock( $product ) {
		$attributes = $product->get_attributes();

		foreach ( $attributes as $attribute_name => $attribute ) {
			if ( ! $attribute['is_taxonomy'] || ! $attribute['is_variation'] ) {
				continue;
			}

			$terms = get_the_terms( $product->id, $attribute_name );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				return;
			}

			$ids = wp_list_pluck( $terms, 'term_id' );

			foreach ( $ids as $term_id ) {
				delete_transient( 'wc_layered_nav_query_post_ids_' . $term_id );
			}
		}
	}

	/**
	 * When an order is placed, make sure that all transients are cleared because items could now be out of stock.
	 *
	 * @param $order
	 */
	function woocommerce_reduce_order_stock( $order ) {
		$products = $order->get_items();

		foreach ( $products as $product ) {
			if ( empty( $product['variation_id'] ) ) {
				continue;
			}

			$product_obj = new WC_Product( $product['product_id'] );

			$attributes = $product_obj->get_attributes();

			foreach ( $attributes as $attribute_name => $attribute ) {
				if ( ! $attribute['is_taxonomy'] || ! $attribute['is_variation'] ) {
					continue;
				}

				$terms = get_the_terms( $product_obj->id, $attribute_name );

				if ( empty( $terms ) || is_wp_error( $terms ) ) {
					return;
				}

				$ids = wp_list_pluck( $terms, 'term_id' );

				foreach ( $ids as $term_id ) {
					delete_transient( 'wc_layered_nav_query_post_ids_' . $term_id );
				}
			}
		}
	}

	/**
	 * When someone saves a product's variations in the admin, let's clear transients.
	 *
	 * @param $product
	 */
	function woocommerce_ajax_save_product_variations( $product_id ) {
		$product = new WC_Product( $product_id );

		$attributes = $product->get_attributes();

		foreach ( $attributes as $attribute_name => $attribute ) {
			if ( ! $attribute['is_taxonomy'] || ! $attribute['is_variation'] ) {
				continue;
			}

			$terms = get_the_terms( $product->id, $attribute_name );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				return;
			}

			$ids = wp_list_pluck( $terms, 'term_id' );

			foreach ( $ids as $term_id ) {
				delete_transient( 'wc_layered_nav_query_post_ids_' . $term_id );
			}
		}
	}

	/**
	 * When a product is saved, clear out the transients for it's variation attributes.
	 *
	 * @param $post_id
	 */
	function save_post_product( $post_id ) {
		$product = new WC_Product( $post_id );

		$attributes = $product->get_attributes();

		foreach ( $attributes as $attribute_name => $attribute ) {
			if ( ! $attribute['is_taxonomy'] || ! $attribute['is_variation'] ) {
				continue;
			}

			$terms = get_the_terms( $product->id, $attribute_name );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				return;
			}

			$ids = wp_list_pluck( $terms, 'term_id' );

			foreach ( $ids as $term_id ) {
				delete_transient( 'wc_layered_nav_query_post_ids_' . $term_id );
			}
		}
	}
}
$woocommerce_in_stock_layered_nav = new WooCommerce_In_Stock_Layered_Nav();

/**
 * Remove all transients on deactivation.
 */
function wc_isln_deactivation_hook() {
	if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		return;
	}

	$taxonomies = wc_get_attribute_taxonomies();

	if ( empty( $taxonomies ) ) {
		return;
	}

	foreach ( $taxonomies as $taxonomy ) {
		$terms = get_terms( 'pa_' . $taxonomy->attribute_name );

		if ( empty( $terms ) || is_wp_error() ) {
			continue;
		}

		foreach ( $terms as $term ) {
			delete_transient( 'wc_layered_nav_query_post_ids_' . $term->term_id );
		}
	}
}
register_deactivation_hook( __FILE__, 'wc_isln_deactivation_hook' );