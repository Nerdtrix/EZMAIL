<?php
    namespace EZMAIL;
    use \Exception;

    /**
    * @copyright (c) Nerdtrix LLC 2021
    * @author Name: Jerry Urena
    * @author Social link:  @jerryurenaa
    * @author email: jerryurenaa@gmail.com
    * @author website: jerryurenaa.com
    * @license MIT (included with this project)
    */

    class SMTP
    {

        private $_connection;

        private 
            $isDebug = false,
            $portNumber,  
            $timeout = 30,
            $hostName,
            $username,
            $password,
            $authToken,
            $authType = "STANDARD", //STANDARD, PLAIN & 2AUTH CURRENTLY SUPPORTED
            $options = []; 
            

        public function __construct(array $config)
        {
            foreach($config as $key => $value) 
            {
                if(property_exists($this, $key))
                {
                    $this->$key = $value;
                }
            }

            try
            {
                $this->connect();

                
                $this->handshake();
                

                $this->auth();
            }
            catch(Exception $ex)
            {
                $error = $ex->getMessage();

                if(unserialize($error) !== false)
                {
                    $error = unserialize($error);
                }

                print_r($error);

                $this->quit();
            }
        }


        /**
         * @throws exceptions
         * @note THis method attempts to connect to the given host and port and
         * if successful it will validate the response from the server and continue
         */
        public function connect() : void
        {
            #If the port is 465 and the url does not contains ssl:// then add it.
            if($this->portNumber === 465 && strpos($this->hostName, "ssl://") === false)
            {
                $this->hostName = sprintf("ssl://%s", $this->hostName);
            }
            
            /**
             * This is the default method of connections because it has more
             * functionalities than the fsockopen function.
             */
            if (function_exists("stream_socket_client"))
            {
                #Create a context stream with the option array.
                $stream = stream_context_create($this->options);

                #Create a connection
                $this->_connection = stream_socket_client(
                    sprintf("%s:%s", $this->hostName, $this->portNumber),
                    $errorNumber,
                    $errorMessage,
                    $this->timeout,
                    STREAM_CLIENT_CONNECT,
                    $stream
                );
            }
            else
            {
                #Alternative connection.
                $this->_connection = fsockopen(
                    $this->hostName,
                    $this->portNumber,
                    $errorNumber,
                    $errorMessage,
                    $this->timeout
                );
            }

            #Verify that we are properly connected
            if (!is_resource($this->_connection))
            {
                throw new Exception (
                    serialize([
                        "errorCode" => $errorNumber,
                        "errorMessage" => $errorMessage
                    ])
                );
            }

            #read output
            $response = $this->read();

            #A successful connection should be code 220 everything else is error
            if((int)$response->code !== 220)
            {
                throw new Exception (
                    serialize([
                        "errorCode" => $response->code,
                        "errorMessage" => $response->response
                    ])
                );
            }
        }


        /**
         * @throws exceptions
         * @note This method is the handshake process for both ports.
         */
        private function handshake() : void
        {
            if($this->portNumber !== 587) throw new Exception("Invalid handshake method, please use port 587");

            $isHelo = false;

            $helo = $this->write(sprintf("%s %s", "EHLO", $this->hostName));

            if($helo->code !== 250)
            {
                $helo = $this->write(sprintf("%s %s", "HELO", $this->hostName));

                if($helo->code !== 250) throw new Exception("An error occurred while sending EHLO/HELO");

                $isHelo = true;
            }

            #THis process of tls is only required on port 587 since the ssl on port 465 is done at the connection time.
            if($this->portNumber === 587)
            {
                $this->writeAndValidate("STARTTLS", 220);

                if(!stream_socket_enable_crypto($this->_connection, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT))
                {
                    throw new Exception("Failed to start TLS");
                }
            
                $this->writeAndValidate(sprintf("%s %s", !$isHelo ? "EHLO" : "HELO", $this->hostName), 250);
            }
        }


        /**
         * @param string authMethod (optional)
         * @throws exceptions 
         * @note this is the auth process
         */
        private function auth() : void
        {
            if($this->authType === "STANDARD")
            {
                $this->writeAndValidate("AUTH LOGIN", 334);
        
                $this->writeAndValidate(base64_encode($this->username), 334);
        
                $this->writeAndValidate(base64_encode($this->password), 235);
            }
            else if($authMethod === "PLAIN")
            {
                $this->writeAndValidate("AUTH PLAIN", 334); 

                $userAndPassword = base64_encode(sprintf(
                    "\0%s\0%s", 
                    $this->username,
                    $this->password
                ));

                $this->writeAndValidate($userAndPassword, 235); 
            }
            else if($authMethod === "2AUTH")
            {
                $token = base64_encode(sprintf("user=%s%sauth=Bearer %s%s%s",
                    $this->username, chr(1),
                    $this->authToken, chr(1), chr(1)
                ));
            
                $this->writeAndValidate("AUTH XOAUTH2 {$token}", 235);
            }
            else
            {
                throw new Exception ("Authentication method not supported: {$authMethod}");
            }
        }


        /**
         * @throws exceptions
         * @return int
         * @note This function gets the unreabytes from the 
         * stream to ensure there are no more bytes to read
         * before sending a new command.
         */
        private function unreadBytes() : int
        {
            $meta = stream_get_meta_data($this->_connection);

            if($meta["eof"]) throw new Exception("connection closed");

            return (int)$meta["unread_bytes"];
        }
        

        /**
         * @param string command
         * @param int correctCode
         * @return bool
         * @note this function validates the correct code with the response code.
         */
        public function writeAndValidate(string $command, int $correctCode) : bool
        {
            $response = $this->write($command);

            if($response->code !== $correctCode)
            {
                throw new Exception($response->message);
            }

            return true;
        }


        /**
         * @param string command
         * @return object
         * Note This function writes and return its response from the stream.
         */
        public function write(string $command) : object
        {
            #Print log while in debug mode
            if($this->isDebug)
            {
                print(sprintf("Sent: %s%s", $command, PHP_EOL));
            }

            #Write command
            fwrite($this->_connection, sprintf("%s%s", $command, PHP_EOL));

            #Return response
            return $this->read();
        }


        /**
         * @note This functions sends a quit command and 
         * close the stream connection. 
         */
        public function quit() : void
        {
            #Send quit even though some servers do not support this command. 
            $this->write("QUIT");

            #Close the stream connection
            fclose($this->_connection);
        }


        /**
         * @param int bytesToRead optional (default 512)
         * @return object
         * @note This function attempts to read every single bytes from the stream.
         */
        private function read(int $bytesToRead = 512) : object
        {
            $response = null;

            $count = 0;
            while((int)$bytesToRead > 0 )
            {
                #Read the response from the connection
                $response .= fgets($this->_connection, $bytesToRead + 1);

                #Check if there are any unread bytes
                $bytesToRead = $this->unreadBytes();

                # Sometimes the 1 will keep showing up so we will break the loop after 3 attempts.
                if($bytesToRead === 1) $count++;

                #Break the loop after the bytesToRead shows 1 for 3 times.
                if($count === 3) break;
            }
        
            #Print the log while in debug mode
            if($this->isDebug)
            {
                print(sprintf("Received: %s%s", $response, PHP_EOL));
            }

            #parse the response code
            $responseCode = substr(trim($response), 0, 3);

            #Return the response and the message
            return (object)["code" => (int)$responseCode, "message" => $response];
        }
    }