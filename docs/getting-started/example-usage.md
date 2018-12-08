Each courier is implemented as an adapter in it's own dependency, so
the first step is to pull the correct courier implementation into your project.

```bash
# Send emails with SendGrid
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

For details on building the email objects, see the [Php
Email](https://github.com/quartzy/php-email).

## Tracking Emails

Many SaaS email providers return an ID, sort of like a package receipt, on the
API response. If you would like to get the ID for your own auditing, then this
can be done with any `Courier` that implements the `ConfirmingCourier`
interface.

```php
<?php

use Courier\Sparkpost\SparkpostCourier;
use GuzzleHttp\Client;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use PhpEmail\EmailBuilder;
use PhpEmail\Content\SimpleContent;
use SparkPost\SparkPost;

// The Sparkpost courier implements ConfirmingCourier
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

$receipt = $courier->receiptFor($email);
```
