=== Plugin Name ===
Contributors: Big Cloud Media, Payfirma
Tags: woocommerce, credit card payments, Payfirma, payment gateway
Requires at least: 3.5
Tested up to: 3.6.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The purpose of this plugin is to add the Payfirma payment gateway to WooCommerce so site admins can take direct credit card payments via their Payfirma HQ account.

== Description ==

If you are already using Payfirma for your credit card processing needs, now you can use it on your WooCommerce site too.

Take payments using the Payfirma Payment Gateway and keep track of them in your Payfirma HQ.  Easy peasy.

*** This plugin requires WooCommerce 2.0+ and an SSL connection ***

== Installation ==

1. Upload Payfirma_Woo_Gateway to the /wp-content/plugins/ directory or install it from the Plugin uploader
1. Activate the plugin through the Plugins menu in the WordPress administrator dashboard
1. Visit the settings page under WooCommerce > Settings > Payment Gateways to add your API key and Merchant ID and to enable the Gateway.

== Frequently Asked Questions ==

= Can you use any currency? =

No. At this time the currencies you can use with this gateway are Canadian Dollars (CAD) and US dollars (USD).

= How does it work? =

Payfirma Woo Gateway will automatically add an additional Payment Gateway to your WooCommerce > Settings > Gateway page.

Once your WooCommerce API info is entered and the gateway is enabled, it will automatically add an additional payment option to your WooCommerce payment screen for your visitor to use.

= Is it secure? =

When using an SSL connection, your customer's payment data is secure.

No credit card data is stored locally and all payments through this gateway are processed on Payfirma's secure servers.

A valid SSL connection is required in order to secure the interaction between your site and the Payfirma servers.


== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 3.1 = 
* update plugin with V2 API 
* change key & merchant_id to Client Id & Client Secret

= 2.1 =
* Bug fixes
* General code review and cleanup
* Added check for enabled "Force HTTP after checkout" option before validating check out page SSL connection.
* Updated error processing and display on gateway page.

= 1.0 =
* This is the first version of this plugin