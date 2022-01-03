<?php

namespace EZMAIL;

class Logger implements ILogger
{
    public function log(string $format, ...$values) : void
    {
        // Print to console.
        print(sprintf($format, ...$values) . PHP_EOL);
    }
}

?>