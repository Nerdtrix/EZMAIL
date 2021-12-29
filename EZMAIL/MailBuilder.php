<?php

namespace EZMAIL;

class MailBuilder
{
    private IMailBuilderWriter $writer;
    private IFileReader $fileReader;

    public function __construct(
        IMailBuilderWriter $writer,
        ?IFileReader $fileReader = null
    )
    {
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

    private function getBoundary(string $id) : string
    {
        return "boundary" . $id;
    }

    private function buildHeader(
        string $id,
        string $subject,
        array $from,
        array $to,
        array $cc,
        array $bcc,
        array $attachments,
        string $bounceAddress,
        string $replyTo,
        string $appName,
    ) : void
    {
        $this->writer->writeHeader("MIME-Version: 1.0");
        $this->writer->writeHeader("X-Mailer: " . $appName);
        $this->writer->writeHeader("Date: " . date("r"));
        $this->writer->writeHeader("Priority: 3");
        $this->writer->writeHeader(
            sprintf(
                "Subject: =?utf-8?B?%s?=",
                base64_encode($subject)
            )
        );
        $this->writer->writeHeader("Return-Path: " . $bounceAddress);
        $this->writer->writeHeader(
            sprintf(
                "From: %s <%s>",
                key($from),
                end($from)
            )
        );
        $this->writer->writeHeader("Message-ID: " . $id);
        $this->writer->writeHeader("To: " . $this->generateMimeAddresses($to));
        
        if (!empty($cc))
        {
            $this->writer->writeHeader("Cc: " . $this->generateMimeAddresses($cc));
        }

        
        if (!empty($bcc))
        {
            $this->writer->writeHeader("Bcc: " . $this->generateMimeAddresses($bcc));
        }

        $this->writer->writeHeader("Reply-To: " . $replyTo);
        $contentType = "multipart/alternative";

        if (!empty($attachments))
        {
            $contentType = "multipart/mixed";
        }

        $this->writer->writeHeader(
            sprintf("Content-Type: %s; boundary=\"%s\"", $contentType, $this->getBoundary($id))
        );
        $this->writer->writeHeader("");
    }

    private function encodeContent(string $content) : array
    {
        $enc = base64_encode($content);
        $enc = trim(chunk_split($enc, separator: " "));
        return explode(" ", $enc);
    }

    private function buildContent(
        string $id,
        string $message
    ) : void
    {
        $this->writer->writeBody("--" . $this->getBoundary($id));
        $this->writer->writeBody("Content-Type: text/html; charset=\"UTF-8\"");
        $this->writer->writeBody("Content-Transfer-Encoding: base64");
        $this->writer->writeBody("");

        foreach ($this->encodeContent($message) as $line)
        {
            $this->writer->writeBody($line);
        }
    }

    private function buildAttachments(
        string $id,
        array $attachments
    ) : void
    {
        foreach ($attachments as $name => $path)
        {
            $this->writer->writeBody("--" . $this->getBoundary($id));
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

    private function buildEndBoundary(string $id) : void
    {
        $this->writer->writeBody("--" . $this->getBoundary($id) . "--");
    }

    public function build(
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
    ) : void
    {
        $this->buildHeader(
            $id,
            $subject,
            $from,
            $to,
            $cc,
            $bcc,
            $attachments,
            $bounceAddress,
            $replyTo,
            $appName
        );
        $this->buildContent(
            $id,
            $message
        );
        $this->buildAttachments(
            $id,
            $attachments
        );
        $this->buildEndBoundary($id);
    }
}

?>