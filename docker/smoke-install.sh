#!/bin/sh
set -eu
for version in 10 11 12 13; do
  destination="/tmp/ocr-host-$version"
  composer create-project "laravel/laravel:^$version.0" "$destination" --no-install --no-scripts --no-interaction > "/results/install-laravel$version.log" 2>&1
  cd "$destination"
  composer config repositories.ai-ocr path /source >> "/results/install-laravel$version.log" 2>&1
  composer require imrandevbd/laravel-ai-ocr:@dev --no-update --no-interaction >> "/results/install-laravel$version.log" 2>&1
  security_flag=""
  case "$version" in 10|11) security_flag="--no-security-blocking";; esac
  composer update --no-dev --prefer-dist --no-interaction $security_flag >> "/results/install-laravel$version.log" 2>&1
  php artisan ai-ocr:doctor --no-interaction >> "/results/install-laravel$version.log" 2>&1
  php artisan ai-ocr:types --no-interaction >> "/results/install-laravel$version.log" 2>&1
  echo "INSTALL SMOKE PASSED: Laravel $version" >> "/results/install-laravel$version.log"
done
