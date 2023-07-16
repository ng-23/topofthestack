<?php

declare(strict_types=1);

namespace tots\Services\Blogs;

/**
 * My only issue with such a service is that it seems to broad on its own
 * If I wanted to change the title of a blog, I could use this service and just pass in the old blog data but with the title
 * But I think it would maybe be better to have something like ChangeBlogTitleService instead, as that's more specific
 * Both accomplish the same thing, but I think the latter is a more intuitive and clearer approach
 * This approach could/would lead to a lot of simple classes that really only have 1 method to do 1 thing, 
 * which I think is a little wasteful
 * Instead, maybe keep the UpdateEntityService, but instead of just having a general update() method, have methods like
 * updateTitle() or updateTagName(), that way you only need to instantiate 1 class while having access to methods with
 * clearly-defined methods that only update what they need to
 */

class UpdateBlogService {

    public function changeTitle(int $blog_id, String $new_title) {

    }

    public function changeTags(int $blog_id, array $new_tags) {

    }

    public function changeBodyContents(int $blog_id, array $new_body_contents) {
        
    }
    
}