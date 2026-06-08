# Flowgate — Documentação da API

> **Versão:** 1.0  
> **Base URL:** `http://flowgate.local` (desenvolvimento) / `https://api.flowgate.com.br` (produção)  
> **Formato:** JSON (`Content-Type: application/json; charset=UTF-8`)

---

## O que é a Flowgate

A Flowgate é o hub de fornecedores do projeto Systemic. Em vez de a Automax (ou qualquer outra oficina cliente) precisar negociar e integrar com cada fornecedora de peças individualmente, a Flowgate centraliza esse catálogo em uma única API.

```
Automax ──► Flowgate API ──► [AutoPeças Brasil]
                         ──► [RapidPart]
                         ──► [MotoSupply SC]
                         ──► [... outras fornecedoras]
```

A Automax envia uma chave de API em cada requisição. A Flowgate valida, busca nos dados das fornecedoras cadastradas e devolve o resultado unificado.

---

## Autenticação

Todas as rotas (exceto `/health`) exigem o header:

```
X-Flowgate-Key: <sua-chave>
```

A chave é gerada pela equipe Flowgate e entregue ao cliente. O banco armazena apenas o hash SHA-256 — a chave em texto puro nunca é persistida.

**Chave de desenvolvimento da Automax:**
```
automax-dev-key-2026
```

**Exemplo com curl:**
```bash
curl -H "X-Flowgate-Key: automax-dev-key-2026" \
     http://flowgate.local/api/categorias
```

**Erros de autenticação:**
| Situação | Status | Corpo |
|---|---|---|
| Header ausente | `401` | `{ "erro": "Header X-Flowgate-Key ausente." }` |
| Chave inválida ou inativa | `401` | `{ "erro": "Chave de API inválida ou inativa." }` |

---

## Rotas

### `GET /health`

Verifica se o serviço está de pé. **Não exige autenticação.**

**Resposta `200`:**
```json
{ "status": "ok", "servico": "flowgate" }
```

---

### `GET /api/categorias`

Lista todas as categorias de peças disponíveis no catálogo.

**Resposta `200`:**
```json
{
  "categorias": [
    { "id": 1, "slug": "filtros",   "nome": "Filtros" },
    { "id": 2, "slug": "fluidos",   "nome": "Fluidos e Óleos" },
    { "id": 3, "slug": "freios",    "nome": "Freios" },
    { "id": 4, "slug": "suspensao", "nome": "Suspensão" },
    { "id": 5, "slug": "eletrico",  "nome": "Sistema Elétrico" },
    { "id": 6, "slug": "motor",     "nome": "Motor" },
    { "id": 7, "slug": "correia",   "nome": "Correias e Tensores" },
    { "id": 8, "slug": "outros",    "nome": "Outros" }
  ]
}
```

---

### `GET /api/fornecedoras`

Lista todas as fornecedoras ativas integradas à Flowgate.

**Resposta `200`:**
```json
{
  "fornecedoras": [
    {
      "id": 1,
      "nome": "AutoPeças Brasil Ltda",
      "email": "contato@autopecasbrasil.com.br",
      "telefone": "(11) 3000-1111"
    }
  ]
}
```

---

### `GET /api/pecas`

Busca e lista peças do catálogo com paginação.

**Query params:**

| Param | Tipo | Obrigatório | Descrição |
|---|---|---|---|
| `q` | string | não | Busca por nome, SKU ou descrição |
| `categoria` | string | não | Slug da categoria (ex: `filtros`) |
| `fornecedora` | int | não | ID da fornecedora |
| `pagina` | int | não | Número da página (default: `1`) |
| `por_pagina` | int | não | Itens por página (default: `20`, máx: `50`) |

**Exemplos:**
```
GET /api/pecas
GET /api/pecas?q=filtro+de+oleo
GET /api/pecas?categoria=freios&pagina=2
GET /api/pecas?fornecedora=3&por_pagina=10
```

**Resposta `200`:**
```json
{
  "pecas": [
    {
      "id": 1,
      "nome": "Filtro de Óleo Bosch F026407006",
      "sku": "FO-B-7006",
      "descricao": "Filtro de óleo compatível com motores 1.0 a 2.0 flex.",
      "preco": 42.90,
      "estoque": 80,
      "unidade": "un",
      "categoria": { "slug": "filtros", "nome": "Filtros" },
      "fornecedora": { "id": 1, "nome": "AutoPeças Brasil Ltda" }
    }
  ],
  "total": 12,
  "pagina": 1,
  "por_pagina": 20,
  "paginas": 1
}
```

