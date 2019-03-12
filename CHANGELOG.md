# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project attempts to follow [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## Unreleased

### Removed

* Removed `MailCourier` class

### Added

* Added `LoggingCourier` class for local testing purposes

## 0.6.0

### Removed

* Dropped `EmptyContent` class
* Drop support for PHP Email ^0.5.1

### Added

* Added `MailCourier` implementation using native `mail` function

### Changed

* Add support for PHP Email ^0.6.0

## 0.5.0 - 2018-12-09

### Removed

* Removed `Courier\SparkpostCourier` in favor of `Courier\Sparkpost\SparkpostCourier` see https://github.com/quartzy/courier-sparkpost
* Removed `Courier\PostmarkCourier` in favor of `Camuthig\Courier\Postmark\PostmarkCourier` see https://github.com/camuthig/courier-postmark
* Removed `Courier\SendGridCourier` in favor of `Camuthig\Courier\SendGrid\SendGridCourier` see https://github.com/camuthig/courier-sendgrid

## 0.4.1 - 2018-12-08

### Deprecated

* Deprecated `Courier\SparkpostCourier` in favor of `Courier\Sparkpost\SparkpostCourier` see https://github.com/quartzy/courier-sparkpost
* Deprecated `Courier\PostmarkCourier` in favor of `Camuthig\Courier\Postmark\PostmarkCourier` see https://github.com/camuthig/courier-postmark
* Deprecated `Courier\SendGridCourier` in favor of `Camuthig\Courier\SendGrid\SendGridCourier` see https://github.com/camuthig/courier-sendgrid

## 0.4.0 - 2018-10-27

### Added

* Send custom headers defined on the `Email` (caveat on SparkPost templated emails in readme).
* Add inline attachments defined on the `Email`
* Add charset to attachments for all couriers that support it
* Added integration tests for Postmark, SendGrid and SparkPost

### Changed

* Upgrade to [php-email](https://github.com/quartzy/php-email) version 0.5.0.
* Update use of `SimpleContent` to handle BC incompatible changes from php-email.

## 0.3.0 - 2017-12-13

### Changed

#### Dependencies

* Updated php-email to 0.4.0

### Added

* Created `ConfirmingCourier` interface for `Courier` implementations that return an ID for tracking

## 0.2.0 - 2017-11-08

### Changed

#### General

* Added argument and return type hints
* Added strict types

#### Dependencies

* Support was dropped for 5.6 and 7.0. Project only supports 7.1+.
* Updated php-email to 0.3.0

## 0.1.5 - 2017-10-30

### Fixed

* Avoid empty content headers to SparkPost

## 0.1.4 - 2017-10-05

### Fixed

* Only send the CC header with `SparkPostCourier` if it isn't empty

## 0.1.3 - 2017-09-22

### Fixed

* Send recipients with correct format in `SparkPostCourier` based on this [FAQ](https://www.sparkpost.com/docs/faq/cc-bcc-with-rest-api/)

## 0.1.2 - 2017-08-15

### Fixed

* Made sure `SparkPostCourier` implemented the `Courier` interface

## 0.1.1 - 2017-08-03

### Fixed

* Fixed the SparkPostCourier to handle templated sender information (e.g. `{{fromEmail}}`) correctly without throwing validation errors during the process of sending templated content with an attachment.

### Dependencies

* Updated php-email to 0.2.0
