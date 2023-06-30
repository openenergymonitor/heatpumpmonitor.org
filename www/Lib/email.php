<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');


class Email
{
    private $auth_key;

    public function __construct($auth_key=false)
    {
        if (!$auth_key) {
            global $settings;
            $auth_key = $settings['mailersend_api_key'];
        }
        $this->auth_key = $auth_key;
    }

    function send($message) {

        if (!isset($message['from'])) $message['from'] = "emoncms@openenergymonitor.org";
        if (!isset($message['to'])) return array("success"=>false, "message"=>"No recipient");
        if (!isset($message['subject'])) return array("success"=>false, "message"=>"No subject");
        if (!isset($message['text'])) return array("success"=>false, "message"=>"No text");
        if (!isset($message['html'])) return array("success"=>false, "message"=>"No html");

        if (!is_array($message['to'])) {
            $message['to'] = array(array("email" => $message['to']));
        }

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, "https://api.mailersend.com/v1/email");
        curl_setopt($curl, CURLOPT_POST, true);

        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array(
            "from" => array("email" => $message['from']),
            "to" => $message['to'],
            "subject" => $message['subject'],
            "text" => $message['text'],
            "html" => $message['html']
        )));

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "X-Requested-With: XMLHttpRequest",
            "Authorization: Bearer $this->auth_key"
        ));

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        curl_close($curl);

        print $response;

        return $response;
    }
}