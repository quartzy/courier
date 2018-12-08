# Courier

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-travisci]][link-travisci]
[![Coverage Status][ico-codecov]][link-codecov]
[![Style Status][ico-styleci]][link-styleci]
[![Scrutinizer Code Quality][ico-scrutinizer]][link-scrutinizer]

A library to send transactional emails using domain objects and concise
interfaces.

Check out the [documentation](https://quartzy.github.io/courier/) for more details on
how to use Courier!

This library provides an interface to send standardized emails using third-party 
SaaS SMTP provides, like SparkPost and Postmark. By leveraging a [standardized domain
model](https://github.com/quartzy/php-email) for defining emails, Courier is
capable of defining drivers (or "couriers" in our case) that allow the developer
to easily switch out how they send their emails without changing any part of
their code that builds and delivers the email.

## Install

### Via Composer

```bash
composer require quartzy/courier
```

## Usage

Each email provider will also have their own courier dependency:

```bash
# Send emails with Sparkpost
composer require quartzy/courier-sparkpost
```

Now you just need to create an email and send it:

```php
<?php

use Courier\Sparkpost\SparkpostCourier;
use GuzzleHttp\Client;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use PhpEmail\EmailBuilder;
use PhpEmail\Content\SimpleContent;
use SparkPost\SparkPost;

$courier = new SparkPostCourier(
    new SparkPost(new GuzzleAdapter(new Client()), ['key'=>'YOUR_API_KEY'])
);

$email = EmailBuilder::email()
    ->withSubject('Welcome!')
    ->withContent(SimpleContent::text('Start your free trial now!!!'))
    ->from('me@test.com')
    ->to('you@yourbusiness.com')
    ->build();

$courier->deliver($email);
```

For details on building the email objects, see [Php Email](https://github.com/quartzy/php-email).

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email [opensource@quartzy.com](mailto:opensource@quartzy.com) instead of using the issue tracker.

## Credits

- [Chris Muthig](https://github.com/camuthig)
- [All Contributors][link-contributors]


## License

The Apache License, v2.0. Please see [License File](LICENSE) for more information.

[ico-version]: https://img.shields.io/packagist/v/quartzy/courier.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-Apache%202.0-brightgreen.svg?style=flat-square
[ico-travisci]: https://img.shields.io/travis/quartzy/courier.svg?style=flat-square
[ico-codecov]: https://img.shields.io/scrutinizer/coverage/g/quartzy/courier.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/98693280/shield
[ico-scrutinizer]: https://img.shields.io/scrutinizer/g/quartzy/courier.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/quartzy/courier.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/quartzy/courier
[link-travisci]: https://travis-ci.org/quartzy/courier
[link-codecov]: https://scrutinizer-ci.com/g/quartzy/courier
[link-styleci]: https://styleci.io/repos/98693280
[link-scrutinizer]: https://scrutinizer-ci.com/g/quartzy/courier
[link-downloads]: https://packagist.org/packages/quartzy/courier
[link-contributors]: ../../contributors
