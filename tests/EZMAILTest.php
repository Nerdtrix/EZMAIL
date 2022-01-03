<?php

namespace EZMAIL\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use EZMAIL\EZMAIL;
use EZMAIL\SMTP;
use InvalidArgumentException;

class EZMAILTest extends TestCase
{
    private FakeSMTPFactory $smtpFactory;
    private FakeMailIdGenerator $mailIdGenerator;
    private FakeMailBuilder $mailBuilder;
    private EZMAIL $ezmail;

    public function setUp() : void
    {
        $this->smtpFactory = new FakeSMTPFactory;
        $this->mailIdGenerator = new FakeMailIdGenerator;
        $this->mailBuilder = new FakeMailBuilder;
        $this->ezmail = new EZMAIL(
            smtpFactory: $this->smtpFactory,
            mailIdGenerator: $this->mailIdGenerator,
            mailBuilder: $this->mailBuilder
        );
    }

    public function testSend()
    {
        // Fake mail id.
        $this->mailIdGenerator->result = "111";

        // Fake mail builder writer data.
        $this->mailBuilder->header = [ "H1", "H2" ];
        $this->mailBuilder->body = [ "B1" ];

        // Fake smtp send result.
        $this->smtpFactory->result->endSendMailResult = "111";

        // Parameters.
        $this->ezmail->subject = "this is subject";
        $this->ezmail->body = "this is message";
        $this->ezmail->from = [ "Mr From" => "from@mail.com" ];
        $this->ezmail->to = [ "Mr Recv" => "recv@mail.com" ];
        $this->ezmail->cc = [ "Mr Cc" => "cc@mail.com" ];
        $this->ezmail->bcc = [ "Mr Bcc" => "bcc@mail.com" ];
        $this->ezmail->replyTo = [ "Mr Reply" => "reply-to@mail.com" ];
        $this->ezmail->attachments = [ "file.txt" ];
        $this->ezmail->bounceAddress = "bounce@mail.com";

        $this->ezmail->appName = "Test App";
        $this->ezmail->hostName = "smtp.mail.com";
        $this->ezmail->portNumber = 123;
        $this->ezmail->timeout = 10;
        $this->ezmail->username = "user@mail.com";
        $this->ezmail->password = "password123";
        $this->ezmail->authToken = "token123";

        // Test.
        $result = $this->ezmail->send();

        // Assert.
        $this->assertEquals("111", $result);

        $this->assertEquals("smtp.mail.com", $this->smtpFactory->hostName);
        $this->assertEquals(123, $this->smtpFactory->portNumber);
        $this->assertEquals(10, $this->smtpFactory->timeout);
        $this->assertEquals("user@mail.com", $this->smtpFactory->result->authUsername);
        $this->assertEquals("password123", $this->smtpFactory->result->authPassword);
        $this->assertEquals(SMTP::AUTH_TYPE_STANDARD, $this->smtpFactory->result->authType);
        $this->assertTrue($this->smtpFactory->result->isConnected);
        $this->assertTrue($this->smtpFactory->result->doneHandshake);
        $this->assertTrue($this->smtpFactory->result->hasQuit);

        $this->assertEquals(3, count($this->smtpFactory->result->writeMailDataArgs));
        $this->assertEquals("H1", $this->smtpFactory->result->writeMailDataArgs[0]);
        $this->assertEquals("H2", $this->smtpFactory->result->writeMailDataArgs[1]);
        $this->assertEquals("B1", $this->smtpFactory->result->writeMailDataArgs[2]);

        $this->assertEquals(1, count($this->mailBuilder->buildArgs));
        $buildArgs = $this->mailBuilder->buildArgs[0];
        $this->assertEquals($this->ezmail, $buildArgs["writer"]);
        $this->assertEquals("this is subject", $buildArgs["subject"]);
        $this->assertEquals("this is message", $buildArgs["message"]);
        $this->assertEquals([ "Mr From" => "from@mail.com" ], $buildArgs["from"]);
        $this->assertEquals([ "Mr Recv" => "recv@mail.com" ], $buildArgs["to"]);
        $this->assertEquals([ "Mr Cc" => "cc@mail.com" ], $buildArgs["cc"]);
        $this->assertEquals([ "Mr Bcc" => "bcc@mail.com" ], $buildArgs["bcc"]);
        $this->assertEquals([ "Mr Reply" => "reply-to@mail.com" ], $buildArgs["replyTo"]);
        $this->assertEquals([ "file.txt" ], $buildArgs["attachments"]);
        $this->assertEquals("bounce@mail.com", $buildArgs["bounceAddress"]);
        $this->assertEquals("Test App", $buildArgs["appName"]);
    }

