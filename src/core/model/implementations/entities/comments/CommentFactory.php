<?php

declare(strict_types=1);

class CommentFactory
{
    public const DEFAULT_TEXT = "Hello world!";

    public function makeComment(int $blog_id, int $commentor_id)
    {
        return new Comment($commentor_id, $blog_id, self::DEFAULT_TEXT);
    }
}
