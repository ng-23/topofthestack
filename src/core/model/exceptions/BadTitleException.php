<?php

namespace tots\Exceptions;

/**
 * The title is in some way invalid
 * Could be that it just doesn't exist (no blogs, no drafts)
 * Might be too long/too short
 * Might contain illegal character(s)
 */
class BadTitleException extends TotsException {
    protected $code = 3000;

    protected $message = "Invalid title";
}