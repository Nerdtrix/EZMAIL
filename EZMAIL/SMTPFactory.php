<?php

namespace EZMAIL;

class SMTPFactory implements ISMTPFactory
{
    public function create(
        string $hostName,
        int $portNumber,
        float $timeout = 30
    ) : ISMTP
    {
        return new SMTP($hostName, $portNumber, $timeout);
    }
}

?>