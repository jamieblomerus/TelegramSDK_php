<?php
$bot_token = ""; // Insert your bot token here

require_once __DIR__."/../vendor/autoload.php";

require __DIR__."/../src/TelegramApiWrapper.php";

$instance = new \TelegramApiWrapper\Bot($bot_token);

var_dump($instance->getUpdates());