<?php

namespace EZMAIL;

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
        return $this->getRandomStr(64) . "@" . $_SERVER["HOST_NAME"];
    }
}
