<?php

namespace tots\Exceptions;

class BadNotificationTypeException extends TotsException {
    protected $code = 5000;

    protected $message = "Invalid notification type";
}