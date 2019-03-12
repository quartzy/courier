<?php

declare(strict_types=1);

namespace Courier;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use PhpEmail\Address;
use PhpEmail\Attachment;
use PhpEmail\Content\Contracts\SimpleContent;
use PhpEmail\Content\Contracts\TemplatedContent;
use PhpEmail\Email;
use Psr\Log\LoggerInterface;

/**
 * A Courier implementation the writes the information to a logger.
 *
 * This implementation is not designed to be used in a production system, but it can act as a drop-in testing implementation,
 * allowing developers to build content and log it to their local systems, without delivering emails. This can be
 * helpful in cases where the email might contain important information, like a generated password reset token.
 */
class LoggingCourier implements Courier
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
        $this->logger->debug('Delivered email');
        $this->logger->debug("Subject: {$email->getSubject()}");
        $this->logger->debug("From: {$email->getFrom()->toRfc2822()}");
        $this->logger->debug("Reply To: {$this->mapAddresses($email->getReplyTos())}");
        $this->logger->debug("To: {$this->mapAddresses($email->getToRecipients())}");
        $this->logger->debug("CC: {$this->mapAddresses($email->getCcRecipients())}");
        $this->logger->debug("BCC: {$this->mapAddresses($email->getBccRecipients())}");

        $this->logHeaders($email);
        $this->logAttachments($email);
        $this->logContent($email);
    }

    private function mapAddresses(array $addresses): string
    {
        return implode(', ', array_map(function (Address $address) {
            return $address->toRfc2822();
        }, $addresses));
    }

    private function logHeaders(Email $email): void
    {
        foreach ($email->getHeaders() as $header) {
            $this->logger->debug("{$header->getField()}: {$header->getValue()}");
        }
    }

    private function logAttachments(Email $email): void
    {
        $attachmentNames = implode(', ', array_map(function (Attachment $attachment) {
            return $attachment->getName();
        }, $email->getAttachments()));

        $embeddedIds = implode(', ', array_map(function (Attachment $attachment) {
            return $attachment->getContentId() ?? 'NA';
        }, $email->getAttachments()));

        $this->logger->debug("Attaching: $attachmentNames");
        $this->logger->debug("Embedding IDs: $embeddedIds");
    }

    private function logContent(Email $email): void
    {
        $content = $email->getContent();
        if ($content instanceof TemplatedContent) {
            $this->logger->debug("Template ID: {$content->getTemplateId()}");
            $this->logger->debug("Template Data:\n" . json_encode($content->getTemplateData(), JSON_PRETTY_PRINT));
        }

        if ($content instanceof SimpleContent) {
            $this->logger->debug("HTML:\n");
            $this->logger->debug($content->getHtml()->getBody());
            $this->logger->debug("Text:\n");
            $this->logger->debug($content->getText()->getBody());
        }
    }
}
