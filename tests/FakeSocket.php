<?php

namespace EZMAIL\Tests;

use Exception;
use EZMAIL\ISocket;

class FakeSocket implements ISocket
{
    public bool $isClosed = false;
    public string $openHost = "";
    public int $openPort = 0;
    public float $openTimeout = 0;
    public bool $isCryptoEnabled = false;
    public array $readStringLengths = [];
    public array $readStringResults = [];
    public array $writeStringData = [];

    public function open(string $host, int $port, float $timeout) : void
    {
        $this->isClosed = false;
        $this->openHost = $host;
        $this->openPort = $port;
        $this->openTimeout = $timeout;
    }

    public function enableCrypto(): void
    {
        $this->isCryptoEnabled = true;
    }

    public function readString(int $lenToRead) : string
    {
        if (count($this->readStringResults) == 0)
        {
            throw new Exception("No more read result");
        }

        array_push($this->readStringLengths, $lenToRead);
        $result = $this->readStringResults[0];
        array_shift($this->readStringResults);
        return $result;
    }

    public function writeString(string $data) : void
    {
        array_push($this->writeStringData, $data);
    }

    public function close() : void
    {
        $this->isClosed = true;
    }
}

?>