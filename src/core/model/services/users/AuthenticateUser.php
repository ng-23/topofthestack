<?php

declare(strict_types=1);

namespace tots\Services\Users;

use tots\Mappers\UserMapper;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;

/**
 * Service for authenticating a user in a variety of ways
 */
class AuthenticateUserService
{
    public const JWT_ALGO = "HS256";
    public const JWT_PAYLOAD_KEYS = ["iss", "sub", "iat", "exp"];

    private UserMapper $user_mapper;
    private ?String $jwt;

    public function __construct(UserMapper $user_mapper)
    {
        $this->user_mapper = $user_mapper;
        $this->jwt = NULL;
    }

    private function verifyJwt(String $jwt)
    {
        try {
            $secret = file_get_contents(realpath(dirname(__FILE__) . "../../../../../") . "/config/jwt_secret");
            // TODO: consider getting header data too
            // not sure what good it would do though....
            $payload = (array)JWT::decode($jwt, new Key($secret, self::JWT_ALGO));
        }
        catch (SignatureInvalidException) {
            return false;
        }

        foreach(self::JWT_PAYLOAD_KEYS as $key) {
            if (!array_key_exists($key, $payload)) {
                return false;
            }
        }

        // at this point payload has all necessary keys
        // have to check values though

        if ($payload["iss"] != "topofthestack") {
            return false;
        }

        if (is_int($payload["iat"]) and is_int($payload["exp"])) {
            // can't have negative time
            if ($payload["iat"] < 0 or $payload["exp"] < 0) {
                return false;
            } 

            // can't expire before it's issued
            if ($payload["iat"] > $payload["exp"]) {
                return false;
            }

            // can't have issued JWT in the future or if JWT expired already
            $current_time = time();
            if ($current_time < $payload["iat"] or $current_time > $payload["exp"]) {
                return false;
            }
        }

        else {
            return false;
        }

        if (is_int($payload["sub"])) {
            // does a user with this id exist?
            if (!$this->user_mapper->existsById($payload["sub"])) {
                return false;
            }
        }
        else {
            return false;
        }

        return true;
    }

    private function generateJwt(int $user_id): String
    {
        $secret = file_get_contents(realpath(dirname(__FILE__) . "../../../../../") . "/config/jwt_secret");
        $issue_time = time();
        $expiration_time = strtotime("+8 hours");
        $payload = self::JWT_PAYLOAD_KEYS;
        $payload = ["iss" => "topofthestack", "sub" => $user_id, "iat" => $issue_time, "exp" => $expiration_time];
        return JWT::encode($payload, $secret, self::JWT_ALGO);
    }

    public function authWithCreds(String $email, String $password)
    {
        if ($this->user_mapper->existsByEmail($email)) {
            $user = $this->user_mapper->fetchByEmail($email);
            if (password_verify($password, $user->getPasswordHash())) {
                $jwt = $this->generateJwt($user->getId());
                $this->jwt = $jwt;
            }
        }
    }

    public function authWithJwt(String $jwt)
    {
        if ($this->verifyJwt($jwt)) {
            $this->jwt = $jwt;
        }
    }

    public function getJwt(): ?String
    {
        // if this is NULL, then you know the user wasn't able to log in successfully
        return $this->jwt;
    }
}
