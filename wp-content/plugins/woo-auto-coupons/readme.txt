=== Auto Coupons for WooCommerce ===
Contributors: rermis
Tags: woocommerce, coupons, auto apply, discount, duplicate
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 4.6
Tested up to: 6.8
Stable tag: 3.0.32

Apply WooCommerce Coupons automatically with a fast, lightweight plugin. Set minimum product quantities, apply coupons by URL or automatically.

== Description ==
Apply WooCommerce Coupons automatically with a simple, fast and lightweight plugin.

## Features
&#9745; **Unlimited Auto Apply Coupons** using native WooCommerce coupon conditions
 
&#9745; **Set Minimum Product Quantities** for coupons

&#9745; **Apply Coupons by URL** when a specific page is visited

&#9745; **Coupon Troubleshooting** by itemized coupon on the cart page

&#9745; **WooCommerce Blocks** Compatible

## PRO Features
&#9989;  **Auto-Apply Defaults** - Default future coupons to auto-apply automatically.

&#9989;  **Bulk Updates** - Update auto-apply on active coupons in bulk.

&#9989;  **Copy Coupons & More** - Duplicate coupons, products, posts and pages.

&#9989;  **Streamline Email** Restrictions - Prompt for customer email in cart when a coupon with email restrictions is applied.

&#9989;  **Coupon Defaults** - Set default verbiage for new coupons.


== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/woo-auto-coupons` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the \'Plugins\' screen in WordPress
3. Go to Marketing > Coupons
4. Create a coupon or visit an existing coupon
5. Auto Coupon options are found on individual WooCommerce coupon settings.

== Screenshots ==
1. Coupon Applied
2. Minimum Quantity for discount
3. Admin Settings
4. Troubleshooting mode

== Changelog ==
= 3.0.32 = * Compatibility with WC 9.9, bug fix for coupon applied check
= 3.0.30 = * Bug fix to unserialize restrictions
= 3.0.29 = * Bug fix to SQL case stmt
= 3.0.26 = * Coupon Query optimization.
= 3.0.23 = * Support for bulk apply settings.
= 3.0.19 = * Automate checks for future-auto-apply.
= 3.0.18 = * Bug fix to cart refresh on update.
= 3.0.17 = * Bug fix to min items qty in cart.
= 3.0.15 = * Added sanitization to setting tabs.
= 3.0.14 = * Compatibility checks, additional input sanitization.
= 3.0.12 = * Minor css improvements.
= 3.0.11 = * Bug fix to admin cache-clear.
= 3.0.9 = * Minor setup improvements.
= 3.0.7 = * Minor bug fixes.
= 3.0.0 = * Redesigned interface. Improvements to native WC email restriction behavior. Dependencies for future features.


== Frequently Asked Questions ==

= Does this plugin have a coupon limit? =
This plugin works with an unlimited number of coupons.

= Why isn't my coupon auto-applying? =
The coupon will apply only when conditions are met and the coupon has not been previously removed from the cart.  If a previously auto-applied coupon is manually removed from the cart, it will not auto apply again unless another coupon has been added and removed.

= How can I troubleshoot? =
Visit WooCommerce > Auto Coupons > Status to see troubleshooting options.  This page provides a button to reset the coupon cache, and the 'Troubleshoot' button will output a status for all coupons. You may also test coupons using your browser's private/incognito feature to bypass the cache.

= My cart page name is not '/cart', will the plugin still work? =
Auto Coupons will work with a non-standard cart page name.  Just open or save any coupon for the change to take effect.

= How can I troubleshoot my coupons? =
To troubleshoot, you must be logged in as an administrator.  Visit the cart page and press the 'Auto Coupons Status' button to list all coupons with status.
