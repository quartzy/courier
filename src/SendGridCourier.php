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
use SendGrid\Mail\Attachment;
use SendGrid\Mail\Mail;
use SendGrid\Mail\PlainTextContent;
use SendGrid\Mail\HtmlContent;

/**
 * A courier implementation using the SendGrid v3 web API and sendgrid-php library to send emails.
 *
 * While SendGrid supports sending batches of emails using "personalizations", this does not fit completely into the
 * paradigm of transactional emails. For this reason, this courier only creates a single personalization with multiple
 * recipients.
 */
class SendGridCourier implements ConfirmingCourier
{
    use SavesReceipts;

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
    public function deliver(Email $email): void
    {
        if (!$this->supportsContent($email->getContent())) {
            throw new UnsupportedContentException($email->getContent());
        }

        $mail = $this->prepareEmail($email);

        switch (true) {
            case $email->getContent() instanceof Content\EmptyContent:
                $response = $this->sendEmptyContent($mail);
                break;

            case $email->getContent() instanceof Content\Contracts\SimpleContent:
                $response = $this->sendSimpleContent($mail, $email->getContent());
                break;

            case $email->getContent() instanceof Content\Contracts\TemplatedContent:
                $response = $this->sendTemplatedContent($mail, $email->getContent());
                break;

            default:
                // Should never get here
                // @codeCoverageIgnoreStart
                throw new UnsupportedContentException($email->getContent());
                // @codeCoverageIgnoreEnd
        }

        $this->saveReceipt($email, $this->getReceipt($response));
    }

    protected function getReceipt(SendGrid\Response $response): string
    {
        $key = 'X-Message-Id';

        foreach ($response->headers() as $header) {
            $parts = explode(':', $header, 2);

            if ($parts[0] === $key) {
                return $parts[1];
            }
        }

        throw new TransmissionException();
    }

    /**
     * {@inheritdoc}
     */
    protected function supportedContent(): array
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
    protected function supportsContent(Content $content): bool
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
    protected function distinctAddresses(array $emails, array $existing = []): array
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
     * @return Mail
     */
    protected function prepareEmail(Email $email): Mail
    {
        $message = new Mail();

        $message->setSubject($email->getSubject());
        $message->setFrom($email->getFrom()->getEmail(), $email->getFrom()->getName());

        foreach ($this->distinctAddresses($email->getToRecipients()) as $recipient) {
            $message->addTo($recipient->getEmail(), $recipient->getName());
        }

        $existingAddresses = $email->getToRecipients();
        foreach ($this->distinctAddresses($email->getCcRecipients(), $existingAddresses) as $recipient) {
            $message->addCc($recipient->getEmail(), $recipient->getName());
        }

        $existingAddresses = array_merge($email->getToRecipients(), $email->getCcRecipients());
        foreach ($this->distinctAddresses($email->getBccRecipients(), $existingAddresses) as $recipient) {
            $message->addBcc($recipient->getEmail(), $recipient->getName());
        }

        if (!empty($email->getReplyTos())) {
            // The SendGrid API only supports one "Reply To" :(
            $replyTos = $email->getReplyTos();
            $first    = reset($replyTos);

            $message->setReplyTo($first->getEmail(), $first->getName());
        }

        if ($attachments = $this->buildAttachments($email)) {
            $message->addAttachments($attachments);
        }

        foreach ($email->getHeaders() as $header) {
            $message->addHeader($header->getField(), $header->getValue());
        }

        return $message;
    }

    /**
     * @param Mail $email
     *
     * @return SendGrid\Response
     */
    protected function send(Mail $email): SendGrid\Response
    {
        try {
            /** @var SendGrid\Response $response */
            $response = $this->sendGrid->send($email);

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

            return $response;
        } catch (Exception $e) {
            throw new TransmissionException($e->getCode(), $e);
        }
    }

    /**
     * @param Mail $email
     *
     * @return SendGrid\Response
     */
    protected function sendEmptyContent(Mail $email): SendGrid\Response
    {
        $email->addContent(new SendGrid\Mail\PlainTextContent(''));

        return $this->send($email);
    }

    /**
     * @param Mail                            $email
     * @param Content\Contracts\SimpleContent $content
     *
     * @return SendGrid\Response
     */
    protected function sendSimpleContent(
        Mail $email,
        Content\Contracts\SimpleContent $content
    ): SendGrid\Response {
        if ($content->getText() !== null) {
            $email->addContent(new PlainTextContent($content->getText()->getBody()));
        }

        if ($content->getHtml() !== null) {
            $email->addContent(new HtmlContent($content->getHtml()->getBody()));
        }

        if ($content->getHtml() === null && $content->getText() === null) {
            $email->addContent(new PlainTextContent(''));
        }

        return $this->send($email);
    }

    /**
     * @param Mail                               $email
     * @param Content\Contracts\TemplatedContent $content
     *
     * @return SendGrid\Response
     */
    protected function sendTemplatedContent(
        Mail $email,
        Content\Contracts\TemplatedContent $content
    ): SendGrid\Response {
        $email->addSubstitutions($content->getTemplateData());
        $email->setTemplateId($content->getTemplateId());

        return $this->send($email);
    }

    /**
     * @param Email $email
     *
     * @return Attachment[]
     */
    protected function buildAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $sendGridAttachment = new Attachment(
                $attachment->getBase64Content(),
                $attachment->getContentType(),
                $attachment->getName()
            );

            $attachments[] = $sendGridAttachment;
        }

        foreach ($email->getEmbedded() as $attachment) {
            $sendGridAttachment = new Attachment(
                $attachment->getBase64Content(),
                $attachment->getContentType(),
                $attachment->getName(),
                'inline',
                $attachment->getContentId()
            );

            $attachments[] = $sendGridAttachment;
        }

        return $attachments;
    }
}
