<?php

declare(strict_types=1);

require_once __DIR__ . "/base64url.php";

class Jwt
{
    const TOKEN_TYPE = "jwt";
    const HASH_ALGO = "HS256";

    private array $header;

    private array $payload;

    private String $secret;

    public function __construct($secret, array $payload = [], array $header = ["alg" => self::HASH_ALGO, "typ" => self::TOKEN_TYPE])
    {
        $header_keys = array_keys($header);
        if (in_array("alg", $header_keys) and $header["alg"] != self::HASH_ALGO) {
            throw new Exception();
        }

        $this->header = $header;
        $this->payload = $payload;
        $this->secret = $secret;
    }

    private function encodeHeader()
    {
        return base64url_encode(json_encode($this->header));
    }

    private function encodePayload()
    {
        return base64url_encode(json_encode($this->payload));
    }

    private function generate_signature()
    {
        $encoded_header = $this->encodeHeader();
        $encoded_payload = $this->encodePayload();
        $signature = hash_hmac("sha256", "$encoded_header.$encoded_payload", $this->secret, true);
        return base64url_encode($signature);
    }

    public function getValFromPayload(String $key)
    {
        if (!in_array($key, array_keys($this->payload))) {
            throw new OutOfBoundsException("No such parameter {$key}");
        }
        return $this->payload[$key];
    }

    public function getToken()
    {
        $token = $this->encodeHeader() . "." . $this->encodePayload() . "." . $this->generate_signature();
        return $token;
    }

    public static function validateHeader(String $encoded_header)
    {
        $decoded_header = json_decode(base64url_decode($encoded_header), true);
        if (is_array($decoded_header)) {
            $header_keys = array_keys($decoded_header);
            if (in_array("alg", $header_keys) and $decoded_header["alg"] == self::HASH_ALGO) {
                return true;
            }
        }
        return false;
    }

    public static function validatePayload(String $encoded_payload)
    {
        $decoded_payload = json_decode(base64url_decode($encoded_payload), true);
        if (is_array($decoded_payload)) {
            return true;
        }
        return false;
    }

    public static function tokenFromString(String $str, String $secret)
    {
        $token = NULL;

        $parts = explode(".", $str);
        if (count($parts) == 3) {
            $header = $parts[0];
            $payload = $parts[1];
            if (Jwt::validateHeader($header) and Jwt::validatePayload($payload)) {
                $given_signature = base64url_decode($parts[2]);
                $expected_signature = hash_hmac("sha256", "$header.$payload", $secret, true);

                if (hash_equals($expected_signature, $given_signature)) {
                    $token = new Jwt($secret, json_decode(base64url_decode($payload), true), json_decode(base64url_decode($header), true));
                }
            }
        }
        return $token;
    }
}
