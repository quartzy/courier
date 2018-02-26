<?php

declare(strict_types=1);

namespace Courier;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use PhpEmail\Address;
use PhpEmail\Attachment;
use PhpEmail\Content;
use PhpEmail\Email;
use Postmark\Models\DynamicResponseModel;
use Postmark\Models\PostmarkException;
use Postmark\PostmarkClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PostmarkCourier implements ConfirmingCourier
{
    use SavesReceipts;

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
     * @param Email $email
     *
     * @throws TransmissionException
     * @throws UnsupportedContentException
     *
     * @return void
     */
    public function deliver(Email $email): void
    {
        $content = $email->getContent();

        if (!$this->supportsContent($content)) {
            throw new UnsupportedContentException($content);
        }

        switch (true) {
            case $content instanceof Content\TemplatedContent:
                $response = $this->sendTemplateEmail($email);
                break;

            case $content instanceof Content\SimpleContent:
                $response = $this->sendNonTemplateEmail($email);
                break;

            case $content instanceof Content\EmptyContent:
                $response = $this->sendNonTemplateEmail($email);
                break;

            default:
                // Should never get here
                // @codeCoverageIgnoreStart
                throw new UnsupportedContentException($content);
                // @codeCoverageIgnoreEnd
        }

        $this->saveReceipt($email, $response['MessageID']);
    }

    /**
     * @param Email $email
     *
     * @return DynamicResponseModel
     */
    protected function sendTemplateEmail(Email $email): DynamicResponseModel
    {
        try {
            return $this->client->sendEmailWithTemplate(
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
                $this->buildHeaders($email),
                $this->buildAttachments($email),
                null
            );
        } catch (PostmarkException $pe) {
            $this->logError($pe);

            throw new TransmissionException($pe->postmarkApiErrorCode, $pe);
        }
    }

    /**
     * @param Email $email
     *
     * @return DynamicResponseModel
     */
    protected function sendNonTemplateEmail(Email $email): DynamicResponseModel
    {
        $content     = $email->getContent();
        $htmlContent = 'No message';
        $textContent = 'No message';
        if ($content instanceof Content\Contracts\SimpleContent) {
            $htmlContent = $content->getHtml() !== null ? $content->getHtml()->getBody() : null;
            $textContent = $content->getText() !== null ? $content->getText()->getBody() : null;
        }

        try {
            return $this->client->sendEmail(
                $email->getFrom()->toRfc2822(),
                $this->buildRecipients(...$email->getToRecipients()),
                $email->getSubject(),
                $htmlContent,
                $textContent,
                null,
                true,
                $this->buildReplyTo($email),
                $this->buildRecipients(...$email->getCcRecipients()),
                $this->buildRecipients(...$email->getBccRecipients()),
                $this->buildHeaders($email),
                $this->buildAttachments($email),
                null
            );
        } catch (PostmarkException $pe) {
            $this->logError($pe);

            throw new TransmissionException($pe->postmarkApiErrorCode, $pe);
        }
    }

    protected function buildReplyTo(Email $email): ?string
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
    protected function buildRecipients(Address ...$addresses): string
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
    protected function buildAttachments(Email $email): array
    {
        return array_map(function (Attachment $attachment) {
            return [
                'Name'        => $attachment->getName(),
                'Content'     => $attachment->getBase64Content(),
                'ContentType' => $attachment->getContentType(),
                'ContentID'   => $attachment->getContentId(),
            ];
        }, array_merge($email->getAttachments(), $email->getEmbedded()));
    }

    /**
     * @param Email $email
     *
     * @return array
     */
    protected function buildHeaders(Email $email): array
    {
        $headers = [];

        foreach ($email->getHeaders() as $header) {
            $headers[$header->getField()] = $header->getValue();
        }

        return $headers;
    }

    /**
     * @param Email $email
     *
     * @return array
     */
    protected function buildTemplateData(Email $email): array
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
    protected function logError(PostmarkException $pe): void
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
