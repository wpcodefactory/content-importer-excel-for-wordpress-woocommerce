# Changelog

**Types of changes**
* `Added` for new features.
* `Changed` for changes in existing functionality.
* `Deprecated` for soon-to-be removed features.
* `Removed` for now removed features.
* `Fixed` for any bug fixes.
* `Security` in case of vulnerabilities

## [1.1.4] - 2026-10-14
### Fixed
* Top white margin on settings page since last change on WooCommerce version 11.2.

## [1.1.3] - 2026-10-06
### Fixed
* Dependabot alert.

## [1.1.2] - 2026-07-29
### Fixed
* Dependabot alert.

## [1.1.1] - 2026-05-20
### Fixed
* Dependabot alert.

## [1.1.0] - 2026-04-14
### Fixed
* Dependabot alert.

## [1.0.9] - 2026-03-10
### Fixed
* Dependabot alert.

## [1.0.8] - 2025-06-20
### Fixed
* Some notices that were being displayed in the wrong place.
### Added
* `is-scrolled` class on `wpfam-woocommerce-layout__header` to replicate WooCommerce`s behaviour.

## [1.0.7] - 2025-06-18
### Fixed
* Img was still getting the width set.

## [1.0.6] - 2025-06-17
### Changed
* Plugin icon default margin right to 5px.
* Now Setting default height instead of width.
## Added
* Subparameters for `plugin_icon` param: `get_url_method`, and `wporg_plugin_slug`. Example:
```php
'plugin_icon' => array(
    'get_url_method'    => 'wporg_plugins_api',
    'wporg_plugin_slug'   => 'cost-of-goods-for-woocommerce',    
)
```

## [1.0.5] - 2025-06-11
### Fixed
* Missing `plugin_icon` parameter php warning.

## [1.0.4] - 2025-06-11
### Fixed
* Compatibility with WooCommerce 9.9.
### Added
* Parameter `plugin_icon` with its own parameters args (`url`, `style`, `width`) . Example: 
```php
'plugin_icon' => array(
    'url'   => 'https://ps.w.org/product-quantity-for-woocommerce/assets/icon.svg?rev=2971558',
    'style' => 'margin-top:-5px;',
    'width' => 35,
)
```

## [1.0.3] - 2024-10-14
### Fixed
* Capability to `manage_woocommerce` if woocommerce is enabled, or else `manage_options`.

## [1.0.2] - 2024-10-08
### Changed
* Visibility from settings tabs to hidden by default when accessing the current plugin settings page.
* Default `page_title` param set from `menu_title` param when using the `move_wc_settings_tab_to_wpfactory_menu()` method.

### Added
* Flag `$show_current_plugin_tab` on `WC_Settings_Menu_Item_Swapper:hide_wc_settings_tabs()` to show/hide current plugin tab.
* Method `add_submenu_page()`.

## [1.0.1] - 2024-09-19
### Added
* Method `move_wc_settings_tab_to_wpfactory_menu()` with the possibility of adding specific WooCommerce settings tabs as WPFactory submenu items.
* Version property.
* Translation files.

## [1.0.0] - 2024-09-17
### Added
* Initial release.