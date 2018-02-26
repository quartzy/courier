<?php

declare(strict_types=1);

namespace Courier\Test;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use Courier\SendGridCourier;
use Courier\Test\Support\TestContent;
use Exception;
use Mockery;
use PhpEmail\Address;
use PhpEmail\Attachment\FileAttachment;
use PhpEmail\Content\EmptyContent;
use PhpEmail\Content\SimpleContent;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\Email;
use PhpEmail\Header;
use SendGrid;
use SendGrid\Client;
use SendGrid\Mail;
use SendGrid\Response;

/**
 * @covers \Courier\SendGridCourier
 * @covers \Courier\Exceptions\TransmissionException
 * @covers \Courier\Exceptions\UnsupportedContentException
 */
class SendGridCourierTest extends TestCase
{
    /**
     * @var string
     */
    private static $file = '/tmp/attachment_test.txt';

    /**
     * @var Mockery\Mock|Client
     */
    private $client;

    /**
     * @var SendGridCourier
     */
    private $courier;

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

    public function setUp()
    {
        $sendGrid = new \SendGrid('key');

        /* @var Mockery\Mock|Client $client */
        $this->client     = Mockery::mock(Client::class);
        $sendGrid->client = $this->client;

        $this->courier = new SendGridCourier($sendGrid);
    }

    private function success(): Response
    {
        return new Response(202, [], ['X-Message-Id: 123452']);
    }

