<?php

declare(strict_types=1);

/*
 * Endpoint: GET /api/pecas/:id
 *
 * Retorna os dados completos de uma peça pelo seu ID.
 * Inclui dados da fornecedora e da categoria.
 * Requer autenticação via X-Flowgate-Key.
 *
 * Respostas:
 *   200  { peca: {...} }
 *   400  { erro: "ID inválido" }
 *   401  { erro: "..." }
 *   404  { erro: "Peça não encontrada" }
 *   500  { erro: "..." }
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../libs/ApiAuth.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

ApiAuth::exigir();

// ── Valida o parâmetro :id vindo da rota ──────────────────────────────────

/*
 * O index.php extrai o id do path e passa via $_GET['id'].
 * Exemplo: GET /api/pecas/42 → $_GET['id'] = '42'
 */
$id_raw = $_GET['id'] ?? '';
$id     = filter_var($id_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($id === false) {
    http_response_code(400);
    echo json_encode(['erro' => 'ID inválido. Forneça um inteiro positivo.']);
    exit;
}

// ── Executa ───────────────────────────────────────────────────────────────

try {
    $db  = Database::get_instance();
    $row = $db->query_one(
        "SELECT
             p.id_peca,
             p.nome,
             p.codigo_sku,
             p.descricao,
             p.preco,
             p.estoque,
             p.unidade,
             p.ativo,
             p.atualizado_em,
             c.slug        AS categoria_slug,
             c.nome        AS categoria_nome,
             f.id_fornecedora,
             f.nome        AS fornecedora_nome,
             f.email_contato AS fornecedora_email,
             f.telefone    AS fornecedora_telefone
           FROM pecas p
           JOIN categorias   c ON c.id_categoria   = p.id_categoria
           JOIN fornecedoras f ON f.id_fornecedora = p.id_fornecedora
          WHERE p.id_peca = :id
          LIMIT 1",
        [':id' => $id]
    );

    if ($row === null) {
        http_response_code(404);
        echo json_encode(['erro' => "Peça #{$id} não encontrada."]);
        exit;
    }

    $peca = [
        'id'           => (int)   $row['id_peca'],
        'nome'         =>         $row['nome'],
        'sku'          =>         $row['codigo_sku'],
        'descricao'    =>         $row['descricao'],
        'preco'        => (float) $row['preco'],
        'estoque'      => (int)   $row['estoque'],
        'unidade'      =>         $row['unidade'],
        'ativo'        => (bool)  $row['ativo'],
        'atualizado_em' =>        $row['atualizado_em'],
        'categoria' => [
            'slug' => $row['categoria_slug'],
            'nome' => $row['categoria_nome'],
        ],
        'fornecedora' => [
            'id'      => (int) $row['id_fornecedora'],
            'nome'    => $row['fornecedora_nome'],
            'email'   => $row['fornecedora_email'],
            'telefone' => $row['fornecedora_telefone'],
        ],
    ];

    http_response_code(200);
    echo json_encode(['peca' => $peca], JSON_UNESCAPED_UNICODE);

} catch (DatabaseException $e) {
    error_log('[Flowgate /api/pecas/:id] DB: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno. Tente novamente.']);
} catch (\Throwable $e) {
    error_log('[Flowgate /api/pecas/:id] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno inesperado.']);
}
