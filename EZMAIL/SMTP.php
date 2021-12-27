<?php

namespace EZMAIL;

use Exception;

define("AUTH_TYPE_STANDARD", 1);
define("AUTH_TYPE_PLAIN", 2);
define("AUTH_TYPE_2AUTH", 3);

class SMTP
{
    const BUFFER_SIZE = 512;

    private bool $isSSL;
    private string $hostName;
    private string $portNumber;
    private string $username;
    private string $password;
    private bool $useSMTP;
    private float $timeout;
    private int $authType;
    private ISocket $socket;

    public function __construct(
        string $hostName,
        int $portNumber,
        string $username,
        string $password,
        bool $useSMTP = true,
        float $timeout = 30,
        int $authType = AUTH_TYPE_STANDARD,
        ISocket $socket = null,
        bool $autoConnect = true,
    )
    {
        $this->hostName = $hostName;
        $this->portNumber = $portNumber;
        $this->username = $username;
        $this->password = $password;
        $this->useSMTP = $useSMTP;
        $this->timeout = $timeout;
        $this->authType = $authType;
        $this->socket = $socket;

        if ($this->socket == null)
        {
            $this->socket = new Socket;
        }

        if ($autoConnect)
        {
            // TODO
        }
    }

    private function read() : object
    {
        // Reading socket.
        $result = "";

        while (true)
        {
            $response = $this->socket->readString(self::BUFFER_SIZE);
            $result .= $response;

            if ($response == "")
            {
                // No more to read.
                break;
            }
        }

        // Parsing.
        $code = (int)substr(trim($result), 0, 3);
        $message = trim(substr($result, 3));
        return (object)[
            "code" => $code,
            "message" => $message
        ];
    }

    private function write(int $code, string $message) : void
    {
        $this->socket->writeString(
            sprintf("%d %s%s", $code, $message, PHP_EOL)
        );
    }

    public function connect() : string
    {
        $this->isSSL = strpos($this->hostName, "ssl://") !== false;

        if ($this->portNumber == 465 && !$this->isSSL)
        {
            $this->hostName = "ssl://" . $this->hostName;
        }

        // Opening socket.
        $this->socket->open(
            $this->hostName,
            $this->portNumber,
            $this->timeout
        );

        // Reading announcement.
        $response = $this->read();

        if ($response->code !== 220)
        {
            $this->socket->close();
            throw new Exception("Invalid announcement response: " . $response->code);
        }

        return $response->message;
    }

}

?>