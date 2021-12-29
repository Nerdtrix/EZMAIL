<?php

namespace EZMAIL\Tests;

use EZMAIL\IMailBuilder;
use EZMAIL\IMailBuilderWriter;

class FakeMailBuilder implements IMailBuilder
{
    public array $buildArgs = [];

    public array $header = [];
    public array $body = [];
    private IMailBuilderWriter $writer;

    public function __construct(IMailBuilderWriter $writer)
    {
        $this->writer = $writer;
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
                "attachments" => $attachments,
                "bounceAddress" => $bounceAddress,
                "replyTo" => $replyTo,
                "appName" => $appName,
                "writer" => $writer
            )
        );

        foreach ($this->header as $line)
        {
            $this->writer->writeHeader($line);
        }

        foreach ($this->body as $line)
        {
            $this->writer->writeBody($line);
        }
    }
}

?>