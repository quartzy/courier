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

## Notes for Postmark Templates

Postmark allows users to define template keys in the subject of templated emails. To support this functionality, the courier will pass the `subject` of the `Email` into the template variables with the key `subject`.
