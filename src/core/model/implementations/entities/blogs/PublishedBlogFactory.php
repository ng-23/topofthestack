<?php

declare(strict_types=1);

require_once("PublishedBlog.php");
require_once realpath(dirname(__FILE__) . '../../../../../../../') . '/vendor/autoload.php';

class PublishedBlogFactory
{
    private HTMLPurifier $html_purifier;

    public function __construct(HTMLPurifier $html_purifier)
    {
        $this->html_purifier = $html_purifier;
    }

    public function makeBlog(int $author_id, String $title)
    {
        $int1 = rand(0, 100000);
        $int2 = rand(0, 100000);
        $int3 = rand(0, 100000);

        $file_name = sha1($author_id . time() . $int1 . $int2 . $int3 . ".html");
        $uri = "/resources/blogs/" . $file_name;
        return new PublishedBlog($this->html_purifier, $author_id, $uri, $title);
    }
}
