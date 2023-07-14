<?php

declare(strict_types=1);

namespace tots\Entities\Drafts;

use HTMLPurifier;

class BlogDraftFactory
{
    private HTMLPurifier $html_purifier;

    public function __construct(HTMLPurifier $html_purifier)
    {
        $this->html_purifier = $html_purifier;
    }

    public function makeBlogDraft(int $drafter_id)
    {
        $int1 = rand(0, 100000);
        $int2 = rand(0, 100000);
        $int3 = rand(0, 100000);

        // TODO: figure out better, clearer names for this stuff...
        $file_name = sha1($drafter_id . time() . $int1 . $int2 . $int3 . ".html");
        $draft_name = substr($file_name, 0, BlogDraft::MAX_NAME_LEN);

        return new BlogDraft($this->html_purifier, $drafter_id, $file_name, $draft_name);
    }
}
