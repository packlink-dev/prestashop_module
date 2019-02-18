outfile = packlink.zip
plugin_dir = $(shell pwd)

$(outfile):
	rm -rf packlink
	mkdir packlink
	cp -r ./src/* packlink
	rm -rf packlink/tests
	rm -rf packlink/phpunit.xml
	rm -rf packlink/config.xml
	rm -rf packlink/vendor
	composer install -d $(plugin_dir)/packlink --no-dev
	rm -rf packlink/vendor/packlink/integration-core/.git
	rm -rf packlink/vendor/packlink/integration-core/.gitignore
	rm -rf packlink/vendor/packlink/integration-core/.idea
	rm -rf packlink/vendor/packlink/integration-core/tests
	rm -rf packlink/vendor/packlink/integration-core/generic_tests
	rm -rf packlink/vendor/packlink/integration-core/README.md
	rm -rf packlink/vendor/itbz/fpdf/tutorial
	php "$(plugin_dir)/lib/autoindex/index.php" "$(plugin_dir)/packlink"
	zip -r -q  $(outfile) ./packlink/*
	rm -rf packlink
