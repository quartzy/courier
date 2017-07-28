<?php

namespace Courier;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use PhpEmail\Address;
use PhpEmail\Attachment;
use PhpEmail\Content;
use PhpEmail\Email;
use Postmark\Models\PostmarkException;
use Postmark\PostmarkClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PostmarkCourier implements Courier
{
    /**
     * @var PostmarkClient
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param PostmarkClient       $client
     * @param LoggerInterface|null $logger
     */
    public function __construct(PostmarkClient $client, LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->logger = $logger ?: new NullLogger();
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
     * @throws TransmissionException
     * @throws UnsupportedContentException
     *
     * @return void
     */
    public function deliver(Email $email)
    {
        $content = $email->getContent();

        if (!$this->supportsContent($content)) {
            throw new UnsupportedContentException($content);
        }

        switch (true) {
            case $content instanceof Content\TemplatedContent:
                $this->sendTemplateEmail($email);
                break;

            case $content instanceof Content\SimpleContent:
                $this->sendNonTemplateEmail($email, $content->getHtml(), $content->getText());
                break;

            case $content instanceof Content\EmptyContent:
                $this->sendNonTemplateEmail($email, 'No message', 'No message');
                break;
        }
    }

    protected function sendTemplateEmail(Email $email)
    {
        try {
            $this->client->sendEmailWithTemplate(
                $email->getFrom()->toRfc2822(),
                $this->buildRecipients(...$email->getToRecipients()),
                (int) $email->getContent()->getTemplateId(),
                $this->buildTemplateData($email),
                false,
                null,
                true,
                $this->buildReplyTo($email),
                $this->buildRecipients(...$email->getCcRecipients()),
                $this->buildRecipients(...$email->getBccRecipients()),
                null,
                $this->buildAttachments($email),
                null
            );
        } catch (PostmarkException $pe) {
            $this->logError($pe);

            throw new TransmissionException($pe->postmarkApiErrorCode, $pe);
        }
    }

    /**
     * @param Email       $email
     * @param string|null $html
     * @param string|null $text
     */
    protected function sendNonTemplateEmail(Email $email, $html, $text)
    {
        try {
            $this->client->sendEmail(
                $email->getFrom()->toRfc2822(),
                $this->buildRecipients(...$email->getToRecipients()),
                $email->getSubject(),
                $html,
                $text,
                null,
                true,
                $this->buildReplyTo($email),
                $this->buildRecipients(...$email->getCcRecipients()),
                $this->buildRecipients(...$email->getBccRecipients()),
                null,
                $this->buildAttachments($email),
                null
            );
        } catch (PostmarkException $pe) {
            $this->logError($pe);

            throw new TransmissionException($pe->postmarkApiErrorCode, $pe);
        }
    }

    protected function buildReplyTo(Email $email)
    {
        /** @var Address|null $replyTo */
        $replyTo = null;

        if (!empty($email->getReplyTos())) {
            // The Postmark API only supports one "Reply To"
            $replyTos = $email->getReplyTos();
            $replyTo  = reset($replyTos);
            $replyTo  = $replyTo->toRfc2822();
        }

        return $replyTo;
    }

    /**
     * @param Address[] $addresses
     *
     * @return string
     */
    protected function buildRecipients(Address ...$addresses)
    {
        return implode(',', array_map(function (Address $address) {
            return $address->toRfc2822();
        }, $addresses));
    }

    /**
     * @param Email $email
     *
     * @return array
     */
    protected function buildAttachments(Email $email)
    {
        return array_map(function (Attachment $attachment) {
            return [
                'Name'        => $attachment->getName(),
                'Content'     => $attachment->getBase64Content(),
                'ContentType' => $attachment->getContentType(),
            ];
        }, $email->getAttachments());
    }

    /**
     * @param Email $email
     *
     * @return array
     */
    protected function buildTemplateData(Email $email)
    {
        $data = $email->getContent()->getTemplateData();

        // Add the subject from the email for dynamic replacement
        $data['subject'] = $email->getSubject();

        return $data;
    }

    /**
     * @param PostmarkException $pe
     *
     * @return void
     */
    protected function logError(PostmarkException $pe)
    {
        $this->logger->error(
            'Received status {httpCode} and API code {apiCode} from Postmark with message: {message}',
            [
                'httpCode' => $pe->httpStatusCode,
                'apiCode'  => $pe->postmarkApiErrorCode,
                'message'  => $pe->message,
            ]
        );
    }
}
