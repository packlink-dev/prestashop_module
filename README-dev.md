![Packlink logo](https://pro.packlink.es/public-assets/common/images/icons/packlink.svg)

# Packlink PrestaShop plugin

## Development Guidelines

It might help in working with PHPStorm to add PrestaShop Autocomplete as an external library. 
Link to the repository: https://github.com/julienbourdeau/PhpStorm-PrestaShop-Autocomplete

## Package deployment

- Set proper plugin version in `composer.json` and in `packlink.php`
- Run `./deploy.sh` in project's root directory. Command will output results of the deployment operation.
- Write release notes in file located in deployment directory for current version.
- Run package validation at https://validator.prestashop.com/
