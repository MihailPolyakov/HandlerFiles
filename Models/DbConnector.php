<?php


namespace Models;
require_once __DIR__ . "/../env.php";

class DbConnector
{
    private static $connections = [];

    public static function getConnection($id = 'default') {
        if (!empty(self::$connections[$id])) return self::$connections[$id];
        $pdo = new \PDO("mysql:dbname=" . DB_NAME . ";host=" . DB_HOST, DB_USER, DB_PASSWORD);
        $sql = 'CREATE TABLE IF NOT EXISTS user_statuses
        (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            chat_id varchar (255) NOT NULL,
            status varchar (255) NOT NULL
        ) DEFAULT CHARACTER SET utf8 ENGINE=InnoDB;
        ';
        $pdo->exec($sql);
        return self::$connections[$id] = $pdo;
    }
}