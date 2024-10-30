=== Cart recovery for WordPress ===
Contributors: leewillis77
Donate link: https://wp-cart-recovery.com
Tags: marketing, ecommerce, woocommerce, abanadoned, cart
Requires at least: 6.4
Tested up to: 6.7
License: GPL v2
Requires PHP: 7.4
Stable tag: 3.3.3

Cart recovery for WordPress brings abandoned cart recovery and tracking to your WordPress store.

== Description ==

Cart recovery for WordPress brings abandoned cart recovery and tracking to your WordPress store. Here’s what you can expect from Cart recovery for WordPress:

* Tracks customer names and emails as soon as they’re entered at checkout
* Automated cart recovery emails & cart re-population
* Includes easy to use stats inside WordPress
* WooCommerce, WP e-Commerce, Easy Digital Downloads and Restrict Content Pro compatibility out-of-the-box
* Track abandoned carts, view stats in your WordPress dashboard, and configure automatic recovery email sending
* Integrates with WordPress' GDPR compliance tools for data access and data removal requests

Find out more at: [wp-cart-recovery.com](https://wp-cart-recovery.com/)

Pro version also available from [wp-cart-recovery.com](https://wp-cart-recovery.com/) that includes:

* Ability to generate and include dynamic per-customer discount codes
* Flexible email timing – choose when to send your emails
* Multiple emails – send a schedule of emails - not just one
* View details of carts in the recovery process
* Export abandoned cart details for separate follow-up
* View detailed interaction history of individual carts

## Treeware

You're free to use this package for free, but if it makes it to your production environment please [buy the world a tree](https://ecologi.com/ademtisoftware?gift-trees).

== Installation ==

* Install it as you would any other plugin
* Activate it
* Head over to Cart Recovery and set up your recovery email

== Screenshots ==

1. Detailed stats available in your WordPress admin area.
2. Configurable HTML email templates
3. Built-in HTML email templates, with tags for personalising your emails

== Changelog ==

= 3.3.3 =
* Fix: Resolve issue where preview emails could fail if a zero-value product was selected

= 3.3.2 = 
* Update: Compatible with WooCommerce  up to v9.4
* Update: Compatible with WordPress up to v6.6
* Update: Internal changes and tidy-ups for stability

= 3.3.1 =
* Update: Compatible with WooCommerce 8.5

= 3.3.0 =
* New: Automatically set the email address during checkout when clicking through from a recovery email, unless the user already has one
* Fix: Remove the user ID if it is stored in the meta table when anonymising a record
* Update: Internal refactoring to use more modern WooCommerce APIs

= 3.2.1 =
* Update: Compatibility with WooCommerce up to 8.4

= 3.2.0 =
* New: Compatibility with WooCommerce's block-based checkout

= 3.1.1 =
* Fix: Resolve issue where some styles weren't included correctly.

= 3.1.0 =
* New: Compatibility with WooCommerce up to 7.4
* New: Compatibility with WooCommerce High Performance Order Storage

= 3.0.1 =
* Change: Various internal cleanups and fixes
* New: Compatibility with Easy Digital Downloads v3
* New: Compatibility with WooCommerce up to 7.3

= 2.9.0 =
* Fix issues where warnings could be thrown on initial campaign creation

= 2.8.6 =
* Compatibility with WooCommerce up to 6.2
* Compatibility with Restrict Content Pro 3.4 and higher

= 2.8.4 =
* Tweak load order to fix an issue with "free" carts on Easy Digital Downloads

= 2.8.3 =
* Compatible with WooCommerce 5.3

= 2.8.2 =
* Compatible with WooCommerce 5.2

= 2.8.1 =
* Fix: Fix warning from wp_localize_script on WordPress 5.7+

= 2.8.0 =
* Change: Allow saved cart data to be filtered by customisations
* Change: Compatible with WooCommerce 4.5
* Fix: Fix warnings that could be generated when products do not have an image, or had an invalid image reference

= 2.7.2 =
* Fix: Tweak to default email content
* Fix: Update translation files
* Fix: Price formatting not always accurate on some eCommerce systems

= 2.7.1 =
* New: Integrate with WooCommerce 4.0 navigation bar.
* New: Allow notification email address to be filtered.

= 2.7.0 =
* New: Send admin email notification on successfully recovered carts.

= 2.6.8 =
* Admin CSS tweaks

= 2.6.7 =
* Support for some template tags in email subject lines

= 2.6.6 =
* Compatible with WooCommerce 3.9

= 2.6.5 = 
* Compatible with WooCommerce 3.8

= 2.6.4 =
* Fix issue with Pro integration where images were missing in preview emails.

= 2.6.3 =
* Add description of statuses to overview page.

= 2.6.2 =
* Use accessor methods rather than direct property access for some calls. Future-compat preparation. 

= 2.6.1 =
* Update pacakage dependencies, and rebuild assets
* Change: Expire old carts if due before attempting to send to them.

= 2.6.0 =
* New: Integration with WordPress' built-in data access request tool
* New: Integration with WordPress' built-in data removal request tool

= 2.5.1 =
* New: Filter that allows cart recording to be blocked

= 2.5.0 =
* Fix: Paragraphs sometimes weren't rendered properly in the emails
* New: Allow anonymisation of old cart records

= 2.4.1 =
* Change: Abandon carts if user empties the basket while it is in recovery.
* New: Additions to cart API for Pro features.

= 2.4.0 =
* Fix: {store_email} tag didn't work
* Change: Carts won't be captured if the customer has a very recently completed cart. Improves performance with slow gateways.

= 2.3.6 =
* Fix: Resolve issue with RestrictContentPro integration

= 2.3.5 =
* New: Make the return URL filterable (crfw_return_redirect_url) so you can send people to places other than the checkout page
* New: Tested with WooCommerce 3.3

= 2.3.4 =
* Fix: Avoid logging completion note more than once if cart continually updated to complete

= 2.3.3 =
* Change: Status graph takes site timezone into consideration.

= 2.3.2 =
* New: Adds additional feature to support Pro add-on improvements
* Fix: Make subject of email available to email templates

= 2.3.1 =
* New: Store the logged in user ID in cart meta.
* New: Add filter (crfw_send_campaign) that allows sending of a campaign to be blocked.

= 2.3.0 =
* Record recovered cart values, and display on dashboard.

= 2.2.2 =
* Record cart event when cart is marked as completed
* Record cart event when cart is marked as uncompleted

= 2.2.1 =
* Add warnings when cron is not running
* Add cron debug information

= 2.2.0 =
* Do not keep re-trying delivery if campaign can not be sent. Try a maximum of 3 times.

= 2.1.5 =
* [All] Do not track or attempt recovery on empty carts

= 2.1.4 =
* [All] Fix error when {last_name} tag used in email content.

= 2.1.3 =
* [Restrict Content Pro] Better behaviour of registration redirects when WordPress is hosted in a folder
* [WP e-Commerce] Fixes for checkout redirection / work on future compatibility with WP e-Commerce v4.0
* [All] Apply crfw_image_size filter consistently to allow customisation of image preset used in emails

= 2.1.2 =
* Fix packaging issue with graph scripts

= 2.1.1 =
* Avoid "headers already sent" message that could show up on WooCommerce
* Avoid error that could be thrown due to autoloader confusion when using WP-CLI
* Show normally completed orders on the summary graph

= 2.1.0 =
* Code cleanups
* Make back-to-cart URLs filterable
* Add additional hooks that run when carts are repopulated

= 2.0.3 =
* Fix issue where cart details weren't always captured when site/admin are on different HTTP schemes.

= 2.0.2 =
* Minor fix to email validation during cart capture.

= 2.0.1 =
* Fix issue where email addresses containing + symbols weren't brought back to checkout reliably.

= 2.0.0 =
* Support for [Restrict Content Pro](https://restrictcontentpro.com/)
* Better support for products with no images
* Ensure jQuery is loaded if not present already

= 1.9.1 =
* Send email to the site admin when campaigns can be activated.

= 1.9.0 =
* For logged in users, track carts as soon as products are added
* Optimise on-page Javascript
* Better tracking when carts are updated

= 1.8.4 =
* Fix issue where line prices in emails could be over-calculated on WooCommerce stores
* Fix issue where tax not always included in line prices in emails.

= 1.8.3 =
* Make sure duplicate cart records are all completed.

= 1.8.2 =
* Tidy up styling of the email tag help section
* Include quantities in the recovery emails
* Make prices reflect quantity selected in recovery emails

= 1.8.1 =
* Add hooks on cart actions to support further features.

= 1.8 =
* Show a list of valid tags on the edit campaign screen.

= 1.7.1 =
* Changes to translation text domain
* No other user-facing changes

= 1.7 =
* Localisation fixes
* Updates to list of Pro features
* Better cart completion tracking with callback-based payment gateways

= 1.6.5. =
* Database structure changes to reduce space usage on busy stores
* Stop duplicate carts being recorded on slow hosting by merging based on email address.

= 1.6.4 =
* Additional internal event tracking
* Code cleanups
* Changes to support discount generation in Pro add-on

= 1.6.3 =
* Correction to information in promo banners.

= 1.6.2 =
* Correctly honour the email address and from name set in the settings.

= 1.6.1 =
* Now handles updates via WordPress.org

= 1.5.1 =
* Templating fix.

= 1.5 =
* PHP 5.5 compatibility fix. Props @elvismdev

= 1.4 =
* Record events when users unsubscribe.

= 1.3 =
* Integrate with licensing and auto upgrades in the absence of a WordPress.org repo.

= 1.2 =
* Set email subject in HTML email title tag.

= 1.1 =
* Minor fixes to HTML email styling

= 1.0 =
* Initial release
