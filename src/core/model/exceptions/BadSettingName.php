<?php

namespace tots\Exceptions;

class BadSettingNameException extends TotsException {
    protected $code = 6000;

    protected $message = "Invalid setting";
}