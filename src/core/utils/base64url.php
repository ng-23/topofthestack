<?php

// taken from the comments at https://www.php.net/manual/en/function.base64-encode.php

declare(strict_types=1);

function base64url_encode(String $data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(String $data) {
    return base64_decode(str_replace(['-','_'], ['+','/'], $data));
}