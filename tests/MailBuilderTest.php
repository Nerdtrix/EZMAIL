<?php

namespace EZMAIL\Tests;

use PHPUnit\Framework\TestCase;
use EZMAIL\MailBuilder;

class MailBuilderTest extends TestCase
{
    public function testBuild()
    {
        // Fake file reader.
        $fileReader = new FakeFileReader;
        $f1Content = 
            "this is file content\r\n" .
            "line 2 of the file content\r\n" .
            "another line of the file content\r\n" .
            "another line of the file content\r\n" .
            "another line of the file content\r\n" .
            "another line of the file content\r\n" .
            "another line of the file content\r\n";
        array_push($fileReader->readResults, $f1Content);
        $f2Content = 
            "this is file2 content\r\n" .
            "line 2 of the file2 content\r\n" .
            "another line of the file2 content\r\n" .
            "another line of the file2 content\r\n" .
            "another line of the file2 content\r\n" .
            "another line of the file2 content\r\n" .
            "another line of the file2 content\r\n";
        array_push($fileReader->readResults, $f2Content);

        // Fake writer.
        $writer = new FakeMailBuilderWriter;
        
        // Test.
        $message = 
            "this is message\r\n" .
            "line 2 of the message\r\n" .
            "another line of the message\r\n" .
            "another line of the message\r\n" .
            "another line of the message\r\n" .
            "another line of the message\r\n" .
            "another line of the message\r\n";
        $builder = new MailBuilder($fileReader);
        $builder->build(
            id: "111",
            subject: "this is subject",
            message: $message,
            from: array("Sender" => "sender@mail.com"),
            to: array(
                "To 1" => "to1@mail.com",
                "to2@mail.com",
            ),
            cc: array(
                "To 3" => "to3@mail.com",
                "to4@mail.com",
            ),
            bcc: array(
                "to5@mail.com",
                "To 6" => "to6@mail.com",
            ),
            attachments: array(
                "file.txt" => "/home/test/file.txt",
                "/home/test/file2.txt"
            ),
            bounceAddress: "bounce@mail.com",
            replyTo: "reply@mail.com",
            appName: "Test App",
            writer: $writer
        );
        
        // Assert header.
        $this->assertEquals("MIME-Version: 1.0", $writer->readHeader());
        $this->assertEquals("X-Mailer: Test App", $writer->readHeader());
        $this->assertEquals("Date: " . date("r"), $writer->readHeader());
        $this->assertEquals("Priority: 3", $writer->readHeader());
        $this->assertEquals("Subject: =?utf-8?B?dGhpcyBpcyBzdWJqZWN0?=", $writer->readHeader());
        $this->assertEquals("Return-Path: bounce@mail.com", $writer->readHeader());
        $this->assertEquals("From: Sender <sender@mail.com>", $writer->readHeader());
        $this->assertEquals("Message-ID: 111", $writer->readHeader());
        $this->assertEquals("To: To 1 <to1@mail.com>,<to2@mail.com>", $writer->readHeader());
        $this->assertEquals("Cc: To 3 <to3@mail.com>,<to4@mail.com>", $writer->readHeader());
        $this->assertEquals("Bcc: <to5@mail.com>,To 6 <to6@mail.com>", $writer->readHeader());
        $this->assertEquals("Reply-To: reply@mail.com", $writer->readHeader());
        $this->assertEquals("Content-Type: multipart/mixed; boundary=\"boundary111\"", $writer->readHeader());
        $this->assertEquals("", $writer->readHeader());
        $this->assertEmpty($writer->header);

        // Assert message.
        $this->assertEquals("--boundary111", $writer->readBody());
        $this->assertEquals("Content-Type: text/html; charset=\"UTF-8\"", $writer->readBody());
        $this->assertEquals("Content-Transfer-Encoding: base64", $writer->readBody());
        $this->assertEquals("", $writer->readBody());
        $this->assertEquals("dGhpcyBpcyBtZXNzYWdlDQpsaW5lIDIgb2YgdGhlIG1lc3NhZ2UNCmFub3RoZXIgbGluZSBvZiB0", $writer->readBody());
        $this->assertEquals("aGUgbWVzc2FnZQ0KYW5vdGhlciBsaW5lIG9mIHRoZSBtZXNzYWdlDQphbm90aGVyIGxpbmUgb2Yg", $writer->readBody());
        $this->assertEquals("dGhlIG1lc3NhZ2UNCmFub3RoZXIgbGluZSBvZiB0aGUgbWVzc2FnZQ0KYW5vdGhlciBsaW5lIG9m", $writer->readBody());
        $this->assertEquals("IHRoZSBtZXNzYWdlDQo=", $writer->readBody());

        // Assert attachment.
        $this->assertEquals("--boundary111", $writer->readBody());
        $this->assertEquals("Content-Type: application/octet-stream; name=\"file.txt\"", $writer->readBody());
        $this->assertEquals("Content-Transfer-Encoding: base64", $writer->readBody());
        $this->assertEquals("Content-Disposition: attachment; filename=\"file.txt\"", $writer->readBody());
        $this->assertEquals("", $writer->readBody());
        $this->assertEquals("dGhpcyBpcyBmaWxlIGNvbnRlbnQNCmxpbmUgMiBvZiB0aGUgZmlsZSBjb250ZW50DQphbm90aGVy", $writer->readBody());
        $this->assertEquals("IGxpbmUgb2YgdGhlIGZpbGUgY29udGVudA0KYW5vdGhlciBsaW5lIG9mIHRoZSBmaWxlIGNvbnRl", $writer->readBody());
        $this->assertEquals("bnQNCmFub3RoZXIgbGluZSBvZiB0aGUgZmlsZSBjb250ZW50DQphbm90aGVyIGxpbmUgb2YgdGhl", $writer->readBody());
        $this->assertEquals("IGZpbGUgY29udGVudA0KYW5vdGhlciBsaW5lIG9mIHRoZSBmaWxlIGNvbnRlbnQNCg==", $writer->readBody());

        // Assert attachment 2.
        $this->assertEquals("--boundary111", $writer->readBody());
        $this->assertEquals("Content-Type: application/octet-stream; name=\"file2.txt\"", $writer->readBody());
        $this->assertEquals("Content-Transfer-Encoding: base64", $writer->readBody());
        $this->assertEquals("Content-Disposition: attachment; filename=\"file2.txt\"", $writer->readBody());
        $this->assertEquals("", $writer->readBody());
        $this->assertEquals("dGhpcyBpcyBmaWxlMiBjb250ZW50DQpsaW5lIDIgb2YgdGhlIGZpbGUyIGNvbnRlbnQNCmFub3Ro", $writer->readBody());
        $this->assertEquals("ZXIgbGluZSBvZiB0aGUgZmlsZTIgY29udGVudA0KYW5vdGhlciBsaW5lIG9mIHRoZSBmaWxlMiBj", $writer->readBody());
        $this->assertEquals("b250ZW50DQphbm90aGVyIGxpbmUgb2YgdGhlIGZpbGUyIGNvbnRlbnQNCmFub3RoZXIgbGluZSBv", $writer->readBody());
        $this->assertEquals("ZiB0aGUgZmlsZTIgY29udGVudA0KYW5vdGhlciBsaW5lIG9mIHRoZSBmaWxlMiBjb250ZW50DQo=", $writer->readBody());
        
        // Assert attachment end.
        $this->assertEquals("--boundary111--", $writer->readBody());
        $this->assertEmpty($writer->body);
    }

