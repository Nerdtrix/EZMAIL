<?php
    namespace EZMAIL;

    class EmptyLogger implements ILogger
    {
        public function log(string $format, ...$values) : void
        {
            // Do nothing.
        }
    }

?>