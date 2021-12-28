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
        ISocket $socket = null
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
    }

    private function read() : object
    {
        // Reading socket.
        $messages = [];
        $code = 0;

        while (true)
        {
            $response = $this->socket->readString(self::BUFFER_SIZE);
            
            if (strlen($response) < 4)
            {
                throw new Exception("Invalid server response length");
            }

            $code = (int)substr($response, 0, 3);
            array_push($messages, substr($response, 4));

            if ($response[3] == " ")
            {
                // https://stackoverflow.com/a/7776454/5638260
                // No more to read.
                break;
            }
        }

        // Parsing.
        return (object)[
            "code" => $code,
            "messages" => $messages
        ];
    }

    private function write(string $command) : void
    {
        $this->socket->writeString($command . PHP_EOL);
    }

    public function connect() : array
    {
        $this->isSSL = strpos($this->hostName, "ssl://") !== false;

        if ($this->portNumber == 465 && !$this->isSSL)
        {
            $this->hostName = "ssl://" . $this->hostName;
            $this->isSSL = true;
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
            throw new Exception("Invalid announcement response: " . $response->code);
        }

        return $response->messages;
    }

    private function doHELO() : void
    {
        // Sending command.
        $hostName = $this->hostName;

        if ($this->isSSL)
        {
            $hostName = substr($hostName, 6);
        }

        $this->write("HELO " . $hostName);

        // Reading response.
        $response = $this->read();

        if ($response->code !== 250)
        {
            throw new Exception("Invalid HELO response: " . $response->code);
        }
    }

    private function doEHLO() : bool
    {
        // Sending command.
        $hostName = $this->hostName;

        if ($this->isSSL)
        {
            $hostName = substr($hostName, 6);
        }

        $this->write("EHLO " . $hostName);

        // Reading response.
        $response = $this->read();

        if ($response->code !== 250)
        {
            // Cannot use EHLO.
            return false;
        }

        return true;
    }

    public function doHandshake() : void
    {
        // Send EHLO.
        $useHELO = false;

        if (!$this->doEHLO())
        {
            $this->doHELO();
            $useHELO = true;
        }

        if ($this->isSSL)
        {
            // Already secure.
            return;
        }

        // Sending STARTTLS.
        $this->write("STARTTLS");

        // Reading response.
        $response = $this->read();

        if ($response->code !== 220)
        {
            throw new Exception("Invalid STARTTLS response: " . $response->code);
        }

        // Upgrading socket.
        $this->socket->enableCrypto();

        // Sending EHLO/HELO.
        if ($useHELO)
        {
            $this->doHELO();
        }
        else
        {
            if (!$this->doEHLO())
            {
                throw new Exception("Unable to do EHLO after STARTTLS");
            }
        }
    }
}

?>