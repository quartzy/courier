<?php

declare(strict_types=1);

namespace Courier\Test;

use ZBateson\MailMimeParser\Header\Part\ParameterPart;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message;

class IntegrationTestCase extends TestCase
{
    protected function getTo(): string
    {
        return getenv('TO_EMAIL');
    }

    protected function getCc(): string
    {
        return getenv('CC_EMAIL');
    }

    protected function getBcc(): string
    {
        return getenv('BCC_EMAIL');
    }

    protected function getEmailDeliveredToTo(string $subject): ?Message
    {
        return $this->getEmailFromMailBox('Courier/To', $subject);
    }

    protected function getEmailDeliveredToCc(string $subject): ?Message
    {
        return $this->getEmailFromMailBox('Courier/CC', $subject);
    }

    protected function getEmailDeliveredToBcc(string $subject): ?Message
    {
        return $this->getEmailFromMailBox('Courier/BCC', $subject);
    }

    protected static function assertHasAttachmentWithContentId(Message $message, string $contentId): void
    {
        $parts = $message->getAllParts(
            new Message\PartFilter(
                [
                    'headers' => [
                        Message\PartFilter::FILTER_INCLUDE => [
                            'Content-ID' => sprintf('<%s>', $contentId),
                        ],
                    ],
                ]
            )
        );

        self::assertEquals(1, count($parts), 'Unable to find embedded with Content-ID ' . $contentId);
    }

    protected static function assertHasAttachmentWithName(Message $message, string $name): void
    {
        $attachments = $message->getAllParts(
            new Message\PartFilter(
                [
                    'headers' => [
                        Message\PartFilter::FILTER_INCLUDE => [
                            'Content-Disposition' => 'attachment',
                        ],
                    ],
                ]
            )
        );

        $matching = [];
        foreach ($attachments as $attachment) {
            foreach ($attachment->getHeaders() as $header) {
                if ($header->getName() === 'Content-Disposition') {
                    foreach ($header->getParts() as $parameter) {
                        if ($parameter instanceof ParameterPart
                            && $parameter->getName() === 'filename'
                            && $parameter->getValue() === $name) {
                            $matching[] = $attachment;
                        }
                    }
                }
            }
        }

        self::assertEquals(1, count($matching), 'Unable to find an attachment with the name ' . $name);
    }

    private function getEmailFromMailBox(string $mailBox, string $subject): ?Message
    {
        $parser = new MailMimeParser();

        $attempts = 5;
        while ($attempts > 0) {
            $conn = imap_open(getenv('IMAP_SERVER') . $mailBox, getenv('IMAP_USERNAME'), getenv('IMAP_PASSWORD'));

            $messages = imap_search($conn, 'SUBJECT "' . $subject . '"');

            if ($messages !== false) {
                return $parser->parse(imap_fetchbody($conn, $messages[0], ''));
            }

            $attempts--;
            imap_close($conn);
            sleep(2);
        }

        return null;
    }
}
