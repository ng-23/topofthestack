<?php

namespace tots\Exceptions;

/**
 * Exception for when a URI is in some way invalid
 * Could be too short/too long
 * May contain illegal characters
 */
class BadUriException extends TotsException {
    public const DEFAULT_CODE = 200;
    public const BAD_LENGTH = 210;
    public const BAD_CHARS = 220;
    public const NONEXISTENT = 230;
    public const DUPE_URI = 240;

    protected $code = self::DEFAULT_CODE;

    protected $message = "Invalid URI";
}