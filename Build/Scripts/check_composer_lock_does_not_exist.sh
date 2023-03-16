#!/bin/bash
# check if composer.lock does not exist
# exit code 0 (ok): platform.php does not exist in config section

if [ -f composer.lock ];then
    exit 1
fi
exit 0
