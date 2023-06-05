<?php

/**
 * Create name spaces and what not for PSR4 (https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader-examples.md) compliancy and for autoloading
 * Create an Autoloader class
 * Use Autoloader class in ServiceFactory
 * Set up bootstrapper.php
 * Create a temporary HTML twig file for use in HomeView
 * Create HomeController class
 * Create a file to define endpoints
 * Create a function/class for programmatically adding endpoints to RequestRouter
 * 
 */

declare(strict_types=1);

require_once __DIR__ . "/service_autoloader.php";
require_once __DIR__ . "/mapper_autoloader.php";

spl_autoload_register("autoload_service");
spl_autoload_register("autoload_mapper");

class ServiceFactory {
    public function makeService(String $service_name, PDO $db_connection) {
        switch($service_name) {
            case "LogIn":
                $mapper = new UserMapper($db_connection);
                $auth_service = new AuthenticateUser($mapper);
                $user_finder_service = new FindUsers($mapper);
                return new LogIn($auth_service, $user_finder_service);
            case "AuthenticateUser":
                $mapper = new UserMapper($db_connection);
                return new AuthenticateUser($mapper);
            case "FindUsers":
                $mapper = new UserMapper($db_connection);
                return new FindUsers($mapper);
            case "FindTags":
                $mapper = new TagMapper($db_connection);
                return new FindTags($mapper);
            default:
                throw new Exception();
        }
    }

}

/* $dsn = "mysql" . ":host=" . "localhost" . ";dbname=genericBlog;charset=utf8";
$pdo = new PDO($dsn, "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$s = new ServiceFactory;
$r = $s->makeService("AuthenticateUser", $pdo);
var_dump($r); */