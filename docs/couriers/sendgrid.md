The SendGrid courier supports both templated and simple emails. To use the
SendGrid courier, you will need an API key with the following permissions:

* `Mail Send - Full Access`

You will also need to include `sendgrid/sendgrid` into your dependencies. You
can then make a SendGrid courier like so:

```php
<?php

use Courier\SendGridCourier;

$courier = new SendGridCourier(new \SendGrid("mysendgridkey"));
```

## Note on Charset

SendGrid does not support adding `; charset="x"` when defining the type of an
attachment, as such, whatever value is defined on the `Email` will be ignored
when preparing the delivery for SendGrid.
