-- Seed: dados de demonstração da Automax
--
--  Roda depois de seed_funcionarios.sql e seed_cliente_teste.sql:
--    - funcionarios: 1=gerente, 2=recepção, 3=mecânico (já existentes)
--    - clientes:     1=Cliente Teste (já existente)
--
--  Popula produtos (vitrine + estoque interno), fornecedores locais,
--  clientes/veículos extras, ordens de serviço em vários status e
--  agendamentos — só para deixar a demo com cara de sistema em uso.
--
--  NUNCA use estes dados em produção.

USE oficina_db;

-- 1. Fornecedores locais (tabela da Automax, distinta das
--    fornecedoras da Flowgate)
INSERT INTO fornecedores (nome_fornecedor, cnpj) VALUES
    ('Distribuidora Central de Peças', '11.222.333/0001-44'),
    ('AutoImport SC',                  '22.333.444/0001-55')
ON DUPLICATE KEY UPDATE nome_fornecedor = VALUES(nome_fornecedor);

-- 2. Produtos (vitrine pública em /produtos e estoque
--    interno em /estoque — mesma tabela, exposta por duas APIs)
INSERT INTO produtos (nome, preco, stock, imagem, categoria, detalhes) VALUES
    ('Óleo Motor 5W-30 Sintético 1L',        69.90,  40, 'https://placehold.co/400x300/1E2126/B0B3B8?text=Oleo+5W30',       'fluidos', 'Óleo sintético API SN/CF, compatível com a maioria dos motores flex.'),
    ('Filtro de Óleo Universal',              35.50,  55, 'https://placehold.co/400x300/1E2126/B0B3B8?text=Filtro+Oleo',     'pecas',   'Filtro de óleo rosca padrão, uso geral em veículos de passeio.'),
    ('Pastilha de Freio Dianteira',           98.00,  22, 'https://placehold.co/400x300/1E2126/B0B3B8?text=Pastilha+Freio',  'pecas',   'Jogo dianteiro, compatível com os principais hatches e sedãs nacionais.'),
    ('Bateria 60Ah Selada',                  390.00,   8, 'https://placehold.co/400x300/1E2126/B0B3B8?text=Bateria+60Ah',    'eletrico','Bateria selada livre de manutenção, 18 meses de garantia.'),
    ('Fluido de Freio DOT 4 500ml',           28.00,  30, 'https://placehold.co/400x300/1E2126/B0B3B8?text=Fluido+DOT4',     'fluidos', 'Ponto de ebulição seco elevado, indicado para uso urbano e rodoviário.'),
    ('Kit Correia Dentada + Tensor',         145.00,  12, 'https://placehold.co/400x300/1E2126/B0B3B8?text=Correia+Dentada', 'pecas',   'Kit completo para motores 1.6 16v, inclui correia e tensor.'),
    ('Jogo de Velas de Ignição (4un)',        72.00,  18, 'https://placehold.co/400x300/1E2126/B0B3B8?text=Velas+Ignicao',   'eletrico','Eletrodo de cobre, conjunto com 4 unidades.'),
    ('Amortecedor Dianteiro',                210.00,   9, 'https://placehold.co/400x300/1E2126/B0B3B8?text=Amortecedor',    'pecas',   'Gas-Magnum, eixo dianteiro, aplicação universal.'),
    ('Flux Capacitor DeLorean FX-88',       9999.99,   1, 'https://placehold.co/400x300/1E2126/B0B3B8?text=Flux+Capacitor', 'eletrico','Componente essencial para viagem no tempo. Requer 1,21 gigawatts. Uso interno apenas para teste do catálogo.');

-- "produtos" não tem UNIQUE KEY em "nome", então este INSERT não é
-- idempotente por natureza — assim como agendamentos, depende do
-- docker-entrypoint-initdb.d rodar só na primeira subida do container.

