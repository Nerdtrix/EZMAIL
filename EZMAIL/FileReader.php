<?php
    namespace EZMAIL;

    interface IFileReader
    {
        public function read(string $path) : string;
    }

    class FileReader implements IFileReader
    {
        public function read(string $path) : string
        {
            return file_get_contents($path);
        }
    }
?>