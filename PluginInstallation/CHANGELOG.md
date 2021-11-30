# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [3.2.2](https://github.com/packlink-dev/prestashop_module/compare/v3.2.1...v3.2.2)
### Changed
- Updated the mechanism for fetching controller URLs on the frontend views.

## [3.2.1](https://github.com/packlink-dev/prestashop_module/compare/v3.2.0...v3.2.1)
### Changed
- Updated to the new shipping statuses and sending custom shipment reference to the Packlink API.

## [3.2.0](https://github.com/packlink-dev/prestashop_module/compare/v3.1.3...v3.2.0)
### Changed
- Updated to the module white-label changes.
- Updated to the multi-currency changes.

## [3.1.3](https://github.com/packlink-dev/prestashop_module/compare/v3.1.2...v3.1.3)
### Changed
- Fix shipping labels printing in PrestaShop 1.6.

## [3.1.2](https://github.com/packlink-dev/prestashop_module/compare/v3.1.1...v3.1.2)
### Changed
- Changed the extended template for hidden fields on the order overview page.
### Added
- Added tax configuration to services.

## [3.1.1](https://github.com/packlink-dev/prestashop_module/compare/v3.1.0...v3.1.1)
### Added
- Added a migration script that converts all string values within the default parcel to number.

## [3.1.0](https://github.com/packlink-dev/prestashop_module/compare/v3.0.4...v3.1.0)
### Added
- Added compatibility with PrestaShop version 1.7.7.
- Added postal code transformer service that transforms postal code into supported postal code format for GB, NL, US and PT countries.
- Added new warehouse countries.

## [v3.0.4](https://github.com/packlink-dev/prestashop_module/compare/v3.0.2...v3.0.4) - 2020-11-10
### Changed
- Update to the latest core version 3.0.6.
- Fix issue with image URL in update script for version 2.3.0.

## [v3.0.2](https://github.com/packlink-dev/prestashop_module/compare/v3.0.1...v3.0.2) - 2020-10-21
### Changed
- Update to the latest core version 3.0.4.

## [v3.0.1](https://github.com/packlink-dev/prestashop_module/compare/v3.0.0...v3.0.1) - 2020-10-08
### Changed
- Fix packlink-build script.
- Fix drop-off button style on PrestaShop 1.6.

## [v3.0.0](https://github.com/packlink-dev/prestashop_module/compare/v2.2.7...v3.0.0) - 2020-09-10
### Changed
- New design and new pricing policy.

## [v2.2.7](https://github.com/packlink-dev/prestashop_module/compare/v2.2.6...v2.2.7) - 2020-08-26
### Changed
- Fix issue with order update in PS 1.6 when advanced stock management is enabled.

## [v2.2.6](https://github.com/packlink-dev/prestashop_module/compare/v2.2.5...v2.2.6) - 2020-08-25
### Changed
- Fixed issue with migration script.

## [v2.2.5](https://github.com/packlink-dev/prestashop_module/compare/v2.2.4...v2.2.5) - 2020-07-14
### Changed
- Fixed issue with the variant weight handling.

## [v2.2.4](https://github.com/packlink-dev/prestashop_module/compare/v2.2.3...v2.2.4) - 2020-06-29
### Added
- Added Hungary to the list of supported countries.

### Changed
- Fixed drop-off address creation.

## [v2.2.3](https://github.com/packlink-dev/prestashop_module/compare/v2.2.2...v2.2.3) - 2020-06-11
### Added
- Added "Send with Packlink" button on order overview page.

## [v2.2.2](https://github.com/packlink-dev/prestashop_module/compare/v2.2.1...v2.2.2) - 2020-06-02
### Added
- Compatibility with one page checkout plugin.

## [v2.2.1](https://github.com/packlink-dev/prestashop_module/compare/v2.2.0...v2.2.1) - 2020-04-29
### Changed
- Fixed bug in selecting carrier services
- Updated to the latest Core

