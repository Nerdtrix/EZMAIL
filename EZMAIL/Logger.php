<?php
    namespace EZMAIL;

    interface ILogger
    {
        public function log(string $format, ...$values) : void;
    }


    class Logger implements ILogger
    {
        public function log(string $format, ...$values) : void
        {
            // Print to console.
            print(sprintf($format, ...$values) . PHP_EOL);
        }
    }
?>