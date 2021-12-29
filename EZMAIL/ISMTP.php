<?php

namespace EZMAIL;

interface ISMTP
{
    public function connect() : array;
    public function doHandshake() : void;
    public function doAuth(
        string $username,
        string $password,
        int $authType = SMTP::AUTH_TYPE_STANDARD
    ) : void;
    public function startSendMail(
        string $from,
        array $to
    ) : void;
    public function writeMailData(string $data) : void;
    public function endSendMail() : string;
    public function quit() : void;
}

?>