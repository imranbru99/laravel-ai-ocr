#!/bin/sh
set -eu
laravel_version=${1:-12}
case "$laravel_version" in 10|11|12|13) ;; *) exit 2;; esac
mkdir -p /tmp/ai-ocr-package
cd /tmp/ai-ocr-package
cp -r /source/src /source/config /source/resources /source/routes /source/database /source/tests /source/stubs .
cp /source/composer.json /source/phpunit.xml /source/phpstan.neon /source/pint.json .
composer require --dev "laravel/framework:^${laravel_version}.0" --no-update --no-interaction
security_flag=""
case "$laravel_version" in 10|11) security_flag="--no-security-blocking";; esac
if [ -n "${FILAMENT_VERSION:-}" ]; then composer require --dev "filament/filament:^${FILAMENT_VERSION}.0" --no-update --no-interaction; fi
lowest_flag=""
if [ "${DEPENDENCIES:-highest}" = lowest ]; then lowest_flag="--prefer-lowest"; fi
composer update $lowest_flag --no-interaction --prefer-dist --prefer-stable $security_flag > /tmp/composer-install.log 2>&1 || { cat /tmp/composer-install.log; exit 1; }
vendor/bin/phpunit
if [ "${DEPENDENCIES:-highest}" != lowest ]; then vendor/bin/pint --test; fi
