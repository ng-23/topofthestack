<?php

declare(strict_types=1);

namespace tots\Entities\Users;

use tots\Entities\Entity;
use DateTimeImmutable;
use ReflectionClass;
use tots\Entities\Blogs\PublishedBlog;
use Exception;
use LengthException;
use tots\Exceptions\BadDisplayNameException;
use tots\Exceptions\BadFileUploadException;
use tots\Exceptions\BadSettingNameException;
use tots\Mappers\UserMapper;
use function tots\Utils\generate_display_name;
use function tots\Utils\is_png;
use function tots\Utils\is_jpg;

class User extends Entity
{
    public const DATE_FORMAT = "Y-m-d H:i:s";
    public const MIN_DISPLAY_NAME_LEN = 3;
    public const MAX_DISPLAY_NAME_LEN = 24;
    public const DISPLAY_NAME_REGEX = "#^[a-zA-Z][a-zA-Z0-9._-]{" . self::MIN_DISPLAY_NAME_LEN - 1 . "," . self::MAX_DISPLAY_NAME_LEN - 1 . "}$#";
    public const MAX_BIO_LEN = 125;
    public const COUNTRY_CODE_LEN = 2;
    public const DEFAULT_PFPS = ["pfp_default01.png", "pfp_default02.png", "pfp_default03.png", "pfp_default04.png"];

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
                // BadDisplayNameException
                throw new BadDisplayNameException();
            }

            $this->display_name = $display_name;
        } else {
            $this->display_name = generate_display_name();
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
            if ($uri_len < PublishedBlog::MIN_URI_LEN or $uri_len > PublishedBlog::MAX_URI_LEN) {
                throw new LengthException();
            }
            $this->pfp_uri = $uri;
        } else {
            // choose 1 of 4 random default pfps
            $pfp = array_rand(self::DEFAULT_PFPS, 1);

            $this->pfp_uri = UserMapper::PFP_DIR . "/{$pfp}";

            $this->setPfpImageData(file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/{$this->pfp_uri}"));
        }
    }

    public function setPfpImageData(String $image_data)
    {
        // image_data is basically just the raw bytes of the image file
        // this method is where you check mime type, size, etc.

        /**
         * should this be concerned with image size (eg 96x96)
         * i feel like that is presentation layer logic
         * but i do think backend should be concerned with file size
         * also should probably consider (and this goes for every class) moving certain constants into config files
         * like PFP_MAX_FILE_SIZE_MB should probably be in a config file
         * MAX_URI_LEN could probably either stay a class constant or be part of a "global" config file
         * see https://ux.stackexchange.com/questions/95196/how-can-we-go-about-deciding-an-appropriate-filesize-upload-limit
         * see https://stackoverflow.com/questions/3511106/filesize-from-a-string
         * see https://stackoverflow.com/questions/4286677/show-image-using-file-get-contents?noredirect=1&lq=1
         * see https://stackoverflow.com/questions/6061505/detecting-image-type-from-base64-string-in-php
         * see https://www.php.net/manual/en/function.exif-imagetype.php
         * see https://stackoverflow.com/questions/9314164/php-uploading-files-image-only-checking
         * see https://stackoverflow.com/questions/15117303/saving-image-straight-to-directory-in-php
         */

        // TODO: consider reimplementing pfp file size check...

        if (!is_png($image_data) or !is_jpg($image_data)) {
            throw new BadFileUploadException();
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
                // UnsupportedSettingException
                throw new BadSettingNameException();
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
