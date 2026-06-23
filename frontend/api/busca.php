<?php
declare(strict_types=1);

use Automax\Config\Database;
use Automax\Config\DatabaseException;
use Automax\Controllers\AuthController;

/*
 * Endpoint: GET /api/busca?q=:termo&pagina=:n&categoria=:cat
 *
 * Busca produtos por nome (LIKE) com paginação e filtro opcional por categoria.
 * Exige autenticação (sessão ativa).
 *
 * Respostas:
 *   200  { resultados: [...], total: int, pagina: int, por_pagina: int, paginas: int }
 *   401  { erro: "Não autenticado" }
 *   405  { erro: "Método não permitido" }
 *   500  { erro: "Erro interno" }
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

AuthController::exigir_autenticacao();

$por_pagina = 12;

$termo = trim($_GET['q'] ?? '');
if (mb_strlen($termo) > 100) {
    $termo = mb_substr($termo, 0, 100);
}

$pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($pagina === false) {
    $pagina = 1;
}

$categorias_permitidas = ['pecas', 'fluidos', 'eletrico', 'todos'];
$categoria_raw = strtolower(trim($_GET['categoria'] ?? 'todos'));
$categoria = in_array($categoria_raw, $categorias_permitidas, true) ? $categoria_raw : 'todos';

function montar_filtro_busca(string $termo, string $categoria): array
{
    $condicoes = [];
    $params    = [];

    if ($termo !== '') {
        $condicoes[] = 'nome LIKE :termo';
        $params[':termo'] = '%' . $termo . '%';
    }

    if ($categoria !== 'todos') {
        $condicoes[] = 'categoria = :categoria';
        $params[':categoria'] = $categoria;
    }

    $where_sql = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';
    return [$where_sql, $params];
}

try {
    $db     = Database::get_instance();
    $offset = ($pagina - 1) * $por_pagina;

    [$where_sql, $params_base] = montar_filtro_busca($termo, $categoria);

    $total = (int) ($db->query_one(
        "SELECT COUNT(*) AS total FROM produtos {$where_sql}",
        $params_base
    )['total'] ?? 0);

    $params_rows = array_merge($params_base, [
        ':limite' => $por_pagina,
        ':offset' => $offset,
    ]);

    $linhas = $db->query(
        "SELECT id_produto, nome, preco, stock, imagem, categoria
           FROM produtos
         {$where_sql}
          ORDER BY nome ASC
          LIMIT :limite OFFSET :offset",
        $params_rows
    );

    $resultados = array_map(fn(array $r): array => [
        'id'        => (int)   $r['id_produto'],
        'nome'      =>         $r['nome'],
        'preco'     => (float) $r['preco'],
        'stock'     => (int)   $r['stock'],
        'imagem'    =>         $r['imagem'],
        'categoria' =>         $r['categoria'],
    ], $linhas);

    http_response_code(200);
    echo json_encode([
        'resultados' => $resultados,
        'total'      => $total,
        'pagina'     => $pagina,
        'por_pagina' => $por_pagina,
        'paginas'    => (int) ceil($total / $por_pagina),
    ], JSON_UNESCAPED_UNICODE);

} catch (DatabaseException $e) {
    error_log('[API busca] DatabaseException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno. Tente novamente mais tarde.']);
} catch (Throwable $e) {
    error_log('[API busca] Throwable: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno inesperado.']);
}