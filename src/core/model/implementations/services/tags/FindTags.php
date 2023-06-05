<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/mappers/TagMapper.php';
require_once realpath(dirname(__FILE__) . '../../../') . '/entities/User.php';

class FindTags {

    private TagMapper $mapper;

    public function __construct(TagMapper $mapper) {
        $this->mapper = $mapper;
    }

    public function findById(int $tag_id, bool $return_entity = true): Tag|array|NULL {
        $tag = $this->mapper->fetchById($tag_id);
        if($return_entity) {
            return $tag;
        }

        $tag_data = [];
        if($tag) {
            $tag_data = $tag->toArray();
        }
        return $tag_data;
    }

    public function findRandomTags(int $numb = 1, bool $return_entities = true) {
        $rand_tags = [];

        if($numb > 0) {
            $min_id = $this->mapper->fetchMinId();
            $max_id = $this->mapper->fetchMaxId();

            for($i = 0; $i < $numb; $i++) {
                $rand_id = random_int($min_id, $max_id);
                $rand_tag = $this->mapper->fetchById($rand_id);
                if($return_entities) {
                    array_push($rand_tags, $rand_tag);
                }
                else {
                    array_push($rand_tags, $rand_tag->toArray());
                }
            }
        }

        return $rand_tags;

    }


}