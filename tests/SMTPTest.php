<?php

namespace EZMAIL\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use EZMAIL\SMTP;

class SMTPTest extends TestCase
{
    public function testConnect()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "220-server");
        array_push($socket->readStringResults, "220-is");
        array_push($socket->readStringResults, "220 ready");
        
        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        $announcements = $smtp->connect();

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertEquals("localhost", $socket->openHost);
        $this->assertEquals(587, $socket->openPort);
        $this->assertEquals(30, $socket->openTimeout);
        $this->assertEmpty($socket->readStringResults);
        $this->assertEmpty($socket->writeStringData);

        $this->assertEquals(3, count($announcements));
        $this->assertEquals("server", $announcements[0]);
        $this->assertEquals("is", $announcements[1]);
        $this->assertEquals("ready", $announcements[2]);
    }

    public function testConnectWithout220Response()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "420 server not ready");

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        
        try
        {
            $smtp->connect();

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEquals("localhost", $socket->openHost);
            $this->assertEquals(587, $socket->openPort);
            $this->assertEquals(30, $socket->openTimeout);
            $this->assertEmpty($socket->readStringResults);
            $this->assertEmpty($socket->writeStringData);
            $this->assertEquals(
                "Invalid announcement response: 420",
                $ex->getMessage()
            );
        }
    }

    public function testConnectOnPort465()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "220 server ready");

        // Test.
        $smtp = new SMTP(
            "localhost",
            465,
            socket: $socket
        );
        $announcements = $smtp->connect();

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertEquals("ssl://localhost", $socket->openHost);
        $this->assertEquals(465, $socket->openPort);
        $this->assertEquals(30, $socket->openTimeout);
        $this->assertEmpty($socket->readStringResults);
        $this->assertEmpty($socket->writeStringData);

        $this->assertEquals(1, count($announcements));
        $this->assertEquals("server ready", $announcements[0]);
    }

    public function testDoHandshake()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "250 mailserver"); // EHLO
        array_push($socket->readStringResults, "220 server ready"); // STARTTLS
        array_push($socket->readStringResults, "250 mailserver"); // EHLO

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        $smtp->doHandshake();

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertTrue($socket->isCryptoEnabled);
        $this->assertEmpty($socket->readStringResults);
        
        $this->assertEquals(3, count($socket->writeStringData));
        $this->assertEquals("EHLO localhost" . PHP_CRLF, $socket->writeStringData[0]);
        $this->assertEquals("STARTTLS" . PHP_CRLF, $socket->writeStringData[1]);
        $this->assertEquals("EHLO localhost" . PHP_CRLF, $socket->writeStringData[2]);
    }

    public function testDoHandshakeWithHELO()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "420 whatsthat"); // EHLO
        array_push($socket->readStringResults, "250 mailserver"); // HELO
        array_push($socket->readStringResults, "220 server ready"); // STARTTLS
        array_push($socket->readStringResults, "250 mailserver"); // HELO

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        $smtp->doHandshake();

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertTrue($socket->isCryptoEnabled);
        $this->assertEmpty($socket->readStringResults);
        
        $this->assertEquals(4, count($socket->writeStringData));
        $this->assertEquals("EHLO localhost" . PHP_CRLF, $socket->writeStringData[0]);
        $this->assertEquals("HELO localhost" . PHP_CRLF, $socket->writeStringData[1]);
        $this->assertEquals("STARTTLS" . PHP_CRLF, $socket->writeStringData[2]);
        $this->assertEquals("HELO localhost" . PHP_CRLF, $socket->writeStringData[3]);
    }

    public function testDoHandshakeHandleHELOError()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "420 whatsthat"); // EHLO
        array_push($socket->readStringResults, "421 whatsthat"); // HELO

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doHandshake();

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertFalse($socket->isCryptoEnabled);
            $this->assertEmpty($socket->readStringResults);
            
            $this->assertEquals(2, count($socket->writeStringData));
            $this->assertEquals("EHLO localhost" . PHP_CRLF, $socket->writeStringData[0]);
            $this->assertEquals("HELO localhost" . PHP_CRLF, $socket->writeStringData[1]);

            $this->assertEquals("Invalid HELO response: 421", $ex->getMessage());
        }
    }

    public function testDoHandshakeHandleSTARTTLSError()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "250 mailserver"); // EHLO
        array_push($socket->readStringResults, "420 what"); // STARTTLS
        
        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doHandshake();

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertFalse($socket->isCryptoEnabled);
            $this->assertEmpty($socket->readStringResults);
            
            $this->assertEquals(2, count($socket->writeStringData));
            $this->assertEquals("EHLO localhost" . PHP_CRLF, $socket->writeStringData[0]);
            $this->assertEquals("STARTTLS" . PHP_CRLF, $socket->writeStringData[1]);

            $this->assertEquals("Invalid STARTTLS response: 420", $ex->getMessage());
        }
    }

    public function testDoHandshakeHandleEHLOErrorAfterSTARTTLS()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "250 mailserver"); // EHLO
        array_push($socket->readStringResults, "220 server ready"); // STARTTLS
        array_push($socket->readStringResults, "420 what"); // EHLO

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doHandshake();

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertTrue($socket->isCryptoEnabled);
            $this->assertEmpty($socket->readStringResults);
            
            $this->assertEquals(3, count($socket->writeStringData));
            $this->assertEquals("EHLO localhost" . PHP_CRLF, $socket->writeStringData[0]);
            $this->assertEquals("STARTTLS" . PHP_CRLF, $socket->writeStringData[1]);
            $this->assertEquals("EHLO localhost" . PHP_CRLF, $socket->writeStringData[2]);

            $this->assertEquals("Unable to do EHLO after STARTTLS", $ex->getMessage());
        }
    }

    public function testDoHandshakeHandleHELOErrorAfterSTARTTLS()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "420 whatsthat"); // EHLO
        array_push($socket->readStringResults, "250 mailserver"); // HELO
        array_push($socket->readStringResults, "220 server ready"); // STARTTLS
        array_push($socket->readStringResults, "420 what"); // HELO

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        
        try
        {
            $smtp->doHandshake();

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertTrue($socket->isCryptoEnabled);
            $this->assertEmpty($socket->readStringResults);
            
            $this->assertEquals(4, count($socket->writeStringData));
            $this->assertEquals("EHLO localhost" . PHP_CRLF, $socket->writeStringData[0]);
            $this->assertEquals("HELO localhost" . PHP_CRLF, $socket->writeStringData[1]);
            $this->assertEquals("STARTTLS" . PHP_CRLF, $socket->writeStringData[2]);
            $this->assertEquals("HELO localhost" . PHP_CRLF, $socket->writeStringData[3]);

            $this->assertEquals("Invalid HELO response: 420", $ex->getMessage());
        }
    }

    public function testDoHandshakeIgnoreSSL()
    {
        // Fake socket.
        $socket = new FakeSocket;

        // connect
        array_push($socket->readStringResults, "220 server ready");

        // doHandshake
        array_push($socket->readStringResults, "250 mailserver"); // EHLO

        // Test.
        $smtp = new SMTP(
            "localhost",
            465,
            socket: $socket
        );
        $smtp->connect();
        $socket->writeStringData = [];
        $smtp->doHandshake();

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertFalse($socket->isCryptoEnabled);
        $this->assertEmpty($socket->readStringResults);

        $this->assertEquals(1, count($socket->writeStringData));
        $this->assertEquals("EHLO localhost" . PHP_CRLF, $socket->writeStringData[0]);
    }

    public function testDoAuth()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "334 " . base64_encode("Username:")); // AUTH LOGIN
        array_push($socket->readStringResults, "334 " . base64_encode("Password:")); // after username
        array_push($socket->readStringResults, "235 2.7.0 Authentication successful"); // after password

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        $smtp->doAuth(
            "user",
            "password123"
        );

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertEmpty($socket->readStringResults);

        $this->assertEquals(3, count($socket->writeStringData));
        $this->assertEquals("AUTH LOGIN" . PHP_CRLF, $socket->writeStringData[0]);
        $this->assertEquals(base64_encode("user") . PHP_CRLF, $socket->writeStringData[1]);
        $this->assertEquals(base64_encode("password123") . PHP_CRLF, $socket->writeStringData[2]);
    }

    public function testDoAuthHandleInvalidUsernameCode()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "420 " . base64_encode("Username:")); // AUTH LOGIN

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doAuth(
                "user",
                "password123"
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(1, count($socket->writeStringData));
            $this->assertEquals("AUTH LOGIN" . PHP_CRLF, $socket->writeStringData[0]);

            $this->assertEquals("Invalid AUTH LOGIN response: 420", $ex->getMessage());
        }
    }

    public function testDoAuthHandleInvalidUsernameRequest()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "334 " . base64_encode("what")); // AUTH LOGIN

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doAuth(
                "user",
                "password123"
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(1, count($socket->writeStringData));
            $this->assertEquals("AUTH LOGIN" . PHP_CRLF, $socket->writeStringData[0]);

            $this->assertEquals("Invalid SMTP username prompt", $ex->getMessage());
        }
    }

    public function testDoAuthHandleInvalidPasswordCode()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "334 " . base64_encode("Username:")); // AUTH LOGIN
        array_push($socket->readStringResults, "420 " . base64_encode("Password:")); // after username

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doAuth(
                "user",
                "password123"
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(2, count($socket->writeStringData));
            $this->assertEquals("AUTH LOGIN" . PHP_CRLF, $socket->writeStringData[0]);
            $this->assertEquals(base64_encode("user") . PHP_CRLF, $socket->writeStringData[1]);

            $this->assertEquals("Invalid AUTH LOGIN username response: 420", $ex->getMessage());
        }
    }

    public function testDoAuthHandleInvalidPasswordRequest()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "334 " . base64_encode("Username:")); // AUTH LOGIN
        array_push($socket->readStringResults, "334 " . base64_encode("what")); // after username

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doAuth(
                "user",
                "password123"
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(2, count($socket->writeStringData));
            $this->assertEquals("AUTH LOGIN" . PHP_CRLF, $socket->writeStringData[0]);
            $this->assertEquals(base64_encode("user") . PHP_CRLF, $socket->writeStringData[1]);

            $this->assertEquals("Invalid SMTP password prompt", $ex->getMessage());
        }
    }

    public function testDoAuthHandleAuthenticationFailed()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "334 " . base64_encode("Username:")); // AUTH LOGIN
        array_push($socket->readStringResults, "334 " . base64_encode("Password:")); // after username
        array_push($socket->readStringResults, "535 2.7.0 Authentication failed"); // after password

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doAuth(
                "user",
                "password123"
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(3, count($socket->writeStringData));
            $this->assertEquals("AUTH LOGIN" . PHP_CRLF, $socket->writeStringData[0]);
            $this->assertEquals(base64_encode("user") . PHP_CRLF, $socket->writeStringData[1]);
            $this->assertEquals(base64_encode("password123") . PHP_CRLF, $socket->writeStringData[2]);

            $this->assertEquals("SMTP authentication failed", $ex->getMessage());
        }
    }

    public function testDoAuthHandleInvalidAuthenticationResponse()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "334 " . base64_encode("Username:")); // AUTH LOGIN
        array_push($socket->readStringResults, "334 " . base64_encode("Password:")); // after username
        array_push($socket->readStringResults, "420 what"); // after password

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doAuth(
                "user",
                "password123"
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(3, count($socket->writeStringData));
            $this->assertEquals("AUTH LOGIN" . PHP_CRLF, $socket->writeStringData[0]);
            $this->assertEquals(base64_encode("user") . PHP_CRLF, $socket->writeStringData[1]);
            $this->assertEquals(base64_encode("password123") . PHP_CRLF, $socket->writeStringData[2]);

            $this->assertEquals("Invalid SMTP authentication response: 420", $ex->getMessage());
        }
    }

    public function testDoAuthHandlePlain()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "334"); // AUTH PLAIN
        array_push($socket->readStringResults, "235 Authentication successful"); // after username password

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        $smtp->doAuth(
            "user",
            "password123",
            SMTP::AUTH_TYPE_PLAIN
        );

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertEmpty($socket->readStringResults);

        $this->assertEquals(2, count($socket->writeStringData));
        $this->assertEquals("AUTH PLAIN" . PHP_CRLF, $socket->writeStringData[0]);
        $this->assertEquals(base64_encode("\0user\0password123") . PHP_CRLF, $socket->writeStringData[1]);
    }

    public function testDoAuthHandlePlainInvalidAuthPlainResponseCode()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "420 what"); // AUTH PLAIN

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doAuth(
                "user",
                "password123",
                SMTP::AUTH_TYPE_PLAIN
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(1, count($socket->writeStringData));
            $this->assertEquals("AUTH PLAIN" . PHP_CRLF, $socket->writeStringData[0]);

            $this->assertEquals("Invalid AUTH PLAIN response: 420", $ex->getMessage());
        }
    }

    public function testDoAuthHandlePlainAuthenticationFailed()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "334"); // AUTH PLAIN
        array_push($socket->readStringResults, "535 Authentication failed"); // after username password

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doAuth(
                "user",
                "password123",
                SMTP::AUTH_TYPE_PLAIN
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(2, count($socket->writeStringData));
            $this->assertEquals("AUTH PLAIN" . PHP_CRLF, $socket->writeStringData[0]);
            $this->assertEquals(base64_encode("\0user\0password123") . PHP_CRLF, $socket->writeStringData[1]);

            $this->assertEquals("SMTP authentication failed", $ex->getMessage());
        }
    }

    public function testDoAuthHandlePlainInvalidAuthenticationResponse()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "334"); // AUTH PLAIN
        array_push($socket->readStringResults, "420 what"); // after username password

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doAuth(
                "user",
                "password123",
                SMTP::AUTH_TYPE_PLAIN
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(2, count($socket->writeStringData));
            $this->assertEquals("AUTH PLAIN" . PHP_CRLF, $socket->writeStringData[0]);
            $this->assertEquals(base64_encode("\0user\0password123") . PHP_CRLF, $socket->writeStringData[1]);

            $this->assertEquals("Invalid SMTP authentication response: 420", $ex->getMessage());
        }
    }

    public function testDoAuthHandle2Auth()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "235 Authentication successful"); // AUTH XOAUTH2

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        $smtp->doAuth(
            "user",
            "password123",
            SMTP::AUTH_TYPE_2AUTH
        );

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertEmpty($socket->readStringResults);

        $this->assertEquals(1, count($socket->writeStringData));
        $token = base64_encode(sprintf("user=%s%sauth=Bearer %s%s%s",
            "user", chr(1),
            "password123", chr(1), chr(1)
        ));
        $this->assertEquals("AUTH XOAUTH2 " . $token . PHP_CRLF, $socket->writeStringData[0]);
    }

    public function testDoAuthHandle2AuthAuthenticationFailed()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "535 Authentication failed"); // AUTH XOAUTH2

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doAuth(
                "user",
                "password123",
                SMTP::AUTH_TYPE_2AUTH
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(1, count($socket->writeStringData));
            $token = base64_encode(sprintf("user=%s%sauth=Bearer %s%s%s",
                "user", chr(1),
                "password123", chr(1), chr(1)
            ));
            $this->assertEquals("AUTH XOAUTH2 " . $token . PHP_CRLF, $socket->writeStringData[0]);

            $this->assertEquals("SMTP authentication failed", $ex->getMessage());
        }
    }

    public function testDoAuthHandle2AuthInvalidAuthenticationResponse()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "420 what"); // AUTH XOAUTH2

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doAuth(
                "user",
                "password123",
                SMTP::AUTH_TYPE_2AUTH
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(1, count($socket->writeStringData));
            $token = base64_encode(sprintf("user=%s%sauth=Bearer %s%s%s",
                "user", chr(1),
                "password123", chr(1), chr(1)
            ));
            $this->assertEquals("AUTH XOAUTH2 " . $token . PHP_CRLF, $socket->writeStringData[0]);

            $this->assertEquals("Invalid SMTP authentication response: 420", $ex->getMessage());
        }
    }

    public function testDoAuthHandleUnsupportedType()
    {
        // Fake socket.
        $socket = new FakeSocket;

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->doAuth(
                "user",
                "password123",
                100
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->writeStringData);
            $this->assertEquals("Invalid auth type: 100", $ex->getMessage());
        }
    }

    public function testStartSendMail()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "250 2.1.0 Sender OK"); // MAIL FROM
        array_push($socket->readStringResults, "250 2.1.5 Recipient OK"); // RCPT TO
        array_push($socket->readStringResults, "250 2.1.5 Recipient OK"); // RCPT TO
        array_push($socket->readStringResults, "354 Start mail input; end with <CRLF>.CRLF>"); // DATA

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        $smtp->startSendMail(
            "sender@mail.com",
            array(
                "Receiver 1" => "recv1@mail.com",
                "Receiver 2" => "recv2@mail.com"
            )
        );

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertEmpty($socket->readStringResults);

        $this->assertEquals(4, count($socket->writeStringData));
        $this->assertEquals("MAIL FROM:<sender@mail.com>" . PHP_CRLF, $socket->writeStringData[0]);
        $this->assertEquals("RCPT TO:<recv1@mail.com>" . PHP_CRLF, $socket->writeStringData[1]);
        $this->assertEquals("RCPT TO:<recv2@mail.com>" . PHP_CRLF, $socket->writeStringData[2]);
        $this->assertEquals("DATA" . PHP_CRLF, $socket->writeStringData[3]);
    }

    public function testStartSendMailHandleSenderNotOK()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "420 what"); // MAIL FROM

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->startSendMail(
                "sender@mail.com",
                array("Receiver 1" => "recv1@mail.com")
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(1, count($socket->writeStringData));
            $this->assertEquals("MAIL FROM:<sender@mail.com>" . PHP_CRLF, $socket->writeStringData[0]);

            $this->assertEquals("Invalid MAIL FROM response: 420", $ex->getMessage());
        }
    }

    public function testStartSendMailHandleRecipientNotOK()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "250 2.1.0 Sender OK"); // MAIL FROM
        array_push($socket->readStringResults, "420 what"); // RCPT TO

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->startSendMail(
                "sender@mail.com",
                array("Receiver 1" => "recv1@mail.com")
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(2, count($socket->writeStringData));
            $this->assertEquals("MAIL FROM:<sender@mail.com>" . PHP_CRLF, $socket->writeStringData[0]);
            $this->assertEquals("RCPT TO:<recv1@mail.com>" . PHP_CRLF, $socket->writeStringData[1]);

            $this->assertEquals("Invalid RCPT TO response: 420", $ex->getMessage());
        }
    }

    public function testStartSendMailHandleDataError()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "250 2.1.0 Sender OK"); // MAIL FROM
        array_push($socket->readStringResults, "250 2.1.5 Recipient OK"); // RCPT TO
        array_push($socket->readStringResults, "420 what"); // DATA

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->startSendMail(
                "sender@mail.com",
                array("Receiver 1" => "recv1@mail.com")
            );

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(3, count($socket->writeStringData));
            $this->assertEquals("MAIL FROM:<sender@mail.com>" . PHP_CRLF, $socket->writeStringData[0]);
            $this->assertEquals("RCPT TO:<recv1@mail.com>" . PHP_CRLF, $socket->writeStringData[1]);
            $this->assertEquals("DATA" . PHP_CRLF, $socket->writeStringData[2]);

            $this->assertEquals("Invalid DATA response: 420", $ex->getMessage());
        }
    }

    public function testWriteMailData()
    {
        // Fake socket.
        $socket = new FakeSocket;

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        $smtp->writeMailData("MIME-Version: 1.0");
        $smtp->writeMailData("Message-ID: 123");

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertEquals(2, count($socket->writeStringData));
        $this->assertEquals("MIME-Version: 1.0" . PHP_CRLF, $socket->writeStringData[0]);
        $this->assertEquals("Message-ID: 123" . PHP_CRLF, $socket->writeStringData[1]);
    }

    public function testEndSendMail()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "250 2.0.0 OK <111@host.com>"); // .

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        $mailId = $smtp->endSendMail();

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertEmpty($socket->readStringResults);

        $this->assertEquals(1, count($socket->writeStringData));
        $this->assertEquals("." . PHP_CRLF, $socket->writeStringData[0]);

        $this->assertEquals("111@host.com", $mailId);
    }

    public function testEndSendMailHandleError()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "420 what"); // DATA

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->endSendMail();

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(1, count($socket->writeStringData));
            $this->assertEquals("." . PHP_CRLF, $socket->writeStringData[0]);

            $this->assertEquals("Invalid DATA end response: 420", $ex->getMessage());
        }
    }

    public function testEndSendMailHandleInvalidResponseLength()
    {
        // Fake socket.
        $socket = new FakeSocket;
        array_push($socket->readStringResults, "250 what"); // DATA

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );

        try
        {
            $smtp->endSendMail();

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertFalse($socket->isClosed);
            $this->assertEmpty($socket->readStringResults);

            $this->assertEquals(1, count($socket->writeStringData));
            $this->assertEquals("." . PHP_CRLF, $socket->writeStringData[0]);

            $this->assertEquals("Invalid DATA response: what", $ex->getMessage());
        }
    }

    public function testQuit()
    {
        // Fake socket.
        $socket = new FakeSocket;

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        $smtp->quit();

        // Assert.
        $this->assertTrue($socket->isClosed);

        $this->assertEquals(1, count($socket->writeStringData));
        $this->assertEquals("QUIT" . PHP_CRLF, $socket->writeStringData[0]);
    }

    public function testQuitIgnoreError()
    {
        // Fake socket.
        $socket = new FakeSocket;
        $socket->writeStringError = new Exception("error");

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            socket: $socket
        );
        
        try
        {
            $smtp->quit();

            // No error.
            $this->fail();
        }
        catch (Exception $ex)
        {
            // Assert.
            $this->assertTrue($socket->isClosed);
            $this->assertEmpty($socket->writeStringData);
            $this->assertEquals("error", $ex->getMessage());
        }
    }
}

?>