The SparkPost courier supports both templated and simple emails. To use the
SparkPost courier, you will need an API key with the following permissions:

* `Transmissions: Read/Write`
* `Templates: Read-only`

You should follow the steps documented in the [SparkPost
PHP](https://github.com/SparkPost/php-sparkpost) project for details on how to
build a SparkPost client and pass it into a `SparkPostCourier`:

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

## Notes on Headers

At this time, custom headers are only sent on `SimpleContent` emails or
`TemplatedContent` emails that include an attachment.

SparkPost does not currently support sending headers on stored template emails.
There is currently not a known release date for when this might get fixed, but
Courier is ready whenever it does and already sends the `headers` value with all
headers defined on the `Email`.

## Notes for SparkPost Templates

SparkPost allows users to define templated keys in the from, reply to, and
subject fields along with the body. In order to make your courier work as
expected, the library will automatically created the following template values
based on the properties of the `Email`:

* `fromEmail`
* `fromDomain`
* `fromName`
* `replyTo`
* `subject`
* `ccHeader`

These will be added to the template data already defined in the
`TemplatedContent` assuming the keys are not already set manually.

## Temporary fix for correctly displaying CC header

As documented in this
[post](https://www.sparkpost.com/docs/faq/cc-bcc-with-rest-api/), the SparkPost
API requires sending the `CC` header information in order to properly display
recipients. In the context of inline templates and non-templated emails, setting
this header works fine. However, if sending a standard templated email,
SparkPost's API does not respect the `CC` header. To work around this, Courier
will set the `ccHeader` variable in the substitution data to what the value
_should_ be. In order to leverage this variable, you will need to update your
template using the API (the header attributes are not available in the web
editor) to include the value. This can be done with a request like:

```javascript
// PUT https://api.sparkpost.com/api/v1/templates/my-template-id

{
  "content": {
    // All of your other content must go here as this PUT will overwrite all other content
    "headers": {
      "CC": "{{ccHeader}}"
    }
  }
}

```
