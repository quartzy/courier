<?php

namespace Courier;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use Courier\Exceptions\ValidationException;
use PhpEmail\Address;
use PhpEmail\Attachment;
use PhpEmail\Content;
use PhpEmail\Email;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SparkPost\SparkPost;
use SparkPost\SparkPostException;

/**
 * A courier implementation using SparkPost as the third-party provider. This library uses the web API and the
 * php-sparkpost library to send transmissions.
 *
 * An important note is that while the SparkPost API does not support sending attachments on templated transmissions,
 * this API simulates the feature by creating an inline template based on the defined template using the API. In this
 * case, all template variables will be sent as expected, but tracking/reporting may not work as expected within
 * SparkPost.
 */
class SparkPostCourier
{
    const RECIPIENTS        = 'recipients';
    const CC                = 'cc';
    const BCC               = 'bcc';
    const REPLY_TO          = 'reply_to';
    const SUBSTITUTION_DATA = 'substitution_data';

    const CONTENT     = 'content';
    const FROM        = 'from';
    const SUBJECT     = 'subject';
    const HTML        = 'html';
    const TEXT        = 'text';
    const ATTACHMENTS = 'attachments';
    const TEMPLATE_ID = 'template_id';

    const ADDRESS       = 'address';
    const CONTACT_NAME  = 'name';
    const CONTACT_EMAIL = 'email';

    const ATTACHMENT_NAME = 'name';
    const ATTACHMENT_TYPE = 'type';
    const ATTACHMENT_DATA = 'data';

    /**
     * @var SparkPost
     */
    private $sparkPost;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SparkPost       $sparkPost
     * @param LoggerInterface $logger
     */
    public function __construct(SparkPost $sparkPost, LoggerInterface $logger = null)
    {
        $this->sparkPost = $sparkPost;
        $this->logger    = $logger ?: new NullLogger();
    }

    public function deliver(Email $email)
    {
        if (!$this->supportsContent($email->getContent())) {
            throw new UnsupportedContentException($email->getContent());
        }

        $mail = $this->prepareEmail($email);
        $mail = $this->prepareContent($email, $mail);

        $this->send($mail);
    }

    /**
     * @param array $mail
     *
     * @return void
     */
    protected function send(array $mail)
    {
        $promise = $this->sparkPost->transmissions->post($mail);
        try {
            $promise->wait();
        } catch (SparkPostException $e) {
            $this->logger->error(
                'Received status {code} from SparkPost with body: {body}',
                [
                    'code' => $e->getCode(),
                    'body' => $e->getBody(),
                ]
            );

            throw new TransmissionException($e->getCode(), $e);
        }
    }

    /**
     * @return array
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
     * @param Email $email
     *
     * @return array
     */
    protected function prepareEmail(Email $email)
    {
        $message = [];

        $message[self::RECIPIENTS] = [];

        foreach ($email->getToRecipients() as $recipient) {
            $message[self::RECIPIENTS][] = $this->createAddress($recipient);
        }

        $message[self::CC] = [];

        foreach ($email->getCcRecipients() as $recipient) {
            $message[self::CC][] = $this->createAddress($recipient);
        }

        if (!$message[self::CC]) {
            unset($message[self::CC]);
        }

        $message[self::BCC] = [];

        foreach ($email->getBccRecipients() as $recipient) {
            $message[self::BCC][] = $this->createAddress($recipient);
        }

        if (!$message[self::BCC]) {
            unset($message[self::BCC]);
        }

        return $message;
    }

    /**
     * @param Email $email
     * @param array $message
     *
     * @return array
     */
    protected function prepareContent(Email $email, array $message)
    {
        switch (true) {
            case $email->getContent() instanceof Content\Contracts\TemplatedContent:
                $message[self::CONTENT]           = $this->buildTemplateContent($email);
                $message[self::SUBSTITUTION_DATA] = $this->buildTemplateData($email);

                break;

            case $email->getContent() instanceof Content\EmptyContent:
                $email->setContent(new Content\SimpleContent('', ''));

                $message[self::CONTENT] = $this->buildSimpleContent($email);

                break;

            case $email->getContent() instanceof Content\SimpleContent:
                $message[self::CONTENT] = $this->buildSimpleContent($email);
        }

        return $message;
    }

