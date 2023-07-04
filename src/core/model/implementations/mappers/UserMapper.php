<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/users/User.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/users/UserFactory.php';

class UserMapper extends DataMapper
{
    public const PFP_FILE_NAME_REGEX = "#^[a-zA-Z][a-zA-Z0-9_]*$#";
    public const PFP_DIR = "resources/images";
    public const PFP_MAX_FILE_SIZE_MB = 3;

    private UserFactory $user_factory;

    public function __construct(PDO $db_connection, UserFactory $user_factory)
    {
        parent::__construct($db_connection);
        $this->user_factory = $user_factory;
    }

    private function userFromData(array $user_data): User
    {
        $user = $this->user_factory->makeUser($user_data["email"]);

        $user->setId($user_data["user_id"]);
        $user->setPasswordHash($user_data["password_hash"]);
        $user->setActivationStatus((bool)$user_data["activated"]);
        $user->setDisplayName($user_data["display_name"]);
        $user->setBio($user_data["bio"]);
        $user->setCountryCode($user_data["country_code"]);
        $user->setPfpUri($user_data["pfp_uri"]);

        $user->setCreatedAt(new DateTimeImmutable(date(Tag::DATE_FORMAT, $user_data["created_at"])));
        $user->setOnlineAt(new DateTimeImmutable(date(Tag::DATE_FORMAT, $user_data["online_at"])));

        // this can be redundant but helps make the usersFromData method work well
        // also helps hide the fact that just selecting from users table does not include settings...
        $user_data = $this->pushSettings($user_data);

        $settings = $user_data["settings"][0];
        foreach (array_keys($settings) as $setting) {
            $enabled = (bool)$settings[$setting];
            $user->setSetting($setting, $enabled);
        }

        return $user;
    }

    private function usersFromData(array $users_data): array
    {
        $users = [];

        foreach ($users_data as $user_data) {
            $user = $this->userFromData($user_data);
            array_push($users, $user);
        }

        return $users;
    }

    private function pushSettings(array $user_data): array
    {
        $query = "SELECT `notify_follow_req`, `notify_blog_comment`, `notify_blog_from_following`, 
                `public_profile` FROM `user_settings` WHERE `user_id` = ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $user_data["user_id"], PDO::PARAM_INT);
        $stmt->execute();

        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $user_data["settings"] = $settings;