    public function testSendValidate()
    {
        try
        {
            // Empty subject.
            $this->ezmail->send();
            $this->fail();
        }
        catch (InvalidArgumentException $ex)
        {
            $this->assertEquals("Message subject is empty", $ex->getMessage());
        }

        $this->ezmail->subject = "this is subject";

        try
        {
            // Empty body.
            $this->ezmail->send();
            $this->fail();
        }
        catch (InvalidArgumentException $ex)
        {
            $this->assertEquals("Message body is empty", $ex->getMessage());
        }
        
        $this->ezmail->body = "this is body";

        try
        {
            // Empty recipient.
            $this->ezmail->send();
            $this->fail();
        }
        catch (InvalidArgumentException $ex)
        {
            $this->assertEquals("No message recipients", $ex->getMessage());
        }
        
        $this->ezmail->to = [
            "test@mail.com"
        ];

        try
        {
            // Empty hostname.
            $this->ezmail->send();
            $this->fail();
        }
        catch (InvalidArgumentException $ex)
        {
            $this->assertEquals("Hostname is empty", $ex->getMessage());
        }

        $this->ezmail->hostName = "smtp.server.com";

        try
        {
            // Empty username.
            $this->ezmail->send();
            $this->fail();
        }
        catch (InvalidArgumentException $ex)
        {
            $this->assertEquals("Username is empty", $ex->getMessage());
        }

        $this->ezmail->username = "sender@server.com";

        try
        {
            // Empty password.
            $this->ezmail->send();
            $this->fail();
        }
        catch (InvalidArgumentException $ex)
        {
            $this->assertEquals("Password is empty", $ex->getMessage());
        }

        $this->ezmail->authType = SMTP::AUTH_TYPE_2AUTH;

        try
        {
            // Empty auth token.
            $this->ezmail->send();
            $this->fail();
        }
        catch (InvalidArgumentException $ex)
        {
            $this->assertEquals("Auth token is empty", $ex->getMessage());
        }

        $this->ezmail->authToken = "123";
        $this->ezmail->from = [
            "Sender" => "sender@mail.com",
            "sender2@mail.com"
        ];

        try
        {
            // Too many sender.
            $this->ezmail->send();
            $this->fail();
        }
        catch (InvalidArgumentException $ex)
        {
            $this->assertEquals("Too many sender", $ex->getMessage());
        }

        $this->assertNull($this->smtpFactory->hostName);
    }

    public function testSendHandleConnectError()
    {
        // Connect error.
        $this->smtpFactory->result->connectException = new Exception("connect");

        // Parameters.
        $this->ezmail->subject = "this is subject";
        $this->ezmail->body = "this is message";
        $this->ezmail->from = [ "Mr From" => "from@mail.com" ];
        $this->ezmail->to = [ "Mr Recv" => "recv@mail.com" ];

        $this->ezmail->hostName = "smtp.mail.com";
        $this->ezmail->portNumber = 123;
        $this->ezmail->username = "user@mail.com";
        $this->ezmail->password = "password123";

        try
        {
            // Test.
            $this->ezmail->send();

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertEquals("smtp.mail.com", $this->smtpFactory->hostName);
            $this->assertEquals(123, $this->smtpFactory->portNumber);
            $this->assertFalse($this->smtpFactory->result->isConnected);
            $this->assertFalse($this->smtpFactory->result->doneHandshake);
            $this->assertTrue($this->smtpFactory->result->hasQuit);
            $this->assertEquals("connect", $ex->getMessage());
        }
    }

    public function testSendHandle2AUTH()
    {
        // Fake mail id.
        $this->mailIdGenerator->result = "111";

        // Fake smtp send result.
        $this->smtpFactory->result->endSendMailResult = "111";

        // Parameters.
        $this->ezmail->subject = "this is subject";
        $this->ezmail->body = "this is message";
        $this->ezmail->to = [ "Mr Recv" => "recv@mail.com" ];

        $this->ezmail->appName = "Test App";
        $this->ezmail->hostName = "smtp.mail.com";
        $this->ezmail->portNumber = 123;
        $this->ezmail->username = "user@mail.com";
        $this->ezmail->password = "password123";
        $this->ezmail->authToken = "token123";
        $this->ezmail->authType = SMTP::AUTH_TYPE_2AUTH;

        // Test.
        $this->ezmail->send();

        // Assert.
        $this->assertEquals("user@mail.com", $this->smtpFactory->result->authUsername);
        $this->assertEquals("token123", $this->smtpFactory->result->authPassword);
        $this->assertEquals(SMTP::AUTH_TYPE_2AUTH, $this->smtpFactory->result->authType);
    }

    public function testSendHandleEmptyFrom()
    {
        // Fake mail id.
        $this->mailIdGenerator->result = "111";

        // Fake smtp send result.
        $this->smtpFactory->result->endSendMailResult = "111";

        // Parameters.
        $this->ezmail->subject = "this is subject";
        $this->ezmail->body = "this is message";
        $this->ezmail->to = [ "Mr Recv" => "recv@mail.com" ];

        $this->ezmail->appName = "Test App";
        $this->ezmail->hostName = "smtp.mail.com";
        $this->ezmail->portNumber = 123;
        $this->ezmail->username = "user@mail.com";
        $this->ezmail->password = "password123";
        $this->ezmail->authToken = "token123";

        // Test.
        $this->ezmail->send();

        // Assert.
        $this->assertEquals(1, count($this->mailBuilder->buildArgs));
        $buildArgs = $this->mailBuilder->buildArgs[0];
        $this->assertEquals($this->ezmail, $buildArgs["writer"]);
        $this->assertEquals([ "user@mail.com" ], $buildArgs["from"]);
    }

