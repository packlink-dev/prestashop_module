#!/bin/bash

file_using_html="$1"

if [ -e "$file_using_html" ]; then
    sed -i "s/\.html'/'/g" "$file_using_html"
fi