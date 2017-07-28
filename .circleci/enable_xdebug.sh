#!/usr/bin/env bash

XDEBUG_SO="$(command find '/usr/local/lib/php/' -name 'xdebug.so' | command head -n 1)"

echo "zend_extension=\"$XDEBUG_SO\"" > /usr/local/etc/php/conf.d/xdebug.ini
