#!/usr/bin/env bash

mkdir reports
./vendor/bin/phpunit --log-junit "reports/junit-php$1.xml" --coverage-clover="reports/coverage-php$1.xml"
