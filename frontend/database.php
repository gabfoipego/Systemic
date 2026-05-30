<?php

declare(strict_types=1);

class DatabaseException extends \RuntimeException {}

class Database
{
    private static ?Database $instance = null;
    private readonly PDO $connection;

    private function __construct()
    {
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'db';
        $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'oficina_db';
        $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'automax';
        $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'automax123';

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->connection = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            throw new DatabaseException('Não foi possível conectar ao banco de dados.', 0, $e);
        }
    }

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /*
     * Executa uma query com parâmetros nomeados e retorna todos os registros.
     * Exemplo: query("SELECT * FROM funcionarios WHERE email = :email", [':email' => $email])
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /*
     * Igual ao query(), mas retorna apenas o primeiro registro ou null.
     * Ideal para buscas por chave única (id, email, CPF).
     */
    public function query_one(string $sql, array $params = []): ?array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /*
     * Executa INSERT / UPDATE / DELETE e retorna o número de linhas afetadas.
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /*
     * Retorna o ID do último INSERT realizado na conexão atual.
     */
    public function last_insert_id(): string
    {
        return $this->connection->lastInsertId();
    }
}