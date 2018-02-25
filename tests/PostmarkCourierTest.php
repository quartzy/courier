<?php

declare(strict_types=1);

namespace Courier\Test;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use Courier\PostmarkCourier;
use Courier\Test\Support\TestContent;
use Mockery;
use PhpEmail\Attachment\FileAttachment;
use PhpEmail\Content\EmptyContent;
use PhpEmail\Content\SimpleContent;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\EmailBuilder;
use Postmark\Models\DynamicResponseModel;
use Postmark\Models\PostmarkException;
use Postmark\PostmarkClient;

/**
 * @covers \Courier\PostmarkCourier
 * @covers \Courier\Exceptions\TransmissionException
 * @covers \Courier\Exceptions\UnsupportedContentException
 */
class PostmarkCourierTest extends TestCase
{
    /**
     * @var string
     */
    private static $file = '/tmp/postmark_attachment_test.txt';

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        file_put_contents(self::$file, 'Attachment file');
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        unlink(self::$file);
    }

    private function success(): DynamicResponseModel
    {
        return new DynamicResponseModel(['MessageID' => '0a129aee-e1cd-480d-b08d-4f48548ff48d']);
    }

    /**
     * @testdox It should send an email with simple content
     */
    public function testSendsSimpleEmail()
    {
        /** @var Mockery\Mock|PostmarkClient $client */
        $client = Mockery::mock(PostmarkClient::class);

        $courier = new PostmarkCourier($client);

        $email = EmailBuilder::email()
            ->from('sender@test.com', 'Sender')
            ->to('receiver@test.com')
            ->to('other@test.com')
            ->withSubject('Test From Postmark API')
            ->withContent(SimpleContent::html('<b>Test from Postmark</b>')->addText('Test from Postmark'))
            ->cc('copy@test.com')
            ->bcc('blind.copy@test.com')
            ->replyTo('reply.to@test.com', 'Replier')
            ->attach(new FileAttachment(self::$file, 'Test File'))
            ->addHeader('X-Test-Header', 'test')
            ->build();

        $client
            ->shouldReceive('sendEmail')
            ->once()
            ->with(
                '"Sender" <sender@test.com>',
                'receiver@test.com,other@test.com',
                'Test From Postmark API',
                '<b>Test from Postmark</b>',
                'Test from Postmark',
                null,
                true,
                '"Replier" <reply.to@test.com>',
                'copy@test.com',
                'blind.copy@test.com',
                ['X-Test-Header' => 'test'],
                [
                    [
                        'Content'     => base64_encode('Attachment file'),
                        'ContentType' => mime_content_type(self::$file),
                        'Name'        => 'Test File',
                    ],
                ],
                null
            )
            ->andReturn($this->success());

        $courier->deliver($email);
    }

    /**
     * @testdox It should send an email with no content
     */
    public function testSendsEmptyEmail()
    {
        /** @var Mockery\Mock|PostmarkClient $client */
        $client = Mockery::mock(PostmarkClient::class);

        $courier = new PostmarkCourier($client);

        $email = EmailBuilder::email()
            ->from('sender@test.com', 'Sender')
            ->to('receiver@test.com')
            ->withSubject('Test From Postmark API')
            ->withContent(new EmptyContent())
            ->build();

        $client
            ->shouldReceive('sendEmail')
            ->once()
            ->with(
                '"Sender" <sender@test.com>',
                'receiver@test.com',
                'Test From Postmark API',
                'No message',
                'No message',
                null,
                true,
                null,
                null,
                null,
                [],
                [],
                null
            )
            ->andReturn($this->success());

        $courier->deliver($email);
    }

    /**
     * @testdox It should send an email with templated content
     */
    public function testSendsTemplatedEmail()
    {
        /** @var Mockery\Mock|PostmarkClient $client */
        $client = Mockery::mock(PostmarkClient::class);

        $courier = new PostmarkCourier($client);

        $data = [
            'product_name' => 'Wonderful Product',
            'name'         => 'Buyer',
        ];

        $email = EmailBuilder::email()
            ->from('sender@test.com', 'Sender')
            ->to('receiver@test.com')
            ->to('other@test.com')
            ->withSubject('Template Test From Postmark API')
            ->withContent(new TemplatedContent('1111', $data))
            ->cc('copy@test.com')
            ->bcc('blind.copy@test.com')
            ->replyTo('reply.to@test.com', 'Replier')
            ->attach(new FileAttachment(self::$file, 'Test File'))
            ->addHeader('X-Test-Header', 'test')
            ->build();

        $client
            ->shouldReceive('sendEmailWithTemplate')
            ->once()
            ->with(
                '"Sender" <sender@test.com>',
                'receiver@test.com,other@test.com',
                1111,
                [
                    'subject'      => 'Template Test From Postmark API',
                    'product_name' => 'Wonderful Product',
                    'name'         => 'Buyer',
                ],
                false,
                null,
                true,
                '"Replier" <reply.to@test.com>',
                'copy@test.com',
                'blind.copy@test.com',
                ['X-Test-Header' => 'test'],
                [
                    [
                        'Content'     => base64_encode('Attachment file'),
                        'ContentType' => mime_content_type(self::$file),
                        'Name'        => 'Test File',
                    ],
                ],
                null
            )
            ->andReturn($this->success());

        $courier->deliver($email);
    }

    /**
     * @testdox It should handle a client error with simple content
     */
    public function testHandlesExceptionsOnSimpleContent()
    {
        /** @var Mockery\Mock|PostmarkClient $client */
        $client = Mockery::mock(PostmarkClient::class);

        $courier = new PostmarkCourier($client);

        $email = EmailBuilder::email()
            ->from('sender@test.com', 'Sender')
            ->to('receiver@test.com')
            ->withSubject('Test From Postmark API')
            ->withContent(new EmptyContent())
            ->build();

        $exception                       = new PostmarkException();
        $exception->postmarkApiErrorCode = 1234;
        $exception->httpStatusCode       = 400;
        $exception->message              = 'Error occurred';

        $client
            ->shouldReceive('sendEmail')
            ->once()
            ->with(
                '"Sender" <sender@test.com>',
                'receiver@test.com',
                'Test From Postmark API',
                'No message',
                'No message',
                null,
                true,
                null,
                null,
                null,
                [],
                [],
                null
            )
            ->andThrow($exception);

        self::expectException(TransmissionException::class);
        $courier->deliver($email);
    }

    /**
     * @testdox It should handle a client error with template content
     */
    public function testHandlesExceptionsOnTemplateContent()
    {
        /** @var Mockery\Mock|PostmarkClient $client */
        $client = Mockery::mock(PostmarkClient::class);

        $courier = new PostmarkCourier($client);

        $email = EmailBuilder::email()
            ->from('sender@test.com', 'Sender')
            ->to('receiver@test.com')
            ->withSubject('Template Test From Postmark API')
            ->withContent(new TemplatedContent('1234', []))
            ->build();

        $exception                       = new PostmarkException();
        $exception->postmarkApiErrorCode = 1234;
        $exception->httpStatusCode       = 400;
        $exception->message              = 'Error occurred';

        $client
            ->shouldReceive('sendEmailWithTemplate')
            ->once()
            ->with(
                '"Sender" <sender@test.com>',
                'receiver@test.com',
                1234,
                [
                    'subject' => 'Template Test From Postmark API',
                ],
                false,
                null,
                true,
                null,
                null,
                null,
                null,
                [],
                null
            )
            ->andThrow($exception);

        self::expectException(TransmissionException::class);
        $courier->deliver($email);
    }

    /**
     * @testdox It should throw an error if unsupported content is provided
     */
    public function testChecksForSupportedContent()
    {
        /** @var Mockery\Mock|PostmarkClient $client */
        $client = Mockery::mock(PostmarkClient::class);

        $courier = new PostmarkCourier($client);

        $email = EmailBuilder::email()
            ->withSubject('Subject')
            ->to('recipient@test.com')
            ->from('sender@test.com')
            ->withContent(new TestContent())
            ->build();

        self::expectException(UnsupportedContentException::class);
        $courier->deliver($email);
    }
}
