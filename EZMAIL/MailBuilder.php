<?php

namespace EZMAIL;

class MailBuilder implements IMailBuilder
{
    private IFileReader $fileReader;

    public function __construct(
        ?IFileReader $fileReader = null
    )
    {
        if ($fileReader == null)
        {
            $this->fileReader = new FileReader;
        }
        else
        {
            $this->fileReader = $fileReader;
        }
    }

    private function generateMimeAddresses(array $addresses, bool $onlyOne = false) : string
    {
        $result = "";

        foreach ($addresses as $name => $address)
        {
            if (is_string($name))
            {
                // With name.
                $result .= sprintf("%s <%s>,", $name, $address);
            }
            else
            {
                // No name.
                $result .= sprintf("<%s>,", $address);
            }

            if ($onlyOne)
            {
                break;
            }
        }

        if (empty($result))
        {
            return "";
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
        array $replyTo,
        array $attachments,
        string $bounceAddress,
        string $appName,
        IMailBuilderWriter $writer
    ) : void
    {
        $writer->writeHeader("MIME-Version: 1.0");
        $writer->writeHeader("X-Mailer: " . $appName);
        $writer->writeHeader("Date: " . date("r"));
        $writer->writeHeader("Priority: 3");
        $writer->writeHeader(
            sprintf(
                "Subject: =?utf-8?B?%s?=",
                base64_encode($subject)
            )
        );
        $writer->writeHeader("Return-Path: " . $bounceAddress);
        $writer->writeHeader("From: " . $this->generateMimeAddresses($from, true));
        $writer->writeHeader("Message-ID: " . $id);
        $writer->writeHeader("To: " . $this->generateMimeAddresses($to));
        
        if (!empty($cc))
        {
            $writer->writeHeader("Cc: " . $this->generateMimeAddresses($cc));
        }

        
        if (!empty($bcc))
        {
            $writer->writeHeader("Bcc: " . $this->generateMimeAddresses($bcc));
        }

        $writer->writeHeader("Reply-To: " . $this->generateMimeAddresses($replyTo, true));
        $contentType = "multipart/alternative";

        if (!empty($attachments))
        {
            $contentType = "multipart/mixed";
        }

        $writer->writeHeader(
            sprintf("Content-Type: %s; boundary=\"%s\"", $contentType, $this->getBoundary($id))
        );
        $writer->writeHeader("");
    }

    private function encodeContent(string $content) : array
    {
        $enc = base64_encode($content);
        $enc = trim(chunk_split($enc, 76, " "));
        return explode(" ", $enc);
    }

    private function buildContent(
        string $id,
        string $message,
        IMailBuilderWriter $writer
    ) : void
    {
        $writer->writeBody("--" . $this->getBoundary($id));
        $writer->writeBody("Content-Type: text/html; charset=\"UTF-8\"");
        $writer->writeBody("Content-Transfer-Encoding: base64");
        $writer->writeBody("");

        foreach ($this->encodeContent($message) as $line)
        {
            $writer->writeBody($line);
        }
    }

    private function buildAttachments(
        string $id,
        array $attachments,
        IMailBuilderWriter $writer
    ) : void
    {
        foreach ($attachments as $name => $path)
        {
            if (is_int($name))
            {
                $name = basename($path);
            }

            $writer->writeBody("--" . $this->getBoundary($id));
            $writer->writeBody(
                sprintf("Content-Type: application/octet-stream; name=\"%s\"", $name)
            );
            $writer->writeBody("Content-Transfer-Encoding: base64");
            $writer->writeBody(
                sprintf("Content-Disposition: attachment; filename=\"%s\"", $name)
            );
            $writer->writeBody("");
            
            foreach ($this->encodeContent($this->fileReader->read($path)) as $line)
            {
                $writer->writeBody($line);
            }
        }
    }

    private function buildEndBoundary(
        string $id,
        IMailBuilderWriter $writer
    ) : void
    {
        $writer->writeBody("--" . $this->getBoundary($id) . "--");
    }

    public function build(
        string $id,
        string $subject,
        string $message,
        array $from,
        array $to,
        array $cc,
        array $bcc,
        array $replyTo,
        array $attachments,
        string $bounceAddress,
        string $appName,
        IMailBuilderWriter $writer
    ) : void
    {
        $this->buildHeader(
            $id,
            $subject,
            $from,
            $to,
            $cc,
            $bcc,
            $replyTo,
            $attachments,
            $bounceAddress,
            $appName,
            $writer
        );
        $this->buildContent(
            $id,
            $message,
            $writer
        );
        $this->buildAttachments(
            $id,
            $attachments,
            $writer
        );
        $this->buildEndBoundary(
            $id,
            $writer
        );
    }
}

?>