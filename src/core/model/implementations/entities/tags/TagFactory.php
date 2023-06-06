<?php

declare(strict_types = 1);

require_once("Tag.php");

/**
 * Is this really even necessary/worth it?
 * Why have a whole class just to do something this simple?
 * Consider deprecating...
 */
class TagFactory {
    public const DEFAULT_TAG_NAME = "tag_";

    public function makeTag() {
        return new Tag(self::DEFAULT_TAG_NAME);
    }

}