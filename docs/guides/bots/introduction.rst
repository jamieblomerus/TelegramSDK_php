Introduction to Bots
===================
What is a Telegram bot?
---------------
This is how Telegram describes bots:

    Telegram Bots are special accounts that do not require an additional phone number to set up. These accounts serve as an interface for code running somewhere on your server.

Basically, a bot is a Telegram account controlled by your code. You interact with it using the Telegram API. The API allows you to send and receive messages, photos, videos, and more.

What can bots do?
---------------
Bots can:

- ☑ Send and receive messages
- ☑ Send and receive media, files or stickers
- ☑ Receive payments
- ☑ Host games
- ☐ Cook you a nice dinner

How do I create a bot?
---------------
You can create a bot using the BotFather. The BotFather is a bot that helps you create and manage your bots. You can find it by searching for `@BotFather`_ in Telegram.

Once your bot is created, you can use the :php:class:`TelegramSDK\Bot` class and namespace to interact with it.

.. code-block:: php

    $instance = new \TelegramSDK\Bot("YOUR_BOT_TOKEN");
    $instance->set_callback("callback", "message_text"); // Will call the callback function when a text message is received
    $instance->check_for_messages(); // Checks for new messages and calls the callback function if a message is received 

    function callback($response) {
        global $instance;
        $message = $response["text"];
        $chat_id = $response["chat"];
        $instance->send_message($chat_id, "You said: $message");
    }

Congratulations! You've just created your first bot!

.. note::

    If you ever lose or need to reset your bot token, you can reset it by searching for `@BotFather`_ in Telegram and typing */token*.

.. _@BotFather: https://telegram.me/BotFather