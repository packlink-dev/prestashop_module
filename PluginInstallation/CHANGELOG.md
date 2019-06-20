# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/).

## [Unreleased](https://github.com/logeecom/pl_prestashop_module/compare/master...dev)

## [v2.0.3](https://github.com/logeecom/pl_prestashop_module/compare/v2.0.3...v2.0.2) - 2019-06-20
### Changed
- Update to latest core v1.2.2

## [v2.0.2](https://github.com/logeecom/pl_prestashop_module/compare/v2.0.2...v2.0.1) - 2019-06-01
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

## [v2.0.1](https://github.com/logeecom/pl_prestashop_module/compare/v2.0.1...v2.0.0) - 2019-05-29
### Changed
- Updated to the latest core changes
- Shipment labels are now fetched from Packlink only when order does not have labels set 
and shipment status is in one of:
    * READY_TO_PRINT
    * READY_FOR_COLLECTION
    * IN_TRANSIT
    * DELIVERED

## [v2.0.0](https://github.com/logeecom/pl_prestashop_module/tree/v2.0.0) - 2019-03-11
- First stable release of the new module
