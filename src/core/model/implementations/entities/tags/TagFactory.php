<?php

declare(strict_types=1);

require_once("Tag.php");

/**
 * Is this really even necessary/worth it?
 * Why have a whole class just to do something this simple?
 * Consider deprecating...
 */
class TagFactory
{
    public function makeTag(String $tag_name)
    {
        return new Tag($tag_name);
    }
}
