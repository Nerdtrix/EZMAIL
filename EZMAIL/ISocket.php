<?php

namespace EZMAIL;

interface ISocket
{
    public function open(string $host, int $port, float $timeout) : void;
    public function readString(int $lenToRead) : string;
    public function writeString(string $data) : void;
    public function enableCrypto() : void;
    public function close() : void;
}

?>