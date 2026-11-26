=== Excel Importer for WooCommerce: Products, Posts & Pages with Preview ===
Contributors: wpcodefactory, anbinder, karzin, omardabbas
Tags: excel import, woocommerce import, product import, csv import, bulk edit
Requires at least: 5.3
Requires PHP: 8.1
Tested up to: 7.0
Stable tag: 5.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Update products, posts and pages from an Excel sheet: match by SKU or ID, preview every change before it is saved.

== Description ==

Import and update your store from the sheet you already have: a supplier price list, a stock file, a blog migration.

* **Works with your own files.** .xlsx, .xls, .ods and .csv. The first row holds the column names, in any language.
* **See every change first.** The preview lists each row: what will be created, what will be updated (old value and new value) and what needs a look, such as a sale price above the regular price. Nothing is saved until you start the import.
* **Only the columns in your sheet change.** A price list with three columns updates three fields. Descriptions, status, dates and stock you did not put in the sheet stay as they are.

= What you can do =

* Import and update simple products, posts and pages.
* Find existing items by SKU, by ID or by title. Items of other types with the same title are never touched.
* Choose what happens to rows that match nothing: create them or skip them.
* Columns match themselves: field names, common words in English, German, French and Spanish ("Art.-Nr.", "Lager", "Precio") and the matches you saved for the same sheet last time.
* Match columns with AI on WPFactory AI credits, with 100 free credits a month (optional, see External services).
* Product fields: SKU, regular price, sale price, stock quantity, weight, virtual, categories and tags.
* Post and page fields: title, content, excerpt, status, date, URL slug, author, categories and tags, Polylang language and translations.
* A report of every import: created, updated, skipped and failed rows with links, downloadable as CSV and listed on the History page for 30 days.
* Pause or stop an import at any row.

= Pro =

[Excel Importer for WooCommerce Pro](https://wpfactory.com/item/content-importer-for-wordpress-woocommerce-with-excel/) adds variable products and attributes, images and galleries by URL, downloadable files, custom fields (ACF and others), Yoast SEO fields, upsells and cross-sells, WPML, any post type, deleting content by sheet with a preview, importing and deleting categories, and a role setting for shop managers.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate it.
3. Open WPFactory > Excel Importer, choose what you are importing and drop your sheet.

== Frequently Asked Questions ==

= Will an import change things I did not put in my sheet? =

Not with "Change only the columns in this sheet", which new installs start with. Sites that used the plugin before keep the old "Overwrite every field" choice until you pick the other one on the matching screen and tick "Use this choice for my next imports too".

= How do I update prices only? =

Make a sheet with a SKU column and the price columns, choose "Find existing items by SKU" and "Change only the columns in this sheet". The preview shows every old and new price before you start.

= What happens to a page with the same title as a product? =

Nothing. Rows only match items of the type you import.

= Which files can I use? =

.xlsx, .xls, .ods and .csv. Use the first row for column names. Samples are linked on the import screen.

= What is sent to the AI? =

Only when you click "Match with AI": the column names, up to three sample rows (cells cut to 80 characters, never the cells of columns that look personal, such as email or phone) and the list of fields. See External services.

= Where are my uploaded files kept? =

In a closed folder in your uploads directory, with a random name, until the import finishes. Unfinished uploads are removed after a day. Reports are kept for 30 days.

== External services ==

This plugin can use WPFactory AI (https://ai.wpfactory.com), a service by WPFactory, for its AI features, paid with WPFactory credits. Nothing is sent until a site administrator connects the site on the AI Credits screen with an email address and a 6-digit code sent to it. When connecting, the site address and that email address are sent. After that, the text of each AI request the plugin makes is sent when a user starts it; WPFactory AI passes it to Anthropic, its AI provider, to produce the answer. WPFactory AI keeps only numbers about each request (tokens, credits, time), never its content. Terms: https://wpfactory.com/terms-conditions/#wpfactory-ai Privacy: https://wpfactory.com/privacy-policy/#wpfactory-ai

== Screenshots ==

1. Start: choose what you import and drop your sheet.
2. Match your columns: automatic matches, SKU or ID matching and how to update.
3. Preview: every change, old and new value, before anything is saved.
4. Result and History: what was created, updated and skipped, with a downloadable report.

== Changelog ==

= 5.0.4 - 24/05/2026 =
* Tested up to: 7.0.
* WC tested up to: 10.7.

= 5.0.3 - 16/09/2025 =
* WC tested up to: 10.1.

= 5.0.2 - 10/06/2025 =
* Fix - Fix plugin icon.

= 5.0.1 - 06/06/2025 =
* Dev - Updated plugin icon and banner.

= 5.0.0 - 19/05/2025 =
* Fix - Translation loading fixed.
* Dev - Plugin settings moved to the "WPFactory" menu.
* Dev - "Recommendations" added.
* Dev - Code refactoring and cleanup.
* WC tested up to: 9.8.
* Tested up to: 6.8.

= 4.4 =
* Dev - Update PhpSpreadsheet to 3.4.0, compatibility with WP 6.7, WooCommerce 9.4.

= 4.2 =
* Fix css, check compatibilities.

= 4.1 =
* Fix notice issue.

= 4.0 =
* HPOS compatibility, add deact form.

= 3.9 =
* Fix localization - check wp version.

= 3.8 =
* Fix compatibility with PHP version 8.0.6.

= 3.7 =
* Fix bug on indexing woo products.

= 3.6 =
* Fix indexing WHEN ADDING WooCommerce Products.
* Fix bug deleting temp excel file after import.

= 3.5 =
* Add automatch columns feature.

= 3.4 =
* Replace PHPExcel Library with PHPSpreadsheet.

= 3.3 =
* Do not include "uncategorized" category if posts are imported.

= 3.2 =
* Import with same title even if this exists.

= 3.1 =
* Fixed compatibility with WP 5.5.

= 2.0 =
* Update WP & WooCommerce version.
* Add virtual functionality for products.
* Fix problem with Ajax function.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 4.4 =
Update PhpSpreadsheet to 3.4.0, compatibility with WP 6.7, WooCommerce 9.4.