    public function testSendHandleEmptyReplyTo()
    {
        // Fake mail id.
        $this->mailIdGenerator->result = "111";

        // Fake smtp send result.
        $this->smtpFactory->result->endSendMailResult = "111";

        // Parameters.
        $this->ezmail->subject = "this is subject";
        $this->ezmail->body = "this is message";
        $this->ezmail->from = [ "Mr From" => "from@mail.com" ];
        $this->ezmail->to = [ "Mr Recv" => "recv@mail.com" ];

        $this->ezmail->appName = "Test App";
        $this->ezmail->hostName = "smtp.mail.com";
        $this->ezmail->portNumber = 123;
        $this->ezmail->username = "user@mail.com";
        $this->ezmail->password = "password123";
        $this->ezmail->authToken = "token123";

        // Test.
        $this->ezmail->send();

        // Assert.
        $this->assertEquals(1, count($this->mailBuilder->buildArgs));
        $buildArgs = $this->mailBuilder->buildArgs[0];
        $this->assertEquals($this->ezmail, $buildArgs["writer"]);
        $this->assertEquals([ "user@mail.com" ], $buildArgs["replyTo"]);
    }

    public function testSendHandleEmptyBounceAddress()
    {
        // Fake mail id.
        $this->mailIdGenerator->result = "111";

        // Fake smtp send result.
        $this->smtpFactory->result->endSendMailResult = "111";

        // Parameters.
        $this->ezmail->subject = "this is subject";
        $this->ezmail->body = "this is message";
        $this->ezmail->from = [ "Mr From" => "from@mail.com" ];
        $this->ezmail->to = [ "Mr Recv" => "recv@mail.com" ];

        $this->ezmail->appName = "Test App";
        $this->ezmail->hostName = "smtp.mail.com";
        $this->ezmail->portNumber = 123;
        $this->ezmail->username = "user@mail.com";
        $this->ezmail->password = "password123";
        $this->ezmail->authToken = "token123";

        // Test.
        $this->ezmail->send();

        // Assert.
        $this->assertEquals(1, count($this->mailBuilder->buildArgs));
        $buildArgs = $this->mailBuilder->buildArgs[0];
        $this->assertEquals($this->ezmail, $buildArgs["writer"]);
        $this->assertEquals("user@mail.com", $buildArgs["bounceAddress"]);
    }

    public function testSendDoMessageIdValidation()
    {
        // Fake mail id.
        $this->mailIdGenerator->result = "111";

        // Fake smtp send result.
        $this->smtpFactory->result->endSendMailResult = "112";

        // Parameters.
        $this->ezmail->subject = "this is subject";
        $this->ezmail->body = "this is message";
        $this->ezmail->to = [ "Mr Recv" => "recv@mail.com" ];
        $this->ezmail->skipMessageIdValidation = false;

        $this->ezmail->appName = "Test App";
        $this->ezmail->hostName = "smtp.mail.com";
        $this->ezmail->portNumber = 123;
        $this->ezmail->username = "user@mail.com";
        $this->ezmail->password = "password123";
        $this->ezmail->authToken = "token123";

        try
        {
            // Test.
            $this->ezmail->send();

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertEquals("smtp.mail.com", $this->smtpFactory->hostName);
            $this->assertEquals(123, $this->smtpFactory->portNumber);
            $this->assertTrue($this->smtpFactory->result->isConnected);
            $this->assertTrue($this->smtpFactory->result->doneHandshake);
            $this->assertTrue($this->smtpFactory->result->hasQuit);
            $this->assertEquals("Unable to verify mail id. Expected: 111. Got: 112.", $ex->getMessage());
        }
    }

    public function testSendHandleMismatchMailId()
    {
        // Fake mail id.
        $this->mailIdGenerator->result = "111";

        // Fake smtp send result.
        $this->smtpFactory->result->endSendMailResult = "112";

        // Parameters.
        $this->ezmail->subject = "this is subject";
        $this->ezmail->body = "this is message";
        $this->ezmail->to = [ "Mr Recv" => "recv@mail.com" ];

        $this->ezmail->appName = "Test App";
        $this->ezmail->hostName = "smtp.mail.com";
        $this->ezmail->portNumber = 123;
        $this->ezmail->username = "user@mail.com";
        $this->ezmail->password = "password123";
        $this->ezmail->authToken = "token123";

        // Test.
        $result = $this->ezmail->send();

        // Assert.
        $this->assertEquals("112", $result);
        $this->assertEquals("smtp.mail.com", $this->smtpFactory->hostName);
        $this->assertEquals(123, $this->smtpFactory->portNumber);
        $this->assertTrue($this->smtpFactory->result->isConnected);
        $this->assertTrue($this->smtpFactory->result->doneHandshake);
        $this->assertTrue($this->smtpFactory->result->hasQuit);
    }
}

?>