    /**
     * Attempt to create template data using the from, subject and reply to, which SparkPost considers to be
     * part of the templates substitutable content.
     *
     * @param Email $email
     *
     * @return array
     */
    protected function buildTemplateData(Email $email)
    {
        /** @var Content\TemplatedContent $emailContent */
        $emailContent = $email->getContent();
        $templateData = $emailContent->getTemplateData();

        if ($email->getReplyTos()) {
            $replyTos    = $email->getReplyTos();
            $first       = reset($replyTos);

            if (!array_key_exists('replyTo', $templateData)) {
                $templateData['replyTo'] = $first->toRfc2822();
            }
        }

        if (!array_key_exists('fromName', $templateData)) {
            $templateData['fromName'] = $email->getFrom()->getName();
        }

        if (!array_key_exists('fromEmail', $templateData)) {
            $templateData['fromEmail'] = explode('@', $email->getFrom()->getEmail())[0];
        }

        if (!array_key_exists('fromDomain', $templateData)) {
            $templateData['fromDomain'] = explode('@', $email->getFrom()->getEmail())[1];
        }

        if (!array_key_exists('subject', $templateData)) {
            $templateData['subject'] = $email->getSubject();
        }

        return $templateData;
    }

    /**
     * @param Email $email
     *
     * @return array
     */
    protected function buildTemplateContent(Email $email)
    {
        $content = [
            self::TEMPLATE_ID => $email->getContent()->getTemplateId(),
        ];

        if ($email->getAttachments()) {
            /*
             * SparkPost does not currently support sending attachments with templated emails. For this reason,
             * we will instead get the template from SparkPost and create a new inline template using the information
             * from it instead.
             */
            try {
                $response = $this->sparkPost->syncRequest('GET', "templates/{$email->getContent()->getTemplateId()}");

                $template = $response->getBody()['results'][self::CONTENT];

                $inlineEmail = clone $email;

                $htmlContent = array_key_exists(self::HTML, $template) ? $template[self::HTML] : null;
                $textContent = array_key_exists(self::TEXT, $template) ? $template[self::TEXT] : null;

                $inlineEmail
                    ->setContent(new Content\SimpleContent($htmlContent, $textContent))
                    ->setSubject($template[self::SUBJECT]);

                // If the from contains a templated from, it should be actively replaced now to avoid validation errors.
                if (strpos($template[self::FROM][self::CONTACT_EMAIL], '{{') !== false) {
                    $inlineEmail->setFrom($email->getFrom());
                } else {
                    $inlineEmail->setFrom(
                        new Address(
                            $template[self::FROM][self::CONTACT_EMAIL],
                            $template[self::FROM][self::CONTACT_NAME]
                        )
                    );
                }

                // If the form contains a templated replyTo, it should be actively replaced now to avoid validation errors.
                if (array_key_exists(self::REPLY_TO, $template)) {
                    if (strpos($template[self::REPLY_TO], '{{') !== false) {
                        if (empty($email->getReplyTos())) {
                            throw new ValidationException('Reply to is templated but no value was given');
                        }

                        $inlineEmail->setReplyTos($email->getReplyTos()[0]);
                    } else {
                        $inlineEmail->setReplyTos(Address::fromString($template[self::REPLY_TO]));
                    }
                }

                $content = $this->buildSimpleContent($inlineEmail);

                // Some special sauce provided by SparkPost templates
                if (array_key_exists('headers', $template)) {
                    $content['headers'] = $template['headers'];
                }
            } catch (SparkPostException $e) {
                $this->logger->error(
                    'Received status {code} from SparkPost while retrieving template with body: {body}',
                    [
                        'code' => $e->getCode(),
                        'body' => $e->getBody(),
                    ]
                );

                throw new TransmissionException($e->getCode(), $e);
            }
        }

        return $content;
    }

    /**
     * @param Email $email
     *
     * @return array
     */
    protected function buildSimpleContent(Email $email)
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = $this->buildAttachment($attachment);
        }

        $replyTo = null;
        if (!empty($email->getReplyTos())) {
            // SparkPost only supports a single reply-to
            $replyTos = $email->getReplyTos();
            $first    = reset($replyTos);

            $replyTo = $first->toRfc2822();
        }

        return [
            self::FROM        => [
                self::CONTACT_NAME  => $email->getFrom()->getName(),
                self::CONTACT_EMAIL => $email->getFrom()->getEmail(),
            ],
            self::SUBJECT     => $email->getSubject(),
            self::HTML        => $email->getContent()->getHtml(),
            self::TEXT        => $email->getContent()->getText(),
            self::ATTACHMENTS => $attachments,
            self::REPLY_TO    => $replyTo,
        ];
    }

    /**
     * @param Attachment $attachment
     *
     * @return array
     */
    private function buildAttachment(Attachment $attachment)
    {
        return [
            self::ATTACHMENT_NAME => $attachment->getName(),
            self::ATTACHMENT_TYPE => $attachment->getContentType(),
            self::ATTACHMENT_DATA => $attachment->getBase64Content(),
        ];
    }

    /**
     * @param Address $address
     *
     * @return array
     */
    private function createAddress(Address $address)
    {
        return [
            self::ADDRESS => [
                self::CONTACT_NAME  => $address->getName(),
                self::CONTACT_EMAIL => $address->getEmail(),
            ],
        ];
    }
}
