<?php
declare(strict_types=1);

class DatabaseException extends \RuntimeException {}

class Database
{
    private static ?Database $instance = null;
    private readonly PDO $connection;

    private function __construct()
    {
        $host = $_ENV['FG_DB_HOST'] ?? getenv('FG_DB_HOST') ?: 'db';
        $name = $_ENV['FG_DB_NAME'] ?? getenv('FG_DB_NAME') ?: 'flowgate_db';
        $user = $_ENV['FG_DB_USER'] ?? getenv('FG_DB_USER') ?: 'flowgate';
        $pass = $_ENV['FG_DB_PASS'] ?? getenv('FG_DB_PASS')
            ?: throw new \RuntimeException('Variável de ambiente FG_DB_PASS não configurada.');

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

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->preparar_com_tipos($sql, $params);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function query_one(string $sql, array $params = []): ?array
    {
        $stmt = $this->preparar_com_tipos($sql, $params);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->preparar_com_tipos($sql, $params);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Prepara a query e vincula cada parâmetro com o tipo PDO correto.
     *
     * Necessário porque, com PDO::ATTR_EMULATE_PREPARES desligado, um
     * execute($params) simples vincula tudo como PDO::PARAM_STR — e o
     * driver mysqlnd exige inteiro nativo em cláusulas como
     * "LIMIT :limite OFFSET :offset". Sem isso, toda query paginada
     * (ex: GET /api/pecas) falha com um PDOException e cai no catch
     * genérico do endpoint, virando um 500 sem pista nenhuma do motivo.
     */
    private function preparar_com_tipos(string $sql, array $params): PDOStatement
    {
        $stmt = $this->connection->prepare($sql);

        foreach ($params as $nome => $valor) {
            $tipo = match (true) {
                is_int($valor)  => PDO::PARAM_INT,
                is_bool($valor) => PDO::PARAM_BOOL,
                is_null($valor) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };

            $stmt->bindValue($nome, $valor, $tipo);
        }

        return $stmt;
    }

    public function last_insert_id(): string
    {
        return $this->connection->lastInsertId();
    }

    public function begin_transaction(): void { $this->connection->beginTransaction(); }
    public function commit(): void            { $this->connection->commit(); }

    public function rollback(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }
}