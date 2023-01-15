Getting started
=============

Welcome to the documentation for TelegramSDK_php.

TelegramSDK_php is a PHP library for the Telegram APIs. It currently only supports the Bot API, but the plan is to add support for the other APIs as well.

Installation
------------
To install TelegramSDK_php, you simply download the source code or latest `Release`_ and include the main file in your project.

.. code-block:: php

   require_once 'path/to/TelegramSDK_php/TelegramSDK.php';

.. hint::
    Composer will be availble in the future, when the project is at a later stage.

Usage of the Bot API
--------------------
To use the Bot API, you need to create a new instance of the :php:class:`TelegramSDK\Bot` class. You can then use the methods to send messages, get updates, etc.

Read more about how to get started with the Bot API in the Introduction to bots.

.. _Release: https://github.com/jamieblomerus/TelegramSDK_php/releases