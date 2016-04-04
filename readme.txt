=== In Stock Layered Nav for WooCommerce ===
Contributors: desrosj, linchpin_agency
Tags: woocommerce, stock, faceted search, layered nav
Requires at least: 3.0.1
Tested up to: 4.5
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Hides out of stock products when viewing an attribute catalog page.

== Description ==

Currently, when viewing an attribute catalog page in a store, products still show up if they are out of stock for the specified attribute. This could result in a frustrating customer experience.

Example: Consider Product A. Product A comes in sizes 1, 2, & 3. However, size 2 is currently out of stock. If I filter the catalogue using WooCommerce Layered Nav widget, I will see Product A listed under size 2, even though it is out of stock. This would remove Product A from the size 2 page until it is restocked.

A few notes about the plugin:

* WooCommerce is of course required.
* This will only work for attributes used in variations.
* The attributes need to be taxonomies, and not defined on the product level.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress


== Frequently Asked Questions ==

= I am not seeing out of stock products removed from attribute catalog pages. =

The attribute & options need to be set up as an [attribute taxonomy in WooCommerce](https://docs.woothemes.com/document/managing-product-taxonomies/#adding-attributes-to-your-store “Adding Attributes to your Store”).

= Will this plugin cause any performance issues? =

Even though the best efforts have been made to minimize any performance impacts, this plugin does perform some potentially heavy additional queries to filter out itms not in stock. It is recommended that you test this plugin on a staging version of your site first to ensure everything goes smoothly.

== Changelog ==

= 1.0 =
* Say hello to everyone WooCommerce In Stock Layered Nav!