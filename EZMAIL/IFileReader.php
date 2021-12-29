<?php

namespace EZMAIL;

interface IFileReader
{
    public function read(string $path) : string;
}

?>