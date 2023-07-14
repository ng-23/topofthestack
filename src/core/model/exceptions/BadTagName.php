<?php

namespace tots\Exceptions;

/**
 * The provided tag name is in some way invalid
 * Could be that it doesn't exist
 * Might be too long/too short
 * Could contain illegal character(s)
 */
class BadTagNameException extends TotsException {
    protected $code = 2000;

    protected $message = "Invalid tag name";
}