-- 3. Clientes extras + veículos
--    (Cliente Teste, id 1, já vem do seed_cliente_teste.sql)
INSERT INTO clientes (nome_cliente, CPF, celular, email, senha, vip) VALUES
    ('Marcos Vinícius Souza', '123.456.789-01', '(47) 9 9111-2233', 'marcos.souza@exemplo.com.br', '$2y$12$ZzntjQFjox1nBCC/jsaBz.22.sfovpJyw93Yms8TVfsniIyXF04Bi', 0),
    ('Fernanda Lima Costa',   '234.567.890-12', '(47) 9 9222-3344', 'fernanda.lima@exemplo.com.br','$2y$12$ZzntjQFjox1nBCC/jsaBz.22.sfovpJyw93Yms8TVfsniIyXF04Bi', 1),
    ('Ricardo Alves Pereira', '345.678.901-23', '(47) 9 9333-4455', 'ricardo.alves@exemplo.com.br','$2y$12$ZzntjQFjox1nBCC/jsaBz.22.sfovpJyw93Yms8TVfsniIyXF04Bi', 0)
ON DUPLICATE KEY UPDATE nome_cliente = VALUES(nome_cliente);

INSERT INTO veiculos (marca, cor, ano, modelo, placa, id_cliente) VALUES
    ('Volkswagen', 'Prata',   '2019', 'Gol',       'ABC1D23', (SELECT id_cliente FROM clientes WHERE email = 'teste@gmail.com')),
    ('Chevrolet',  'Branco',  '2021', 'Onix',      'DEF4E56', (SELECT id_cliente FROM clientes WHERE email = 'marcos.souza@exemplo.com.br')),
    ('Hyundai',    'Preto',   '2020', 'HB20',      'GHI7F89', (SELECT id_cliente FROM clientes WHERE email = 'fernanda.lima@exemplo.com.br')),
    ('Fiat',       'Vermelho','2018', 'Argo',      'JKL0G12', (SELECT id_cliente FROM clientes WHERE email = 'ricardo.alves@exemplo.com.br')),
    ('Toyota',     'Cinza',   '2022', 'Corolla',   'MNO3H45', (SELECT id_cliente FROM clientes WHERE email = 'fernanda.lima@exemplo.com.br'))
ON DUPLICATE KEY UPDATE cor = VALUES(cor);

-- 4. Ordens de serviço em vários status (aberta, andamento,
--    aguardando peça, concluída, atrasada)
INSERT INTO ordem
    (id_funcionario, id_cliente, id_veiculo, tipo_ordem, diagnostico,
     abertura, prazo, fechamento, conclusao_ordem, mao_de_obra, orcamento, status)
VALUES
    -- Aberta: acabou de entrar, ainda sem diagnóstico fechado
    (
        (SELECT id_funcionario FROM funcionarios WHERE email = 'recepcao@automax.com.br'),
        (SELECT id_cliente FROM clientes WHERE email = 'teste@gmail.com'),
        (SELECT id_veiculo FROM veiculos WHERE placa = 'ABC1D23'),
        'Revisão geral',
        'Cliente relata ruído na suspensão dianteira ao passar em buracos.',
        NOW(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), NULL, NULL,
        NULL, NULL, 'aberta'
    ),
    -- Em andamento: mecânico já está atuando
    (
        (SELECT id_funcionario FROM funcionarios WHERE email = 'mecanico@automax.com.br'),
        (SELECT id_cliente FROM clientes WHERE email = 'marcos.souza@exemplo.com.br'),
        (SELECT id_veiculo FROM veiculos WHERE placa = 'DEF4E56'),
        'Troca de óleo e filtros',
        'Troca programada de óleo, filtro de óleo e filtro de ar.',
        DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), NULL, NULL,
        80.00, 220.00, 'andamento'
    ),
    -- Aguardando peça: cliente VIP esperando peça específica
    (
        (SELECT id_funcionario FROM funcionarios WHERE email = 'mecanico@automax.com.br'),
        (SELECT id_cliente FROM clientes WHERE email = 'fernanda.lima@exemplo.com.br'),
        (SELECT id_veiculo FROM veiculos WHERE placa = 'GHI7F89'),
        'Troca de bateria',
        'Bateria não segura carga. Aguardando reposição de estoque do modelo compatível.',
        DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), NULL, NULL,
        50.00, 440.00, 'aguardando'
    ),
    -- Concluída: histórico fechado, com valores finais
    (
        (SELECT id_funcionario FROM funcionarios WHERE email = 'mecanico@automax.com.br'),
        (SELECT id_cliente FROM clientes WHERE email = 'ricardo.alves@exemplo.com.br'),
        (SELECT id_veiculo FROM veiculos WHERE placa = 'JKL0G12'),
        'Freios',
        'Pastilhas dianteiras no limite de desgaste, disco dentro do padrão.',
        DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY),
        'Pastilhas dianteiras substituídas. Teste de frenagem aprovado.',
        120.00, 218.00, 'concluida'
    ),
    -- Atrasada: prazo já vencido, ainda sem fechamento
    (
        (SELECT id_funcionario FROM funcionarios WHERE email = 'mecanico@automax.com.br'),
        (SELECT id_cliente FROM clientes WHERE email = 'fernanda.lima@exemplo.com.br'),
        (SELECT id_veiculo FROM veiculos WHERE placa = 'MNO3H45'),
        'Correia dentada',
        'Substituição preventiva de correia dentada e tensor, motor 1.6 16v.',
        DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), NULL, NULL,
        180.00, 325.00, 'atrasada'
    );

