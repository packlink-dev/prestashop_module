#!/bin/bash

# Cleanup any leftovers
rm -f ./packlink.zip
rm -f ./packlink

# Create deployment source
echo -e "\e[32mSTEP 1:\e[39m Copying plugin source..."
mkdir packlink
cp -r ./src/* packlink
rm -rf packlink/lib
rm -rf packlink/tests
rm -rf packlink/phpunit.xml
rm -rf packlink/config.xml
rm -rf packlink/vendor
rm -rf packlink/deploy.sh

# Ensure proper composer dependencies
echo -e "\e[32mSTEP 2:\e[39m Installing composer dependencies..."
composer install -d "$PWD/packlink" --no-dev -q

# Remove unnecessary files from final release archive
echo -e "\e[32mSTEP 3:\e[39m Removing unnecessary files from final release archive..."
rm -rf packlink/vendor/packlink/integration-core/.git
rm -rf packlink/vendor/packlink/integration-core/.gitignore
rm -rf packlink/vendor/packlink/integration-core/.idea
rm -rf packlink/vendor/packlink/integration-core/tests
rm -rf packlink/vendor/packlink/integration-core/generic_tests
rm -rf packlink/vendor/packlink/integration-core/README.md
rm -rf packlink/vendor/zendframework/zendpdf/.git
rm -rf packlink/vendor/zendframework/zendpdf/tests

# Copy resources
echo -e "\e[32mSTEP 4:\e[39m Copying resources from core to the integration..."
source="$PWD/packlink/vendor/packlink/integration-core/src/BusinessLogic/Resources";
destination="$PWD/packlink/views";
if [ ! -d "$destination/img/carriers" ]; then
  mkdir "$destination/img/carriers"
fi
if [ ! -d "$destination/js/core" ]; then
  mkdir "$destination/js/core"
fi
if [ ! -d "$destination/js/location" ]; then
  mkdir "$destination/js/location"
fi
cp -r ${source}/img/carriers/* ${destination}/img/carriers
cp -r ${source}/js/* ${destination}/js/core
cp -r ${source}/LocationPicker/js/* ${destination}/js/location
cp -r ${source}/LocationPicker/css/* ${destination}/css

# Adding PrestaShop mandatory index.php file to all folders
echo -e "\e[32mSTEP 5:\e[39m Adding PrestaShop mandatory index.php file to all folders..."
php "$PWD/lib/autoindex/index.php" "$PWD/packlink" >/dev/null

# Create plugin archive
echo -e "\e[32mSTEP 6:\e[39m Creating new archive..."
zip -r -q  packlink.zip ./packlink

version="$1"
if [ "$version" = "" ]; then
    version=$(php -r "echo json_decode(file_get_contents('src/composer.json'), true)['version'];")
    if [ "$version" = "" ]; then
        echo "Please enter new plugin version (leave empty to use root folder as destination) [ENTER]:"
        read version
    fi
fi

if [ "$version" != "" ]; then
    if [ ! -d ./PluginInstallation/ ]; then
        mkdir ./PluginInstallation/
    fi
    if [ ! -d ./PluginInstallation/"$version"/ ]; then
        mkdir ./PluginInstallation/"$version"/
    fi

    mv ./packlink.zip ./PluginInstallation/${version}/
    touch "./PluginInstallation/$version/Release notes $version.txt"
    echo -e "\e[32mDONE!\n\e[93mNew release created under: $PWD/PluginInstallation/$version"
else
    echo -e "\e[32mDONE!\n\e[93mNew plugin archive created: $PWD/packlink.zip"
fi

rm -fR ./packlink
