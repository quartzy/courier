<?php

declare(strict_types=1);

namespace Courier;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use PhpEmail\Address;
use PhpEmail\Content\Contracts\SimpleContent;
use PhpEmail\Content\Contracts\TemplatedContent;
use PhpEmail\Email;

/**
 * A courier that leverages the built-in `mail` function to deliver emails.
 *
 * This Courier is meant as a drop-in testing option for local development. The courier does not implement template
 * rendering, but will still deliver templated emails, describing the template ID and data provided. This functionally
 * can be extended by overwriting or wrapping the class, if desired.
 */
class MailCourier implements Courier
{
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
        if (!$this->supportsContent($email)) {
            throw new UnsupportedContentException($email->getContent());
        }

        $boundary = '==Multipart_Boundary_' . bin2hex(random_bytes(8));

        $headers = [
            'From: ' . $email->getFrom()->toRfc2822(),
            'Content-Type: multipart/alternative;boundary="' . $boundary . '"',
        ];

        if ($email->getCcRecipients()) {
            $headers[] = 'Cc: ' . $this->mapAddresses($email->getCcRecipients());
        }

        if ($email->getBccRecipients()) {
            $headers[] = 'Bcc: ' . $this->mapAddresses($email->getBccRecipients());
        }

        if ($email->getReplyTos()) {
            $headers[] = 'Reply-To: ' . $this->mapAddresses($email->getReplyTos());
        }

        if ($email->getHeaders()) {
            foreach ($email->getHeaders() as $header) {
                $headers[] = sprintf('%s: %s', $header->getField(), $header->getValue());
            }
        }

        $result = mail(
            $this->mapAddresses($email->getToRecipients()),
            $email->getSubject() ?? '',
            "--{$boundary}\r\n" . implode("\r\n--{$boundary}\r\n", array_merge($this->buildContent($email), $this->buildAttachments($email))),
            implode("\r\n", $headers)
        );

        if (!$result) {
            throw new TransmissionException(0, new \Exception(error_get_last()['message']));
        }
    }

    public function supportsContent(Email $email): bool
    {
        foreach ($this->supportedContent() as $type) {
            if ($email->getContent() instanceof $type) {
                return true;
            }
        }

        return false;
    }

    protected function supportedContent(): array
    {
        return [
            SimpleContent::class,
            TemplatedContent::class
        ];
    }

    /**
     * Build the MIME parts for the content.
     *
     * @param Email $email
     *
     * @return array
     */
    protected function buildContent(Email $email): array
    {
        $content = $email->getContent();

        if ($content instanceof SimpleContent) {
            return $this->buildSimpleContent($content);
        } elseif ($content instanceof TemplatedContent) {
            return $this->buildTemplatedContent($content);
        }

        throw new UnsupportedContentException($content);
    }

    protected function buildSimpleContent(SimpleContent $content): array
    {
        $parts = [];

        if ($content->getText()) {
            $parts[] = <<<MIME
Content-Type: text/plain; {$content->getText()->getCharset()}

{$content->getText()->getBody()}
MIME;
        }

        if ($content->getHtml()) {
            $parts[] = <<<MIME
Content-Type: text/html; {$content->getHtml()->getCharset()}

{$content->getHtml()->getBody()}
MIME;
        }

        return $parts;
    }

    /**
     * Build the text representation of the template ID and data.
     *
     * @param TemplatedContent $content
     *
     * @return array
     */
    protected function buildTemplatedContent(TemplatedContent $content): array
    {
        $parts        = [];
        $templateData = json_encode($content->getTemplateData(), JSON_PRETTY_PRINT);

        $parts[] = <<<MIME
Content-Type: text/plain; utf-8

Template ID: {$content->getTemplateId()}
Template Data:

{$templateData}
MIME;

        return $parts;
    }

    private function mapAddresses(array $addresses): string
    {
        return implode(', ', array_map(function (Address $address) {
            return $address->toRfc2822();
        }, $addresses));
    }

    /**
     * Create the MIME parts for each of the attachments.
     *
     * @param Email $email
     *
     * @return array
     */
    private function buildAttachments(Email $email): array
    {
        $parts = [];

        foreach ($email->getAttachments() as $attachment) {
            $content     = chunk_split($attachment->getBase64Content());
            $contentType = $attachment->getContentType();

            if ($attachment->getCharset()) {
                $contentType .= ';' . $attachment->getCharset();
            }

            $parts[] = <<<MIME
Content-Type: {$contentType}
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="{$attachment->getName()}"

$content
MIME;
        }

        foreach ($email->getEmbedded() as $attachment) {
            $content     = chunk_split($attachment->getBase64Content());
            $contentType = $attachment->getContentType();

            if ($attachment->getCharset()) {
                $contentType .= ';' . $attachment->getCharset();
            }

            $parts[] = <<<MIME
Content-Type: {$contentType}
Content-Transfer-Encoding: base64
Content-Disposition: inline; filename="{$attachment->getName()}"
Content-ID: <{$attachment->getContentId()}>

$content
MIME;
        }

        return $parts;
    }
}
