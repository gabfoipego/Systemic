<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

/*
 * Middleware de autenticação da Flowgate.
 *
 * Clientes enviam a chave no header:
 *   X-Flowgate-Key: <chave-em-texto-puro>
 *
 * O banco armazena o SHA-256 da chave — nunca o valor bruto.
 * A comparação usa hash_equals() para evitar timing attacks.
 *
 * Uso:
 *   ApiAuth::exigir();   // encerra com 401 se inválida
 *   $id = ApiAuth::id_key_atual();  // retorna o id da chave autenticada
 */
class ApiAuth
{
    private static ?int $id_key_autenticado = null;

    public static function exigir(): void
    {
        $chave_recebida = self::extrair_chave_do_header();

        if ($chave_recebida === null) {
            self::recusar(401, 'Header X-Flowgate-Key ausente.');
        }

        $hash_recebido = hash('sha256', $chave_recebida);

        $registro = self::buscar_chave_no_banco($hash_recebido);

        if ($registro === null || !$registro['ativa']) {
            self::recusar(401, 'Chave de API inválida ou inativa.');
        }

        self::$id_key_autenticado = (int) $registro['id_key'];

        self::registrar_log($registro['id_key']);
    }

    public static function id_key_atual(): ?int
    {
        return self::$id_key_autenticado;
    }

    // ── Privados ──────────────────────────────────────────────────────────

    private static function extrair_chave_do_header(): ?string
    {
        /*
         * Apache/PHP expõe headers HTTP como variáveis SERVER com prefixo HTTP_.
         * X-Flowgate-Key → HTTP_X_FLOWGATE_KEY
         */
        $valor = $_SERVER['HTTP_X_FLOWGATE_KEY'] ?? '';
        return $valor !== '' ? $valor : null;
    }

    private static function buscar_chave_no_banco(string $hash): ?array
    {
        try {
            $db = Database::get_instance();
            return $db->query_one(
                'SELECT id_key, ativa FROM api_keys WHERE chave = :chave LIMIT 1',
                [':chave' => $hash]
            );
        } catch (DatabaseException) {
            return null;
        }
    }

    private static function registrar_log(int $id_key): void
    {
        /*
         * Grava audit trail de forma "best-effort": se falhar,
         * não interrompe a requisição principal.
         */
        try {
            $db = Database::get_instance();
            $db->execute(
                'INSERT INTO consultas_log (id_key, endpoint, parametros, ip)
                 VALUES (:id_key, :endpoint, :params, :ip)',
                [
                    ':id_key'   => $id_key,
                    ':endpoint' => $_SERVER['REQUEST_URI'] ?? '',
                    ':params'   => json_encode($_GET),
                    ':ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
                ]
            );
        } catch (\Throwable) {
            // silencia — log não pode derrubar a API
        }
    }

    private static function recusar(int $codigo, string $mensagem): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['erro' => $mensagem], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
