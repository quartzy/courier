Each courier will have their own dependencies, for example:

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

For details on building the email objects, see the [Php
Email](https://github.com/quartzy/php-email).

## Tracking Emails

Many SaaS email providers return an ID, sort of like a package receipt, on the
API response. If you would like to get the ID for your own auditing, then this
can be done with any `Courier` that implements the `ConfirmingCourier`
interface.

```php
<?php

use Courier\SendGridCourier;
use PhpEmail\EmailBuilder;
use PhpEmail\Content\SimpleContent;

$key     = getenv('SENDGRID_KEY');

// The SendGrid courier implements ConfirmingCourier
$courier = new SendGridCourier(new \SendGrid($key));

$email = EmailBuilder::email()
    ->withSubject('Welcome!')
    ->withContent(SimpleContent::text('Start your free trial now!!!'))
    ->from('me@test.com')
    ->to('you@yourbusiness.com')
    ->build();

$courier->deliver($email);

$receipt = $courier->receiptFor($email);
```
