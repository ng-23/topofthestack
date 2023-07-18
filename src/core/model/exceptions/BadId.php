<?php

namespace tots\Exceptions;

/**
 * The provided ID is in someway invalid
 * Could be that it's a bad value (eg -1935)
 * or that it doesn't exist
 */
class BadIdException extends TotsException
{
    /**
     * see https://stackoverflow.com/questions/5868733/what-do-we-need-the-php-exception-code-for-any-use-case-scenario
     * Maybe subclass this exception
     * and create more specific exceptions like UnknownUserId or DuplicateBlogId
     * instead of using these error codes
     */
    public const DEFAULT_CODE = 1000;
    public const DUPE_ID = 1010;
    public const UNKNOWN_ID = 1020;
    public const ID_OUT_OF_RANGE = 1030;

    protected $code = self::DEFAULT_CODE;

    protected $message = "Bad ID";
}
