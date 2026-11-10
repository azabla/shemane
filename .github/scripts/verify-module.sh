#!/bin/sh
# Installs one module with Composer into this checkout and proves it works in the host.
# Usage: verify-module.sh <module name> <composer package> [path repository]
set -eu

name=$1
package=$2
repository=${3:-}

if [ -n "$repository" ]; then
    composer config repositories.under-test "{\"type\":\"path\",\"url\":\"$repository\",\"options\":{\"symlink\":false}}"
fi

composer require "$package:*" --no-interaction --no-progress

test -f "modules/$name/module.json" || { echo "$package did not install to modules/$name"; exit 1; }

export MODULES_ENABLED="$name" MODULE_UNDER_TEST="$name"

php artisan module:validate --strict
php artisan module:list
php artisan test tests/Composition

if [ -d "modules/$name/tests" ]; then
    php artisan test "modules/$name/tests"
fi
