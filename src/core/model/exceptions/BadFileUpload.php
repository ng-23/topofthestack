<?php

namespace tots\Exceptions;

class BadFileUploadException extends TotsException {
    protected $code = 400;

    protected $message = "Invalid file uploaded";
}