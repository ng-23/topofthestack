<?php

declare(strict_types=1);

// taken from comment https://www.php.net/manual/en/function.exif-imagetype.php#113253

function is_jpeg(String $image_data): bool
{
    return (bin2hex($image_data[0]) == 'ff' and bin2hex($image_data[1]) == 'd8');
}

function is_jpg(String $image_data): bool
{
    return is_jpeg($image_data);
}

function is_png(String $image_data): bool
{
    return (bin2hex($image_data[0]) == '89' and $image_data[1] == 'P' and $image_data[2] == 'N' and $image_data[3] == 'G');
}
