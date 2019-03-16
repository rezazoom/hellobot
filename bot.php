<?php

define('BOT_TOKEN', '12345678:replace-me-with-real-token');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

function exec_curl_request($handle) {
  $response = curl_exec($handle);

  if ($response === false) {
	$errno = curl_errno($handle);
	$error = curl_error($handle);
	error_log("Curl returned error $errno: $error\n");
	curl_close($handle);
	return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
	// do not wat to DDOS server if something goes wrong
	sleep(10);
	return false;
  } else if ($http_code != 200) {
	$response = json_decode($response, true);
	error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
	if ($http_code == 401) {
	  throw new Exception('Invalid access token provided');
	}
	return false;
  } else {
	$response = json_decode($response, true);
	if (isset($response['description'])) {
	  error_log("Request was successful: {$response['description']}\n");
	}
	$response = $response['result'];
  }

  return $response;
}

function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
	error_log("Method name must be a string\n");
	return false;
  }

  if (!$parameters) {
	$parameters = array();
  } else if (!is_array($parameters)) {
	error_log("Parameters must be an array\n");
	return false;
  }

  $parameters["method"] = $method;

  $handle = curl_init(API_URL);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  curl_setopt($handle, CURLOPT_POST, true);
  curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

  return exec_curl_request($handle);
}

function sendAction($chat_id, $action = "typing"){
    apiRequestJson("sendChatAction", array(
        "chat_id" => $chat_id,
        "action" => $action
    ));
}
function sendMessage($chat_id, $text = "DEBUG!", $typing = false, $replyto = null, $disable_preview = false, $parse_mode = "HTML"){
    if($typing){
        sendAction($chat_id);
    }
    apiRequestJson("sendMessage", array(
        'chat_id' => $chat_id, 
        "reply_to_message_id" => $replyto, 
        "text" => $text,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => $disable_preview
    ));
}

function processMessage($message) {

  # process incoming message
    $message_id = $message['message_id'];
    $chat_id = $message['chat']['id'];

    if (isset($message['text'])) {

        # incoming text message
        $text = $message['text'];

        if (strpos($text, "/start") === 0) {
            # start message
            sendMessage($chat_id, "Hey dude!");
        } elseif ($text === "Sure!") {
            # reply to keyboard actions
            sendMessage($chat_id, "Nice to meet you!", false, $message_id);
        } else {
            # not defined command, reply to message!
            sendMessage($chat_id, "I don't understand what are you saying!", false, $message_id);
        }
    } else {
        # non text messages
        sendMessage($chat_id, "I just can understand text messages!", false, $message_id);
  }
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    // receive wrong update, must not happen
    exit;
}

if (isset($update["message"])) {
    processMessage($update["message"]);
}