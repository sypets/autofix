#!/bin/bash

rm -rf .Build
rm -f composer.lock
rm -f .php-cs-fixer.cache
rm -f auth.json
composer config --unset platform.php
composer config --unset platform
