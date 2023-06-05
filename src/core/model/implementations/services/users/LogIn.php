<?php

declare(strict_types=1);

require_once __DIR__ . "/AuthenticateUser.php";
require_once realpath(dirname(__FILE__) . '../../../') . '/mappers/UserMapper.php';
require_once realpath(dirname(__FILE__) . '../../../') . '/entities/User.php';

class LogIn {
    private AuthenticateUser $auth_service;
    private UserMapper $mapper;

    private ?User $user;
    private bool $logged_in;

    public function __construct(AuthenticateUser $auth_service, UserMapper $mapper) {
        $this->auth_service = $auth_service;
        $this->mapper = $mapper;
        $this->logged_in = false;
        $this->user = NULL;
    }

    public function logInWithToken(String $token) {
        $this->auth_service->authWithToken($token);
        $jwt = $this->auth_service->getAuthToken();
        if($jwt) {
            $this->logged_in = true;
            $this->user = $this->mapper->fetchById($jwt->getValFromPayload("sub"));
        }
        else {
            $this->logged_in = false;
        }
    }

    public function isLoggedIn() {
        return $this->logged_in;
    }

    public function getUser(bool $return_as_entity = true): User|array|NULL {
        // you could use this to determine whether or not a user logged in, but having the isLoggedIn()
        // method is more intuitive IMO
        // still might get rid of though
        if($return_as_entity) {
            return $this->user;
        }

        $user_data = [];
        if($this->user) {
            $user_data = $this->user->toArray();
        }
        return $user_data;
    }
}