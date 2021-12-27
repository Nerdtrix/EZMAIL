<?php

namespace EZMAIL\Tests;

use Exception;
use EZMAIL\ISocket;

class FakeSocket implements ISocket
{
    public
        $openHost = "",
        $openPort = -1,
        $openTimeout = 0,
        $isClosed = false,
        $readStringResults = [],
        $writeStringData = [];

    public function open(string $host, int $port, float $timeout) : void
    {
        $this->isClosed = false;
        $this->openHost = $host;
        $this->openPort = $port;
        $this->openTimeout = $timeout;
    }

    public function readString(int $lenToRead) : string
    {
        if (count($this->readStringResults) == 0)
        {
            throw new Exception("No more read result");
        }

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