<?php
    namespace EZMAIL;

    interface IMailIdGenerator
    {
        public function generate() : string;
    }

    class MailIdGenerator implements IMailIdGenerator
    {
        private function getRandomStr(int $n) : string
        {
            // https://stackhowto.com/how-to-generate-random-string-in-php/
            $str = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $randomStr = '';

            for ($i = 0; $i < $n; $i++)
            {
                $index = rand(0, strlen($str) - 1);
                $randomStr .= $str[$index];
            }

            return $randomStr;
        }

        public function generate() : string
        {
            $name = "";

            if (isset($_SERVER["HOST_NAME"]))
            {
                $name = $_SERVER["HOST_NAME"];
            }
            else
            {
                $name = "localhost";
            }

            return $this->getRandomStr(64) . "@" . $name;
        }
    }
?>
