#!/bin/bash
# check if platform does NOT exist in composer.json
# exit code 0 (ok): platform.php does not exist in config section

composer config --list | grep -E "^\[platform.php\]" >/dev/null 2>/dev/null
if [ $? -eq 0 ];then
    echo "platform.php found"
    exit 1
fi
exit 0
