#!/bin/bash

for html_file in "$1"/*.html; do
    if [ -e "$html_file" ]; then
        novo_ime="${html_file%.html}"
        mv "$html_file" "$novo_ime"
    fi
done

./scripts/change_file_that_use_html.sh "./packlink/packlink.php"
./scripts/change_file_that_use_html.sh "./packlink/vendor/packlink/integration-core/src/DemoUI/src/Views/ACME/index.php"
./scripts/change_file_that_use_html.sh "./packlink/vendor/packlink/integration-core/src/DemoUI/src/Views/PRO/index.php"