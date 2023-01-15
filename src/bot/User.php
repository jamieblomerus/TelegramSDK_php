<?php
/**
 * Project: PHP Telegram SDK
 * 
 * @file User.php
 * @brief This file lets you get information about users.
 * @details This file contains the User class. It is used to get information about users.
 * 
 * @author 	Jamie Blomerus <jamie.blomerus@protonmail.com>
 */

namespace TelegramSDK\Bot;
use SleekDB\Store;
use TelegramSDK\Bot;

/**
 * @brief This class lets you get information about users.
 * @details This class contains functions to get information about users. To change the user, use the set_user() method.
 * 
 * @property-read int $user_id The user id. Use set_user() to change the user.
 * @property-read string $username The username.
 * @property-read string $full_name The full name. This is a combination of the first and last name.
 * @property-read string $first_name The first name.
 * @property-read string $last_name The last name.
 * @see User::set_user()
 */
class User {
    /** @ignore */
    protected int $user_id;
    /** @ignore */
    protected string $username;
    /** @ignore */
    protected string $first_name;
    /** @ignore */
    protected string $last_name;

    function __construct() {
        // Check if database is setup
        if (!isset(Bot::$db_stores["users"])) {
            throw new \Exception("Database is not setup. Have you initialized the Bot class?");
        }
    }

    /**
     * @brief Get protected properties, keeping them protected from being changed.
     * 
     * @param string $name
     * @throws \Exception If variable does not exist or is not allowed to be accessed externally.
     * @return mixed
     */
    public function __get(string $name) {
        switch ($name) {
            case "user_id":
                return $this->user_id;
            case "username":
                return $this->username;
            case "full_name":
                return $this->first_name." ".$this->last_name;
            case "first_name":
                return $this->first_name;
            case "last_name":
                return $this->last_name;
            default:
                throw new \Exception("Property $name does not exist or not allowed to be accessed externally.");
        }
    }

    /**
     * @brief Sets user. Returns false if user does not exist in database.
     * 
     * @param string|int $identifier Can be a username or a user id.
     * @return bool
     */
    public function set_user(string|int $identifier): bool { 
        // Check if identifier is a username
        if (is_string($identifier)) {
            $user = Bot::$db_stores["users"]->findOneBy(array("username", "=", $identifier));
        } else {
            $user = Bot::$db_stores["users"]->findOneBy(array("id", "=", $identifier));
        }
        if (!isset($user)) {
            return false;
        } else {
            $this->user_id = $user["id"];
            $this->username = $user["username"];
            $this->first_name = $user["first_name"];
            $this->last_name = $user["last_name"];
            return true;
        }
    }

    /**
     * @brief Get user profile picture url. Only returns the first one.
     * 
     * Note: This function returns a url containing sensitive information. Make sure to protect it and not share with end users.
     * 
     * @param int $size (Optional) Size of the profile picture. Default is 320.
     * @return string
     */
    public function get_profile_picture(int $size = 320): string {
        if (!isset($this->user_id)) {
            throw new \Exception("User is not set.");
        }

        $query_result = Bot::$instance->send_custom_request("getUserProfilePhotos", array("user_id" => $this->user_id, "limit" => 1));
        $photos = $query_result->photos;
        if (count($photos) == 0) {
            return "";
        } else {
            foreach ($photos[0] as $photo) {
                if ($photo->width == $size) {
                    $file_id = $photo->file_id;
                    $query_result = Bot::$instance->get_file($file_id);
                    $file_path = $query_result->file_path;
                    return $file_path;
                }
            }

            // If no photo with the requested size is found, return the biggest one.
            $file_id = end($photos[0])->file_id;
            return Bot::$instance->get_file($file_id);
        }
    }

    /**
     * @brief Get latest (default 100) messages from user. PM only.
     * 
     * @param int $amount (Optional) Amount of messages to get. Default is 100.
     * @return int
     */
    public function get_latest_messages(int $amount = 100): array {
        if (!isset($this->user_id)) {
            throw new \Exception("User is not set.");
        }

        $messages = Bot::$db_stores["messages"]->findBy(["chat", "=", $this->user_id], ["date" => "desc"], $amount);
        $messages = array_reverse($messages);
        return $messages;
    }

    /**
     * @brief Get new user by id
     * 
     * @return array
     */
    public static function get_new_user_by_id(int $user_id): array {
        $query_result = Bot::$instance->send_custom_request("getChat", array("chat_id" => $user_id));
        $user = array(
            "id" => $query_result->id,
            "username" => $query_result->username,
            "first_name" => $query_result->first_name,
            "last_name" => $query_result->last_name
        );
        return $user;
    }
}