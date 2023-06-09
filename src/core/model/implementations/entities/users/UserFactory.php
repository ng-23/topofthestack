<?php

declare(strict_types=1);

// consider moving this into utils or even services (?)
require_once("User.php");

class UserFactory {
    public const DEFAULT_PASSWORD = "password12345";

    public function makeUser(String $email): User {
        $hash = password_hash(self::DEFAULT_PASSWORD, PASSWORD_BCRYPT);
        return new User($email, $hash);
    }


}