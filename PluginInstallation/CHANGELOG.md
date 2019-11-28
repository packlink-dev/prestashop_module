# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [Unreleased](https://github.com/packlink-dev/prestashop_module/compare/master...logeecom:dev)

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
