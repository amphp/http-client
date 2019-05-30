#!/usr/bin/env bash

set -e

curl -LS https://pecl.php.net/get/pcov | tar -xz
pushd pcov-*
phpize
./configure --enable-pcov
make
make install
popd
echo "extension=pcov.so" >> "$(php -r 'echo php_ini_loaded_file();')"
