<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/mappers/UserMapper.php';
require_once realpath(dirname(__FILE__) . '../../../') . '/entities/User.php';
require_once realpath(dirname(__FILE__) . '../../../../../') . '/utils/Jwt.php';

/**
 * Service for authenticating a user in a variety of ways
 */
class AuthenticateUser {
    private UserMapper $mapper;
    private ?Jwt $jwt;
    
    public function __construct(UserMapper $mapper) {
        $this->mapper = $mapper;
        $this->jwt = NULL;
    }

    private function validateJwtPayload(Jwt $jwt) {
        try {
            $issuer = $jwt->getValFromPayload("iss");
            $user_id = $jwt->getValFromPayload("sub");
            $issued_at = $jwt->getValFromPayload("iat");
            $expires_at = $jwt->getValFromPayload("exp");
        }
        catch (OutOfBoundsException) {
            return false;
        }
        
        if($issuer != "topofthestack") {
            return false;
        }

        if(!is_int($issued_at) or !is_int($expires_at)) {
            return false;
        }

        // can't have negative time
        if($issued_at < 0 or $expires_at < 0) {
            return false;
        }

        // can't expire before it's issued
        if($issued_at > $expires_at) {
            return false;
        }

        $current_time = time();
        if($current_time < $issued_at or $current_time > $expires_at) {
            return false;
        }

        if(!is_int($user_id)) {
            return false;
        }

        // does a user with this id exist?
        if(!$this->mapper->existsById($user_id)) {
            return false;
        }

        return true;
    }

    private function generateAuthToken(int $user_id): Jwt {
        $jwt_secret = file_get_contents(realpath(dirname(__FILE__) . "../../../../../") . "/config/jwt_secret");
        $jwt_issue_time = time();
        $jwt_expiration_time = strtotime("+8 hours");
        $jwt_payload = ["iss" => "genericBlog", "sub" => $user_id, "iat" => $jwt_issue_time, "exp" => $jwt_expiration_time];
        return new Jwt($jwt_secret, $jwt_payload);
    }

    public function authWithCreds(String $email_addr, String $password) {
        $found_user = $this->mapper->fetchByEmail($email_addr);
        if($found_user) {
            $is_correct = password_verify($password, $found_user->getPasswordHash());
            if ($is_correct) {
                // issue a new JWT
                $jwt = $this->generateAuthToken($found_user->getId());
                $this->jwt = $jwt;
            }
        }

    }

    public function authWithToken(String $token) {
        $jwt_secret = file_get_contents(realpath(dirname(__FILE__) . "../../../../../") . "/config/jwt_secret");
        $jwt = Jwt::tokenFromString($token, $jwt_secret);

        if($jwt and $this->validateJwtPayload($jwt)) {
            $this->jwt = $jwt;
        }
    }

    public function getAuthToken(): ?Jwt {
        // if this is NULL, then you know the user wasn't able to log in successfully
        return $this->jwt;
    }

    
}