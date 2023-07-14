<?php

namespace tots\Exceptions;

class DuplicateKeyException extends TotsException {
    protected $code = 100;

    protected $message = "Key exists already";
}