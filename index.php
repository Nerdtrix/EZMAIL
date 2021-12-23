<?php
    use EZMAIL\EZMAIL;

    /**
    * @copyright (c) Nerdtrix LLC 2021
    * @author Name: Jerry Urena
    * @author Social link:  @jerryurenaa
    * @author email: jerryurenaa@gmail.com
    * @author website: jerryurenaa.com
    * @license MIT (included with this project)
    */

    #Autoload
    spl_autoload_register(function ($className)
    {
        $fileName = sprintf("%s.php", $className);

        if (file_exists($fileName))
        {
            require ($fileName);
        }
        else
        {
            die(sprintf("Class not found %s", $fileName));
        }
    });

    
    /**
     * This config includes all of the available parameters.
     */
    $config = [
        "appName" => "EZMAIL",
        "useSMTP" => true,
        "subject" => "Test Email",
        "body" => "<p>This is a sample email that can be in plain text or HTML</p>",
        "to" => ["My name" => "example@host.com"],
        "replyTo" => "",
        "from" => [],
        "attachment" => [],
        "cc" => [],
        "bcc" => [],
        "config" => [
            "isDebug" => true,
            "hostName" => "smtp.myhost.com",
            "portNumber" => 587,
            "timeout" => 30,
            "username" => "example@host.com",
            "password" => "MyCredentials",
            "authToken" => "",
            "authType" => "STANDARD",
            "options" => []
        ]
    ];


    /**
     * This is the most common config.
     */
    $config1 = [
        "appName" => "EZMAIL",
        "subject" => "Test Email",
        "body" => "<p>This is a sample email that can be in plain text or HTML</p>",
        "to" => ["My name" => "example@host.com"],
        "config" => [
            "hostName" => "smtp.myhost.com",
            "portNumber" => 587,
            "username" => "example@host.com",
            "password" => "MyCredentials",
        ]
    ];


    /**
     * Config to use the default php mail() function. (not recommended)
     */
    $config2 = [
        "appName" => "EZMAIL",
        "useSMTP" => false,
        "subject" => "Test Email",
        "body" => "<p>This is a sample email that can be in plain text or HTML</p>",
        "to" => ["My name" => "example@host.com"],
        "config" => ["username" => "example@host.com"]
    ];

    
    try
    {
        $ezmail = new EZMAIL($config1);
        
        if($ezmail->send()) print("Email sent succesfully");
    }
    catch(Exception $ex)
    {
        print("unable to send message");
    }   