        return $user_data;
    }

    private function validatePfpUri(String $uri): bool
    {
        $is_valid = false;

        // uri should be of pattern base_dir/file_name
        if (str_starts_with($uri, self::PFP_DIR)) {
            $file_name = substr($uri, strlen(self::PFP_DIR));
            // can't exceed maximum uri length
            if (strlen(self::PFP_DIR) + strlen($file_name) <= User::MAX_PFP_URI_LEN) {
                // file_name can't contain certain characters
                $has_valid_chars = preg_match(self::PFP_FILE_NAME_REGEX, $file_name);
                if ($has_valid_chars) {
                    $is_valid = true;
                }
            }
        }

        return $is_valid;
    }

    private function existsByPfpUri(String $pfp_uri)
    {
        $exists = false;

        $query = "SELECT `user_id` FROM `users` WHERE `pfp_uri` = ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $pfp_uri, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $exists = true;
        }

        return $exists;
    }

    private function existsByPfpUriAndId(String $pfp_uri, int $user_id)
    {
        $exists = false;

        $query = "SELECT `user_id` FROM `users` WHERE `pfp_uri` = :pfp_uri AND `user_id` = :user_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":pfp_uri", $pfp_uri, PDO::PARAM_STR);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $exists = true;
        }

        return $exists;
    }

    private function savePfpImage(User $user)
    {
        $pfp_uri = $user->getPfpUri();
        $pfp_img_file = fopen($_SERVER["DOCUMENT_ROOT"] . "/{$pfp_uri}", "w");
        fwrite($pfp_img_file, $user->getPfpImageData());
        fclose($pfp_img_file);
    }

    private function deletePfpImage(User $user)
    {
        $pfp_uri = $user->getPfpUri();
        $pfp_image_filepath = $_SERVER["DOCUMENT_ROOT"] . "/{$pfp_uri}";;
        unlink($pfp_image_filepath);
    }

    public function existsById(int $user_id): bool
    {
        $exists = false;

        if ($this->fetchById($user_id)) {
            $exists = true;
        }

        return $exists;
    }

    public function existsByEmail(String $email): bool
    {
        $exists = false;

        if ($this->fetchByEmail($email)) {
            $exists = true;
        }

        return $exists;
    }

    public function save(User $user)
    {
        // is the pfp_uri valid?
        if (!$this->validatePfpUri($user->getPfpUri())) {
            throw new Exception();
        }

        $is_default_pfp = false;
        foreach (User::DEFAULT_PFPS as $default_pfp) {
            $default_pfp_uri = self::PFP_DIR . "/{$default_pfp}";
            if ($user->getPfpUri() == $default_pfp_uri) {
                $default_pfp_data = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/{$default_pfp_uri}");
                if ($default_pfp_data != $user->getPfpImageData()) {
                    throw new Exception();
                }
                $is_default_pfp = true;
                break;
            }
        }

        if (!$is_default_pfp and $this->existsByPfpUri($user->getPfpUri())) {
            throw new Exception();
        }

        $user_id_param_type = PDO::PARAM_NULL;
        if ($user->getId()) {
            // TODO: custom exceptions; DuplicateKey exception
            $this->existsById($user->getId()) ? throw new Exception() : '';
            $user_id_param_type = PDO::PARAM_INT;
        }

        if ($this->existsByEmail($user->getEmail())) {
            throw new Exception();
        }

        $query = "INSERT INTO `users` (`user_id`, `display_name`, `password_hash`, `email`, `activated`, `bio`, 
                `country_code`, `pfp_uri`, `created_at`, `online_at`) 
                VALUES (:user_id, :disp_name, :psswd_hash, :email, :activated, :bio, :cc, :pfp_uri, :created_at, :online_at)";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user->getId(), $user_id_param_type);
        $stmt->bindParam(":disp_name", $user->getDisplayName(), PDO::PARAM_STR);
        $stmt->bindParam(":passwd_hash", $user->getPasswordHash(), PDO::PARAM_STR);
        $stmt->bindParam(":email", $user->getEmail(), PDO::PARAM_STR);
        $stmt->bindParam(":activated", (int)$user->getActivationStatus(), PDO::PARAM_BOOL);
        $stmt->bindParam(":bio", $user->getBio(), PDO::PARAM_STR);
        $stmt->bindParam(":cc", $user->getCountryCode(), PDO::PARAM_STR);
        $stmt->bindParam(":pfp_uri", $user->getPfpUri(), PDO::PARAM_STR);
        $stmt->bindParam(":created_at", $user->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":online_at", $user->getOnlineAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();

        // do not need to insert anything in the settings table - that is done automatically by a trigger

        $this->savePfpImage($user);
    }


    public function fetchById(int $user_id): ?User
    {
        $user = NULL;

        $query = "SELECT `user_id`, `display_name`, `password_hash`, `email`, `activated`, `bio`, 
                `country_code`, `pfp_uri`, `created_at`, `online_at` FROM `users`
                WHERE `user_id` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $user = $this->userFromData($user_data);
        }

        return $user;
    }

    public function fetchByEmail(String $email_addr): ?User
    {
        $user = NULL;

        $query = "SELECT `user_id`, `display_name`, `password_hash`, `email`, `activated`, `bio`, 
                `country_code`, `pfp_uri`, `created_at`, `online_at` FROM `users`
                WHERE `email` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $email_addr);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $user = $this->userFromData($user_data);
        }

        return $user;
    }

    /**
     * Returns the user(s) with the display name
     */
    public function fetchByDisplayName(String $display_name, int $amount = 1, int $offset = 0): array
    {
        $is_valid = preg_match(User::DISPLAY_NAME_REGEX, $display_name);
        if ($is_valid) {
            return [];
        }

        $users = [];

        $query = "SELECT `user_id`, `display_name`, `password_hash`, `email`, `activated`, `bio`, 
                `country_code`, `pfp_uri`, `created_at`, `online_at` FROM `users` 
                WHERE `display_name` = :disp_name LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":disp_name", $display_name);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $users = $this->usersFromData($users_data);
        }

        return $users;
    }

    /**
     * Returns the user(s) with the country code
     */
    public function fetchByCountryCode(String $country_code, int $amount = 1, int $offset = 0)
    {
        $users = [];

        if (strlen($country_code) == User::COUNTRY_CODE_LEN) {
            $query = "SELECT `user_id`, `display_name`, `password_hash`, `email`, `activated`, `bio`, 
                `country_code`, `pfp_uri`, `created_at`, `online_at` FROM `users` 
                WHERE `country_code` = :cc LIMIT :lim OFFSET :off";
            $stmt = $this->db_connection->prepare($query);
            $stmt->bindParam(":cc", $country_code, PDO::PARAM_STR);
            $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
            $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() >= 1) {
                $users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $users = $this->usersFromData($users_data);
            }
        }

        return $users;
    }

    public function update(User $user, bool $change_email = false)
    {
        if (!$user->getId() or !$this->existsById($user->getId())) {
            throw new Exception();
        }

        if ($change_email) {
            // if we're changing the email address but it's already taken, there's a problem
            $this->existsByEmail($user->getEmail()) ? throw new Exception() : '';
        } else {
            // if we're not changing the email address but no users have it, there's a problem
            !$this->existsByEmail($user->getEmail()) ? throw new Exception() : '';
        }


        $is_default_pfp = false;
        foreach (User::DEFAULT_PFPS as $default_pfp) {
            $default_pfp_uri = self::PFP_DIR . "/{$default_pfp}";
            if ($user->getPfpUri() == $default_pfp_uri) {
                $default_pfp_data = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/{$default_pfp_uri}");
                if ($user->getPfpImageData() != $default_pfp_data) {
                    throw new Exception();
                }
                $is_default_pfp = true;
                break;
            }
        }

        if (!$is_default_pfp) {
            if (!$this->existsByPfpUriAndId($user->getPfpUri(), $user->getId())) {
                if ($this->existsByPfpUri($user->getPfpUri())) {
                    throw new Exception();
                }
                $this->deletePfpImage($this->fetchById($user->getId()));
            }
        }

        $query = "UPDATE `users` SET `display_name`= :disp_name,`password_hash` = :passwd_hash,
                `email` = :email, `activated` = :activated, `bio` = :bio, `country_code` = :cc,
                `pfp_uri` = :pfp_uri, `created_at` = :created_at, `online_at` = :online_at WHERE `user_id` = :user_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user->getId(), PDO::PARAM_INT);
        $stmt->bindParam(":disp_name", $user->getDisplayName(), PDO::PARAM_STR);
        $stmt->bindParam(":passwd_hash", $user->getPasswordHash(), PDO::PARAM_STR);
        $stmt->bindParam(":email", $user->getEmail(), PDO::PARAM_STR);
        $stmt->bindParam(":activated", (int)$user->getActivationStatus(), PDO::PARAM_BOOL);
        $stmt->bindParam(":bio", $user->getBio(), PDO::PARAM_STR);
        $stmt->bindParam(":cc", $user->getCountryCode(), PDO::PARAM_STR);
        $stmt->bindParam(":pfp_uri", $user->getPfpUri(), PDO::PARAM_STR);
        $stmt->bindParam(":created_at", $user->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":online_at", $user->getOnlineAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();

        $query = "UPDATE `user_settings` SET `notify_follow_req` = :notify_follow_request, `notify_blog_comment` = :notify_blog_comment, 
                `notify_blog_from_following` = :notify_blog_from_following, `public_profile` = :public_profile
                WHERE `user_id` = :user_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user->getId(), PDO::PARAM_INT);
        $settings = $user->getSettings();
        foreach (array_keys($settings) as $setting) {
            $enabled = (int)$settings[$setting];
            $stmt->bindParam(":{$setting}", $enabled, PDO::PARAM_INT);
        }
        $stmt->execute();

        $this->savePfpImage($user);
    }

    // note about delete methods: do not need to delete from settings table as a trigger takes care of that...
    public function deleteById(int $user_id)
    {
        // should we check that user exists with that ID?
        // not sure if delete methods should theoretically throw exceptions or not...

        $user = NULL;

        $query = "DELETE FROM `users`
                WHERE `user_id` = ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $user = $this->userFromData($user_data);
        }

        $this->deletePfpImage($user);

        return $user;
    }

    public function deleteByEmail(String $email)
    {
        $user = NULL;

        $query = "DELETE FROM `users`
                WHERE `email` = ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $email, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $user = $this->userFromData($user_data);
        }

        $this->deletePfpImage($user);

        return $user;
    }
}