    public function testBuildNoAttachments()
    {
        // Fake writer.
        $writer = new FakeMailBuilderWriter;
        
        // Test.
        $builder = new MailBuilder(new FakeFileReader);
        $builder->build(
            id: "111",
            subject: "this is subject",
            message: "this is message",
            from: array("sender@mail.com"),
            to: array("To 1" => "to1@mail.com"),
            cc: array("To 3" => "to3@mail.com",),
            bcc: array("To 5" => "to5@mail.com"),
            attachments: [],
            bounceAddress: "bounce@mail.com",
            replyTo: "reply@mail.com",
            appName: "Test App",
            writer: $writer
        );

        // Assert header.
        $this->assertEquals("MIME-Version: 1.0", $writer->readHeader());
        $this->assertEquals("X-Mailer: Test App", $writer->readHeader());
        $this->assertEquals("Date: " . date("r"), $writer->readHeader());
        $this->assertEquals("Priority: 3", $writer->readHeader());
        $this->assertEquals("Subject: =?utf-8?B?dGhpcyBpcyBzdWJqZWN0?=", $writer->readHeader());
        $this->assertEquals("Return-Path: bounce@mail.com", $writer->readHeader());
        $this->assertEquals("From: <sender@mail.com>", $writer->readHeader());
        $this->assertEquals("Message-ID: 111", $writer->readHeader());
        $this->assertEquals("To: To 1 <to1@mail.com>", $writer->readHeader());
        $this->assertEquals("Cc: To 3 <to3@mail.com>", $writer->readHeader());
        $this->assertEquals("Bcc: To 5 <to5@mail.com>", $writer->readHeader());
        $this->assertEquals("Reply-To: reply@mail.com", $writer->readHeader());
        $this->assertEquals("Content-Type: multipart/alternative; boundary=\"boundary111\"", $writer->readHeader());
        $this->assertEquals("", $writer->readHeader());
        $this->assertEmpty($writer->header);

        // Assert message.
        $this->assertEquals("--boundary111", $writer->readBody());
        $this->assertEquals("Content-Type: text/html; charset=\"UTF-8\"", $writer->readBody());
        $this->assertEquals("Content-Transfer-Encoding: base64", $writer->readBody());
        $this->assertEquals("", $writer->readBody());
        $this->assertEquals("dGhpcyBpcyBtZXNzYWdl", $writer->readBody());
        $this->assertEquals("--boundary111--", $writer->readBody());
        $this->assertEmpty($writer->body);
    }

