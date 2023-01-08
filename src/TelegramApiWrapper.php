<?php
/**
 * Project: PHP Telegram API Wrapper
 * 
 * @file TelegramApiWrapper.php
 * @brief This file is the root of the TelegramApiWrapper namespace.
 * @details This file contains the important classes. It is used to communicate with the Telegram API.
 * 
 * @author 	Jamie Blomerus <jamie.blomerus@protonmail.com>
 */

namespace TelegramApiWrapper;
use SleekDB\Store;

// Include files
require_once __DIR__."/bot/User.php";

/**
 * @class Bot
 * @brief This class is used to communicate with the Telegram Bot API.
 * @details This class is used to communicate with the Telegram API. It is used to send messages to the Telegram API.
 */
class Bot {
    const DB_LOCATION = __DIR__."/../db";
    public static Bot $instance;
    protected string $api_url;
    protected string $bot_token;
    public static array $db_stores = array();
    protected string|array|null $callback = null;

    function __construct(string $bot_token) {
        // Check if instance already exists
        if (isset(self::$instance)) {
            throw new \Exception("Bot instance already exists.");
            return;
        }

        // Set instance
        self::$instance = $this;

        // Check that bot token is in the right format
        if (!preg_match("/^[0-9]{8,10}:[a-zA-Z0-9_-]{35}$/", $bot_token)) {
            throw new \InvalidArgumentException("Telegram bot token is in wrong format.");
            return;
        }
        // Set bot token and api url
        $this->bot_token = $bot_token;
        $this->api_url = "https://api.telegram.org/bot$bot_token/";
        // Validate token
        if (!$this->is_token_valid()) {
            throw new \Exception("Telegram bot token is unvalid.");
            return;
        }

        // Setup database
        $this->setup_database();
    }
    
    /**
     * @brief Checks whether the bot has access to a chat.
     * 
     * @param string $chat_id 
     * @return bool
     */
    public function has_access_to_chat(string $chat_id): bool {
        global $telegram_bot_token;
        $url = $this->bot_api_url()."getChatMember?chat_id=$channel_id&user_id=".$telegram_bot_token;
        $result = json_decode(file_get_contents($url));
        if ($result->ok) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @brief Set the callback function.
     * 
     * @param string|array $callable
     * @return void
     */
    public function set_callback(string|array $callable): void {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException("Callback needs to be callable. Official docs: https://www.php.net/manual/en/language.types.callable.php");
            return;
        }

        $this->callback = $callable;
    }

    /**
     * @brief Send a message to a chat.
     * 
     * @param string $chat_id
     * @param string $message
     * @return bool
     */
    public function send_message(string $chat_id, string $message): bool {
        $url = $this->api_url."sendMessage?chat_id=$chat_id&text=".urlencode($message);
        $result = json_decode(file_get_contents($url));
        if ($result->ok) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @brief Check for new messages.
     * 
     * @return void
     */
    public function check_for_messages(): void {
        $updates = $this->get_updates();

        $this->process_updates($updates);
    }

    /**
     * @brief Get bot token.
     * 
     * @return string
     */
    public function get_bot_token(): string {
        return $this->bot_token;
    }
    /**
     * @brief Get updates from the Telegram API. Defaults to only getting new messages.
     * 
     * @param int $offset Defaults to only new updates
     * @param int $limit
     * @param int $timeout
     * @param array $allowed_updates Check https://core.telegram.org/bots/api#update for more information.
     * @return array
     */
    protected function get_updates(int $offset = null, int $limit = 100, int $timeout = 0, array $allowed_updates = array("message")): array {
        // Use last update as offset
        if ($offset == null) {
            $last_update_id = $this->db_stores["common"]->findById(2);
            if ($last_update_id != null) {
                $offset = $last_update_id["last_update_id"];
                $offset++;
            }
        }

        $url = $this->api_url."getUpdates?offset=$offset&limit=$limit&timeout=$timeout&allowed_updates=".json_encode($allowed_updates);
        $result = json_decode(file_get_contents($url));
        if ($result->ok) {
            // Update last update id in database
            if (count($result->result) > 0) {
                $last_update_id = end($result->result)->update_id;
                $this->db_stores["common"]->updateOrInsert(array("_id"=>2, "last_update_id" => $last_update_id), false);
            }

            return $result->result;
        } else {
            return [];
        }
    }

    /**
     * @brief Process updates.
     * 
     * @param array $updates
     * @return void
     */
    private function process_updates(array $updates): void {
        foreach ($updates as $update) {
            // Save user to database
            $user = $update->message->from;
            $this->store_user($user);

            // Save message to database
            $message = $update->message;
            $this->store_message($message);

            // Call callback
            if ($this->callback != null) {
                call_user_func($this->callback, $message);
            }
        }
    }

    /**
     * @brief Setup databases using SleekDB.
     * 
     * @return bool
     */
    private function setup_database(): bool {
        // Create database directory if missing
        if (!file_exists(self::DB_LOCATION)) {
            mkdir(self::DB_LOCATION);
        }

        // Configuration
        $configuration = [
            "timeout" => false,
        ];

        // Initiate all stores needed
        $this->db_stores["common"] = new \SleekDB\Store("common", self::DB_LOCATION, $configuration);
        $this->db_stores["users"] = new \SleekDB\Store("users", self::DB_LOCATION, $configuration);
        $this->db_stores["messages"] = new \SleekDB\Store("messages", self::DB_LOCATION, $configuration);

        return true;
    }

    /**
     * @brief Validate Telegram bot token by testing it.
     * 
     * @return bool
     */
    private function is_token_valid(): bool {
        $url = $this->api_url."getMe";
        $result = json_decode(file_get_contents($url));
        if ($result->ok) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @brief Get message type.
     * 
     * @param $message
     * @return string
     */
    private function get_message_type($message): string {
        if (isset($message->text)) {
            return "text";
        } else if (isset($message->photo)) {
            return "photo";
        } else if (isset($message->video)) {
            return "video";
        } else if (isset($message->audio)) {
            return "audio";
        } else if (isset($message->voice)) {
            return "voice";
        } else if (isset($message->document)) {
            return "document";
        } else if (isset($message->sticker)) {
            return "sticker";
        } else {
            return "unknown";
        }
    }

    /**
     * @brief Save message to database.
     * 
     * @param $message
     * @return void
     */
    protected function store_message($message): void {
        $message = [
            "message_id" => $message->message_id,
            "from" => $message->from->id,
            "chat" => $message->chat->id,
            "date" => $message->date,
            "type" => $this->get_message_type($message),
            "object" => serialize($message)
        ];
        $this->db_stores["messages"]->insert($message);
    }

    /**
     * @brief Save user to database.
     * 
     * @param $chat
     * @return void
     */
    private function store_user($from): void {
        $user = $this->db_stores["users"]->findBy(array("id", "=", $from->id), null, 1);
        if ($user != null) {
            // Update user if needed
            if ($user[0]["username"] != $from->username) {
                $this->db_stores["users"]->updateById($user[0]["_id"], array("username" => $from->username));
            }
            if ($user[0]["first_name"] != $from->first_name) {
                $this->db_stores["users"]->updateById($user[0]["_id"], array("first_name" => $from->first_name));
            }
            if ($user[0]["last_name"] != $from->last_name) {
                $this->db_stores["users"]->updateById($user[0]["_id"], array("last_name" => $from->last_name));
            }
            return;
        }

        $user = [
            "id" => $from->id,
            "username" => $from->username,
            "first_name" => $from->first_name,
            "last_name" => $from->last_name
        ];
        $this->db_stores["users"]->insert($user);
    }
}