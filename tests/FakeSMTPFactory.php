<?php

namespace EZMAIL\Tests;

use EZMAIL\ISMTP;
use EZMAIL\ISMTPFactory;

class FakeSMTPFactory implements ISMTPFactory
{
    public FakeSMTP $result;
    public ?string $hostName = null;
    public ?int $portNumber = null;
    public float $timeout = 0;

    public function __construct()
    {
        $this->result = new FakeSMTP;
    }

    public function create(
        string $hostName,
        int $portNumber,
        float $timeout = 30
    ) : ISMTP
    {
        $this->hostName = $hostName;
        $this->portNumber = $portNumber;
        $this->timeout = $timeout;
        return $this->result;
    }
}

?>