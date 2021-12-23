<?php
    namespace EZMAIL;
    use \Exception;


    class EZMAIL
    {

        /**
         * Within the constructor we will try to assign the parameters values 
         * if they are sent within the instantiation. THis is the preferred way to 
         * set the values.
         */
        public function __construct(array $autoAssign = [])
        {
            if(!empty($autoAssign))
            {
                foreach($autoAssign as $key => $value) 
                {
                    if(property_exists($this, $key))
                    {
                        $this->$key = $value;
                    }
                }
            }
        }


        /**
         * @param string suject (required)
         * The subject of your email is perhaps the most 
         * important few words in the entire email.
         */
        public string $subject;


        /**
         * @param string body (required)
         * The body is the actual text of the email. Generally, you'll write this 
         * just like a normal letter, with a greeting, one or more paragraphs, 
         * and a closing with your name. You can add plain text or HTML content to this string.
         */
        public string $body;


        /**
         * @param string replayTo (obtional)
         * A Reply-To address is identified by inserting the Reply-To header in your email. 
         * It is the email address that the reply message is sent when you want the reply 
         * to go to an email address that is different than the From: address.
         */
        public string $replyTo;


        /**
         * @param array from (obtional)
         * Each message you send out has both the sender and from address. 
         * The sender domain is what the receiving email server sees when 
         * initiating the session. The from address is what your recipients will see.
         * 
         * If this array is not set then the default email address will be used to send the email.
         * This is usually not required if you are going to receive responses within the same email used to deliver.
         * 
         * @example ["name" => "mail@myexamplemail.com"]
         */
        public array $from = [];


        /**
         * @param array to (Required)
         * Is the main recipients of the email.
         * 
         * @example ["name" => "mail@myexamplemail.com"]
         */
        public array $to = [];


        /**
         * @param array attachment (optional)
         * One or more files can be attached to any email message, 
         * and be sent along with it to the recipient.
         * 
         * Here you can add as many attachments as you wish to add in the form of an array.
         * A full URL is required ex: www.mywebsite.com/myfile1.pdf
         * 
         * @example ["name" => "path to file"]
         */
        public array $attachment = [];


        /**
         * @param array cc (obtional)
         * Any address that appears after the Cc: header would receive a copy of the message being sent.
         * 
         * @example ["name" => "mail@myexamplemail.com"]
         */
        public array $cc = [];


        /**
         * @param array bcc (obtional)
         * a copy of the message is sent to the recipient that you specify. 
         * Any recipients added to the Bcc box will not be shown to any other 
         * recipients who receive the message.
         * 
         * @example ["name" => "mail@myexamplemail.com"]
         */
        public array $bcc = [];


        /**
         * @param array config
         * 
         * this variable contains the connection configuration.
         * Without this a connection will not be possible.
         * 
         * @example 
         *  [
                "isDebug" => true, //optional false by default
                "hostName" => "smtp.hostExample.com",
                "portNumber" => 587, 
                "timeout" => 30, //optional
                "username" => "email@example.com",
                "password" => "credentials",
                "authToken" => "" //optional when password is set
                "authType" => "STANDARD",
                "options" => []
            ];
         */
        public array $config = [];


        /**
         * @param string appName
         * This parameter is optional but recommended to set.
         * You usually will want to add the name of your company here.
         */
        public string $appName = "EZMAIL";


        /**
         * @param bool useSMTP
         * If this boolean is enabled the SMTP protocol is used. 
         * If this boolean is disabled you will need to modify the php.ini to add
         * your mail configurations.
         */
        public bool $useSMTP = true;


        /**
         * @param string boundary | headerContent | bodyContent
         * This parameter is going to hold the email content when
         * we call the send function. This parameters does not require any further action.
         */
        private $boundary, $headerContent, $bodyContent;


         /**
         * @method send
         * @return string
         * @throws exceptions
         * 
         * @before attempting to send an email you must first set the
         * required strings to prevent connection errors.
         */
        public function send() : bool
        {   
            #Validate the info before instantiation.
            if(empty($this->subject)) throw new Exception("A subject is required");
            if(empty($this->body)) throw new Exception("The body of the email is required");
            if(empty($this->to)) throw new Exception("The main recipient of the email cannot be empty, Please set the 'To' array.");
            

            /**
             * This is the default SMTP protocol
             */
            if($this->useSMTP)
            {
                if(empty($this->config)) throw new Exception("The configuration array is required");

                #Instantiate the SMTP class
                $ezmail = new SMTP($this->config);

                #Write mail from command
                $ezmail->writeAndValidate(sprintf("MAIL FROM: <%s>", $this->config["username"]), 250);

                #Assign RCPT TO
                foreach($this->to as $name => $email)
                {
                    $ezmail->writeAndValidate("RCPT TO: <{$email}>", 250);
                }

                #Write DATA command
                $ezmail->writeAndValidate("DATA", 354);

                #Build mail header string
                $this->buildHeader(); 

                #Build mail body string
                $this->buildBody(); 

                #Send the data
                $ezmail->writeAndValidate($this->headerContent . $this->bodyContent, 250);

                #Quit
                $ezmail->quit();
            }

            /**
             * With this function you are using a PHP library which has a function called mail.
             */
            if(!$this->useSMTP)
            {
                if (!function_exists("mail"))
                {
                    throw new Exception("Please enable or install php mail");
                }

                #Build mail header string
                $this->buildHeader(); 

                #Build mail body string
                $this->buildBody();    

                #Send using mail
                if(!mail(end($this->to), $this->subject, $this->bodyContent, $this->headerContent))
                {
                    throw new Exception("Unable to send mail");
                }
            }

            return true;
        }


        /**
         * STOP :: WARNING :: before modifying this file 
         * you must read and understant how mime works.
         */
        private function buildHeader() : void
        {
            #Email header
            $this->addString(["MIME-Version" => "1.0"]);
            $this->addString(["X-PoweredBy" => $this->appName]);
            $this->addString(["X-Mailer" => $this->appName]);
            $this->addString(["Date" => date('r')]);
            $this->addString(["X-Priority" => 3]);  
            $this->addString(["Subject" => $this->subject], "headerContent", 1, true);
            $this->addString(["Return-Path" => $this->config["username"]]);
        
            #Default value
            if(empty($this->from))
            {
                $this->from = [$this->appName => $this->config["username"]];
            }

            $this->addString(["From" => sprintf("%s <%s>", key($this->from), end($this->from))]);
            $this->addString(["Message-ID" => sprintf("<%s.%s>", md5(uniqid()),  end($this->from))]);
            
            #To Header 
            $tostring = null;
            foreach ($this->to as $toName => $toEmail) 
            {
                if(!empty($toName))
                {
                    $toName = $this->encodeString($toName);
                }

                $tostring .=  "{$toName}<{$toEmail}>,";
            }

            #Remove the last comma
            $tostring = rtrim($tostring, ",");

            $this->addString(["To" => $tostring]);


            #CC Header 
            $ccString = null;
            foreach ($this->cc as $ccName => $ccEmail) 
            {
                if(!empty($ccName))
                {
                    $ccName = $this->encodeString($ccName); 
                }

                $ccString .=  "{$ccName} <{$ccEmail}>,";
            }

            #Remove the last comma
            $ccString = rtrim($ccString, ",");

            $this->addString(["Cc" => $ccString]);


            #BCC Header 
            $bccstring = null;
            foreach ($this->bcc as $bccName => $bccEmail) 
            {
                if(!empty($bccName))
                {
                    $bccName = $this->encodeString($bccName);
                }

                $bccstring .=  "{$bccName} <{$bccEmail}>,";
            }

            #Remove the last comma
            $ccString = rtrim($ccString, ",");

            $this->addString(["Bcc" => $ccString]);

            #Reply to
            if(empty($this->replyTo))
            {
                $this->replyTo = $this->config["username"];
            }

            $this->addString(["Reply-To" => $this->replyTo]);
            
            $this->boundary = md5(uniqid(rand(), true));

            $multiPart = !$this->attachment ? "alternative" : "mixed";

            $this->addString(["Content-Type" => "multipart/{$multiPart}; boundary=\"{$this->boundary}\""]); 
        }


        /**
         * Body email content. 
         */
        private function buildBody() : void
        {
            #html content
            $this->addString("--{$this->boundary}", "bodyContent");
            $this->addString(["Content-Type" => "text/html; charset=\"UTF-8\""], "bodyContent");
            $this->addString(["Content-Transfer-Encoding" => "base64"], "bodyContent", 2); #Two line breaks
            $this->addString(chunk_split(base64_encode($this->body)), "bodyContent");

            #Attachments
            if(!empty($this->attachment))
            {
                foreach ($this->attachment as $name => $path)
                {
                    #Add file extension to the name
                    $name = sprintf("%s.%s", $name, pathinfo($path, PATHINFO_EXTENSION));

                    $this->addString("--{$this->boundary}", "bodyContent");
                    $this->addString(["Content-Type" => "application/octet-stream; name=\"{$name}\""], "bodyContent");
                    $this->addString(["Content-Transfer-Encoding" => "base64"], "bodyContent");
                    $this->addString(["Content-Disposition" => "attachment; filename=\"{$name}\""], "bodyContent", 2);
                    $this->addString(chunk_split(base64_encode(file_get_contents($path))), "bodyContent"); 
                }
            }

            #End alternative
            $this->addString("--{$this->boundary}--", "bodyContent");

            #End content with a period
            $this->addString(".", "bodyContent");
        }


        /**
         * @param string 
         * @return string
         */
        private function encodeString(string $string) : string
        {
            return sprintf("=?utf-8?B?%s?= ", base64_encode($string));
        }


        /**
         * @param string | array content
         * @param string type (header or body)
         * @param int breakNumber (number of line breaks max 2)
         * @param boolean encoded
         * Appends to requested type
         */
        private function addString($content, string $type = "headerContent", int $breakNumber = 1, bool $encoded = false) : void
        {
            #determine line breaks
            $lineBreak = $breakNumber == 1 ? PHP_EOL : PHP_EOL . PHP_EOL;

            #Content is not an array
            if(!is_array($content))
            {
                $this->{$type} .= sprintf("%s%s", $content, $lineBreak);

                return;
            }

            #Content is encoded
            if($encoded)
            {
                $this->{$type} .= sprintf("%s: =?utf-8?B?%s?=%s", key($content), base64_encode(end($content)), $lineBreak);

                return;
            }
            
            #Default
            $this->{$type} .= sprintf("%s: %s%s", key($content), end($content), $lineBreak);
        }
    }