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
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // Never echo the provider response: callers return JSON, and printing
        // here corrupts it and leaks delivery detail to whoever triggered the
        // email. Log failures instead.
        if ($httpcode < 200 || $httpcode >= 300) {
            error_log("Email send failed, http $httpcode: ".substr((string) $response, 0, 500));
        }

        return $response;
    }
}