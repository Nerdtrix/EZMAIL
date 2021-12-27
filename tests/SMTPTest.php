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
        array_push($socket->readStringResults, "220 server rea");
        array_push($socket->readStringResults, "dy ");
        array_push($socket->readStringResults, "");
        
        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            "user",
            "password",
            socket: $socket,
            autoConnect: false
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
        array_push($socket->readStringResults, "");

        // Test.
        $smtp = new SMTP(
            "localhost",
            587,
            "user",
            "password",
            socket: $socket,
            autoConnect: false
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
            $this->assertEmpty($socket->readStringResults);
            $this->assertEmpty($socket->writeStringData);
            $this->assertEquals(
                "Invalid announcement response: 420",
                $ex->getMessage()
            );
        }
    }

    
}

?>