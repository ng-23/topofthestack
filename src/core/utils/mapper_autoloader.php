<?php

declare(strict_types=1);

function autoload_mapper(String $mapper_name) {
    $PARENT_DIR = realpath(dirname(__FILE__) . '../../') . '/model/implementations/mappers/';
    $all_files = array_diff(scandir($PARENT_DIR), [".", ".."]);
    foreach($all_files as $file) {
        if($file == ($mapper_name.".php")) {
            require_once($PARENT_DIR . $file);
            return true;
        }
    }

    return false;

}