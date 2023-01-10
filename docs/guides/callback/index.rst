Callbacks
=========
A callback is a function that is called when a certain event occurs. In the context of this library, a callback is a function that is called on new Updates.

The callback function must be defined through the :php:method:`TelegramSDK\Bot::set_callback()` method. The callback function must accept one parameter, which is an Array. The Array contains the Update data.

Set a callback function
-----------------------
The following example shows how to set a callback function:

.. code-block:: php

    $bot->set_callback('message_text', "my_callback");

    function my_callback($update) {
        // Do something with the update
    }

Unset a callback function
-------------------------
Sometimes you want to unset a callback function. This shall be done by passing the criteria to the :php:method:`TelegramSDK\Bot::unset_callback()` method.

Criterias / Filters
-------
Criterias (sometimes mentioned as filters) are used to filter out unwanted Updates. For example, if you only want to receive text messages, you can use the message\_text filter. This will only call the callback function when a text message is received.

The following criterias are currently supported:

* default
* message
* message\_text
* message\_photo
* message\_video
* message\_audio
* message\_voice
* message\_document
* message\_sticker

The default callback is used when no other suitable callback is specified.