    /**
     * @testdox It should send a simple email
     */
    public function testSendsSimpleEmail()
    {
        $expectedEmail = new Mail(
            new SendGrid\Email(null, 'sender@test.com'),
            'Subject',
            new SendGrid\Email(null, 'recipient@test.com'),
            new SendGrid\Content('text/plain', 'This is a test email')
        );

        $expectedResponse = $this->success();

        $this->setExpectedCall($this->client, $expectedEmail, $expectedResponse);

        $email = new Email(
            'Subject',
            SimpleContent::text('This is a test email'),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $this->courier->deliver($email);
    }

    /**
     * @testdox It should send an empty simple email
     */
    public function testSendsAnEmptySimpleEmail()
    {
        $expectedEmail = new Mail(
            new SendGrid\Email(null, 'sender@test.com'),
            'Subject',
            new SendGrid\Email(null, 'recipient@test.com'),
            new SendGrid\Content('text/plain', '')
        );

        $expectedResponse = $this->success();

        $this->setExpectedCall($this->client, $expectedEmail, $expectedResponse);

        $email = new Email(
            'Subject',
            new SimpleContent(null, null),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $this->courier->deliver($email);
    }

    /**
     * @testdox It should send an empty email
     */
    public function testSendsEmptyEmail()
    {
        $expectedEmail = new Mail(
            new SendGrid\Email(null, 'sender@test.com'),
            'Subject',
            new SendGrid\Email(null, 'recipient@test.com'),
            new SendGrid\Content('text/plain', '')
        );

        $expectedResponse = $this->success();

        $this->setExpectedCall($this->client, $expectedEmail, $expectedResponse);

        $email = new Email(
            'Subject',
            new EmptyContent(),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $this->courier->deliver($email);
    }

    /**
     * @testdox It should send a templated email
     */
    public function testSendTemplatedEmail()
    {
        $personalization = new SendGrid\Personalization();
        $personalization->addTo(new SendGrid\Email(null, 'recipient@test.com'));

        $expectedEmail = new Mail();
        $expectedEmail->setSubject('Subject');
        $expectedEmail->setFrom(new SendGrid\Email(null, 'sender@test.com'));
        $expectedEmail->addPersonalization($personalization);
        $expectedEmail->setTemplateId('1234');
        $expectedEmail->getPersonalizations()[0]->addSubstitution('test', 'value');

        $expectedResponse = $this->success();

        $this->setExpectedCall($this->client, $expectedEmail, $expectedResponse);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $this->courier->deliver($email);
    }

    /**
     * @testdox It should add all email values for SendGrid
     */
    public function testAcceptsAllEmailValues()
    {
        $personalization = new SendGrid\Personalization();
        $personalization->addTo(new SendGrid\Email(null, 'recipient@test.com'));
        $personalization->addCc(new SendGrid\Email('CC', 'cc@test.com'));
        $personalization->addBcc(new SendGrid\Email('BCC', 'bcc@test.com'));

        $attachment = new SendGrid\Attachment();
        $attachment->setContent(base64_encode(file_get_contents(self::$file)));
        $attachment->setFilename('file name.txt');
        $attachment->setType(mime_content_type(self::$file));

        $inlineAttachment = new SendGrid\Attachment();
        $inlineAttachment->setContent(base64_encode(file_get_contents(self::$file)));
        $inlineAttachment->setFilename('image.jpg');
        $inlineAttachment->setContentID('inline');
        $inlineAttachment->setDisposition('inline');
        $inlineAttachment->setType(mime_content_type(self::$file));

        $expectedEmail = new Mail();
        $expectedEmail->setSubject('This is the Subject');
        $expectedEmail->setFrom(new SendGrid\Email(null, 'sender@test.com'));
        $expectedEmail->addPersonalization($personalization);
        $expectedEmail->addContent(new SendGrid\Content('text/html', 'This is a test email'));
        $expectedEmail->setReplyTo(new SendGrid\Email('Reply To', 'replyTo@test.com'));
        $expectedEmail->addAttachment($attachment);
        $expectedEmail->addAttachment($inlineAttachment);
        $expectedEmail->addHeader('X-Test-Header', 'test');

        $expectedResponse = $this->success();

        $this->setExpectedCall($this->client, $expectedEmail, $expectedResponse);

        $email = new Email(
            'This is the Subject',
            SimpleContent::html('This is a test email'),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->setReplyTos(new Address('replyTo@test.com', 'Reply To'));
        $email->setCcRecipients(new Address('cc@test.com', 'CC'));
        $email->setBccRecipients(new Address('bcc@test.com', 'BCC'));
        $email->setAttachments(new FileAttachment(self::$file, 'file name.txt'));
        $email->embed(new FileAttachment(self::$file, 'image.jpg'), 'inline');
        $email->setHeaders(new Header('X-Test-Header', 'test'));

        $this->courier->deliver($email);
    }

    /**
     * @testdox It should ensure unique addresses across to, cc, and bcc
     */
    public function testUsesUniqueAddress()
    {
        $personalization = new SendGrid\Personalization();
        $personalization->addTo(new SendGrid\Email(null, 'recipient@test.com'));
        $personalization->addCc(new SendGrid\Email('CC', 'cc@test.com'));
        $personalization->addBcc(new SendGrid\Email('BCC', 'bcc@test.com'));

        $expectedEmail = new Mail();
        $expectedEmail->setSubject('This is the Subject');
        $expectedEmail->setFrom(new SendGrid\Email(null, 'sender@test.com'));
        $expectedEmail->addPersonalization($personalization);
        $expectedEmail->addContent(new SendGrid\Content('text/html', 'This is a test email'));

        $expectedResponse = $this->success();

        $this->setExpectedCall($this->client, $expectedEmail, $expectedResponse);

        $email = new Email(
            'This is the Subject',
            SimpleContent::html('This is a test email'),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->setCcRecipients(
            new Address('cc@test.com', 'CC'),
            new Address('recipient@test.com'),
            new Address('CC@test.com')
        );
        $email->setBccRecipients(
            new Address('bcc@test.com', 'BCC'),
            new Address('recipient@test.com'),
            new Address('cc@test.com')
        );

        $this->courier->deliver($email);
    }

    /**
     * @testdox It should validate the content is deliverable
     */
    public function testValidatesContent()
    {
        $email = new Email(
            'Subject',
            new TestContent(),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        self::expectException(UnsupportedContentException::class);
        $this->courier->deliver($email);
    }

    /**
     * @testdox It should handle an exception during email transmission
     */
    public function testHandlesTransmissionException()
    {
        $expectedEmail = new Mail(
            new SendGrid\Email(null, 'sender@test.com'),
            'Subject',
            new SendGrid\Email(null, 'recipient@test.com'),
            new SendGrid\Content('text/plain', '')
        );

        $this->setExpectedCall($this->client, $expectedEmail, new Exception());

        $email = new Email(
            'Subject',
            new EmptyContent(),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        self::expectException(TransmissionException::class);
        $this->courier->deliver($email);
    }

    /**
     * @testdox It should throw an exception if an ID can't be found
     */
    public function testThrowsWithoutId()
    {
        $expectedEmail = new Mail(
            new SendGrid\Email(null, 'sender@test.com'),
            'Subject',
            new SendGrid\Email(null, 'recipient@test.com'),
            new SendGrid\Content('text/plain', '')
        );

        $expectedResponse = new Response(202, [], [/*missing the message ID header*/]);

        $this->setExpectedCall($this->client, $expectedEmail, $expectedResponse);

        $email = new Email(
            'Subject',
            new EmptyContent(),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        self::expectException(TransmissionException::class);
        $this->courier->deliver($email);
    }

    /**
     * @testdox It should handle an error response during email transmission
     */
    public function testHandlesTransmissionErrorResponse()
    {
        $expectedEmail = new Mail(
            new SendGrid\Email(null, 'sender@test.com'),
            'Subject',
            new SendGrid\Email(null, 'recipient@test.com'),
            new SendGrid\Content('text/plain', '')
        );

        $expectedResponse = new Response(400);

        $this->setExpectedCall($this->client, $expectedEmail, $expectedResponse);

        $email = new Email(
            'Subject',
            new EmptyContent(),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        self::expectException(TransmissionException::class);
        $this->courier->deliver($email);
    }

    /**
     * @param Mockery\Mock|Client $client
     * @param Mail                $expectedMail
     * @param Response|Exception  $response
     */
    private function setExpectedCall($client, Mail $expectedMail, $response)
    {
        $client
            ->shouldReceive('mail')
            ->once()
            ->andReturn($client);

        $client
            ->shouldReceive('send')
            ->once()
            ->andReturn($client);

        if ($response instanceof Exception) {
            $client
                ->shouldReceive('post')
                ->once()
                ->with(Mockery::on(function (Mail $mail) use ($expectedMail) {
                    return $mail == $expectedMail;
                }))
                ->andThrow($response);
        } else {
            $client
                ->shouldReceive('post')
                ->once()
                ->with(Mockery::on(function (Mail $mail) use ($expectedMail) {
                    return $mail == $expectedMail;
                }))
                ->andReturn($response);
        }
    }
}
