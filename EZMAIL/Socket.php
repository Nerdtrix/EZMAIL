<?php

namespace EZMAIL;
use Exception;

interface ISocket
{
    public function open(string $host, int $port, float $timeout) : void;
    public function readString(int $lenToRead) : string;
    public function writeString(string $data) : void;
    public function enableCrypto() : void;
    public function close() : void;
}

class Socket implements ISocket
{
    private $connection; // resource.

    public function open(string $host, int $port, float $timeout) : void
    {
        $errorCode = 0;
        $errorMessage = "";
        
        // Opening socket.
        if (function_exists("stream_socket_client"))
        {
            $this->connection = stream_socket_client(
                sprintf("%s:%d", $host, $port),
                $errorCode,
                $errorMessage,
                $timeout
            );
        }
        else
        {
            $this->connection = fsockopen(
                $host,
                $port,
                $errorCode,
                $errorMessage,
                $timeout
            );
        }
        
        if (!is_resource($this->connection))
        {
            // Open failed.
            throw new Exception (
                serialize([
                    "errorCode" => $errorCode,
                    "errorMessage" => $errorMessage
                ])
            );
        }
    }

    public function enableCrypto(): void
    {
        if (!stream_socket_enable_crypto(
            $this->connection,
            true,
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
        ))
        {
            throw new Exception("Unable to enable crypto");
        }
    }

    public function readString(int $lenToRead) : string
    {
        // Checking connection state.
        $meta = stream_get_meta_data($this->connection);

        if ($meta["eof"])
        {
            throw new Exception("Connection closed");
        }

        if ($meta["unread_bytes"] === 0)
        {
            // No more to read.
            return "";
        }

        // Reading.
        return fgets($this->connection, $lenToRead + 1);
    }

    public function writeString(string $data) : void
    {
        fwrite($this->connection, $data);
    }

    public function close() : void
    {
        fclose($this->connection);
    }
}

?>