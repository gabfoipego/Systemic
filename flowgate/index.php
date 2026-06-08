<?php

declare(strict_types=1);

require_once __DIR__ . '/libs/router.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/libs/ApiAuth.php';

$router = new Router(__DIR__);

// ── Cabeçalhos globais ────────────────────────────────────────────────────

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// ── Health check — sem autenticação ──────────────────────────────────────

/*
 * GET /health
 * Usado pelo Docker/balanceador para checar se o serviço está de pé.
 * Não expõe dados internos.
 */
$router->get('/health', function () {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'servico' => 'flowgate']);
});

// ── Catálogo de peças ─────────────────────────────────────────────────────

/*
 * GET /api/pecas
 * Lista o catálogo com busca, filtro por categoria/fornecedora e paginação.
 * Params: q, categoria, fornecedora, pagina, por_pagina
 */
$router->get('/api/pecas', function () {
    include __DIR__ . '/api/pecas.php';
});

/*
 * GET /api/pecas/:id
 * Retorna os dados completos de uma única peça.
 */
$router->get('/api/pecas/:id', function (array $params) {
    $_GET['id'] = $params['id'];
    include __DIR__ . '/api/peca.php';
});

// ── Disponibilidade em lote ───────────────────────────────────────────────

/*
 * GET /api/disponibilidade?skus=SKU1,SKU2,...
 * Verifica estoque de múltiplos SKUs em uma chamada.
 * Ideal para pré-checagem antes de abrir uma OS na Automax.
 */
$router->get('/api/disponibilidade', function () {
    include __DIR__ . '/api/disponibilidade.php';
});

// ── Metadados ─────────────────────────────────────────────────────────────

/*
 * GET /api/fornecedoras
 * Lista todas as fornecedoras ativas.
 */
$router->get('/api/fornecedoras', function () {
    include __DIR__ . '/api/fornecedoras.php';
});

/*
 * GET /api/categorias
 * Lista todas as categorias de peças.
 */
$router->get('/api/categorias', function () {
    include __DIR__ . '/api/categorias.php';
});

// ── Dispatch ──────────────────────────────────────────────────────────────

try {
    $router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
