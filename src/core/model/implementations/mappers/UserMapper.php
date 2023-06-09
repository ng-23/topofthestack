<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/users/User.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/users/UserFactory.php';

class UserMapper extends DataMapper
{
    private UserFactory $user_factory;

    public function __construct(PDO $db_connection, UserFactory $user_factory)
    {
        parent::__construct($db_connection);
        $this->user_factory = $user_factory;
    }

    private function userFromData(array $user_data): User
    {
        $user = $this->user_factory->makeUser($user_data["email"]);

        $activated = false;
        if ($user_data["activated"] == 1) {
            $activated = true;
        }

        $user->setId($user_data["user_id"]);
        $user->setPasswordHash($user_data["password_hash"]);
        $user->setActivationStatus($activated);
        $user->setDisplayName($user_data["display_name"]);
        $user->setBio($user_data["bio"]);
        $user->setCountryCode($user_data["country_code"]);
        $user->setPfpUri($user_data["pfp_uri"]);

        $user->setCreatedAt(DateTimeImmutable::createFromFormat(User::DATE_FORMAT, $user_data["created_at"]));
        $user->setOnlineAt(DateTimeImmutable::createFromFormat(User::DATE_FORMAT, $user_data["online_at"]));

        $settings = $user_data["settings"][0];
        foreach (array_keys($settings) as $setting) {
            $enabled = true;
            if ($settings[$setting] == 0) {
                $enabled = false;
            }
            $user->setSetting($setting, $enabled);
        }

        return $user;
    }

    private function pushSettings(array $user_data)
    {
        $query = "SELECT `notify_follow_req`, `notify_blog_comment`, `notify_blog_from_following`, 
                `public_profile` FROM `user_settings` WHERE `user_id` = ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $user_data["user_id"], PDO::PARAM_INT);
        $stmt->execute();

        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $user_data["settings"] - $settings;

        return $user_data;
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
        if ($this->existsById($user->getId())) {
            // TODO: custom exceptions; DuplicateKey exception
            throw new Exception();
        }

        if ($this->existsByEmail($user->getEmail())) {
            throw new Exception();
        }

        $query = "INSERT INTO `users` (`user_id`, `display_name`, `password_hash`, `email`, `activated`, `bio`, 
                `country_code`, `pfp_uri`, `created_at`, `online_at`) 
                VALUES (:user_id, :disp_name, :psswd_hash, :email, :activated, :bio, :cc, :pfp_uri, :created_at, :online_at)";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user->getId(), PDO::PARAM_INT);
        $stmt->bindParam(":disp_name", $user->getDisplayName(), PDO::PARAM_STR);
        $stmt->bindParam(":passwd_hash", $user->getPasswordHash(), PDO::PARAM_STR);
        $stmt->bindParam(":email", $user->getEmail(), PDO::PARAM_STR);
        $stmt->bindParam(":activated", $user->getActivationStatus(), PDO::PARAM_BOOL);
        $stmt->bindParam(":bio", $user->getBio(), PDO::PARAM_STR);
        $stmt->bindParam(":cc", $user->getCountryCode(), PDO::PARAM_STR);
        $stmt->bindParam(":pfp_uri", $user->getPfpUri(), PDO::PARAM_STR);
        $stmt->bindParam(":created_at", $user->getCreatedAt()->format(User::DATE_FORMAT), PDO::PARAM_STR);
        $stmt->bindParam(":online_at", $user->getOnlineAt()->format(User::DATE_FORMAT), PDO::PARAM_STR);
        $stmt->execute();
    }

    public function fetchById(int $user_id): ?User
    {
        $user = NULL;

        $query = "SELECT `user_id`, `display_name`, `password_hash`, `email`, `activated`, `bio`, 
                `country_code`, `pfp_uri`, `created_at`, `online_at` FROM `users`
                WHERE `user_id` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $user_data = $this->pushSettings($user_data);

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

            $user_data = $this->pushSettings($user_data);

            $user = $this->userFromData($user_data);
        }

        return $user;
    }

    /**
     * Returns the user(s) with the display name
     */
    public function fetchByDisplayName(String $display_name, int $amount = 1, int $offset = 0): array
    {
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

            foreach ($users_data as $user_data) {
                $user_data = $this->pushSettings($user_data);
                $user = $this->userFromData($user_data);
                array_push($users, $user);
            }
        }

        return $users;
    }

    /**
     * Returns the user(s) with the country code
     */
    public function fetchByCountryCode(String $country_code, int $amount = 1, int $offset = 0)
    {
        $users = [];

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

            foreach ($users_data as $user_data) {
                $user_data = $this->pushSettings($user_data);
                $user = $this->userFromData($user_data);
                array_push($users, $user);
            }
        }

        return $users;
    }

    public function update(User $user, bool $change_email = false)
    {
        if (!$this->existsById($user->getId())) {
            throw new Exception();
        }

        if ($change_email) {
            // if we're changing the email address but it's already taken, there's a problem
            $this->existsByEmail($user->getEmail()) ? throw new Exception() : '';
        } else {
            // if we're not changing the email address but no users have it, there's a problem
            !$this->existsByEmail($user->getEmail()) ? throw new Exception() : '';
        }

        // this won't work if a user get's an ID change but that shouldn't be happening anyway
        // don't want people to be able to update/change their numerical IDs so....
        $query = "UPDATE `users` SET `display_name`= :disp_name,`password_hash` = :passwd_hash,
                `email` = :email, `activated` = :activated, `bio` = :bio, `country_code` = :cc,
                `pfp_uri` = :pfp_uri, `created_at` = :created_at, `online_at` = :online_at WHERE `user_id` = :user_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user->getId(), PDO::PARAM_INT);
        $stmt->bindParam(":disp_name", $user->getDisplayName(), PDO::PARAM_STR);
        $stmt->bindParam(":passwd_hash", $user->getPasswordHash(), PDO::PARAM_STR);
        $stmt->bindParam(":email", $user->getEmail(), PDO::PARAM_STR);
        $stmt->bindParam(":activated", $user->getActivationStatus(), PDO::PARAM_BOOL);
        $stmt->bindParam(":bio", $user->getBio(), PDO::PARAM_STR);
        $stmt->bindParam(":cc", $user->getCountryCode(), PDO::PARAM_STR);
        $stmt->bindParam(":pfp_uri", $user->getPfpUri(), PDO::PARAM_STR);
        $stmt->bindParam(":created_at", $user->getCreatedAt()->format(User::DATE_FORMAT), PDO::PARAM_STR);
        $stmt->bindParam(":online_at", $user->getOnlineAt()->format(User::DATE_FORMAT), PDO::PARAM_STR);
        $stmt->execute();
    }

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

            $user_data = $this->pushSettings($user_data);

            $user = $this->userFromData($user_data);
        }

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

        if ($stmt->rowCount() >= 1) {
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $user_data = $this->pushSettings($user_data);

            $user = $this->userFromData($user_data);
        }

        return $user;
    }
}
