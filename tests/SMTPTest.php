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
        array_push($socket->readStringResults, "220-server ");
        array_push($socket->readStringResults, "220-rea");
        array_push($socket->readStringResults, "220 dy");
        
        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            "user",
            "password",
            socket: $socket
        );
        $announcement = $smtp->connect();

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertEquals("localhost", $socket->openHost);
        $this->assertEquals(587, $socket->openPort);
        $this->assertEquals(30, $socket->openTimeout);
        $this->assertEmpty($socket->readStringResults);
        $this->assertEmpty($socket->writeStringData);
        $this->assertEquals("server ready", $announcement);
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
            "user",
            "password",
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
            "user",
            "password",
            socket: $socket
        );
        $announcement = $smtp->connect();

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertEquals("ssl://localhost", $socket->openHost);
        $this->assertEquals(465, $socket->openPort);
        $this->assertEquals(30, $socket->openTimeout);
        $this->assertEmpty($socket->readStringResults);
        $this->assertEmpty($socket->writeStringData);
        $this->assertEquals("server ready", $announcement);
    }

    public function testDoHandshake()
    {
        // Fake socket.
        $socket = new FakeSocket;

        // connect
        array_push($socket->readStringResults, "220 server ready");

        // doHandshake
        array_push($socket->readStringResults, "250 mailserver"); // EHLO
        array_push($socket->readStringResults, "220 server ready"); // STARTTLS
        array_push($socket->readStringResults, "250 mailserver"); // EHLO

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            "user",
            "password",
            socket: $socket
        );
        $smtp->connect();
        $socket->writeStringData = [];
        $smtp->doHandshake();

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertTrue($socket->isCryptoEnabled);
        $this->assertEmpty($socket->readStringResults);
        
        $this->assertEquals(3, count($socket->writeStringData));
        $this->assertEquals("EHLO localhost" . PHP_EOL, $socket->writeStringData[0]);
        $this->assertEquals("STARTTLS" . PHP_EOL, $socket->writeStringData[1]);
        $this->assertEquals("EHLO localhost" . PHP_EOL, $socket->writeStringData[2]);
    }

    public function testDoHandshakeWithHELO()
    {
        // Fake socket.
        $socket = new FakeSocket;

        // connect
        array_push($socket->readStringResults, "220 server ready");

        // doHandshake
        array_push($socket->readStringResults, "420 whatsthat"); // EHLO
        array_push($socket->readStringResults, "250 mailserver"); // HELO
        array_push($socket->readStringResults, "220 server ready"); // STARTTLS
        array_push($socket->readStringResults, "250 mailserver"); // HELO

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            "user",
            "password",
            socket: $socket
        );
        $smtp->connect();
        $socket->writeStringData = [];
        $smtp->doHandshake();

        // Assert.
        $this->assertFalse($socket->isClosed);
        $this->assertTrue($socket->isCryptoEnabled);
        $this->assertEmpty($socket->readStringResults);
        
        $this->assertEquals(4, count($socket->writeStringData));
        $this->assertEquals("EHLO localhost" . PHP_EOL, $socket->writeStringData[0]);
        $this->assertEquals("HELO localhost" . PHP_EOL, $socket->writeStringData[1]);
        $this->assertEquals("STARTTLS" . PHP_EOL, $socket->writeStringData[2]);
        $this->assertEquals("HELO localhost" . PHP_EOL, $socket->writeStringData[3]);
    }

    public function testDoHandshakeHandleHELOError()
    {
        // Fake socket.
        $socket = new FakeSocket;

        // connect
        array_push($socket->readStringResults, "220 server ready");

        // doHandshake
        array_push($socket->readStringResults, "420 whatsthat"); // EHLO
        array_push($socket->readStringResults, "421 whatsthat"); // HELO

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            "user",
            "password",
            socket: $socket
        );
        $smtp->connect();
        $socket->writeStringData = [];

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
            $this->assertEquals("EHLO localhost" . PHP_EOL, $socket->writeStringData[0]);
            $this->assertEquals("HELO localhost" . PHP_EOL, $socket->writeStringData[1]);

            $this->assertEquals("Invalid HELO response: 421", $ex->getMessage());
        }
    }

    public function testDoHandshakeHandleSTARTTLSError()
    {
        // Fake socket.
        $socket = new FakeSocket;

        // connect
        array_push($socket->readStringResults, "220 server ready");

        // doHandshake
        array_push($socket->readStringResults, "250 mailserver"); // EHLO
        array_push($socket->readStringResults, "420 what"); // STARTTLS
        
        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            "user",
            "password",
            socket: $socket
        );
        $smtp->connect();
        $socket->writeStringData = [];

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
            $this->assertEquals("EHLO localhost" . PHP_EOL, $socket->writeStringData[0]);
            $this->assertEquals("STARTTLS" . PHP_EOL, $socket->writeStringData[1]);

            $this->assertEquals("Invalid STARTTLS response: 420", $ex->getMessage());
        }
    }

    public function testDoHandshakeHandleEHLOErrorAfterSTARTTLS()
    {
        // Fake socket.
        $socket = new FakeSocket;

        // connect
        array_push($socket->readStringResults, "220 server ready");

        // doHandshake
        array_push($socket->readStringResults, "250 mailserver"); // EHLO
        array_push($socket->readStringResults, "220 server ready"); // STARTTLS
        array_push($socket->readStringResults, "420 what"); // EHLO

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            "user",
            "password",
            socket: $socket
        );
        $smtp->connect();
        $socket->writeStringData = [];

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
            $this->assertEquals("EHLO localhost" . PHP_EOL, $socket->writeStringData[0]);
            $this->assertEquals("STARTTLS" . PHP_EOL, $socket->writeStringData[1]);
            $this->assertEquals("EHLO localhost" . PHP_EOL, $socket->writeStringData[2]);

            $this->assertEquals("Unable to do EHLO after STARTTLS", $ex->getMessage());
        }
    }

    public function testDoHandshakeHandleHELOErrorAfterSTARTTLS()
    {
        // Fake socket.
        $socket = new FakeSocket;

        // connect
        array_push($socket->readStringResults, "220 server ready");

        // doHandshake
        array_push($socket->readStringResults, "420 whatsthat"); // EHLO
        array_push($socket->readStringResults, "250 mailserver"); // HELO
        array_push($socket->readStringResults, "220 server ready"); // STARTTLS
        array_push($socket->readStringResults, "420 what"); // HELO

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            "user",
            "password",
            socket: $socket
        );
        $smtp->connect();
        $socket->writeStringData = [];
        
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
            $this->assertEquals("EHLO localhost" . PHP_EOL, $socket->writeStringData[0]);
            $this->assertEquals("HELO localhost" . PHP_EOL, $socket->writeStringData[1]);
            $this->assertEquals("STARTTLS" . PHP_EOL, $socket->writeStringData[2]);
            $this->assertEquals("HELO localhost" . PHP_EOL, $socket->writeStringData[3]);

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
            "user",
            "password",
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
        $this->assertEquals("EHLO localhost" . PHP_EOL, $socket->writeStringData[0]);
    }
}

?>