    public function testBuildNoBcc()
    {
        // Fake writer.
        $writer = new FakeMailBuilderWriter;
        
        // Test.
        $builder = new MailBuilder(new FakeFileReader);
        $builder->build(
            id: "111",
            subject: "this is subject",
            message: "this is message",
            from: array("Sender" => "sender@mail.com"),
            to: array(
                "To 1" => "to1@mail.com",
                "To 2" => "to2@mail.com",
            ),
            cc: array(
                "To 3" => "to3@mail.com",
                "To 4" => "to4@mail.com",
            ),
            bcc: [],
            attachments: [],
            bounceAddress: "bounce@mail.com",
            replyTo: "reply@mail.com",
            appName: "Test App",
            writer: $writer
        );

        // Assert header.
        $this->assertEquals("MIME-Version: 1.0", $writer->readHeader());
        $this->assertEquals("X-Mailer: Test App", $writer->readHeader());
        $this->assertEquals("Date: " . date("r"), $writer->readHeader());
        $this->assertEquals("Priority: 3", $writer->readHeader());
        $this->assertEquals("Subject: =?utf-8?B?dGhpcyBpcyBzdWJqZWN0?=", $writer->readHeader());
        $this->assertEquals("Return-Path: bounce@mail.com", $writer->readHeader());
        $this->assertEquals("From: Sender <sender@mail.com>", $writer->readHeader());
        $this->assertEquals("Message-ID: 111", $writer->readHeader());
        $this->assertEquals("To: To 1 <to1@mail.com>,To 2 <to2@mail.com>", $writer->readHeader());
        $this->assertEquals("Cc: To 3 <to3@mail.com>,To 4 <to4@mail.com>", $writer->readHeader());
        $this->assertEquals("Reply-To: reply@mail.com", $writer->readHeader());
        $this->assertEquals("Content-Type: multipart/alternative; boundary=\"boundary111\"", $writer->readHeader());
        $this->assertEquals("", $writer->readHeader());
        $this->assertEmpty($writer->header);

        // Assert message.
        $this->assertEquals("--boundary111", $writer->readBody());
        $this->assertEquals("Content-Type: text/html; charset=\"UTF-8\"", $writer->readBody());
        $this->assertEquals("Content-Transfer-Encoding: base64", $writer->readBody());
        $this->assertEquals("", $writer->readBody());
        $this->assertEquals("dGhpcyBpcyBtZXNzYWdl", $writer->readBody());
        $this->assertEquals("--boundary111--", $writer->readBody());
        $this->assertEmpty($writer->body);
    }

    public function testBuildNoCcBcc()
    {
        // Fake writer.
        $writer = new FakeMailBuilderWriter;
        
        // Test.
        $builder = new MailBuilder(new FakeFileReader);
        $builder->build(
            id: "111",
            subject: "this is subject",
            message: "this is message",
            from: array("Sender" => "sender@mail.com"),
            to: array("To 1" => "to1@mail.com"),
            cc: [],
            bcc: [],
            attachments: [],
            bounceAddress: "bounce@mail.com",
            replyTo: "reply@mail.com",
            appName: "Test App",
            writer: $writer
        );

        // Assert header.
        $this->assertEquals("MIME-Version: 1.0", $writer->readHeader());
        $this->assertEquals("X-Mailer: Test App", $writer->readHeader());
        $this->assertEquals("Date: " . date("r"), $writer->readHeader());
        $this->assertEquals("Priority: 3", $writer->readHeader());
        $this->assertEquals("Subject: =?utf-8?B?dGhpcyBpcyBzdWJqZWN0?=", $writer->readHeader());
        $this->assertEquals("Return-Path: bounce@mail.com", $writer->readHeader());
        $this->assertEquals("From: Sender <sender@mail.com>", $writer->readHeader());
        $this->assertEquals("Message-ID: 111", $writer->readHeader());
        $this->assertEquals("To: To 1 <to1@mail.com>", $writer->readHeader());
        $this->assertEquals("Reply-To: reply@mail.com", $writer->readHeader());
        $this->assertEquals("Content-Type: multipart/alternative; boundary=\"boundary111\"", $writer->readHeader());
        $this->assertEquals("", $writer->readHeader());
        $this->assertEmpty($writer->header);

        // Assert message.
        $this->assertEquals("--boundary111", $writer->readBody());
        $this->assertEquals("Content-Type: text/html; charset=\"UTF-8\"", $writer->readBody());
        $this->assertEquals("Content-Transfer-Encoding: base64", $writer->readBody());
        $this->assertEquals("", $writer->readBody());
        $this->assertEquals("dGhpcyBpcyBtZXNzYWdl", $writer->readBody());
        $this->assertEquals("--boundary111--", $writer->readBody());
        $this->assertEmpty($writer->body);
    }
}

?>