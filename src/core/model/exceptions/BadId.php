<?php

namespace tots\Exceptions;

/**
 * The provided ID is in someway invalid
 * Could be that it's a bad value (eg -1935)
 * or that it doesn't exist
 */
class BadIdException extends TotsException {
    protected $code = 1000;

    protected $message = "ID invalid";
}