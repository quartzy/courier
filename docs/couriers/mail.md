The `MailCourier` is primarily built as a drop-in testing courier for local development, using the native `mail`
function and no other dependencies. The courier will deliver `SimpleContent` emails as defined, however, it does not
contain any logic for rendering templates. When delivering templated content, the courier will include a text body
with the ID of the template and the json-encoded template data.

## Install

This package is included in the core `quartzy/courier` and no other dependencies are necessary.

`composer require quartzy/courier`

## Usage

```php
<?php

use Courier\MailCourier;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\EmailBuilder;

$courier = new MailCourier();
        
$email = EmailBuilder::email()
    ->from('test@mybiz.com')
    ->to('loyal.customer@email.com')
    ->replyTo('test@mybiz.com', 'Your Sales Rep')
    ->withSubject('Welcome!')
    ->withContent(new TemplatedContent('my_email', ['testKey' => 'value']))
    ->build();

$courier->deliver($email);
```

## Adding Template Support

The `MailCourier` can be extended to support template rendering of your choice. The `buildTemplatedContent` protected
function in the class is what build the MIME content of the email body. This function can be overwritten to support
a template rendering pattern.
