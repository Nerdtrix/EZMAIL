<?php
    namespace EZMAIL;
    use Exception;
    use InvalidArgumentException;

    interface ISMTP
    {
        public function connect() : void;
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

    class SMTP implements ISMTP
    {
        const BUFFER_SIZE = 512;
        
        const AUTH_TYPE_STANDARD = 1;
        const AUTH_TYPE_PLAIN = 2;
        const AUTH_TYPE_2AUTH = 3;

        public string $announcement = "";

        private bool $isSSL;
        private string $hostName;
        private string $portNumber;
        private float $timeout;
        private ISocket $socket;
        private ILogger $logger;

        public function __construct(
            string $hostName,
            int $portNumber,
            float $timeout = 30,
            ?ISocket $socket = null,
            ?ILogger $logger = null
        )
        {
            $this->hostName = $hostName;
            $this->portNumber = $portNumber;
            $this->timeout = $timeout;
            $this->isSSL = false;
            
            if ($socket == null)
            {
                $this->socket = new Socket;
            }
            else
            {
                $this->socket = $socket;
            }

            if ($logger == null)
            {
                $this->logger = new EmptyLogger;
            }
            else
            {
                $this->logger = $logger;
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
                $this->logger->log("SMTPRECV:" . trim($response));
                
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
            $this->logger->log("SMTPSEND:" . $command);
            $this->socket->writeString($command . PHP_CRLF);
        }

        public function connect() : void
        {
            $this->isSSL = strpos($this->hostName, "ssl://") !== false;

            if ($this->portNumber == 465 && !$this->isSSL)
            {
                $this->logger->log("Forcing SSL on port 465");
                $this->hostName = "ssl://" . $this->hostName;
                $this->isSSL = true;
            }

            // Opening socket.
            $this->socket->open(
                $this->hostName,
                $this->portNumber,
                $this->timeout
            );
            $this->logger->log("Connected to smtp server");
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
            // Reading announcement.
            $response = $this->read();

            if ($response->code !== 220)
            {
                throw new Exception("Invalid announcement response: " . $response->code);
            }

            $this->announcement = implode(" ", $response->messages);

            // Send EHLO.
            $useHELO = false;

            if (!$this->doEHLO())
            {
                $this->logger->log("Using HELO instead of EHLO");
                $this->doHELO();
                $useHELO = true;
            }

            if ($this->isSSL)
            {
                // Already secure.
                $this->logger->log("Skipping STARTTLS on SSL connection");
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
            $this->logger->log("Connection to smtp server is secured");

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
                $this->logger->log("Doing standard auth");
                $this->doStandardAuth($username, $password);
            }
            else if ($authType == self::AUTH_TYPE_PLAIN)
            {
                $this->logger->log("Doing plain auth");
                $this->doPlainAuth($username, $password);
            }
            else if ($authType == self::AUTH_TYPE_2AUTH)
            {
                $this->logger->log("Doing 2auth");
                $this->do2Auth($username, $password);
            }
            else
            {
                throw new InvalidArgumentException("Invalid auth type: " . $authType);
            }
        }

        public function startSendMail(
            string $from,
            array $to
        ) : void
        {
            // Sending MAIL FROM.
            $this->write(
                sprintf("MAIL FROM: <%s>", $from)
            );

            // Reading response.
            $response = $this->read();

            if ($response->code !== 250)
            {
                throw new Exception("Invalid MAIL FROM response: " . $response->code);
            }

            // Sending receipents.
            foreach ($to as $address)
            {
                // Sending RCPT TO.
                $this->write(
                    sprintf("RCPT TO: <%s>", $address)
                );

                // Reading response.
                $response = $this->read();

                if ($response->code !== 250)
                {
                    throw new Exception("Invalid RCPT TO response: " . $response->code);
                }
            }

            // Sending DATA.
            $this->write("DATA");

            // Reading response.
            $response = $this->read();

            if ($response->code !== 354)
            {
                throw new Exception("Invalid DATA response: " . $response->code);
            }

            $this->logger->log("Ready to send mail data");
        }

        public function writeMailData(string $data) : void
        {
            $this->write($data);
        }

        public function endSendMail() : string
        {
            // Sending .
            $this->write(".");

            // Reading response.
            $response = $this->read();

            if ($response->code !== 250)
            {
                throw new Exception("Invalid DATA end response: " . $response->code);
            }

            $this->logger->log("Mail data sent");
            $okpos = strpos(strtoupper($response->messages[0]), "OK");

            if ($okpos === false)
            {
                throw new Exception("Invalid DATA end response: " . $response->messages[0]);
            }

            $result = substr($response->messages[0], $okpos + 3); // Skip "OK ".
            $result = explode(" ", $result, 1)[0]; // Get only the first string after OK.
            $result = trim($result, "<>"); // Remove <> brackets.

            if (strpos($result, "id=") === 0) // Some hostings use this format.
            {
                $result = substr($result, 3);
            }

            return $result;
        }

        public function quit() : void
        {
            try
            {
                // Sending QUIT.
                $this->write("QUIT");
            }
            catch (Exception $ex) { }
            finally
            {
                // Closing socket.
                $this->socket->close();
                $this->logger->log("Disconnected from smtp server");
            }
        }
    }
?>