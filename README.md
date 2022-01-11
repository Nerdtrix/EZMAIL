# EZMAIL 

[![Latest Stable Version](http://poser.pugx.org/ezmail/ezmail/v)](https://packagist.org/packages/ezmail/ezmail)downloads)](https://packagist.org/packages/ezmail/ezmail) 
[![License](http://poser.pugx.org/ezmail/ezmail/license)](https://packagist.org/packages/ezmail/ezmail) 
[![PHP Version Require](http://poser.pugx.org/ezmail/ezmail/require/php)](https://packagist.org/packages/ezmail/ezmail)

## Overview
EZMAIL is a lightweight package created with PHP using the official MIME documentation to send emails using the latest SMTP configuration. By using this package you will be able to send encrypted emails to anyone with a valid email address.

## Features
- Send SMTP emails from PHP 
- Send mail from localhost or server
- Send one or multiple attachments supported
- Send emails with multiple To, CC and BCC
- Auto email encryption
- Supports LOGIN, PLAIN and XOAUTH2 login types
- Compatible with php 7.4 or later
- Easy implementation within any php code.
- Supports Plain or HTML body
- Log details while in debug mode.


## Further Documentation for developers

[Link to RFC0821](https://www.ietf.org/rfc/rfc0821.txt)

[Link to RFC0822](https://tools.ietf.org/html/rfc822)

[Link to RFC1869](https://tools.ietf.org/html/rfc1869)

[Link to RFC2045](https://tools.ietf.org/html/rfc2045)

[Link to RFC2821](https://www.ietf.org/rfc/rfc2821.txt)

## Installation

```
composer require ezmail/ezmail
```


## Ussage 

```php
<?php
    use EZMAIL\EZMAIL; //update this according to your path.
    use \Exceptions; 

    #Autoload
    require 'vendor/autoload.php';

    $ezmail = new EZMAIL();

    #Config
    $ezmail->appName = "EZMAIL";
    $ezmail->hostName = "smtp.myhost.com";
    $ezmail->portNumber = 123;
    $ezmail->username = "myUsername";
    $ezmail->password = "myPassword";

    #Email
    $ezmail->subject = "this is subject";
    $ezmail->body = "this is message";
    $ezmail->to = [ "Mr Recv" => "toEmail@example.com" ];

    #uncomment to send email with attachments. A full file path is required.
    //$ezmail->attachments = [ "https://mywebsite/myfile.txt" ];

    try
    {
        if($ezmail->send())
        {
            print("Email sent succesfully");
        }
        else
        {
            print("unable to send message");
        }
    }
    catch(Exception $ex)
    {
        print($ex->getMessage());
    } 
```

## List of available configurations

```php

    #New instance
    $ezmail = new EZMAIL();

    $ezmail->subject = "";
    $ezmail->body = "";
    $ezmail->to = []; 
    $ezmail->from = []; //optional
    $ezmail->cc = []; //optional
    $ezmail->bcc = []; //optional
    $ezmail->replyTo = []; //optional
    $ezmail->attachments = []; //optional
    $ezmail->bounceAddress = ""; //optional
    $ezmail->skipMessageIdValidation = true; //optional

    #Connection.
    $ezmail->appName = "EZMAIL";
    $ezmail->hostName = "";
    $ezmail->portNumber = 587;
    $ezmail->username = "";
    $ezmail->password = "";
    $ezmail->timeout = 30; //optional
    $ezmail->authType = 1; // 1 by default. 2 = auth plain and 3 = 2auth | optional
    $ezmail->authToken = ""; //optional
    $ezmail->isDebug = false; //optional

    #send mail
    $ezmail->send();

```



## Tips
- If you are using gmail as your SMTP server you must enable the less secure apps on google. [Learn more](https://www.google.com/settings/security/lesssecureapps)  


## Credits
[@jerryurenaa](http://jerryurenaa.com)
[@realivanjx](https://github.com/realivanjx)



## License
EZMAIL is [MIT](https://github.com/Nerdtrix/FetchAsync/blob/main/LICENSE.md) licensed.


###### Powered by [Nerdtrix.com](http://nerdtrix.com) | Reinventing the wheels for a better future!