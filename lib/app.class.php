<?php
/**
 * Core application container.
 *
 * Exposes two PDO connections (`$db` and `$dbi`) configured for safe use:
 *   - Real prepared statements (ATTR_EMULATE_PREPARES = false)
 *   - ERRMODE_EXCEPTION so DB failures don't silently return false
 *   - FETCH_ASSOC as the default fetch style
 */

declare(strict_types=1);

class app
{
    /** @var PDO */
    public $db;

    /** @var PDO */
    public $dbi;

    /** @var array<string,mixed> */
    public $options = [];

    public function __construct()
    {
        $pdoOptions = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            $this->db  = new PDO(SHARES_DSN,  DATABASE_USER,  DATABASE_PASSWORD,  $pdoOptions);
            $this->dbi = new PDO(SHARES_DSN2, DATABASE_USER2, DATABASE_PASSWORD2, $pdoOptions);
        } catch (PDOException $exception) {
            error_log('Database connection failed: ' . $exception->getMessage());
            http_response_code(503);
            exit('The service is temporarily unavailable.');
        }
    }

    /**
     * Run a parameterised query on the primary connection and return the
     * PDOStatement for iteration.
     *
     * @param array<string|int,mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Run a parameterised query on the secondary connection.
     *
     * @param array<string|int,mixed> $params
     */
    public function queryi(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->dbi->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
