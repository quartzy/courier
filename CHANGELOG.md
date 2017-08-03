# Change Log

## 0.1.1 - 2017-08-03

### Fixed

* Fixed the SparkPostCourier to handle templated sender information (e.g. `{{fromEmail}}`) correctly without throwing validation errors during the process of sending templated content with an attachment.

### Dependencies

* Updated php-email to 0.2.0
