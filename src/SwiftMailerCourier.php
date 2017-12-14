<?php

declare(strict_types=1);

namespace Courier;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use Courier\Exceptions\ValidationException;
use PhpEmail\Attachment\FileAttachment;
use PhpEmail\Content;
use PhpEmail\Email;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;

class SwiftMailerCourier implements Courier
{
    /**
     * @var Swift_Mailer
     */
    private $mailer;

    public function __construct(Swift_Mailer $mailer)
    {
        $this->mailer = $mailer;
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
        if (!$this->supportsContent($email->getContent())) {
            throw new UnsupportedContentException($email->getContent());
        }

        $message = new Swift_Message($email->getSubject());

        $this->addBody($email->getContent(), $message);

        $this->addRecipients($email, $message);

        $this->addFrom($email, $message);

        $this->addAttachments($email, $message);

        $this->mailer->send($message);
    }

    /**
     * @return array
     */
    protected function supportedContent(): array
    {
        return [
            Content\EmptyContent::class,
            Content\Contracts\SimpleContent::class,
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

    protected function addBody(Content $content, Swift_Message $message): void
    {
        if ($content instanceof Content\SimpleContent) {
            if ($content->getHtml() && $content->getText()) {
                // Use HTML for the body and add a part for the text
                $message->setBody($content->getHtml(), 'text/html');
                $message->addPart($content->getText(), 'text/plain');
            } elseif ($content->getHtml()) {
                $message->setBody($content->getHtml(), 'text/html');
            } elseif ($content->getText()) {
                $message->setBody($content->getText(), 'text/plain');
            }
        }
    }

    protected function addRecipients(Email $email, Swift_Message $message): void
    {
        foreach ($email->getToRecipients() as $recipient) {
            $message->addTo($recipient->getEmail(), $recipient->getName());
        }

        foreach ($email->getCcRecipients() as $recipient) {
            $message->addCc($recipient->getEmail(), $recipient->getName());
        }

        foreach ($email->getBccRecipients() as $recipient) {
            $message->addBcc($recipient->getEmail(), $recipient->getName());
        }
    }

    protected function addFrom(Email $email, Swift_Message $message): void
    {
        $message->setFrom($email->getFrom()->getEmail(), $email->getFrom()->getName());

        foreach ($email->getReplyTos() as $replyTo) {
            $message->addReplyTo($replyTo->getEmail(), $replyTo->getName());
        }
    }

    protected function addAttachments(Email $email, Swift_Message $message): void
    {
        foreach ($email->getAttachments() as $attachment) {
            if ($attachment instanceof FileAttachment) {
                $swiftAttachment = Swift_Attachment::fromPath($attachment->getFile(), $attachment->getContentType());

                $swiftAttachment->setFilename($attachment->getName());

                $message->attach($swiftAttachment);
            } else {
                throw new ValidationException('Unsupported attachment type ' . get_class($attachment));
            }
        }
    }
}
