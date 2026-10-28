<?php
declare(strict_types=1);
namespace Arzoie\Core;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection !== null) { return self::$connection; }
        $driver = Env::get('DB_DRIVER', 'pgsql');
        if (!in_array($driver, ['pgsql', 'mysql'], true)) { throw new \RuntimeException('Use pgsql or mysql (MariaDB).'); }
        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', $driver === 'pgsql' ? '5432' : '3306');
        $name = Env::get('DB_DATABASE', 'arzoiedns');
        foreach ([$host, $port, $name] as $part) {
            if (preg_match('/[;\x00-\x20]/', $part)) { throw new \RuntimeException('Invalid database configuration'); }
        }
        $dsn = "$driver:host=$host;port=$port;dbname=$name" . ($driver === 'mysql' ? ';charset=utf8mb4' : '');
        return self::$connection = new PDO($dsn, Env::get('DB_USERNAME'), Env::get('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function transaction(callable $action): mixed
    {
        $db = self::connect();
        $db->beginTransaction();
        try { $result = $action($db); $db->commit(); return $result; }
        catch (\Throwable $e) { if ($db->inTransaction()) { $db->rollBack(); } throw $e; }
    }

    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
