<?php
$bot_token = ""; // Insert your bot token here

require_once __DIR__."/../vendor/autoload.php";

require __DIR__."/../src/TelegramSDK.php";

$instance = new \TelegramSDK\Bot($bot_token);

$instance->set_callback("callback", "message_text");

$instance->check_for_messages();

function callback($response) {
    global $instance;
    $message = $response["text"];
    $chat_id = $response["chat"];
    $instance->send_message($chat_id, $message);
}