<?php

namespace tots\Exceptions;

class BadDisplayNameException extends TotsException {
    protected $code = 7000;

    protected $message = "Invalid display name";
}