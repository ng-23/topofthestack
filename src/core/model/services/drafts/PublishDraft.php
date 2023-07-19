<?php

declare(strict_types=1);

namespace tots\Services\Drafts;

/**
 * Service (action) for publishing a blog draft
 * If the draft is associated with a blog already, then
 * the changes from the draft will be merged with the blog implicitly
 * If the draft isn't associated with an existing blog, then publishing will
 * simply create a new blog
 */
class PublishDraftService {

}