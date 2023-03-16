#!/bin/bash

# 7.4 and composer latest

# abort on error
set -e

echo "composer validate"
Build/Scripts/runTests.sh -s composerValidate

echo "composer install"
composer install

echo "cgl"
rm -f .php-cs-fixer.cache
Build/Scripts/runTests.sh -s cgl -n

echo "lint"
Build/Scripts/runTests.sh -s lint

echo "phpstan"
Build/Scripts/runTests.sh -s phpstan

echo "Unit tests"
Build/Scripts/runTests.sh -s unit -v

echo "functional tests"
Build/Scripts/runTests.sh -d mariadb -s functional
Build/Scripts/runTests.sh -d mysql -s functional
Build/Scripts/runTests.sh -d postgresql -s functional

echo "done"
