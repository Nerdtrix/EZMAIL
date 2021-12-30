<?php

namespace EZMAIL\Tests;

use EZMAIL\IMailBuilder;
use EZMAIL\IMailBuilderWriter;

class FakeMailBuilder implements IMailBuilder
{
    public array $buildArgs = [];

    public array $header = [];
    public array $body = [];

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
        array_push(
            $this->buildArgs,
            array(
                "id" => $id,
                "subject" => $subject,
                "message" => $message,
                "from" => $from,
                "to" => $to,
                "cc" => $cc,
                "bcc" => $bcc,
                "replyTo" => $replyTo,
                "attachments" => $attachments,
                "bounceAddress" => $bounceAddress,
                "appName" => $appName,
                "writer" => $writer
            )
        );

        foreach ($this->header as $line)
        {
            $writer->writeHeader($line);
        }

        foreach ($this->body as $line)
        {
            $writer->writeBody($line);
        }
    }
}

?>