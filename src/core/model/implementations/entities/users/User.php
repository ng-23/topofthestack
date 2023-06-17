<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../../') . '/abstractions/entities/Entity.php';
require_once realpath(dirname(__FILE__) . '../../../../../') . '/utils/username_generator.php';
require_once realpath(dirname(__FILE__) . '../../../../../') . '/utils/Jwt.php';
require_once realpath(dirname(__FILE__) . '../../../../../') . '/utils/isimage.php';

class User extends Entity
{
    public const DATE_FORMAT = "Y-m-d H:i:s";
    public const MIN_DISPLAY_NAME_LEN = 3;
    public const MAX_DISPLAY_NAME_LEN = 24;
    public const DISPLAY_NAME_REGEX = "#^[a-zA-Z][a-zA-Z0-9._-]{" . self::MIN_DISPLAY_NAME_LEN - 1 . "," . self::MAX_DISPLAY_NAME_LEN - 1 . "}$#";
    public const MAX_BIO_LEN = 125;
    public const COUNTRY_CODE_LEN = 2;
    public const MIN_PFP_URI_LEN = 5;
    public const MAX_PFP_URI_LEN = 255;
    public const BYTES_IN_MB = 1000000;

    private String $display_name;
    private String $password_hash;
    private String $email;
    private bool $activated;
    private ?String $bio;
    private ?String $country_code;
    private String $pfp_uri;
    private String $pfp_img_data;
    private DateTimeImmutable $created_at, $online_at;

    private bool $notify_follow_request, $notify_blog_comment, $notify_blog_from_following, $public_profile;

    public function __construct(String $email, String $password_hash, bool $activated = false, ?String $display_name = NULL, ?int $user_id = NULL)
    {
        $this->id = $user_id;
        $this->setEmail($email);
        $this->setPasswordHash($password_hash);
        $this->setDisplayName($display_name);

        $this->activated = $activated;

        $this->setBio(NULL);
        $this->setCountryCode(NULL);
        $this->setPfpUri(NULL);
        $this->setCreatedAt(NULL);
        $this->setOnlineAt(NULL);

        $this->notify_follow_request = $this->notify_blog_comment = $this->notify_blog_from_following = $this->public_profile = true;
    }

    public function getEmail(): String
    {
        return $this->email;
    }

    public function getDisplayName(): String
    {
        return $this->display_name;
    }

    public function getPasswordHash(): String
    {
        return $this->password_hash;
    }

    public function getActivationStatus(): bool
    {
        return $this->activated;
    }

    public function getBio(): ?String
    {
        return $this->bio;
    }

    public function getCountryCode(): ?String
    {
        return $this->country_code;
    }

    public function getPfpUri(): String
    {
        return $this->pfp_uri;
    }

    public function getPfpImageData(): String
    {
        return $this->pfp_img_data;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getOnlineAt(): DateTimeImmutable
    {
        return $this->online_at;
    }

    public function getSettings(): array
    {
        $settings = [
            "notify_follow_request" => $this->notify_follow_request,
            "notify_blog_comment" => $this->notify_blog_comment,
            "notify_blog_from_following" => $this->notify_blog_from_following,
            "public_profile" => $this->public_profile
        ];

        return $settings;
    }

    public function setId(?int $user_id)
    {
        $this->id = $user_id;
    }

    public function setEmail(String $email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception();
        }

        $this->email = $email;
    }

    public function setDisplayName(?String $display_name)
    {
        if ($display_name) {
            $is_valid = preg_match(self::DISPLAY_NAME_REGEX, $display_name);
            if (!$is_valid) {
                throw new Exception();
            }

            $this->display_name = $display_name;
        } else {
            $this->display_name = generate_username();
        }
    }

    public function setPasswordHash(String $hash)
    {
        $hash_data = password_get_info($hash);

        // unknown hashing algo used; the hash is bad in some way
        if ($hash_data["algo"] == 0) {
            throw new Exception();
        }

        $this->password_hash = $hash;
    }

    public function setActivationStatus(bool $activated)
    {
        $this->activated = $activated;
    }

    public function setBio(?String $bio)
    {
        if ($bio and strlen($bio) > self::MAX_BIO_LEN) {
            throw new LengthException();
        }

        $this->bio = $bio;
    }

    public function setCountryCode(?String $country_code)
    {
        if ($country_code and strlen($country_code) != self::COUNTRY_CODE_LEN) {
            throw new LengthException();
        }

        $this->country_code = $country_code;
    }

    public function setPfpUri(?String $uri)
    {
        if ($uri) {
            $uri_len = strlen($uri);
            if ($uri_len < self::MIN_PFP_URI_LEN or $uri_len > self::MAX_PFP_URI_LEN) {
                throw new Exception();
            }
            $this->pfp_uri = $uri;
        } else {
            // choose 1 of 4 random default pfps
            $rand_int = random_int(1, 4);

            $pfp = "pfp_default0{$rand_int}.png";

            $this->pfp_uri = UserMapper::PFP_DIR . "/{$pfp}";

            $this->setPfpImageData(file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/{$this->pfp_uri}"));
        }
    }

    public function setPfpImageData(String $image_data)
    {


        $size_in_bytes = strlen($image_data);
        if ($size_in_bytes < UserMapper::PFP_MIN_FILE_SIZE_B or $size_in_bytes > UserMapper::PFP_MAX_FILE_SIZE_MB * self::BYTES_IN_MB) {
            throw new Exception();
        }

        if (!is_png($image_data) or !is_jpg($image_data)) {
            throw new Exception();
        }

        $this->pfp_img_data = $image_data;
    }

    public function setCreatedAt(?DateTimeImmutable $date)
    {
        if ($date) {
            $this->created_at = $date;
        } else {
            $this->created_at = new DateTimeImmutable("now");
        }
    }

    public function setOnlineAt(?DateTimeImmutable $date)
    {
        if ($date) {
            $this->online_at = $date;
        } else {
            # this sets online_at to the same date as created_at
            $this->online_at = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $this->created_at->format(self::DATE_FORMAT));
        }
    }

    public function setSetting(String $setting_name, bool $enabled = true)
    {
        switch ($setting_name) {
            case "notify_follow_request":
                $this->notify_follow_request = $enabled;
                break;
            case "notify_blog_comment":
                $this->notify_blog_comment = $enabled;
                break;
            case "notify_blog_from_following":
                $this->notify_blog_from_following;
                break;
            case "public_profile":
                $this->public_profile;
                break;
            default:
                throw new Exception();
        }
    }

    public function toArray(): array
    {
        $reflect = new ReflectionClass($this);
        $properties = $reflect->getProperties();
        $data = [];
        foreach ($properties as $property) {
            $property_name = $property->getName();
            $data[$property_name] = $this->$property_name;
        }
        return $data;
    }
}
