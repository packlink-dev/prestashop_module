#!/bin/bash

# Cleanup any leftovers
rm -f ./packlink.zip
rm -f ./packlink

# Create deployment source
echo "Copying plugin source..."
mkdir packlink
cp -r ./src/* packlink
rm -rf packlink/tests
rm -rf packlink/phpunit.xml
rm -rf packlink/config.xml
rm -rf packlink/vendor
rm -rf packlink/deploy.sh

# Ensure proper composer dependencies
echo "Installing composer dependencies..."
composer install -d "$PWD/packlink" --no-dev

# Remove unnecessary files from final release archive
echo "Removing unnecessary files from final release archive..."
rm -rf packlink/vendor/packlink/integration-core/.git
rm -rf packlink/vendor/packlink/integration-core/.gitignore
rm -rf packlink/vendor/packlink/integration-core/.idea
rm -rf packlink/vendor/packlink/integration-core/tests
rm -rf packlink/vendor/packlink/integration-core/generic_tests
rm -rf packlink/vendor/packlink/integration-core/README.md

php "$PWD/lib/autoindex/index.php" "$PWD/packlink"

# Create plugin archive
echo "Creating new archive..."
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
    echo "New release created under: $PWD/PluginInstallation/$version"
else
    echo "New plugin archive created: $PWD/packlink.zip"
fi

rm -fR ./packlink
