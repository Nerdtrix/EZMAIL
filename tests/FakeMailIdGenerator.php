<?php

namespace EZMAIL\Tests;

use EZMAIL\IMailIdGenerator;

class FakeMailIdGenerator implements IMailIdGenerator
{
    public string $result = "";

    public function generate() : string
    {
        return $this->result;
    }
}

?>