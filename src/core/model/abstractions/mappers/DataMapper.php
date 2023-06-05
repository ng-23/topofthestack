<?php

declare(strict_types=1);

abstract class DataMapper{
    protected PDO $db_connection;

    public function __construct(PDO $db_connection) {
        $this->db_connection = $db_connection;
    }

}