---
layout: default
title: Courier
---

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]][link-license]
[![Build Status][ico-circleci]][link-circleci]
[![Coverage Status][ico-codecov]][link-codecov]
[![Style Status][ico-styleci]][link-styleci]
[![Total Downloads][ico-downloads]][link-downloads]

A transactional email sending library built on the idea of third-party email
senders, using php-email domain objects and drivers for each sender.

Let's face it, most developers don't want to spend their time writing code that
renders email templates. In fact, most don't even want to deal with the
templates at all. That is what makes the features of third-party emailing
services (a la SendGrid, SparkPost, Postmark, etc), such as reporting, template
storing/rendering, retries, etc, so wonderful. These features allow the people
that care the most about the emails own them, while developers can focus on
other matters. Each service provider has their own way of implementing their API
though, making it difficult to switch providers if one starts to let you down.

The goal of this library is to provide the ability to send standardized emails
without having to reinvent the wheel each time you want to change third party
providers. By leveraging a standardized domain model (php-email) for defining
our emails, this library is capable of defining drivers (or "couriers" in our
case) that allow the developer to easily switch out service providers without
changing any part of their code.

[ico-version]: https://img.shields.io/packagist/v/quartzy/courier.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-Apache%202.0-brightgreen.svg?style=flat-square
[ico-circleci]: https://img.shields.io/circleci/project/github/quartzy/courier/master.svg?style=flat-square
[ico-codecov]: https://img.shields.io/codecov/c/github/quartzy/courier.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/98693280/shield
[ico-downloads]: https://img.shields.io/packagist/dt/quartzy/courier.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/quartzy/courier
[link-license]: https://github.com/quartzy/courier/blob/master/LICENSE.md
[link-circleci]: https://circleci.com/gh/quartzy/courier/tree/master
[link-codecov]: http://codecov.io/github/quartzy/courier?branch=master
[link-styleci]: https://styleci.io/repos/98693280
[link-downloads]: https://packagist.org/packages/quartzy/courier
[link-license]: https://github.com/quartzy/courier/blob/master/LICENSE
