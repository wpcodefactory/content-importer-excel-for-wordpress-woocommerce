=== Import Content in WordPress & WooCommerce with Excel ===
Contributors: wpcodefactory, algoritmika, anbinder, karzin, omardabbas
Tags: excel import, content import, bulk import, import translations, migrate
Requires at least: 3.0.1
Requires PHP: 8.1
Tested up to: 6.8
Stable tag: 5.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Import Posts, Pages, Simple Products for WooCommerce & Wordpress with Excel. Migrate Easily. No more CSV Hassle

== Description ==

**You want to do a website migration without adding content 1 by 1? Bulk Edit Content from one screen? Use can now import content in WordPress from an Excel File with an easy Drag & Drop functionality.**

* **Import Posts, Pages, Simple Products for WooCommerce**, any Wordpress Content with Excel. No more CSV Hassle! Website Migration Made Easy
* **Data mapping** by **drag & drop** excel columns to post type fields, or **automatch columns** based on Excel Labels( if same with fields names).
* **Speed!** The form is submitted through **AJAX**, so there is no page reloading.
* **Multilingual!** It is compatible with QTRANSLATE (using the relevant tags for the translations) and POLYLANG for Multilingual Import
[Import any Post Type including WooCommerce Products with Excel](https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel/ "Wordpress Content Excel Importer PRO")
With the latest update you have an option to Import with same title even if this exists.

= Supports =

* Title
* Author
* Date
* Slug
* Description (HTML allowed)
* Short Description - Excerpt (HTML allowed)
* Language
* Translation of post (title)
* [ACF fields - PRO version](https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel/ "Wordpress Content Excel Importer PRO")
* [YOAST SEO Meta - PRO version](https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel/ "Wordpress Content Excel Importer PRO")
* [WPML compatibility - PRO version](https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel/ "Wordpress Content Excel Importer PRO")
<p>Categories/Tags are created along with the Product or Post creation if they don't pre exist.</p>
<p> If a subcategory/subtag is selected on the excel, parent categories will also be added/selected for the relevant product. </p>

= Extra for Posts =

* Category (multiple comma separated)
* Tags (multiple comma separated)

= Extra for Products =

* Product Category (multiple comma separated)
* Product Tags (multiple comma separated)
* Weight
* SKU
* Regular Price
* Sale Price
* Stock
* Virtual

**PRO VERSION**

* IMPORT ANY CUSTOM POST TYPE
* IMPORT ANY CUSTOM POST TYPE WMPL translations
* IMPORT FEATURED IMAGE
* IMPORT ANY CATEGORY, TAG, CUSTOM TAXONOMY
* CUSTOM FIELDS SUPPORT: ACF and other plugin's post meta fields - you can even upload Images and Gallery Images in custom fields with plugins like [ACF PRO](https://www.advancedcustomfields.com/pro/ "ACF PRO")
* IMPORT/UPDATE SIMPLE & VARIABLE PRODUCTS FOR WOOCOMMERCE
* MULTIPLE ATTRIBUTES AND VARIATIONS
* PRODUCT IMPORT - SIMPLE PRODUCTS WITH ATTRIBUTES: add multiple attributes,comma separated, this means you need only one Excel row for each product!
* IMPORTS PRODUCT IMAGES FROM URL --- NEW! UPLOAD IMAGE FROM DROPBOX PUBLIC FILE
* IMPORTS PRODUCT GALLERY IMAGES FROM URL
* DEFINE IF DOWNLOADABLE, DOWNLOADABLE NAME,URL,EXPIRY DATA & LIMIT
* IMPORT CATEGORY TERMS description HTML
* IMPORT YOAST SEO Meta Product fields, like META TITLE, META DESCRIPTION
* IMPORTS CUSTOM TAXONOMIES
* DELETE PRODUCTS THROUGH EXCEL FILE BY id, slug or title
* IMPORT CATEGORY TERMS AND SUB TERMS THROUGH EXCEL FILE
* DELETE CATEGORY TERMS THROUGH EXCEL FILE
* ADDED PROGRESS BAR IN ALL IMPORT / DELETE FUNCTIONS
* ALL IMPORT / DELETE FUNCTIONS RUN IN CHUNKS - NO PHP SERVER TIMEOUTS!

[Get it Here](https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel/ "Wordpress Content Excel Importer PRO")

[youtube https://www.youtube.com/watch?v=znQXiG7c7Sg&rel=0]

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins` directory and unzip, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress
3. Use the "WPFactory > Content Excel Importer" menu link, or "Settings" link on plugins page to use the plugin.
4. Upload your excel file (there is a link for a sample excel file) and proceed to data mapping and creating your products, posts or pages.

== Frequently Asked Questions ==

= Can I do a date migration with this WordPress Plugin? =
Yes absolutely, you just need to from an excel file with the content your website, slugs, titles etc, import it in the configuration screen, map the data with a simple drag and drop and voilla!

= Can I import Translations for qtranslate? =
Yes, insert the relevant taf for an excel field for title or description like in the example: [:en]Title[:][:el]Τίτλος[:]

= Can I import Translations for POLYLANG? =
Yes, insert a new row for each translation, mention the language code and in the Map a field for the translated title with " Translation Of " taxonomy

= Can I update the existing products? =
Yes, update is also supported and will be mentioned while uploading.

= Can I add product categories? =
Yes, when you add a category term in excel, this will be created along with the product.
You can add them comma separated in an excel column.

= How do i define if a product is in stock? =
By default, leaving stock column blank will create products that are 'in stock'.
If you need the to be as 'out of stock', simple add '0' to stock column and map it through the import process.

= Can I add product images / custom taxonomies along with the product? =
This feature is available in the [PRO Version](https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel/ "Wordpress Content Excel Importer PRO")

= Can I add product gallery images along with the product? =
This feature is available in the [PRO Version](https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel/ "Wordpress Content Excel Importer PRO")

= Can I import / update YOAST SEO Meta Fields like Title and description? =
This feature is available in the [PRO Version](https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel/ "Wordpress Content Excel Importer PRO").

== Screenshots ==

1. Import Content in WordPress with Excel - configuration screen
2. Import Content in WordPress with Excel - data mapping
3. Import Content in WordPress with Excel - mapped data

== Changelog ==

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
