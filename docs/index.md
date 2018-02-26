[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-travisci]][link-travisci]
[![Coverage Status][ico-coverage]][link-coverage]
[![Style Status][ico-styleci]][link-styleci]
[![Scrutinizer Code Quality][ico-scrutinizer]][link-scrutinizer]

A library to send transactional emails using domain objects and concise
interfaces.

This library provides tools to send standardized emails without having to
reinvent the wheel to use any SMTP tooling, either a SaaS or your own
servers. By leveraging a [standardized domain
model](https://github.com/quartzy/php-email) for defining our emails, Courier is
capable of defining drivers (or "couriers" in our case) that allow the developer
to easily switch out how they send their emails without changing any part of
their code that builds and delivers the email.

[ico-version]: https://img.shields.io/packagist/v/quartzy/courier.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-Apache%202.0-brightgreen.svg?style=flat-square
[ico-travisci]: https://img.shields.io/travis/quartzy/courier.svg?style=flat-square
[ico-coverage]: https://img.shields.io/scrutinizer/coverage/g/quartzy/courier.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/98693280/shield
[ico-scrutinizer]: https://img.shields.io/scrutinizer/g/quartzy/courier.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/quartzy/courier.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/quartzy/courier
[link-travisci]: https://travis-ci.org/quartzy/courier
[link-coverage]: https://scrutinizer-ci.com/g/quartzy/courier
[link-styleci]: https://styleci.io/repos/98693280
[link-scrutinizer]: https://scrutinizer-ci.com/g/quartzy/courier
[link-downloads]: https://packagist.org/packages/quartzy/courier
