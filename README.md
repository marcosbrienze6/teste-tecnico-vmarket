# Teste Técnico Vmarket - Gestão de Produtos e Fornecedores

Sistema web para gestão de:
- Fornecedores
- Produtos
- Vínculo produto x fornecedor (individual e em massa)
- Pedidos e itens de pedido

Stack principal:
- PHP 8.2+
- Laravel 12
- MySQL
- Redis (filas)
- Inertia + Vue 3

## Requisitos

- PHP `>= 8.2`
- Composer
- Node.js `>= 18`
- NPM
- MySQL em execução
- Redis em execução

## Configuração do ambiente

1. Clone o projeto e entre na pasta:

```bash
git clone <url-do-repositorio>
cd teste-tecnico-vmarket
```

2. Instale dependências PHP e JS:

```bash
composer install
npm install
```

3. Crie o arquivo `.env`:

```bash
cp .env.example .env
php artisan key:generate
```

4. Configure o `.env` para MySQL e Redis:

```env
APP_NAME="Teste Tecnico Vmarket"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vmarket_v1
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=redis

CACHE_STORE=redis
SESSION_DRIVER=database

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

5. Execute migrations:

```bash
php artisan migrate
```

6. Suba o frontend:

```bash
npm run dev
```

7. Suba o backend e worker de fila (em terminais separados):

```bash
php artisan serve
php artisan queue:work redis --tries=3
```

## Acesso

- Aplicação web: `http://127.0.0.1:8000`
- API base: `http://127.0.0.1:8000/api/v1`

Como o projeto usa autenticação (Breeze/Inertia), crie usuário pela tela de registro ou via seed/comando antes de usar as páginas protegidas.

## Estrutura das funcionalidades

### 1) Fornecedores
- Tela: `/fornecedores`
- API:
  - `GET /api/v1/suppliers`
  - `POST /api/v1/suppliers`
  - `PUT /api/v1/suppliers/{id}`
  - `DELETE /api/v1/suppliers/{id}`

### 2) Produtos
- Tela: `/produtos`
- API:
  - `GET /api/v1/products`
  - `POST /api/v1/products`
  - `PUT /api/v1/products/{id}`
  - `DELETE /api/v1/products/{id}`

### 3) Vínculo Produto x Fornecedor
- Tela: `/vinculos`
- Vínculo individual:
  - `GET /api/v1/products/{product}/suppliers`
  - `POST /api/v1/products/{product}/suppliers`
  - `DELETE /api/v1/products/{product}/suppliers/{supplier}`
- Vínculo em massa assíncrono (Redis + Jobs):
  - `POST /api/v1/products/{product}/suppliers/bulk-link`
  - `POST /api/v1/products/{product}/suppliers/bulk-unlink`
  - `GET /api/v1/batch-operations/{id}` para acompanhar status (`pending`, `processing`, `done`, `failed`)

### 4) Pedidos
- Telas:
  - `/pedidos` (listagem + criação)
  - `/pedidos/{id}` (detalhes, status e itens)
- API:
  - `GET /api/v1/orders`
  - `POST /api/v1/orders`
  - `GET /api/v1/orders/{id}`
  - `PUT /api/v1/orders/{id}`
  - `PATCH /api/v1/orders/{id}/status`
  - `POST /api/v1/orders/{id}/items`
  - `DELETE /api/v1/orders/{id}/items/{item}`

## Regras de negócio implementadas

- Pedido vinculado a apenas 1 fornecedor.
- Não permite criar pedido para fornecedor inativo.
- Itens aceitam apenas produtos ativos.
- Produto do item precisa estar vinculado ao fornecedor do pedido.
- Pedido concluído não pode ser editado.
- Para concluir pedido, precisa haver ao menos 1 item.
- Total do pedido é recalculado após inclusão/remoção de itens.

## Teste manual sugerido

1. Cadastrar fornecedores e produtos.
2. Vincular produto x fornecedor (individual e em massa).
3. Validar status de operação em massa na tela de vínculos.
4. Criar pedido para fornecedor ativo.
5. Adicionar item com produto vinculado ao fornecedor.
6. Tentar adicionar item não vinculado (deve falhar).
7. Alterar status para concluído sem itens (deve falhar).
8. Concluir pedido com itens e tentar editar novamente (deve falhar).

## Observações

- O arquivo `DECISOES.md` contém as decisões de modelagem e arquitetura.
- O projeto não possui suíte de testes automatizados completa; a validação principal foi manual.
