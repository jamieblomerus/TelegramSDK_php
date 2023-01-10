Getting updates
================
An update is an event such as a new message, a channel post, a new member in a chat, a chat join request or a change to any of these. Depending on your use case, an update is most often a new or edited message.

This SDK handles most type of updates, sorts them up automatically and save them for the future. And allows you to specify filters for your callbacks.

The SDK will automatically handle the updates for you and only call the appropriate callbacks, but you can also manually handle them by sending an custom request to the Telegram API (more on that in the end).

Getting new messages
--------------------
Messages are the most common type of update. You can get them by using the :php:method:`TelegramSDK\Bot::check_for_messages()` method. This method checks for updates and calls appropriate callbacks.

.. code-block:: php

    $bot->check_for_messages();

Callbacks
---------
For information on how to use callbacks, check its dedicated page.

Get updates manually
--------------------
If you want to get updates manually, you can use the :php:method:`TelegramSDK\Bot::send_custom_request()` method to send a custom request to the Telegram API.

**Example:**

.. code-block:: php

    $updates = $bot->send_custom_request('getUpdates', [
        'offset' => 0,
        'limit' => 100,
        'timeout' => 0,
    ]);

    foreach ($updates as $update) {
        // Do something with the update
    }

This API method "getUpdates" is used to get updates from the Telegram API. And it returns an array of Update_ objects. You can read more about it here: https://core.telegram.org/bots/api#getupdates.

.. _Update: https://core.telegram.org/bots/api#update