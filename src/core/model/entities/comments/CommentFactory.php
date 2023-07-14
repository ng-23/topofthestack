<?php

declare(strict_types = 1);

namespace tots\Entities\Comments;

class CommentFactory
{
    public const DEFAULT_CONTENTS = "Hello world!";

    public function makeComment(int $blog_id, int $commentor_id): Comment
    {
        return new Comment($commentor_id, $blog_id, self::DEFAULT_CONTENTS);
    }
}
