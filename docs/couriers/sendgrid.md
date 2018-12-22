## Install

`composer require camuthig/courier-sendgrid`

## Setup

To use the SendGrid courier, you will need an API key with the following permissions:

* `Mail Send - Full Access`

## Usage

The SendGrid courier supports both templated and simple emails.

```php
<?php

use Camuthig\Courier\SendGrid\SendGridCourier;

$courier = new SendGridCourier(new \SendGrid("mysendgridkey"));
```

## Note on Charset

SendGrid does not support adding `; charset="x"` when defining the type of an
attachment, as such, whatever value is defined on the `Email` will be ignored
when preparing the delivery for SendGrid.

## Known Issues

There are some of known issues regarding the SendGrid courier. These are
issues built into using SendGrid specifically and not generally across Courier.

1. The SendGrid courier will behave unexpectedly when sending multiple attachments
in templated emails. It has been noted and verified with SendGrid support that the
templates do not render properly in these cases and should be avoided.
1. Sending an inline email with a CC list and BCC list along with attachments causes
the BCC list to be lost.
1. Sending a BCC recipient list will sporadically not include the BCC list in the
final rendered email.
