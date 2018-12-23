<?php

declare(strict_types=1);

namespace Courier;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use PhpEmail\Address;
use PhpEmail\Content\Contracts\SimpleContent;
use PhpEmail\Email;

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

        $boundary = "==Multipart_Boundary_" . bin2hex(random_bytes(8));

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
        ];
    }

    private function mapAddresses(array $addresses): string
    {
        return implode(', ', array_map(function (Address $address) {
            return $address->toRfc2822();
        }, $addresses));
    }

    /**
     * Build the MIME parts for the content.
     *
     * @param Email  $email
     *
     * @return array
     */
    private function buildContent(Email $email): array
    {
        /** @var SimpleContent $content */
        $content = $email->getContent();

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
     * Create the MIME parts for each of the attachments
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
