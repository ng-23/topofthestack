<?php

declare(strict_types=1);

function autoload_service(String $service_name) {
    $get_dirs = function(String $parent_dir, array $files) {
        $dirs = [];
        foreach($files as $file) {
            if(is_dir($parent_dir."/".$file)) {
                array_push($dirs, $file);
            }
        }
        return $dirs;
    };

    $PARENT_DIR = realpath(dirname(__FILE__) . '../../') . '/model/implementations/services/';
    $sub_dirs = $get_dirs($PARENT_DIR, array_diff(scandir($PARENT_DIR), [".", ".."]));

    foreach($sub_dirs as $sub_dir) {
        $file = $PARENT_DIR . $sub_dir . "/{$service_name}.php";
        if(is_file($file)) {
            require_once($file);
            return true;
        }
    }

    return false;

}