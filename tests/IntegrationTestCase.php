<?php

declare(strict_types=1);

namespace Courier\Test;

use GuzzleHttp\Client;
use MailSlurp\Swagger\Api\InboxControllerApi;
use MailSlurp\Swagger\ApiException;
use MailSlurp\Swagger\Model\InboxDto;
use ZBateson\MailMimeParser\Header\Part\ParameterPart;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message;

class IntegrationTestCase extends TestCase
{
    /**
     * @var InboxControllerApi
     */
    protected $inboxClient;

    public function setUp(): void
    {
        parent::setUp();

        $this->inboxClient = new InboxControllerApi(new Client());
    }

    protected function createInbox(): ?InboxDto
    {
        try {
            return $this->inboxClient->createRandomInboxUsingPOST(getenv('MAIL_SLURP_KEY'))
                ->getPayload();
        } catch (ApiException $ae) {
            self::fail('Unable to create an inbox');

            return null;
        }
    }

    protected function getLatestEmail(InboxDto $inbox): ?Message
    {
        try {
            $parser = new MailMimeParser();

            $email = $this->inboxClient->getEmailsForInboxUsingGET(
                getenv('MAIL_SLURP_KEY'),
                $inbox->getId(),
                1
            )->getPayload()[0];

            return $parser->parse($email->getBody());
        } catch (ApiException $ae) {
            self::fail('Unable to retrieve the latest email');

            return null;
        }
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
}
