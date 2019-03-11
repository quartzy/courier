## Install

`composer require camuthig/courier-mailjet`


## Usage

Visit [Mailjet](https://app.mailjet.com/transactional) to retrieve your API key and secret.

```php
<?php

use Camuthig\Courier\Mailjet\MailjetCourier;
use Mailjet\Client;
use PhpEmail\EmailBuilder;
use PhpEmail\Content\SimpleContent;

$client = new Client(getenv('MAILJET_API_KEY'), getenv('MAILJET_API_SECRET'));
$courier = new MailjetCourier($client);

$email = EmailBuilder::email()
            ->to('to@test.com')
            ->from('from@test.com')
            ->withSubject('Great Email!')
            ->withContent(SimpleContent::text('Text')->addHtml('HTML'))
            ->build();

$courier->deliver($email);
```

### Receipt ID

Mailjet returns a unique ID for each receipient of a message. However, the Courier receipt
API expects a single ID to be returned for each email delivery. To work around this, the
receipt ID returned by this implementation is actually added to the messages as the Custom ID
property.