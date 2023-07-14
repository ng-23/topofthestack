<?php

declare(strict_types=1);

namespace tots\Services\Users;

use tots\Mappers\UserMapper;

/**
 * Something to consider
 * Should services depend on each other?
 * Theoretically, you could remove any inter-service dependencies
 * and just call them in a certain order
 * Like for this service, we want authentication before just returning the data
 * We can do that here, or leave it up to controllers to first verify identity with auth service, then call this service
 * I think that may be better
 * 
 * Also, I'm just gonna return the entities directly
 * and skip the whole converting to array business
 * though i'll keep the toArray() method in the entities
 */

class SearchUsersService {
    private UserMapper $user_mapper;

    private array $users;

    public function __construct(UserMapper $user_mapper) {
        $this->user_mapper = $user_mapper;
        $this->users = [];
    }

    public function searchById(int $user_id) {
        $this->users = [$this->user_mapper->fetchById($user_id)];
    }

    public function searchByEmail(String $email) {
        $this->users = [$this->user_mapper->fetchByEmail($email)];
    }

    public function searchByDisplayName(String $display_name, int $amount, int $offset) {
        $this->users = $this->user_mapper->fetchByDisplayName($display_name, $amount, $offset);
    }

    public function searchByCountryCode(String $country_code, int $amount, int $offset) {
        $this->users = $this->user_mapper->fetchByCountryCode($country_code, $amount, $offset);
    }

    public function getUsers(): array {
        return $this->users;
    }
}