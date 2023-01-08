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
    protected array|null $callback = null;

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
     * @param string|null $criteria
     * @return void
     */
    public function set_callback(string|array $callable, string|null $criteria = null): void {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException("Callback needs to be callable. Official docs: https://www.php.net/manual/en/language.types.callable.php");
            return;
        }

        switch ($criteria) {
            case null:
            case "default":
                $this->callback["default"] = $callable;
                break;
            case "message":
                $this->callback["message"] = $callable;
                break;
            case "message_text":
                $this->callback["message_text"] = $callable;
                break;
            case "message_photo":
                $this->callback["message_photo"] = $callable;
                break;
            case "message_video":
                $this->callback["message_video"] = $callable;
                break;
            case "message_audio":
                $this->callback["message_audio"] = $callable;
                break;
            case "message_voice":
                $this->callback["message_voice"] = $callable;
                break;
            case "message_document":
                $this->callback["message_document"] = $callable;
                break;
            case "message_sticker":
                $this->callback["message_sticker"] = $callable;
                break;
            default:
                throw new \InvalidArgumentException("Invalid criteria. Valid criteria: default, message, message_text, message_photo, message_video, message_audio, message_voice, message_document, message_sticker");
                break;
        }
    }

    /**
     * @brief Unset the callback function.
     * 
     * @param string|null $criteria
     * @return array|null
     */
    public function unset_callback(string|null $criteria = null): array|null {
        if ($criteria === null) {
            $callback = $this->callback;
            $this->callback = null;
            return $callback;
        } else {
            $callback = $this->callback[$criteria];
            unset($this->callback[$criteria]);
            return $callback;
        }
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
     * @brief Get file from file id. API docs: https://core.telegram.org/bots/api#getfile
     * 
     * @param string $file_id
     * @return string|null
     */
    public function get_file(string $file_id): string|null {
        $url = $this->api_url."getFile?file_id=$file_id";
        $result = json_decode(file_get_contents($url));
        if ($result->ok) {
            return "https://api.telegram.org/file/bot$this->bot_token/".$result->result->file_path;
        } else {
            return null;
        }
    }

    /**
     * @brief Send cutom request to the Telegram API.
     * 
     * @param string $method
     * @param array $params
     * @return stdClass|null
     */
    public function send_custom_request(string $method, array $params): \stdClass|null {
        // Check if method is valid
        if (!preg_match("/^[a-zA-Z0-9_]+$/", $method)) {
            throw new \InvalidArgumentException("Method is in wrong format.");
            return array();
        }

        $url = $this->api_url.$method."?";
        foreach ($params as $key => $value) {
            $url .= "$key=".urlencode($value)."&";
        }
        $url = substr($url, 0, -1);
        $result = json_decode(file_get_contents($url));
        if ($result->ok) {
            return $result->result;
        } else {
            return null;
        }
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
            $last_update_id = self::$db_stores["common"]->findById(2);
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
                self::$db_stores["common"]->updateOrInsert(array("_id"=>2, "last_update_id" => $last_update_id), false);
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
            $this->call_message_callback($message);
        }
    }

    /**
     * @brief Call callback on new message.
     * 
     * @param stdClass $message
     * @return void
     */
    private function call_message_callback(\stdClass $message): void {
        if (!isset($this->callback)) {
            return;
        }

        // Get message type
        $message_type = $this->get_message_type($message);

        // Get callback
        $callback = $this->callback["message_".$message_type] ?? null;
        if ($callback == null) {
            $callback = $this->callback["message"] ?? null;
            if ($callback == null) {
                $callback = $this->callback["default"] ?? null;
            }
        }

        if ($callback == null) {
            return;
        }

        // Call callback
        switch ($message_type) {
            case "text":
                call_user_func($callback, array("text" => $message->text, "from" => $message->from->id, "chat" => $message->chat->id, "message" => $message));
                break;
            case "photo":
                $photo_sizes = [
                    "small" => $message->photo[0]->file_id,
                    "medium" => $message->photo[1]->file_id,
                    "large" => $message->photo[2]->file_id
                ];
                call_user_func($callback, array("photo_sizes" => $photo_sizes, "from" => $message->from->id, "chat" => $message->chat->id, "message" => $message));
                break;
            case "video":
                $video = [
                    "file_id" => $message->video->file_id,
                    "width" => $message->video->width,
                    "height" => $message->video->height,
                    "duration" => $message->video->duration,
                    "file_size" => $message->video->file_size ?? null,
                    "filename" => $message->video->file_name ?? null,
                    "thumb" => $message->video->thumb->file_id ?? null
                ];
                call_user_func($callback, array("video" => $video, "from" => $message->from->id, "chat" => $message->chat->id, "message" => $message));
                break;
            case "audio":
                $audio = [
                    "file_id" => $message->audio->file_id,
                    "duration" => $message->audio->duration,
                    "performer" => $message->audio->performer ?? null,
                    "title" => $message->audio->title ?? null,
                    "filename" => $message->audio->file_name ?? null,
                    "thumb" => $message->audio->thumb->file_id ?? null
                ];
                call_user_func($callback, array("audio" => $audio, "from" => $message->from->id, "chat" => $message->chat->id, "message" => $message));
                break;
            case "voice":
                $voice = [
                    "file_id" => $message->voice->file_id,
                    "duration" => $message->voice->duration,
                    "file_size" => $message->voice->file_size ?? null,
                ];
                call_user_func($callback, array("voice" => $voice, "from" => $message->from->id, "chat" => $message->chat->id, "message" => $message));
                break;
            case "document":
                $document = [
                    "file_id" => $message->document->file_id,
                    "filename" => $message->document->file_name ?? null,
                    "file_size" => $message->document->file_size ?? null,
                    "thumb" => $message->document->thumb->file_id ?? null
                ];
                call_user_func($callback, array("document" => $document, "from" => $message->from->id, "chat" => $message->chat->id, "message" => $message));
                break;
            case "sticker":
                $sticker = [
                    "file_id" => $message->sticker->file_id,
                    "width" => $message->sticker->width,
                    "height" => $message->sticker->height,
                    "file_size" => $message->sticker->file_size ?? null,
                    "thumb" => $message->sticker->thumb->file_id ?? null,
                    "emoji" => $message->sticker->emoji ?? null,
                    "set_name" => $message->sticker->set_name ?? null,
                    "is_animated" => $message->sticker->is_animated ?? null,
                    "is_video" => $message->sticker->is_video ?? null
                ];
                call_user_func($callback, array("sticker" => $sticker, "from" => $message->from->id, "chat" => $message->chat->id, "message" => $message));
                break;
            default:
                call_user_func($callback, array($message->from->id, $message->chat->id, $message));
                break;
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
        self::$db_stores["common"] = new \SleekDB\Store("common", self::DB_LOCATION, $configuration);
        self::$db_stores["users"] = new \SleekDB\Store("users", self::DB_LOCATION, $configuration);
        self::$db_stores["messages"] = new \SleekDB\Store("messages", self::DB_LOCATION, $configuration);

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
            "text" => $message->text ?? null,
            "object" => serialize($message)
        ];
        self::$db_stores["messages"]->insert($message);
    }

    /**
     * @brief Save user to database.
     * 
     * @param $chat
     * @return void
     */
    private function store_user($from): void {
        $user = self::$db_stores["users"]->findBy(array("id", "=", $from->id), null, 1);
        if ($user != null) {
            // Update user if needed
            if ($user[0]["username"] != $from->username) {
                self::$db_stores["users"]->updateById($user[0]["_id"], array("username" => $from->username ?? null));
            }
            if ($user[0]["first_name"] != $from->first_name) {
                self::$db_stores["users"]->updateById($user[0]["_id"], array("first_name" => $from->first_name));
            }
            if ($user[0]["last_name"] != $from->last_name) {
                self::$db_stores["users"]->updateById($user[0]["_id"], array("last_name" => $from->last_name ?? null));
            }
            return;
        }

        $user = [
            "id" => $from->id,
            "username" => $from->username ?? null,
            "first_name" => $from->first_name,
            "last_name" => $from->last_name ?? null,
        ];
        self::$db_stores["users"]->insert($user);
    }
}