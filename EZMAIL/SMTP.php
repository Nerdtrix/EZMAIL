<?php

namespace EZMAIL;

use Exception;
use InvalidArgumentException;

class SMTP
{
    const BUFFER_SIZE = 512;
    
    const AUTH_TYPE_STANDARD = 1;
    const AUTH_TYPE_PLAIN = 2;
    const AUTH_TYPE_2AUTH = 3;

    private bool $isSSL;
    private string $hostName;
    private string $portNumber;
    private float $timeout;
    private ?ISocket $socket;

    public function __construct(
        string $hostName,
        int $portNumber,
        float $timeout = 30,
        ISocket $socket = null
    )
    {
        $this->isSSL = false;
        $this->hostName = $hostName;
        $this->portNumber = $portNumber;
        $this->timeout = $timeout;
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
            
            if (strlen($response) < 3)
            {
                throw new Exception("Invalid server response length");
            }

            $code = (int)substr($response, 0, 3);
            
            if (strlen($response) == 3)
            {
                // Only code no message.
                break;
            }
            else
            {
                array_push($messages, substr($response, 4));

                if ($response[3] == " ")
                {
                    // https://stackoverflow.com/a/7776454/5638260
                    // No more to read.
                    break;
                }
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
        $this->socket->writeString($command . PHP_CRLF);
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

    private function isInvalidAuthenticationCode(int $code) : bool
    {
        return $code == 535;
    }

    private function doStandardAuth(string $username, string $password) : void
    {
        // Sending AUTH LOGIN.
        $this->write("AUTH LOGIN");

        // Reading response.
        $response = $this->read();

        if ($response->code !== 334)
        {
            throw new Exception("Invalid AUTH LOGIN response: " . $response->code);
        }

        if (base64_decode($response->messages[0]) !== "Username:")
        {
            throw new Exception("Invalid SMTP username prompt");
        }

        // Sending username.
        $this->write(base64_encode($username));

        // Reading response.
        $response = $this->read();

        if ($response->code !== 334)
        {
            throw new Exception("Invalid AUTH LOGIN username response: " . $response->code);
        }

        if (base64_decode($response->messages[0]) !== "Password:")
        {
            throw new Exception("Invalid SMTP password prompt");
        }

        // Sending password.
        $this->write(base64_encode($password));

        // Reading response.
        $response = $this->read();

        if ($this->isInvalidAuthenticationCode($response->code))
        {
            throw new Exception("SMTP authentication failed");
        }
        else if ($response->code !== 235)
        {
            throw new Exception("Invalid SMTP authentication response: " . $response->code);
        }
    }

    private function doPlainAuth(string $username, string $password) : void
    {
        // Sending AUTH PLAIN.
        $this->write("AUTH PLAIN");

        // Reading response.
        $response = $this->read();

        if ($response->code !== 334)
        {
            throw new Exception("Invalid AUTH PLAIN response: " . $response->code);
        }

        // Sending username and password.
        $this->write(base64_encode(
            sprintf("\0%s\0%s", $username, $password)
        ));

        // Reading response.
        $response = $this->read();

        if ($this->isInvalidAuthenticationCode($response->code))
        {
            throw new Exception("SMTP authentication failed");
        }
        else if ($response->code !== 235)
        {
            throw new Exception("Invalid SMTP authentication response: " . $response->code);
        }
    }

    private function do2Auth(string $username, string $authToken) : void
    {
        // Sending AUTH XOAUTH2.
        $token = base64_encode(sprintf("user=%s%sauth=Bearer %s%s%s",
            $username, chr(1),
            $authToken, chr(1), chr(1)
        ));
        $this->write("AUTH XOAUTH2 " . $token);

        // Reading response.
        $response = $this->read();

        if ($this->isInvalidAuthenticationCode($response->code))
        {
            throw new Exception("SMTP authentication failed");
        }
        else if ($response->code !== 235)
        {
            throw new Exception("Invalid SMTP authentication response: " . $response->code);
        }
    }

    public function doAuth(
        string $username,
        string $password,
        int $authType = self::AUTH_TYPE_STANDARD
    ) : void
    {
        if ($authType == self::AUTH_TYPE_STANDARD)
        {
            $this->doStandardAuth($username, $password);
        }
        else if ($authType == self::AUTH_TYPE_PLAIN)
        {
            $this->doPlainAuth($username, $password);
        }
        else if ($authType == self::AUTH_TYPE_2AUTH)
        {
            $this->do2Auth($username, $password);
        }
        else
        {
            throw new InvalidArgumentException("Invalid auth type: " . $authType);
        }
    }

    public function quit() : void
    {
        try
        {
            // Sending QUIT.
            $this->write("QUIT");
        }
        finally
        {
            // Closing socket.
            $this->socket->close();
        }
    }
}

?>