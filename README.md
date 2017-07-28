# Courier

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-circleci]][link-circleci]
[![Coverage Status][ico-codecov]][link-codecov]
[![Style Status][ico-styleci]][link-styleci]
[![Total Downloads][ico-downloads]][link-downloads]

A transactional email sending library built on the idea of third-party email senders, using php-email domain objects and drivers for each sender.

Let's face it, most developers don't want to spend their time writing code that renders email templates. In fact, most don't even want to deal with the templates at all. That is what makes the features of third-party emailing services (a la SendGrid, SparkPost, Postmark, etc), such as reporting, template storing/rendering, retries, etc, so wonderful. These features allow the people that care the most about the emails own them, while developers can focus on other matters. Each service provider has their own way of implementing their API though, making it difficult to switch providers if one starts to let you down.

The goal of this library is to provide the ability to send standardized emails without having to reinvent the wheel each time you want to change third party providers. By leveraging a standardized domain model (php-email) for defining our emails, this library is capable of defining drivers (or "couriers" in our case) that allow the developer to easily switch out service providers without changing any part of their code. 

## Install

### Via Composer

```bash
composer require quartzy/courier
```

## Usage

Each email provider will also have their own dependencies, for example:

```bash
# Send emails with SendGrid
composer require sendgrid/sendgrid
```

Now you just need to create an email and send it:

```php
<?php

use Courier\SendGridCourier;
use PhpEmail\EmailBuilder;
use PhpEmail\Content\SimpleContent;

$key     = getenv('SENDGRID_KEY');
$courier = new SendGridCourier(new \SendGrid($key));

$email = EmailBuilder::email()
    ->withSubject('Welcome!')
    ->withContent(SimpleContent::text('Start your free trial now!!!'))
    ->from('me@test.com')
    ->to('you@yourbusiness.com')
    ->build();

$courier->deliver($email);
```

For details on building the email objects, see the [Php Email](https://github.com/quartzy/php-email).


### Supported Service Providers

1. SendGrid (using v3 Web API)
1. SparkPost
1. Postmark

#### SendGrid

The SendGrid courier supports both templated and simple emails. To use the SendGrid courier, you will need an API key with the following permissions:

* `Mail Send - Full Access`

You will also need to include `sendgrid/sendgrid` into your dependencies. You can then make a SendGrid courier like so:

```php
<?php

use Courier\SendGridCourier;

$courier = new SendGridCourier(new \SendGrid("mysendgridkey"));
```

#### SparkPost

The SparkPost courier supports both templated and simple emails. To use the SparkPost courier, you will need an API key with the following permissions:

* `Transmissions: Read/Write`
* `Templates: Read-only`

You should follow the steps documented in the [SparkPost PHP](https://github.com/SparkPost/php-sparkpost) project for details on how to build a SparkPost client and pass it into a `SparkPostCourier`:

```php
<?php

use Courier\SparkPostCourier;
use GuzzleHttp\Client;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\EmailBuilder;
use SparkPost\SparkPost;

new Client();

$courier = new SparkPostCourier(
    new SparkPost(new GuzzleAdapter(new Client()), ['key'=>'YOUR_API_KEY'])
);

$email = EmailBuilder::email()
    ->from('test@mybiz.com')
    ->to('loyal.customer@email.com')
    ->replyTo('test@mybiz.com', 'Your Sales Rep')
    ->withSubject('Welcome!')
    ->withContent(new TemplatedContent('my_email', ['testKey' => 'value']))
    ->build();

$courier->deliver($email);
```

##### Notes for SparkPost Templates

SparkPost allows users to define templated keys in the from, reply to, and subject fields along with the body. In order to make your courier work as expected, the library will automatically created the following template values based on the properties of the `Email`:

* `fromEmail`
* `fromDomain`
* `fromName`
* `replyTo`
* `subject`

These will be added to the template data already defined in the `TemplatedContent` assuming the keys are not already set manually.

#### Postmark

The Postmark courier supports both templated and simple emails.

To create a Postmark courier, you should follow the steps documented in the [Postmark PHP](https://github.com/wildbit/postmark-php/wiki/Getting-Started) docs to create a client and pass it into the `PostmarkCourier`.

```php
<?php

use Courier\PostmarkCourier;
use GuzzleHttp\Client;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\EmailBuilder;
use Postmark\PostmarkClient;

new Client();

$courier = new PostmarkCourier(new PostmarkClient('MY_KEY'));
        
$email = EmailBuilder::email()
    ->from('test@mybiz.com')
    ->to('loyal.customer@email.com')
    ->replyTo('test@mybiz.com', 'Your Sales Rep')
    ->withSubject('Welcome!')
    ->withContent(new TemplatedContent('my_email', ['testKey' => 'value']))
    ->build();

$courier->deliver($email);
```

##### Notes for Postmark Templates

Postmark allows users to define template keys in the subject of templated emails. To support this functionality, the courier will pass the `subject` of the `Email` into the template variables with the key `subject`.

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
[ico-circleci]: https://img.shields.io/circleci/project/github/quartzy/courier/master.svg?style=flat-square
[ico-codecov]: https://img.shields.io/codecov/c/github/quartzy/courier.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/98693280/shield
[ico-downloads]: https://img.shields.io/packagist/dt/quartzy/courier.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/quartzy/courier
[link-circleci]: https://circleci.com/gh/quartzy/courier/tree/master
[link-codecov]: http://codecov.io/github/quartzy/courier?branch=master
[link-styleci]: https://styleci.io/repos/98693280
[link-downloads]: https://packagist.org/packages/quartzy/courier
[link-contributors]: ../../contributors
