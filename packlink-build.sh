#!/bin/bash
set -e

# Create deployment source
echo "\e[32mSTEP 1:\e[0m Copying plugin source..."
mkdir packlink
cp -r ./src/* packlink

# Ensure proper composer dependencies
echo "\e[32mSTEP 2:\e[0m Installing composer dependencies..."
cd packlink
# remove resources that will be copied from the core in the post-install script
find views/img/carriers/* ! -name carrier.jpg -delete
rm -rf views/js/core
rm -rf views/js/location
rm -rf vendor
# add version to artifact
echo "$1" >release.version

composer install --no-dev
cd .. || exit

# Remove unnecessary files from final release archive
echo "\e[32mSTEP 3:\e[0m Removing unnecessary files from final release archive..."
rm -rf packlink/lib
rm -rf packlink/tests
rm -rf packlink/phpunit.xml
rm -rf packlink/config.xml
rm -rf packlink/deploy.sh
rm -rf packlink/views/css/.gitignore
rm -rf packlink/views/img/carriers/.gitignore
rm -rf packlink/vendor/packlink/integration-core/.git
rm -rf packlink/vendor/packlink/integration-core/.gitignore
rm -rf packlink/vendor/packlink/integration-core/.idea
rm -rf packlink/vendor/packlink/integration-core/tests
rm -rf packlink/vendor/packlink/integration-core/generic_tests
rm -rf packlink/vendor/packlink/integration-core/README.md
rm -rf packlink/vendor/setasign/fpdf/tutorial/

echo "\e[32mSTEP 4:\e[0m Adding PrestaShop mandatory licence header to files..."
php "$PWD/src/lib/autoLicence.php" "$PWD/packlink"

# Adding PrestaShop mandatory index.php file to all folders
echo "\e[32mSTEP 5:\e[0m Adding PrestaShop mandatory index.php file to all folders..."
php "$PWD/lib/autoindex/index.php" "$PWD/packlink" >/dev/null

# Create plugin archive
echo "\e[32mSTEP 6:\e[0m Creating new archive... artifact.zip"
zip -r -q artifact.zip ./packlink
