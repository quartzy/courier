<?php

declare(strict_types=1);

namespace Courier;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use Exception;
use PhpEmail\Address;
use PhpEmail\Content;
use PhpEmail\Email;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SendGrid;

/**
 * A courier implementation using the SendGrid v3 web API and sendgrid-php library to send emails.
 *
 * While SendGrid supports sending batches of emails using "personalizations", this does not fit completely into the
 * paradigm of transactional emails. For this reason, this courier only creates a single personalization with multiple
 * recipients.
 */
class SendGridCourier implements Courier
{
    /**
     * @var SendGrid
     */
    private $sendGrid;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SendGrid        $sendGrid
     * @param LoggerInterface $logger
     */
    public function __construct(SendGrid $sendGrid, LoggerInterface $logger = null)
    {
        $this->sendGrid = $sendGrid;
        $this->logger   = $logger ?: new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function deliver(Email $email)
    {
        if (!$this->supportsContent($email->getContent())) {
            throw new UnsupportedContentException($email->getContent());
        }

        $mail = $this->prepareEmail($email);

        switch (true) {
            case $email->getContent() instanceof Content\EmptyContent:
                $this->sendEmptyContent($mail);
                break;
            case $email->getContent() instanceof Content\Contracts\SimpleContent:
                $this->sendSimpleContent($mail, $email->getContent());
                break;
            case $email->getContent() instanceof Content\Contracts\TemplatedContent:
                $this->sendTemplatedContent($mail, $email->getContent());
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function supportedContent()
    {
        return [
            Content\EmptyContent::class,
            Content\Contracts\SimpleContent::class,
            Content\Contracts\TemplatedContent::class,
        ];
    }

    /**
     * Determine if the content is supported by this courier.
     *
     * @param Content $content
     *
     * @return bool
     */
    protected function supportsContent(Content $content)
    {
        foreach ($this->supportedContent() as $contentType) {
            if ($content instanceof $contentType) {
                return true;
            }
        }

        return false;
    }

    /**
     * SendGrid does not support having the same, case-insensitive email address in recipient blocks. This
     * function allows for filtering out non-distinct email addresses.
     *
     * @param array $emails
     * @param array $existing
     *
     * @return array
     */
    protected function distinctAddresses(array $emails, array $existing = [])
    {
        $insensitiveAddresses = [];

        $emails = array_filter($emails, function (Address $address) use (&$insensitiveAddresses) {
            if (!in_array(strtolower($address->getEmail()), $insensitiveAddresses)) {
                $insensitiveAddresses[] = strtolower($address->getEmail());

                return true;
            }

            return false;
        });

        $existingEmails = array_map(function (Address $address) {
            return $address->getEmail();
        }, $existing);

        return array_filter($emails, function (Address $address) use ($existingEmails) {
            return !in_array($address->getEmail(), $existingEmails);
        });
    }

    /**
     * @param Email $email
     *
     * @return SendGrid\Mail
     */
    protected function prepareEmail(Email $email)
    {
        $message = new SendGrid\Mail();

        $message->setSubject($email->getSubject());
        $message->setFrom(new SendGrid\Email($email->getFrom()->getName(), $email->getFrom()->getEmail()));

        $personalization = new SendGrid\Personalization();

        foreach ($this->distinctAddresses($email->getToRecipients()) as $recipient) {
            $personalization->addTo(new SendGrid\Email($recipient->getName(), $recipient->getEmail()));
        }

        $existingAddresses = $email->getToRecipients();
        foreach ($this->distinctAddresses($email->getCcRecipients(), $existingAddresses) as $recipient) {
            $personalization->addCc(new SendGrid\Email($recipient->getName(), $recipient->getEmail()));
        }

        $existingAddresses = array_merge($email->getToRecipients(), $email->getCcRecipients());
        foreach ($this->distinctAddresses($email->getBccRecipients(), $existingAddresses) as $recipient) {
            $personalization->addBcc(new SendGrid\Email($recipient->getName(), $recipient->getEmail()));
        }

        $message->addPersonalization($personalization);

        if (!empty($email->getReplyTos())) {
            // The SendGrid API only supports one "Reply To" :(
            $replyTos = $email->getReplyTos();
            $first    = reset($replyTos);
            $replyTo  = new SendGrid\Email($first->getName(), $first->getEmail());

            $message->setReplyTo($replyTo);
        }

        if (!empty($email->getAttachments())) {
            foreach ($email->getAttachments() as $file) {
                $attachment = new SendGrid\Attachment();
                $attachment->setFilename($file->getName());
                $attachment->setContent($file->getBase64Content());

                $message->addAttachment($attachment);
            }
        }

        return $message;
    }

    /**
     * @param SendGrid\Mail $email
     *
     * @return void
     */
    protected function send(SendGrid\Mail $email)
    {
        try {
            /** @var SendGrid\Response $response */
            $response = $this->sendGrid->client->mail()->send()->post($email);

            if ($response->statusCode() >= 400) {
                $this->logger->error(
                    'Received status {code} from SendGrid with body: {body}',
                    [
                        'code' => $response->statusCode(),
                        'body' => $response->body(),
                    ]
                );

                throw new TransmissionException($response->statusCode());
            }
        } catch (Exception $e) {
            throw new TransmissionException($e->getCode(), $e);
        }
    }

    /**
     * @param SendGrid\Mail $email
     *
     * @return void
     */
    protected function sendEmptyContent(SendGrid\Mail $email)
    {
        $email->addContent(new SendGrid\Content('text/plain', ''));

        $this->send($email);
    }

    /**
     * @param SendGrid\Mail                   $email
     * @param Content\Contracts\SimpleContent $content
     *
     * @return void
     */
    protected function sendSimpleContent(SendGrid\Mail $email, Content\Contracts\SimpleContent $content)
    {
        if ($content->getHtml()) {
            $email->addContent(new SendGrid\Content('text/html', $content->getHtml()));
        } elseif ($content->getText()) {
            $email->addContent(new SendGrid\Content('text/plain', $content->getText()));
        } else {
            $email->addContent(new SendGrid\Content('text/plain', ''));
        }

        $this->send($email);
    }

    /**
     * @param SendGrid\Mail                      $email
     * @param Content\Contracts\TemplatedContent $content
     *
     * @return void
     */
    protected function sendTemplatedContent(SendGrid\Mail $email, Content\Contracts\TemplatedContent $content)
    {
        foreach ($content->getTemplateData() as $key => $value) {
            $email->personalization[0]->addSubstitution($key, $value);
        }

        $email->setTemplateId($content->getTemplateId());

        $this->send($email);
    }
}
