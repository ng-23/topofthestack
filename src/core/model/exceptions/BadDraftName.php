<?php

namespace tots\Exceptions;

/**
 * Exception for when a draft name (basically a client-side file name) is invalid in some way
 * Could be that it doesn't exist
 * May be too long/too short
 * Might contain illegal character(s)
 */
class BadDraftNameException extends TotsException {
    protected $code = 4000;

    protected $message = "Invalid draft name";
}