<?php

declare(strict_types=1);

namespace Automax\Controllers;

use Automax\Config\Database;
use Automax\Config\DatabaseException;
use Automax\Auth\AccessControl;

class LogsController
{
    private const POR_PAGINA  = 20;
    private const NIVEIS_VALIDOS = ['gerente', 'mecanico', 'recepcao'];

    public static function listar(): void
    {
        AccessControl::exigir_permissao('logs.visualizar');

        $pagina     = self::validar_int_positivo($_GET['pagina']      ?? '1') ?: 1;
        $busca      = trim($_GET['busca']      ?? '');
        $funcionario = self::validar_int_positivo($_GET['funcionario'] ?? '') ?: null;
        $data_inicio = trim($_GET['data_inicio'] ?? '');
        $data_fim    = trim($_GET['data_fim']    ?? '');

        if ($data_inicio !== '' && !self::data_valida($data_inicio)) $data_inicio = '';
        if ($data_fim    !== '' && !self::data_valida($data_fim))    $data_fim    = '';

        try {
            $db     = Database::get_instance();
            $offset = ($pagina - 1) * self::POR_PAGINA;

            [$where_sql, $params_base] = self::montar_filtros($busca, $funcionario, $data_inicio, $data_fim);

            $total = (int) ($db->query_one(
                "SELECT COUNT(*) AS total
                   FROM ordem o
                   LEFT JOIN funcionarios f ON f.id_funcionario = o.id_funcionario
                   LEFT JOIN clientes c     ON c.id_cliente     = o.id_cliente
                  {$where_sql}",
                $params_base
            )['total'] ?? 0);

            $linhas = $db->query(
                "SELECT o.id_ordem,
                        o.tipo_ordem,
                        o.status,
                        DATE_FORMAT(o.abertura,   '%Y-%m-%d') AS abertura,
                        DATE_FORMAT(o.fechamento, '%Y-%m-%d') AS fechamento,
                        o.mao_de_obra,
                        o.orcamento,
                        f.id_funcionario,
                        f.nome_funcionario,
                        f.nivel_de_acesso,
                        c.nome_cliente
                   FROM ordem o
                   LEFT JOIN funcionarios f ON f.id_funcionario = o.id_funcionario
                   LEFT JOIN clientes c     ON c.id_cliente     = o.id_cliente
                  {$where_sql}
                  ORDER BY o.fechamento DESC, o.id_ordem DESC
                  LIMIT :limite OFFSET :offset",
                array_merge($params_base, [':limite' => self::POR_PAGINA, ':offset' => $offset])
            );

            $registros = array_map(fn(array $r): array => [
                'id_ordem'         => (int)   $r['id_ordem'],
                'tipo_ordem'       =>          $r['tipo_ordem'],
                'status'           =>          $r['status'],
                'abertura'         =>          $r['abertura'],
                'fechamento'       =>          $r['fechamento'],
                'mao_de_obra'      => isset($r['mao_de_obra']) ? (float)$r['mao_de_obra'] : null,
                'orcamento'        => isset($r['orcamento'])   ? (float)$r['orcamento']   : null,
                'id_funcionario'   => $r['id_funcionario']   ? (int)$r['id_funcionario'] : null,
                'nome_funcionario' =>          $r['nome_funcionario'],
                'nivel_de_acesso'  =>          $r['nivel_de_acesso'],
                'nome_cliente'     =>          $r['nome_cliente'],
            ], $linhas);

            self::responder_json([
                'registros'     => $registros,
                'total'         => $total,
                'pagina'        => $pagina,
                'total_paginas' => max(1, (int) ceil($total / self::POR_PAGINA)),
            ]);

        } catch (DatabaseException) {
            self::responder_erro('Erro ao consultar banco de dados.', 500);
        }
    }

    public static function funcionarios_ativos(): void
    {
        AccessControl::exigir_permissao('logs.visualizar');

        try {
            $db   = Database::get_instance();
            $rows = $db->query(
                'SELECT id_funcionario, nome_funcionario, nivel_de_acesso
                   FROM funcionarios
                  ORDER BY nome_funcionario ASC'
            );

            self::responder_json(array_map(fn(array $r): array => [
                'id'    => (int) $r['id_funcionario'],
                'nome'  =>       $r['nome_funcionario'],
                'nivel' =>       $r['nivel_de_acesso'],
            ], $rows));

        } catch (DatabaseException) {
            self::responder_erro('Erro ao consultar banco de dados.', 500);
        }
    }

    private static function montar_filtros(
        string $busca,
        ?int   $funcionario,
        string $data_inicio,
        string $data_fim
    ): array {
        $conds  = ["o.status = 'concluida'"];
        $params = [];

        if ($busca !== '') {
            $conds[]          = '(f.nome_funcionario LIKE :busca OR c.nome_cliente LIKE :busca OR o.tipo_ordem LIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }

        if ($funcionario !== null) {
            $conds[]               = 'o.id_funcionario = :funcionario';
            $params[':funcionario'] = $funcionario;
        }

        if ($data_inicio !== '') {
            $conds[]               = 'o.fechamento >= :data_inicio';
            $params[':data_inicio'] = $data_inicio;
        }

        if ($data_fim !== '') {
            $conds[]            = 'o.fechamento <= :data_fim';
            $params[':data_fim'] = $data_fim;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $conds);
        return [$where_sql, $params];
    }

    private static function data_valida(string $data): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $data);
        return $d && $d->format('Y-m-d') === $data;
    }

    private static function validar_int_positivo(mixed $valor): ?int
    {
        $int = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $int !== false ? $int : null;
    }

    private static function responder_json(array $dados): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    }

    private static function responder_erro(string $mensagem, int $codigo): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['erro' => $mensagem], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
