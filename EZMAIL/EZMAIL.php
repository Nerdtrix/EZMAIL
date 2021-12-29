<?php

namespace EZMAIL;

use Exception;
use finfo;

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
    public array $attachments;
    public string $bounceAddress;
    public string $replyTo;

    private ISMTPFactory $smtpFactory;
    private IMailIdGenerator $mailIdGenerator;
    private IMailBuilder $mailBuilder;

    private ?ISMTP $smtp = null;

    public function __construct(
        // Message.
        string $subject,
        string $body,
        array $to,

        // Connection.
        string $appName,
        string $hostName,
        int $portNumber,
        string $username,
        string $password,

        // Message.
        array $from = [],
        array $cc = [],
        array $bcc = [],
        array $attachments = [],
        string $bounceAddress = "",
        string $replyTo = "",

        // Connection.
        float $timeout = 30,
        int $authType = SMTP::AUTH_TYPE_STANDARD,
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

    public function send() : void
    {
        // Creating SMTP instance.
        $this->smtp = $this->smtpFactory->create(
            $this->hostName,
            $this->portNumber,
            $this->timeout
        );

        // Connecting.
        $this->smtp->connect();

        try
        {
            
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