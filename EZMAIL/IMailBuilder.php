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
        array $attachments,
        string $bounceAddress,
        string $replyTo,
        string $appName,
        IMailBuilderWriter $writer
    ) : void;
}

?>