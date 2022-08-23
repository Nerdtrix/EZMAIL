<?php
    use EZMAIL\EZMAIL;

    /**
    * @copyright (c) Nerdtrix LLC 2021
    * @author Name: Jerry Urena
    * @author Name: Ivan Kara
    * @license MIT (included with this project)
    */

    #Autoload
    require "vendor/autoload.php";
    
    /**
     * This config includes all of the available parameters.
     */
    $ezmail = new EZMAIL();

    #Config
    $ezmail->appName = "EZMAIL";
    $ezmail->hostName = "Myhost";
    $ezmail->portNumber = 587;
    $ezmail->username = "myUsername";
    $ezmail->password = "MyPassword";

    #Email
    $ezmail->subject = "this is subject";
    $ezmail->body = "this is message";
    $ezmail->to = [ "Mr Recv" => "exampleEmail@gmail.com" ];
    
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
?>