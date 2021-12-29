<?php

namespace EZMAIL\Tests;

use Exception;
use EZMAIL\IFileReader;

class FakeFileReader implements IFileReader
{
    public array $readPaths = [];
    public array $readResults = [];

    public function read(string $path) : string
    {
        if (count($this->readResults) == 0)
        {
            throw new Exception("No more read result");
        }

        array_push($this->readPaths, $path);
        $result = $this->readResults[0];
        array_shift($this->readResults);
        return $result;
    }
}

?>