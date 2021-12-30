<?php

namespace EZMAIL;

interface IMailBuilder
{
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
    ) : void;
}

?>