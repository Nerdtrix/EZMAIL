<?php

namespace EZMAIL;

class MailBuilder
{
    private string $id;
    private string $subject;
    private string $message;
    private array $from;
    private array $to;
    private array $cc;
    private array $bcc;
    private array $attachments;
    private string $bounceAddress;
    private string $replyTo;
    private string $appName;
    private IMailBuilderWriter $writer;
    private IFileReader $fileReader;

    public function __construct(
        string $id,
        string $subject,
        string $message,
        array $from,
        array $to,
        array $cc,
        array $bcc,
        array $attachments,
        string $bounceAddress,
        string $replyTo,
        string $appName,
        IMailBuilderWriter $writer,
        ?IFileReader $fileReader = null
    )
    {
        $this->id = $id;
        $this->subject = $subject;
        $this->message = $message;
        $this->from = $from;
        $this->to = $to;
        $this->cc = $cc;
        $this->bcc = $bcc;
        $this->attachments = $attachments;
        $this->bounceAddress = $bounceAddress;
        $this->replyTo = $replyTo;
        $this->appName = $appName;
        $this->writer = $writer;
        
        if ($fileReader == null)
        {
            $this->fileReader = new FileReader;
        }
        else
        {
            $this->fileReader = $fileReader;
        }
    }

    private function generateMimeAddresses(array $addresses) : string
    {
        $result = "";

        foreach ($addresses as $name => $address)
        {
            $result .= sprintf("%s <%s>,", $name, $address);
        }

        return substr($result, 0, strlen($result) - 1);
    }

    private function getBoundary() : string
    {
        return "boundary" . $this->id;
    }

    private function buildHeader() : void
    {
        $this->writer->writeHeader("MIME-Version: 1.0");
        $this->writer->writeHeader("X-Mailer: " . $this->appName);
        $this->writer->writeHeader("Date: " . date("r"));
        $this->writer->writeHeader("Priority: 3");
        $this->writer->writeHeader(
            sprintf(
                "Subject: =?utf-8?B?%s?=",
                base64_encode($this->subject)
            )
        );
        $this->writer->writeHeader("Return-Path: " . $this->bounceAddress);
        $this->writer->writeHeader(
            sprintf(
                "From: %s <%s>",
                key($this->from),
                end($this->from)
            )
        );
        $this->writer->writeHeader("Message-ID: " . $this->id);
        $this->writer->writeHeader("To: " . $this->generateMimeAddresses($this->to));
        
        if (!empty($this->cc))
        {
            $this->writer->writeHeader("Cc: " . $this->generateMimeAddresses($this->cc));
        }

        
        if (!empty($this->bcc))
        {
            $this->writer->writeHeader("Bcc: " . $this->generateMimeAddresses($this->bcc));
        }

        $this->writer->writeHeader("Reply-To: " . $this->replyTo);
        $contentType = "multipart/alternative";

        if (!empty($this->attachments))
        {
            $contentType = "multipart/mixed";
        }

        $this->writer->writeHeader(
            sprintf("Content-Type: %s; boundary=\"%s\"", $contentType, $this->getBoundary())
        );
        $this->writer->writeHeader("");
    }

    private function encodeContent(string $content) : array
    {
        $enc = base64_encode($content);
        $enc = trim(chunk_split($enc, separator: " "));
        return explode(" ", $enc);
    }

    private function buildContent() : void
    {
        $this->writer->writeBody("--" . $this->getBoundary());
        $this->writer->writeBody("Content-Type: text/html; charset=\"UTF-8\"");
        $this->writer->writeBody("Content-Transfer-Encoding: base64");
        $this->writer->writeBody("");

        foreach ($this->encodeContent($this->message) as $line)
        {
            $this->writer->writeBody($line);
        }
    }

    private function buildAttachments() : void
    {
        foreach ($this->attachments as $name => $path)
        {
            $this->writer->writeBody("--" . $this->getBoundary());
            $this->writer->writeBody(
                sprintf("Content-Type: application/octet-stream; name=\"%s\"", $name)
            );
            $this->writer->writeBody("Content-Transfer-Encoding: base64");
            $this->writer->writeBody(
                sprintf("Content-Disposition: attachment; filename=\"%s\"", $name)
            );
            $this->writer->writeBody("");
            
            foreach ($this->encodeContent($this->fileReader->read($path)) as $line)
            {
                $this->writer->writeBody($line);
            }
        }
    }

    private function buildEndBoundary() : void
    {
        $this->writer->writeBody("--" . $this->getBoundary() . "--");
    }

    public function build() : void
    {
        $this->buildHeader();
        $this->buildContent();
        $this->buildAttachments();
        $this->buildEndBoundary();
    }
}

?>