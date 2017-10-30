# Change Log

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