## [v2.2.0](https://github.com/packlink-dev/prestashop_module/compare/v2.1.5...v2.2.0) - 2020-04-03
### Changed
- Updated to the Packlink Integrations Core v2.0.0 (system improvements and code optimization).

### Added
- Added task cleanup.
- Packlink now supports more countries.

## [v2.1.5](https://github.com/packlink-dev/prestashop_module/compare/v2.1.4...v2.1.5) - 2020-01-10
### Changed
- Fix minor bugs and warnings

## [v2.1.4](https://github.com/packlink-dev/prestashop_module/compare/v2.1.3...v2.1.4) - 2020-01-10
### Changed
- Fix displaying selected tax class in shipping service configuration.
- Fix duplicated entity error in PrestaShop log.

## [v2.1.3](https://github.com/packlink-dev/prestashop_module/compare/v2.1.2...v2.1.3) - 2019-11-28
### Changed
- Skip handling drop-off location for non-packlink services.
- Fix language selection in the checkout process
- Update to the latest core v1.5.1

## [v2.1.2](https://github.com/packlink-dev/prestashop_module/compare/v2.1.1...v2.1.2) - 2019-11-18
### Added
- Drop-off selection on order confirm page.

### Changed
- Removed array_column usages from code.
- Fixed bugs in BaseRepository discovered by the new test suite.
- Shipping cost calculator now takes in consideration specific shipping costs set on product level.
- The lowest boundary in fixed price policies (by weight and by price) can be higher than zero. 
This allows users to disable a shipping method for orders below the set limit.

## [v2.1.1](https://github.com/packlink-dev/prestashop_module/compare/v2.1.0...v2.1.1) - 2019-10-28 
### Changed
- Fixed class auto-loader.

## [v2.1.0](https://github.com/packlink-dev/prestashop_module/compare/v2.0.4...v2.1.0) - 2019-10-15 
### Added
- Auto-test and auto-configuration features.

### Changed
- Update to latest core v1.4.*
- Fixed sending full address 
- Fixed using first and last name for drop-off address from shipping address instead of the customer.
- Fixed a case when old reference exists and order page was throwing order not found exception. 

## [v2.0.4](https://github.com/packlink-dev/prestashop_module/compare/v2.0.3...v2.0.4) - 2019-07-11
### Changed
- Update to latest core v1.3.0
- Fixed override mechanism and disabled adding overrides when it already exist by other module.
- Added handing of free shipping preferences.

## [v2.0.3](https://github.com/packlink-dev/prestashop_module/compare/v2.0.2...v2.0.3) - 2019-07-01
First marketplace release of the new version.

### Changed
- Update to latest core v1.2.2

## [v2.0.2](https://github.com/packlink-dev/prestashop_module/compare/v2.0.1...v2.0.2) - 2019-06-01
### Changed
- Updated to the latest core changes
- Module now supports sending analytics events
- Fix the upgrade process for overrides. Module now handles properly overridden 
PrestaShop files - if other module did the override before Packlink module, it will 
be handled gracefully - Packlink overrides will not be installed. Before this update,
PrestaShop was giving an error message and module could not be activated.
- Moved code for package shipping cost calculation to a separate class `PackageCostCalculator`
- Removed licence header from all files. This is now maintained in the deploy process.
- Fixed bug in adding bulk print action.

## [v2.0.1](https://github.com/packlink-dev/prestashop_module/compare/v2.0.0...v2.0.1) - 2019-05-29
### Changed
- Updated to the latest core changes
- Shipment labels are now fetched from Packlink only when order does not have labels set 
and shipment status is in one of:
    * READY_TO_PRINT
    * READY_FOR_COLLECTION
    * IN_TRANSIT
    * DELIVERED

## [v2.0.0](https://github.com/packlink-dev/prestashop_module/tree/v2.0.0) - 2019-03-11
- First stable release of the new module