---

### `GET /api/pecas/:id`

Retorna os dados completos de uma única peça, incluindo contato da fornecedora.

**Exemplo:**
```
GET /api/pecas/7
```

**Resposta `200`:**
```json
{
  "peca": {
    "id": 7,
    "nome": "Pastilha de Freio Dianteira Fras-le",
    "sku": "PF-FL-D01",
    "descricao": "Eixo dianteiro, compatível com HB20, Onix, Polo 2018+.",
    "preco": 98.00,
    "estoque": 50,
    "unidade": "jogo",
    "ativo": true,
    "atualizado_em": "2026-06-08 22:00:00",
    "categoria": { "slug": "freios", "nome": "Freios" },
    "fornecedora": {
      "id": 3,
      "nome": "MotoSupply SC",
      "email": "sc@motosupply.com.br",
      "telefone": "(47) 3000-3333"
    }
  }
}
```

**Resposta `404`:**
```json
{ "erro": "Peça #99 não encontrada." }
```

---

### `GET /api/disponibilidade`

Verifica o estoque de múltiplos SKUs em uma única chamada. Projetado para a Automax checar peças antes de abrir uma OS.

**Query params:**

| Param | Tipo | Obrigatório | Descrição |
|---|---|---|---|
| `skus` | string | sim | SKUs separados por vírgula (máx: 20) |
| `fornecedora` | int | não | Filtra por fornecedora específica |

**Exemplo:**
```
GET /api/disponibilidade?skus=FO-B-7006,PF-FL-D01,SKU-INEXISTENTE
```

**Resposta `200`:**
```json
{
  "disponibilidade": [
    { "sku": "FO-B-7006",       "nome": "Filtro de Óleo Bosch F026407006",  "estoque": 80, "disponivel": true,  "preco": 42.90 },
    { "sku": "PF-FL-D01",       "nome": "Pastilha de Freio Dianteira Fras-le", "estoque": 50, "disponivel": true,  "preco": 98.00 },
    { "sku": "SKU-INEXISTENTE", "nome": null,                                "estoque": 0,  "disponivel": false, "preco": null  }
  ]
}
```

---

## Tabela de códigos HTTP

| Status | Significado |
|---|---|
| `200` | Sucesso |
| `400` | Parâmetro inválido ou ausente |
| `401` | Chave de API ausente ou inválida |
| `404` | Recurso não encontrado |
| `405` | Método HTTP não permitido |
| `500` | Erro interno do servidor |

---

## Configuração de ambiente

Variáveis necessárias no container/servidor da Flowgate:

```env
FG_DB_HOST=db
FG_DB_NAME=flowgate_db
FG_DB_USER=flowgate
FG_DB_PASS=flowgate123
```

---

## Setup do banco de dados

```bash
# Importar schema + seed de desenvolvimento
mysql -u root -p < docs/flowgate_db.sql
```

O script cria o banco `flowgate_db`, todas as tabelas, as categorias padrão, três fornecedoras de exemplo e a chave de API de desenvolvimento da Automax.

---

## Estrutura de arquivos

```
flowgate/
├── index.php              # Entry point — registra todas as rotas
├── database.php           # Singleton de conexão PDO (flowgate_db)
├── libs/
│   ├── ApiAuth.php        # Middleware de autenticação por API key
│   └── router.php         # Router HTTP
├── api/
│   ├── pecas.php          # GET /api/pecas
│   ├── peca.php           # GET /api/pecas/:id
│   ├── disponibilidade.php # GET /api/disponibilidade
│   ├── fornecedoras.php   # GET /api/fornecedoras
│   └── categorias.php     # GET /api/categorias
└── docs/
    ├── flowgate_db.sql    # Schema + seed do banco
    └── API.md             # Este arquivo
```

---

## Virtual Host (Apache)

Adicionar ao `httpd-vhosts.conf` do XAMPP ou ao Apache do Docker:

```apache
<VirtualHost *:80>
    ServerName flowgate.local
    DocumentRoot "C:/xampp/htdocs/flowgate"

    <Directory "C:/xampp/htdocs/flowgate">
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ /index.php [L]

    ErrorLog  logs/flowgate-error.log
    CustomLog logs/flowgate-access.log combined
</VirtualHost>
```

E no `C:\Windows\System32\drivers\etc\hosts`:
```
127.0.0.1   flowgate.local
```

---

*Desenvolvido pela equipe Systemic — SENAI Situação de Aprendizagem*
