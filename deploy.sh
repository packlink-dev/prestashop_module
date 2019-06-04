#!/bin/bash

echo
echo -e "\e[48;5;124m ALWAYS RUN UNIT TESTS BEFORE CREATING DEPLOYMENT PACKAGE! \e[0m"
echo
sleep 2

# Cleanup any leftovers
echo -e "\e[32mCleaning up...\e[39m"
rm -f ./packlink.zip
rm -f ./packlink

# Create deployment source
echo -e "\e[32mSTEP 1:\e[39m Copying plugin source..."
mkdir packlink
cp -r ./src/* packlink

# Ensure proper composer dependencies
echo -e "\e[32mSTEP 2:\e[39m Installing composer dependencies..."
cd packlink
rm -rf vendor
composer install --no-dev -q
cd ..

# Remove unnecessary files from final release archive
echo -e "\e[32mSTEP 3:\e[39m Removing unnecessary files from final release archive..."
rm -rf packlink/lib
rm -rf packlink/tests
rm -rf packlink/phpunit.xml
rm -rf packlink/config.xml
rm -rf packlink/deploy.sh
rm -rf packlink/vendor/packlink/integration-core/.git
rm -rf packlink/vendor/packlink/integration-core/.gitignore
rm -rf packlink/vendor/packlink/integration-core/.idea
rm -rf packlink/vendor/packlink/integration-core/tests
rm -rf packlink/vendor/packlink/integration-core/generic_tests
rm -rf packlink/vendor/packlink/integration-core/README.md
rm -rf packlink/vendor/zendframework/zendpdf/.git
rm -rf packlink/vendor/zendframework/zendpdf/tests
# remove resources. They are added in the installation process.
rm -rf packlink/views/js/core
rm -rf packlink/views/js/location

# Adding PrestaShop mandatory index.php file to all folders
echo -e "\e[32mSTEP 5:\e[39m Adding PrestaShop mandatory index.php file to all folders..."
php "$PWD/lib/autoindex/index.php" "$PWD/packlink" >/dev/null

# get plugin version
echo -e "\e[32mSTEP 6:\e[39m Reading module version..."

version="$1"
if [ "$version" = "" ]; then
    version=$(php -r "echo json_decode(file_get_contents('src/composer.json'), true)['version'];")
    if [ "$version" = "" ]; then
        echo "Please enter new plugin version (leave empty to use root folder as destination) [ENTER]:"
        read version
    else
      echo -e "\e[35mVersion read from the composer.json file: $version\e[39m"
    fi
fi

# Create plugin archive
echo -e "\e[32mSTEP 7:\e[39m Creating new archive..."
zip -r -q  packlink.zip ./packlink

if [ "$version" != "" ]; then
    if [ ! -d ./PluginInstallation/ ]; then
        mkdir ./PluginInstallation/
    fi
    if [ ! -d ./PluginInstallation/"$version"/ ]; then
        mkdir ./PluginInstallation/"$version"/
    fi

    mv ./packlink.zip ./PluginInstallation/${version}/
    echo -e "\e[34;5;40mSUCCESS!\e[0m"
    echo -e "\e[93mNew release created under: $PWD/PluginInstallation/$version"
else
    echo -e "\e[40;5;34mSUCCESS!\e[0m"
    echo -e "\e[93mNew plugin archive created: $PWD/packlink.zip"
fi

rm -fR ./packlink
