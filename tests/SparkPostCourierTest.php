<?php

declare(strict_types=1);

namespace Courier\Test;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use Courier\Exceptions\ValidationException;
use Courier\SparkPostCourier;
use Courier\Test\Support\TestContent;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Client\Exception\HttpException;
use Mockery;
use PhpEmail\Address;
use PhpEmail\Attachment\FileAttachment;
use PhpEmail\Content\EmptyContent;
use PhpEmail\Content\SimpleContent;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\Email;
use PhpEmail\Header;
use SparkPost\SparkPost;
use SparkPost\SparkPostException;
use SparkPost\SparkPostPromise;
use SparkPost\SparkPostResponse;
use SparkPost\Transmission;

/**
 * @covers \Courier\SparkPostCourier
 * @covers \Courier\Exceptions\TransmissionException
 * @covers \Courier\Exceptions\UnsupportedContentException
 */
class SparkPostCourierTest extends TestCase
{
    /**
     * @var string
     */
    private static $file = '/tmp/sparkpost_attachment_test.txt';

    /**
     * @var Mockery\Mock|SparkPost
     */
    private $sparkPost;

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
        $this->sparkPost = Mockery::mock(SparkPost::class);
    }

    private function success(): SparkPostResponse
    {
        return new SparkPostResponse(new Response(
            200,
            [],
            json_encode([
                'results' => [
                    'id' => '12343523423',
                ],
            ])
        ));
    }

    /**
     * @testdox It should send a simple email
     */
    public function testSendsSimpleEmail()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            SimpleContent::text('This is a test email'),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $expectedArray = [
            'content' => [
                'from'          => [
                    'name'  => null,
                    'email' => 'sender@test.com',
                ],
                'subject'       => 'Subject',
                'html'          => null,
                'text'          => 'This is a test email',
                'inline_images' => [],
                'attachments'   => [],
                'reply_to'      => null,
                'headers'       => [],
            ],
            'recipients' => [
                [
                    'address' => [
                        'email'     => 'recipient@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, $this->success());

        $courier->deliver($email);
    }

    /**
     * @testdox It should send an empty email
     */
    public function testSendsEmptyEmail()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new EmptyContent(),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $expectedArray = [
            'content' => [
                'from'          => [
                    'name'  => null,
                    'email' => 'sender@test.com',
                ],
                'subject'       => 'Subject',
                'html'          => '',
                'text'          => '',
                'inline_images' => [],
                'attachments'   => [],
                'reply_to'      => null,
                'headers'       => [],
            ],
            'recipients' => [
                [
                    'address' => [
                        'email'     => 'recipient@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, $this->success());

        $courier->deliver($email);
    }

    /**
     * @testdox It should send a templated email
     */
    public function testSendsTemplatedEmail()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->addReplyTos(new Address('reply.to@test.com'));

        $expectedArray = [
            'content' => [
                'template_id' => '1234',
                'headers'     => [],
            ],
            'substitution_data' => [
                'test'       => 'value',
                'fromName'   => null,
                'fromEmail'  => 'sender',
                'fromDomain' => 'test.com',
                'subject'    => 'Subject',
                'replyTo'    => 'reply.to@test.com',
            ],
            'recipients' => [
                [
                    'address' => [
                        'email'     => 'recipient@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, $this->success());

        $courier->deliver($email);
    }

    /**
     * @testdox It should send a templated email with a CC recipient
     */
    public function testSendsTemplatedEmailWithCc()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->addCcRecipients(new Address('cc@test.com'));
        $email->addReplyTos(new Address('reply.to@test.com'));

        $expectedArray = [
            'content' => [
                'template_id' => '1234',
                'headers'     => [
                    'CC' => 'cc@test.com',
                ],
            ],
            'substitution_data' => [
                'test'       => 'value',
                'fromName'   => null,
                'fromEmail'  => 'sender',
                'fromDomain' => 'test.com',
                'subject'    => 'Subject',
                'replyTo'    => 'reply.to@test.com',
                'ccHeader'   => 'cc@test.com',
            ],
            'recipients' => [
                [
                    'address' => [
                        'email'     => 'recipient@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
                [
                    'address' => [
                        'email'     => 'cc@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, $this->success());

        $courier->deliver($email);
    }

    /**
     * @testdox It should support sending a templated email with attachments
     */
    public function testSendsTemplatedEmailWithAttachment()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email
            ->addAttachments(new FileAttachment(self::$file, 'file name.txt', null, null, 'UTF-16'))
            ->embed(new FileAttachment(self::$file, 'image.jpg'), 'inline');

        $expectedTemplate = [
            'results' => [
                'content' => [
                    'from' => [
                        'email' => 'template.sender@test.com',
                        'name'  => 'Template Address',
                    ],
                    'subject'  => 'Template Subject',
                    'reply_to' => '"Template Replier" <template.replier@test.com>',
                    'html'     => 'This is a template html test',
                    'headers'  => ['X-Header' => 'test'],
                ],
            ],
        ];

        $this->sparkPost
            ->shouldReceive('syncRequest')
            ->once()
            ->with('GET', 'templates/1234')
            ->andReturn(new SparkPostResponse(new Response(200, [], json_encode($expectedTemplate))));

        $expectedArray = [
            'content' => [
                'from'          => [
                    'email' => 'template.sender@test.com',
                    'name'  => 'Template Address',
                ],
                'subject'       => 'Template Subject',
                'html'          => 'This is a template html test',
                'text'          => null,
                'inline_images' => [
                    [
                        'name' => 'inline',
                        'type' => mime_content_type(self::$file) . '; charset="utf-8"',
                        'data' => base64_encode(file_get_contents(self::$file)),
                    ],
                ],
                'attachments'   => [
                    [
                        'name' => 'file name.txt',
                        'type' => mime_content_type(self::$file) . '; charset="UTF-16"',
                        'data' => base64_encode(file_get_contents(self::$file)),
                    ],
                ],
                'reply_to'      => '"Template Replier" <template.replier@test.com>',
                'headers'       => [
                    'X-Header' => 'test',
                ],
            ],
            'substitution_data' => [
                'test'       => 'value',
                'fromName'   => null,
                'fromEmail'  => 'sender',
                'fromDomain' => 'test.com',
                'subject'    => 'Subject',
            ],
            'recipients' => [
                [
                    'address' => [
                        'email'     => 'recipient@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, $this->success());

        $courier->deliver($email);
    }

    /**
     * @testdox It should replace templated CC headers when attaching to a templated email
     */
    public function testReplaceTemplatedEmailCc()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->addCcRecipients(new Address('cc@test.com'));
        $email->addAttachments(new FileAttachment(self::$file, 'file name.txt'));

        $expectedTemplate = [
            'results' => [
                'content' => [
                    'from' => [
                        'email' => 'template.sender@test.com',
                        'name'  => 'Template Address',
                    ],
                    'subject'  => 'Template Subject',
                    'reply_to' => '"Template Replier" <template.replier@test.com>',
                    'html'     => 'This is a template html test',
                    'headers'  => [
                        'X-Header' => 'test',
                        'CC'       => 'templated@test.com',
                    ],
                ],
            ],
        ];

        $this->sparkPost
            ->shouldReceive('syncRequest')
            ->once()
            ->with('GET', 'templates/1234')
            ->andReturn(new SparkPostResponse(new Response(200, [], json_encode($expectedTemplate))));

        $expectedArray = [
            'content' => [
                'from'          => [
                    'email' => 'template.sender@test.com',
                    'name'  => 'Template Address',
                ],
                'subject'       => 'Template Subject',
                'html'          => 'This is a template html test',
                'text'          => null,
                'inline_images' => [],
                'attachments'   => [
                    [
                        'name' => 'file name.txt',
                        'type' => mime_content_type(self::$file) . '; charset="utf-8"',
                        'data' => base64_encode(file_get_contents(self::$file)),
                    ],
                ],
                'reply_to'      => '"Template Replier" <template.replier@test.com>',
                'headers'       => [
                    'X-Header' => 'test',
                    'CC'       => 'cc@test.com',
                ],
            ],
            'substitution_data' => [
                'test'       => 'value',
                'fromName'   => null,
                'fromEmail'  => 'sender',
                'fromDomain' => 'test.com',
                'subject'    => 'Subject',
                'ccHeader'   => 'cc@test.com',
            ],
            'recipients' => [
                [
                    'address' => [
                        'email'     => 'recipient@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
                [
                    'address' => [
                        'email'     => 'cc@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, $this->success());

        $courier->deliver($email);
    }

    /**
     * @testdox It should support sending a templated email with an attachment and a templated from/replyTo
     */
    public function testHandlesTemplatedEmailsWithAttachmentAndDynamicSender()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->addReplyTos(new Address('dynamic@replyto.com'));

        $email->addAttachments(new FileAttachment(self::$file, 'file name.txt'));

        $expectedTemplate = [
            'results' => [
                'content' => [
                    'from' => [
                        'email' => '{{fromEmail}}@{{fromDomain}}',
                        'name'  => 'Template Address',
                    ],
                    'subject'  => 'Template Subject',
                    'reply_to' => '{{replyTo}}',
                    'html'     => 'This is a template html test',
                    'headers'  => ['X-Header' => 'test'],
                ],
            ],
        ];

        $this->sparkPost
            ->shouldReceive('syncRequest')
            ->once()
            ->with('GET', 'templates/1234')
            ->andReturn(new SparkPostResponse(new Response(200, [], json_encode($expectedTemplate))));

        $expectedArray = [
            'content' => [
                'from'          => [
                    'email' => 'sender@test.com',
                    'name'  => null,
                ],
                'subject'       => 'Template Subject',
                'html'          => 'This is a template html test',
                'text'          => null,
                'inline_images' => [],
                'attachments'   => [
                    [
                        'name' => 'file name.txt',
                        'type' => mime_content_type(self::$file) . '; charset="utf-8"',
                        'data' => base64_encode(file_get_contents(self::$file)),
                    ],
                ],
                'reply_to'      => 'dynamic@replyto.com',
                'headers'       => [
                    'X-Header' => 'test',
                ],
            ],
            'substitution_data' => [
                'test'       => 'value',
                'fromName'   => null,
                'fromEmail'  => 'sender',
                'fromDomain' => 'test.com',
                'subject'    => 'Subject',
                'replyTo'    => 'dynamic@replyto.com',
            ],
            'recipients' => [
                [
                    'address' => [
                        'email'     => 'recipient@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, $this->success());

        $courier->deliver($email);
    }

    /**
     * @testdox It should throw an error if the reply to is a templated value but not provided on the email
     */
    public function testHandlesDynamicTemplateMissingSender()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->addAttachments(new FileAttachment(self::$file, 'file name.txt'));

        $expectedTemplate = [
            'results' => [
                'content' => [
                    'from' => [
                        'email' => '{{fromEmail}}@{{fromDomain}}',
                        'name'  => 'Template Address',
                    ],
                    'subject'  => 'Template Subject',
                    'reply_to' => '{{replyTo}}',
                    'html'     => 'This is a template html test',
                    'headers'  => ['X-Header' => 'test'],
                ],
            ],
        ];

        $this->sparkPost
            ->shouldReceive('syncRequest')
            ->once()
            ->with('GET', 'templates/1234')
            ->andReturn(new SparkPostResponse(new Response(200, [], json_encode($expectedTemplate))));

        self::expectException(ValidationException::class);
        $courier->deliver($email);
    }

    /**
     * @testdox It should handle errors when searching for a template
     */
    public function testHandlesTemplateRetrievalErrors()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->addAttachments(new FileAttachment(self::$file, 'file name.txt'));

        $exception = new HttpException('Message', new Request('GET', 'stuff'), new Response(400, [], ''));

        $this->sparkPost
            ->shouldReceive('syncRequest')
            ->once()
            ->with('GET', 'templates/1234')
            ->andThrow(new SparkPostException($exception));

        self::expectException(TransmissionException::class);
        $courier->deliver($email);
    }

    /**
     * @testdox It should support all Email values
     */
    public function testSupportsAllValues()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'This is the Subject',
            SimpleContent::html('This is the html email')->addText('This is the text email'),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->setReplyTos(new Address('replyTo@test.com'));
        $email->setCcRecipients(new Address('cc@test.com', 'CC'));
        $email->setBccRecipients(new Address('bcc@test.com', 'BCC'));
        $email->setAttachments(new FileAttachment(self::$file));
        $email->embed(new FileAttachment(self::$file), 'inline');
        $email->setHeaders(new Header('X-Test-Header', 'test'));

        $expectedArray = [
            'content' => [
                'from'          => [
                    'name'  => null,
                    'email' => 'sender@test.com',
                ],
                'headers'       => [
                    'CC'            => '"CC" <cc@test.com>',
                    'X-Test-Header' => 'test',
                ],
                'subject'       => 'This is the Subject',
                'html'          => 'This is the html email',
                'text'          => 'This is the text email',
                'inline_images' => [
                    [
                        'name' => 'inline',
                        'type' => mime_content_type(self::$file) . '; charset="utf-8"',
                        'data' => base64_encode(file_get_contents(self::$file)),
                    ],
                ],
                'attachments'   => [
                    [
                        'name' => basename(self::$file),
                        'type' => mime_content_type(self::$file) . '; charset="utf-8"',
                        'data' => base64_encode(file_get_contents(self::$file)),
                    ],
                ],
                'reply_to'      => 'replyTo@test.com',
            ],
            'recipients' => [
                [
                    'address' => [
                        'email'     => 'recipient@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
                [
                    'address' => [
                        'email'     => 'cc@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
                [
                    'address' => [
                        'email'     => 'bcc@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, $this->success());

        $courier->deliver($email);
    }

    /**
     * @testdox It should handle an error transmitting the email to SparkPost
     */
    public function testHandlesTransmissionErrors()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new EmptyContent(),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $expectedArray = [
            'content' => [
                'from'          => [
                    'name'  => null,
                    'email' => 'sender@test.com',
                ],
                'subject'       => 'Subject',
                'html'          => '',
                'text'          => '',
                'inline_images' => [],
                'attachments'   => [],
                'reply_to'      => null,
                'headers'       => [],
            ],
            'recipients' => [
                [
                    'address' => [
                        'email'     => 'recipient@test.com',
                        'header_to' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $exception = new HttpException('Message', new Request('GET', 'stuff'), new Response(400, [], ''));
        $this->setExpectedCall($expectedArray, new SparkPostException($exception));

        self::expectException(TransmissionException::class);
        $courier->deliver($email);
    }

    /**
     * @testdox It should validate the input content is deliverable
     */
    public function testValidatesSupportedContent()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TestContent(),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        self::expectException(UnsupportedContentException::class);
        $courier->deliver($email);
    }

    /**
     * @param $expectedArray
     * @param SparkPostResponse|SparkPostException $response
     */
    private function setExpectedCall($expectedArray, $response)
    {
        /** @var Mockery\Mock|Transmission $transmission */
        $transmission = Mockery::mock(Transmission::class);

        /** @var Mockery\Mock|SparkPostPromise $promise */
        $promise = Mockery::mock(SparkPostPromise::class);

        $this->sparkPost->transmissions = $transmission;

        if ($response instanceof SparkPostException) {
            $promise
                ->shouldReceive('wait')
                ->once()
                ->andThrow($response);
        } else {
            $promise
                ->shouldReceive('wait')
                ->once()
                ->andReturn($response);
        }

        $transmission
            ->shouldReceive('post')
            ->once()
            ->with($expectedArray)
            ->andReturn($promise);
    }
}
