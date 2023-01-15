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

class Chat {
    /**
     * @ignore
     */
    protected int $chat_id;
    /**
     * @ignore
     */
    protected ChatType $type;
    /**
     * @ignore
     */
    protected string|null $title;
    /**
     * @ignore
     */
    protected string|null $username;
    /**
     * @ignore
     */
    protected User|null $user;
    private \stdClass|null $chat_obj;

    function __construct(string|int $identifier) {
        // Check if database is setup
        if (!isset(Bot::$db_stores["chats"])) {
            throw new \Exception("Database is not setup. Have you initialized the Bot class?");
        }
        if (is_string($identifier)) {
            $chat = Bot::$db_stores["chats"]->findOneBy([["username", "=", $identifier]]);
        } else {
            $chat = Bot::$db_stores["chats"]->findOneBy([["_id", "=", $identifier]]);
        }
        if (!isset($chat)) {
            throw new \Exception("Chat does not exist in database.");
        } else {
            $this->chat_id = $chat["_id"];
            $this->type = ChatType::from_string($chat["type"]);
            $this->title = $chat["title"];
            $this->username = $chat["username"];
            $this->chat_obj = unserialize($chat["chat_obj"]);
            if ($chat["type"] == "private") {
                $this->user = new User;
                $this->user->set_user($chat["_id"]);
            } else {
                $this->user = null;
            }
    /**
     * @brief Get protected properties, keeping them protected from being changed.
     * 
     * @param string $name Variable name.
     * @throws \Exception If variable does not exist or is not allowed to be accessed externally.
     * @return mixed
     */
    public function __get(string $name): mixed {
        switch($name) {
            case "chat_id":
                return $this->chat_id;
            case "type":
                return $this->type;
            case "title":
                return $this->title;
            case "username":
                return $this->username;
            case "user":
                return $this->user;
            default:
                throw new \Exception("Property $name does not exist or not allowed to be accessed externally.");
        }
    }

    /**
     * @brief Pin a message.
     * 
     * @param int $message_id Message id of the message to pin.
     * @param bool $disable_notification (Optional) Disable notification. Default is false.
     * @return bool
     */
    public function pin_message(int $message_id, bool $disable_notification = false): bool {
        $chat_id = $this->chat_id;
        $params = array(
            "chat_id" => $chat_id,
            "message_id" => $message_id,
            "disable_notification" => $disable_notification
        );
        $response = Bot::request("pinChatMessage", $params);
        if ($response->ok) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @brief Get chat profile picture url. Returns empty string if chat does not have a profile picture.
     * 
     * Note: This function returns a url containing sensitive information. Make sure to protect it and not share with end users.
     * 
     * @param ChatPhotoSize $size (Optional) Size of the profile picture. Default is big.
     * @return string
     */
    public function get_profile_picture(ChatPhotoSize $size = ChatPhotoSize::big): string {
        $chat = $this->chat_obj;
        $chat_id = $chat->id;
        $chat_type = $this->type;

        if ($chat_type == "private") {
            $size = $size == ChatPhotoSize::big ? 640 : 160;
            return $this->user->get_profile_picture($size);
        } else {
            $getChat = $this->get_chat();
            if (!isset($getChat->photo)) {
                return "";
            }
            switch ($size) {
                case ChatPhotoSize::small:
                    $file_id = $getChat->photo->small_file_id;
                    break;
                case ChatPhotoSize::big:
                    $file_id = $getChat->photo->big_file_id;
                    break;
            }
            $file = Bot::$instance->get_file($file_id);
            return $file;
        }
    }

    /**
     * @brief Set chat title.
     * 
     * @param string $title New chat title.
     * @return void
     */
    public function set_title(string $title) {
        if ($this->type == ChatType::private) {
            throw new \Exception("Cannot set title for private chat.");
        }
        if (strlen($title) > 128) {
            throw new \Exception("Chat title cannot be longer than 128 characters.");
        }

        $chat_id = $this->chat_id;
        Bot::$instance->send_custom_request("setChatTitle", ["chat_id" => $chat_id, "title" => $title]);
        $this->title = $title;
        $this->chat_obj->title = $title;
        Bot::$db_stores["chats"]->update(["_id" => $chat_id], ["title" => $title, "chat_obj" => serialize($this->chat_obj)]);
    }

    /**
     * @brief Set chat description.
     * 
     * @param string $description New chat description.
     * @return void
     */
    public function set_description(string $description) {
        if ($this->type == ChatType::private) {
            throw new \Exception("Cannot set description for private chat.");
        }
        if (strlen($description) > 255) {
            throw new \Exception("Chat description cannot be longer than 255 characters.");
        }

        $chat_id = $this->chat_id;
        Bot::$instance->send_custom_request("setChatDescription", ["chat_id" => $chat_id, "description" => $description]);
        $this->chat_obj->description = $description;
        Bot::$db_stores["chats"]->update(["_id" => $chat_id], ["chat_obj" => serialize($this->chat_obj)]);
    }

    /**
     * @brief Get chat administrators.
     * 
     * @return array
     */
    public function get_administrators() {
        $chat_id = $this->chat_id;
        $getChatAdministrators = Bot::$instance->send_custom_request("getChatAdministrators", ["chat_id" => $chat_id]);
        $administrators = [];

        foreach ($getChatAdministrators as $admin) {
            $administrators[] = $this->chat_member_to_array($admin);
        }
        return $administrators;
    }

    /**
     * @brief Get chat permissions.
     * 
     * @return \stdClass|null Chat permissions. Docs: https://core.telegram.org/bots/api#chatpermissions
     */
    public function get_permissions(): \stdClass|null {
        $getChat = $this->get_chat();
        if (!isset($getChat->permissions)) {
            return null;
        }
        return $getChat->permissions;
    }

    /**
     * @brief Set chat permissions.
     * 
     * @param \stdClass $permissions Chat permissions. Docs: https://core.telegram.org/bots/api#chatpermissions
     * @return void
     */
    public function set_permissions(\stdClass $permissions): void {
        $chat_id = $this->chat_id;
        Bot::$instance->send_custom_request("setChatPermissions", ["chat_id" => $chat_id, "permissions" => $permissions]);
    }

    /**
     * @brief Get chat members count.
     * 
     * @return int
     */
    public function get_members_count(): int {
        $chat_id = $this->chat_id;
        $getChatMembersCount = Bot::$instance->send_custom_request("getChatMembersCount", ["chat_id" => $chat_id]);
        return $getChatMembersCount;
    }

    /**
     * @brief Get primary invite link.
     * 
     * @return string
     */
    public function get_invite_link(): string {
        $chat_id = $this->chat_id;
        $getChat = $this->get_chat();
        if (!isset($getChat->invite_link)) {
            return "";
        }
        return $getChat->invite_link;
    }

    /**
     * @brief Create a new chat invite link.
     * 
     * @param int $expire_date (Optional) Date when the link will expire. Default is 0 (never expires).
     * @param int $member_limit (Optional) Maximum number of users that can be members of the chat simultaneously after joining the chat via this invite link; 1-99999. Default is 0 (unlimited).
     * @param bool $approval_needed (Optional) If users joining the chat via the link need to be approved by chat administrators. If true $member_limit is ignored. Default is false.
     * @return string
     */
    public function create_invite_link(int $expire_date = 0, int $member_limit = 0, bool $approval_needed = false): string {
        if ($this->type == ChatType::private) {
            throw new \Exception("Cannot create invite link for private chat.");
        }

        $chat_id = $this->chat_id;
        $parameters = ["chat_id" => $chat_id, "expire_date" => $expire_date];
        if ($approval_needed) {
            $parameters["creates_join_request"] = true;
        } else {
            $parameters["member_limit"] = $member_limit;
        }
        $createChatInviteLink = Bot::$instance->send_custom_request("createChatInviteLink", $parameters);
        return $createChatInviteLink->invite_link;
    }

    /**
     * @brief Revoke an invite link.
     * 
     * @param string $invite_link Invite link to revoke.
     * @return bool
     */
    public function revoke_invite_link(string $invite_link): bool {
        $chat_id = $this->chat_id;
        $revokeChatInviteLink = Bot::$instance->send_custom_request("revokeChatInviteLink", ["chat_id" => $chat_id, "invite_link" => $invite_link]);
        return $revokeChatInviteLink->is_revoked ? true : false;
    }

    /**
     * @brief Decline chat join request.
     * 
     * @param int $user_id User id.
     * @return void
     */
    public function decline_join_request(int $user_id): void {
        $chat_id = $this->chat_id;
        $declineChatJoinRequest = Bot::$instance->send_custom_request("declineChatJoinRequest", ["chat_id" => $chat_id, "user_id" => $user_id]);
        return;
    }

    /**
     * @brief Accept chat join request.
     * 
     * @param int $user_id User id.
     * @return void
     */
    public function accept_join_request(int $user_id): void {
        $chat_id = $this->chat_id;
        $acceptChatJoinRequest = Bot::$instance->send_custom_request("acceptChatJoinRequest", ["chat_id" => $chat_id, "user_id" => $user_id]);
        return;
    }



    /**
     * @brief Get chat member.
     * 
     * @param int $user_id User id.
     * @return array
     */
    public function get_chat_member(): array {
        $chat_id = $this->chat_id;
        $getChatMember = Bot::$instance->send_custom_request("getChatMember", ["chat_id" => $chat_id, "user_id" => $user_id]);
        return $this->chat_member_to_array($getChatMember);
    }

    /**
     * @brief Get latest messages from chat.
     * 
     * @param int $limit (Optional) Limit of messages to get. Default is 100.
     * @return array
     */
    public function get_latest_messages(int $limit = 100): array {
        $chat_id = $this->chat_id;

        $messages = Bot::$db_stores["messages"]->findBy(["chat_id" => $chat_id], ["date" => "desc"], ["limit" => $limit]);
        $messages = array_reverse($messages);
        return $messages;
    }



    /** 
     * @brief Save chat to database on message.
     * 
     * Note: Shall not be called manually. Is called by Bot class when needed.
     * 
     * @param \stdClass $message Message object.
     * @return void
     */
    public static function save_chat_from_message(\stdClass $message): void {
        $chat = $message->chat;
        $chat_id = $chat->id;
        $chat_type = $chat->type;
        $chat_title = $chat->title ?? null;
        $chat_username = $chat->username ?? null;

        $chat_data = array(
            "_id" => $chat_id,
            "type" => $chat_type,
            "title" => $chat_title,
            "username" => $chat_username,
            "chat_obj" => serialize($chat)
        );

        Bot::$db_stores["chats"]->updateOrInsert($chat_data, false);
    }

    /** 
     * @brief Get chat object from Telegram API.
     * 
     * @return \stdClass|null
     */
    private function get_chat(): \stdClass|null {
        $bot_instance = Bot::$instance;
        $chat = $bot_instance->send_custom_request("getChat", array(
            "chat_id" => $this->chat_id
        ));
        return $chat;
    }

    /**
     * @brief Convert Chat member object to array.
     * 
     * @param \stdClass $member_obj Member object.
     * @return array
     */
    private function chat_member_to_array($member_obj): array {
        if (!$member_obj->user->is_bot) {
            $user = new User;
            $user->set_user($member_obj->user->id);
        }
        $member_type = ChatMemberType::from_string($member_obj->status);

        // Start building array
        $member = [
            "user" => $user ?? null,
            "member_type" => $member_type,
            "is_bot" => $member_obj->user->is_bot,
        ];

        switch ($member_type) {
            case ChatMemberType::ChatMemberOwner:
                $member["is_anynomous"] = $member_obj->is_anynomous ?? null;
                $member["custom_title"] = $member_obj->custom_title ?? null;
                break;
            case ChatMemberType::ChatMemberAdministrator:
                $member["is_anynomous"] = $member_obj->is_anynomous ?? null;
                $member["custom_title"] = $member_obj->custom_title ?? null;
                $member["can_be_edited"] = $member_obj->can_be_edited;
                $member["permissions"] = [
                    "can_manage_chat" => $member_obj->can_manage_chat,
                    "can_delete_messages" => $member_obj->can_delete_messages,
                    "can_manage_video_chats" => $member_obj->can_manage_video_chats,
                    "can_restrict_members" => $member_obj->can_restrict_members,
                    "can_promote_members" => $member_obj->can_promote_members,
                    "can_change_info" => $member_obj->can_change_info,
                    "can_invite_users" => $member_obj->can_invite_users,
                    "can_post_messages" => $member_obj->can_post_messages ?? null,
                    "can_edit_messages" => $member_obj->can_edit_messages ?? null,
                    "can_pin_messages" => $member_obj->can_pin_messages ?? null,
                    "can_manage_topics" => $member_obj->can_manage_topics ?? null
                ];
                break;
            case ChatMemberType::ChatMemberMember:
                // Already finished
                break;
            case ChatMemberType::ChatMemberRestricted:
                $member["is_member"] = $member_obj->is_member;
                $member["permissions"] = [
                    "can_change_info" => $member_obj->can_change_info,
                    "can_invite_users" => $member_obj->can_invite_users,
                    "can_pin_messages" => $member_obj->can_pin_messages,
                    "can_manage_topics" => $member_obj->can_manage_topics,
                    "can_send_messages" => $member_obj->can_send_messages,
                    "can_send_media_messages" => $member_obj->can_send_media_messages,
                    "can_send_polls" => $member_obj->can_send_polls,
                    "can_send_other_messages" => $member_obj->can_send_other_messages,
                    "can_add_web_page_previews" => $member_obj->can_add_web_page_previews,
                ];
                $member["until_date"] = $member_obj->until_date;
                break;
            case ChatMemberType::ChatMemberLeft:
                // Already finished
                break;
            case ChatMemberType::ChatMemberBanned:
                $member["until_date"] = $member_obj->until_date;
                break;
            default:
                echo "Unknown chat member type. Status defined is: " . $member_obj->status . PHP_EOL;
                break;
        }
        return $member;
    }
}

// Enumerations
enum ChatType {
    case private;
    case group;
    case supergroup;
    case channel;

    public static function from_string(string $string): ChatType {
        return match ($string) {
            "private" => ChatType::private,
            "group" => ChatType::group,
            "supergroup" => ChatType::supergroup,
            "channel" => ChatType::channel
        };
    }
}

enum ChatMemberType {
    case ChatMemberOwner;
    case ChatMemberAdministrator;
    case ChatMemberMember;
    case ChatMemberRestricted;
    case ChatMemberLeft;
    case ChatMemberBanned;

    public static function from_string(string $string): ChatMemberType {
        return match ($string) {
            "creator" => ChatMemberType::ChatMemberOwner,
            "administrator" => ChatMemberType::ChatMemberAdministrator,
            "member" => ChatMemberType::ChatMemberMember,
            "restricted" => ChatMemberType::ChatMemberRestricted,
            "left" => ChatMemberType::ChatMemberLeft,
            "kicked" => ChatMemberType::ChatMemberBanned
        };
    }
}

enum ChatPhotoSize {
    case small; // 160x160
    case big; // 640x640
}