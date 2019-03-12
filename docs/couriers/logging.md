The LoggingCourier is not designed to be used in a production system, but it can act as a drop-in testing implementation,
allowing developers to build content and log it to their local systems, without delivering emails. This can be
helpful in cases where the email might contain important information, like a generated password reset token.

## Install

This package is included in the core `quartzy/courier` and no other dependencies are necessary.

`composer require quartzy/courier`

## Usage

```php
<?php

use Courier\LoggingCourier;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\EmailBuilder;
use Psr\Log\NullLogger;

// Be sure to create your own logger implementation and pass it into the courier.
$courier = new LoggingCourier(new NullLogger());
        
$email = EmailBuilder::email()
    ->from('test@mybiz.com')
    ->to('loyal.customer@email.com')
    ->replyTo('test@mybiz.com', 'Your Sales Rep')
    ->withSubject('Welcome!')
    ->withContent(new TemplatedContent('my_email', ['testKey' => 'value']))
    ->build();

$courier->deliver($email);
```
