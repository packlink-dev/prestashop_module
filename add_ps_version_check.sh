#!/bin/bash

code_to_add="if (!defined('_PS_VERSION_')) {
    exit;
}"

path="./src"
find "$path" -type f -name "*.php" | while read -r file; do
    # Add PS version check after namespace line
    awk -v code="$code_to_add" '
    {
        if ($0 ~ /^namespace /) {
            print $0 "\n\n" code;
        } else {
            print $0;
        }
    }' "$file" > temp && mv temp "$file"
done

path="./src/controllers"
find "$path" -type f -name "*.php" | while read -r file; do
    # Add PS version check after first line (<?php line)
    awk -v code="$code_to_add" '
    {
        if ($0 ~ /^<\?php/) {
            print $0 "\n\n" code;
        } else {
            print $0;
        }
    }' "$file" > temp && mv temp "$file"
done

path="./src/override"
find "$path" -type f -name "*.php" | while read -r file; do
    # Add PS version check after first line (<?php line)
    awk -v code="$code_to_add" '
    {
        if ($0 ~ /^<\?php/) {
            print $0 "\n\n" code;
        } else {
            print $0;
        }
    }' "$file" > temp && mv temp "$file"
done