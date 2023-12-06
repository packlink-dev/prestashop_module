#!/bin/bash

code_to_add="if (!defined('_PS_VERSION_')) {
    exit;
}"

./scripts/add_ps_version_check_after_namespace.sh "./packlink" "$code_to_add"
./scripts/add_ps_version_check_after_first_line.sh "./packlink/controllers" "$code_to_add"
./scripts/add_ps_version_check_after_first_line.sh "./packlink/override" "$code_to_add"
./scripts/add_ps_version_check_after_first_line.sh "./packlink/translations" "$code_to_add"