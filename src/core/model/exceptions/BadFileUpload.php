<?php

namespace tots\Exceptions;

class BadFileUploadException extends TotsException {
    protected $code = 300;

    protected $message = "Invalid file uploaded";
}