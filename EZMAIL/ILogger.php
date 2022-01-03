<?php

namespace EZMAIL;

interface ILogger
{
    public function log(string $format, ...$values) : void;
}

?>