-- 5. Peças usadas em cada ordem (ordem_pecas)
--    id_peca fica NULL: representa peça vinda do catálogo da
--    Flowgate, não do estoque interno da Automax.
INSERT INTO ordem_pecas (id_ordem, id_peca, nome_peca, quantidade_trocas, valor_unitario)
VALUES
    -- Ordem "andamento" (troca de óleo)
    (
        (SELECT id_ordem FROM ordem WHERE tipo_ordem = 'Troca de óleo e filtros' LIMIT 1),
        (SELECT id_peca FROM pecas WHERE nome_peca = 'Óleo Motor 5W-30 Sintético 1L'),
        'Óleo Motor 5W-30 Sintético 1L', 4, 69.90
    ),
    (
        (SELECT id_ordem FROM ordem WHERE tipo_ordem = 'Troca de óleo e filtros' LIMIT 1),
        (SELECT id_peca FROM pecas WHERE nome_peca = 'Filtro de Óleo Universal'),
        'Filtro de Óleo Universal', 1, 35.50
    ),
    -- Ordem "aguardando" (bateria) — peça do catálogo Flowgate, sem vínculo local
    (
        (SELECT id_ordem FROM ordem WHERE tipo_ordem = 'Troca de bateria' LIMIT 1),
        NULL, 'Bateria Moura 60Ah MF60GE', 1, 390.00
    ),
    -- Ordem "concluida" (freios)
    (
        (SELECT id_ordem FROM ordem WHERE tipo_ordem = 'Freios' LIMIT 1),
        (SELECT id_peca FROM pecas WHERE nome_peca = 'Pastilha de Freio Dianteira'),
        'Pastilha de Freio Dianteira', 1, 98.00
    ),
    -- Ordem "atrasada" (correia dentada)
    (
        (SELECT id_ordem FROM ordem WHERE tipo_ordem = 'Correia dentada' LIMIT 1),
        (SELECT id_peca FROM pecas WHERE nome_peca = 'Kit Correia Dentada + Tensor'),
        'Kit Correia Dentada + Tensor', 1, 145.00
    );

-- 6. Agendamentos (funil de conversão antes de virar OS)
INSERT INTO agendamentos
    (nome, telefone, email, placa, marca, modelo, ano, combustivel, km,
     servico, sintomas, descricao, data_preferida, turno, status)
VALUES
    ('Juliana Ferreira', '(47) 9 9444-5566', 'juliana.ferreira@exemplo.com.br', 'PQR6I78', 'Renault', 'Kwid', 2020, 'flex', 38000,
     'Revisão', 'Barulho ao frear', 'Ruído metálico ao frear em baixa velocidade.', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'manha', 'pendente'),
    ('Bruno Cardoso', '(47) 9 9555-6677', 'bruno.cardoso@exemplo.com.br', 'STU9J01', 'Honda', 'Civic', 2017, 'flex', 82000,
     'Troca de óleo', NULL, 'Manutenção programada, óleo e filtros.', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'tarde', 'confirmado'),
    ('Patrícia Gomes', '(47) 9 9666-7788', 'patricia.gomes@exemplo.com.br', 'VWX2K34', 'Jeep', 'Renegade', 2021, 'flex', 21000,
     'Diagnóstico elétrico', 'Luz de injeção acesa', 'Painel acusando falha, luz de injeção intermitente.', CURDATE(), 'manha', 'pendente');

-- A tabela "agendamentos" não tem chave única além do id, então
-- ON DUPLICATE KEY UPDATE não se aplica aqui. Isso é seguro porque
-- o docker-entrypoint-initdb.d só roda os scripts na primeira subida
-- do container (volume de dados vazio).
