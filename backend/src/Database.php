<?php
declare(strict_types=1);

namespace Tvrtke;

use PDO;

final class Database
{
    public static function connect(): PDO
    {
        return self::pdo(Config::require('TVRTKE_DB_USER'),Config::require('TVRTKE_DB_PASSWORD'));
    }

    /**
     * Public web requests must never authenticate with the importer account.
     * SQL GRANT SELECT only on explicitly exposed normalized tables.
     */
    public static function connectReadOnly(): PDO
    {
        return self::pdo(Config::require('TVRTKE_DB_READ_USER'),
                         Config::require('TVRTKE_DB_READ_PASSWORD'));
    }

    private static function pdo(string $username,string $password): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                Config::require('TVRTKE_DB_HOST'),
                (int) Config::get('TVRTKE_DB_PORT', '3306'),
                Config::require('TVRTKE_DB_NAME')),
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false,
             PDO::ATTR_TIMEOUT => 8]
        );
    }
}
