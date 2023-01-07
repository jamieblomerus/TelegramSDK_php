<?php
$bot_token = ""; // Insert your bot token here

require_once __DIR__."/../vendor/autoload.php";

require __DIR__."/../src/TelegramApiWrapper.php";

$instance = new \TelegramApiWrapper\Bot($bot_token);

$instance->set_callback("callback");

$instance->check_for_messages();

function callback($message) {
    global $instance;
    $instance->send_message($message->chat->id, "Hello ".$message->from->first_name);
}