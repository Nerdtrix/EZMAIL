<?php

namespace EZMAIL\Tests;

use Exception;
use EZMAIL\ISMTP;
use EZMAIL\SMTP;

class FakeSMTP implements ISMTP
{
    public bool $isConnected = false;
    public ?Exception $connectException = null;

    public bool $doneHandshake = false;
    public ?Exception $handshakeException = null;

    public string $authUsername = "";
    public string $authPassword = "";
    public int $authType = 0;

    public string $startSendMailFrom = "";
    public array $startSendMailTo = [];

    public array $writeMailDataArgs = [];

    public bool $doneSendMail = false;
    public string $endSendMailResult = "";

    public bool $hasQuit = false;

    public function connect() : void
    {
        if ($this->connectException != null)
        {
            throw $this->connectException;
        }

        $this->isConnected = true;
    }

    public function doHandshake() : void
    {
        if ($this->handshakeException != null)
        {
            throw $this->handshakeException;
        }

        $this->doneHandshake = true;
    }

    public function doAuth(
        string $username,
        string $password,
        int $authType = SMTP::AUTH_TYPE_STANDARD
    ) : void
    {
        $this->authUsername = $username;
        $this->authPassword = $password;
        $this->authType = $authType;
    }

    public function startSendMail(
        string $from,
        array $to
    ) : void
    {
        $this->startSendMailFrom = $from;
        $this->startSendMailTo = $to;
    }

    public function writeMailData(string $data) : void
    {
        array_push($this->writeMailDataArgs, $data);
    }

    public function endSendMail() : string
    {
        $this->doneSendMail = true;
        return $this->endSendMailResult;
    }

    public function quit() : void
    {
        $this->hasQuit = true;
    }
}

?>