<?php

namespace EZMAIL;

use Exception;
use finfo;
use InvalidArgumentException;

class EZMAIL implements IMailBuilderWriter
{
    public string $appName;
    public string $hostName;
    public string $portNumber;
    public float $timeout;
    public int $authType;
    public string $username;
    public string $password;
    public string $authToken;

    public string $subject;
    public string $body;
    public array $from;
    public array $to;
    public array $cc;
    public array $bcc;
    public array $replyTo;
    public array $attachments;
    public string $bounceAddress;

    private ISMTPFactory $smtpFactory;
    private IMailIdGenerator $mailIdGenerator;
    private IMailBuilder $mailBuilder;

    private ?ISMTP $smtp = null;

    public function __construct(
        // Message.
        string $subject = "",
        string $body = "",
        array $from = [],
        array $to = [],
        array $cc = [],
        array $bcc = [],
        array $replyTo = [],
        array $attachments = [],
        string $bounceAddress = "",

        // Connection.
        string $appName = "EZMAIL",
        string $hostName = "",
        int $portNumber = 587,
        float $timeout = 30,
        int $authType = SMTP::AUTH_TYPE_STANDARD,
        string $username = "",
        string $password = "",
        string $authToken = "",

        // DI.
        ?ISMTPFactory $smtpFactory = null,
        ?IMailIdGenerator $mailIdGenerator = null,
        ?IMailBuilder $mailBuilder = null
    )
    {
        $this->appName = $appName;
        $this->hostName = $hostName;
        $this->portNumber = $portNumber;
        $this->timeout = $timeout;
        $this->authType = $authType;
        $this->username = $username;
        $this->password = $password;
        $this->authToken = $authToken;

        $this->subject = $subject;
        $this->body = $body;
        $this->from = $from;
        $this->to = $to;
        $this->cc = $cc;
        $this->bcc = $bcc;
        $this->attachments = $attachments;
        $this->bounceAddress = $bounceAddress;
        $this->replyTo = $replyTo;

        if ($smtpFactory == null)
        {
            $this->smtpFactory = new SMTPFactory;
        }
        else
        {
            $this->smtpFactory = $smtpFactory;
        }

        if ($mailIdGenerator == null)
        {
            $this->mailIdGenerator = new MailIdGenerator;
        }
        else
        {
            $this->mailIdGenerator = $mailIdGenerator;
        }

        if ($mailBuilder == null)
        {
            $this->mailBuilder = new MailBuilder;
        }
        else
        {
            $this->mailBuilder = $mailBuilder;
        }
    }

    private function validate() : void
    {
        if (empty($this->subject))
        {
            throw new InvalidArgumentException("Message subject is empty");
        }

        if (empty($this->body))
        {
            throw new InvalidArgumentException("Message body is empty");
        }

        if (empty($this->to))
        {
            throw new InvalidArgumentException("No message recipients");
        }

        if (empty($this->hostName))
        {
            throw new InvalidArgumentException("Hostname is empty");
        }

        if (empty($this->username))
        {
            throw new InvalidArgumentException("Username is empty");
        }

        if ($this->authType === SMTP::AUTH_TYPE_2AUTH)
        {
            if (empty($this->authToken))
            {
                throw new InvalidArgumentException("Auth token is empty");
            }
        }
        else
        {
            if (empty($this->password))
            {
                throw new InvalidArgumentException("Password is empty");
            }
        }

        if (count($this->from) > 1)
        {
            throw new InvalidArgumentException("Too many sender");
        }
    }

    public function send() : string
    {
        // Validating.
        $this->validate();

        // Creating SMTP instance.
        $this->smtp = $this->smtpFactory->create(
            $this->hostName,
            $this->portNumber,
            $this->timeout
        );

        try
        {
            // Connecting.
            $this->smtp->connect();

            // Do handshake.
            $this->smtp->doHandshake();

            // Authenticating.
            $useAuthToken = $this->authType === SMTP::AUTH_TYPE_2AUTH;
            $this->smtp->doAuth(
                $this->username,
                $useAuthToken ? $this->authToken : $this->password,
                $this->authType
            );
            
            // Start mail session.
            $fromAddress = $this->username;

            if (!empty($this->from))
            {
                $fromAddress = array_values($this->from)[0];
            }

            $this->smtp->startSendMail($fromAddress, $this->to);

            // Sending mail data.
            $mailId = $this->mailIdGenerator->generate();
            $from = $this->from;

            if (empty($from))
            {
                $from = [ $this->username ];
            }

            $replyTo = $this->replyTo;

            if (empty($replyTo))
            {
                $replyTo = [ $this->username ];
            }

            $this->mailBuilder->build(
                $mailId,
                $this->subject,
                $this->body,
                $from,
                $this->to,
                $this->cc,
                $this->bcc,
                $replyTo,
                $this->attachments,
                $this->bounceAddress,
                $this->appName,
                $this // will write back to $smtp.
            );

            // Done mail session.
            $mailIdResult = $this->smtp->endSendMail();

            if ($mailId !== $mailIdResult)
            {
                throw new Exception(sprintf(
                    "Unable to verify mail id. Expected: %s. Got: %s.",
                    $mailId,
                    $mailIdResult
                ));
            }

            return $mailId;
        }
        finally
        {
            // Closing connection.
            $this->smtp->quit();
            $this->smtp = null;
        }
    }

    public function writeHeader(string $data): void
    {
        if ($this->smtp == null)
        {
            throw new Exception("SMTP not initialized");
        }

        $this->smtp->writeMailData($data);
    }

    public function writeBody(string $data): void
    {
        if ($this->smtp == null)
        {
            throw new Exception("SMTP not initialized");
        }

        $this->smtp->writeMailData($data);
    }
}

?>