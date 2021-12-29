<?php

namespace EZMAIL\Tests;

use Exception;
use EZMAIL\IMailBuilderWriter;

class FakeMailBuilderWriter implements IMailBuilderWriter
{
    public array $header = [];
    public array $body = [];

    public function writeHeader(string $data): void
    {
        array_push($this->header, $data);
    }

    public function writeBody(string $data): void
    {
        array_push($this->body, $data);
    }

    public function readHeader() : string
    {
        if (count($this->header) == 0)
        {
            throw new Exception("No more read data");
        }

        $result = $this->header[0];
        array_shift($this->header);
        return $result;
    }

    public function readBody() : string
    {
        if (count($this->body) == 0)
        {
            throw new Exception("No more read data");
        }

        $result = $this->body[0];
        array_shift($this->body);
        return $result;
    }
}

?>