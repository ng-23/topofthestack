<?php

declare(strict_types=1);

namespace tots\Services\Users;

use tots\Mappers\FollowerMapper;

class FollowUserService {
    private FollowerMapper $follower_mapper;

    public function __construct(FollowerMapper $follower_mapper)
    {
        $this->follower_mapper = $follower_mapper;
    }
    
}