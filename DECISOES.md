# DECISOES

## Modelagem do banco de dados

### Tabela `suppliers`
- Campos: `name`, `cnpj`, `email`, `phone`, `status`.
- `cnpj` e `email` possuem `unique` para evitar duplicidade.
- `status` como `varchar(50)` indexado (evitando `enum` para melhor manutencao).

### Tabela `products`
- Campos: `name`, `description`, `internal_code`, `status`.
- `internal_code` possui `unique` para identificacao interna confiavel.
- `status` como `varchar(50)` indexado.

### Tabela `product_supplier`
- Relacao N:N entre produtos e fornecedores.
- FKs com `cascadeOnDelete`.
- `unique(product_id, supplier_id)` para impedir vinculos duplicados.

### Tabela `orders`
- Campos: `supplier_id`, `order_date`, `status`, `notes`, `total_amount`.
- FK de fornecedor com `restrictOnDelete` para preservar historico.
- `total_amount` persistido para leitura rapida e recalculado pela regra de negocio.

### Tabela `order_items`
- Campos: `order_id`, `product_id`, `quantity`, `unit_price`, `total_price`.
- `total_price` armazenado para auditoria e performance de consulta.

### Tabela `batch_operations`
- Controle de operacoes em massa assincronas.
- Campos: `type`, `product_id`, `payload`, `status`, `error_message`, `processed_at`.
- `type` e `status` como `varchar(50)` para flexibilidade de evolucao.

## Decisoes de arquitetura
- Arquitetura MVC com camada de Services para concentrar regras de negocio.
- Controllers finos: validam entrada (FormRequest), delegam para Services e retornam resposta HTTP/JSON.
- Excecoes de negocio padronizadas com `BusinessRuleException`.
- Models com `protected $table` explicito para evitar ambiguidades.

## Uso de filas e Jobs
- Vinculo e desvinculo em massa sao processados de forma assincrona com Redis.
- Jobs implementados:
  - `BulkLinkSuppliersToProductJob`
  - `BulkUnlinkSuppliersFromProductJob`
- A API responde `202 Accepted` ao enfileirar operacoes em massa.
- A tabela `batch_operations` armazena o progresso (`pending`, `processing`, `done`, `failed`).
- Endpoint de status: `GET /api/v1/batch-operations/{id}`.
- Worker local: `php artisan queue:work redis`.

## Desafios de criatividade escolhidos
- Opcao A (Experiencia do usuario):
  - Filtro de pedidos por status e fornecedor.
  - Busca e filtros nas telas de produtos, fornecedores e vinculos.
  - Interface dedicada para vinculo/desvinculo em massa com feedback de processamento.

- Opcao B (Regra de negocio):
  - Bloqueio de criacao de pedido para fornecedor inativo.
  - Bloqueio de edicao de pedido concluido (com mudanca de status tratada em endpoint dedicado).
  
- Opcao C (Organizacao tecnica):
    - Uso de Services para encapsular regras de negocio (OrderService, ProductSupplierService, etc.).
    - Controllers focados em orquestracao HTTP e validacao.
    - Melhor separacao de responsabilidades e reaproveitamento de codigo.

## Uso de IA
- IA foi usada para acelerar tarefas repetitivas (estrutura inicial e refactors).
- Todas as sugestoes foram revisadas, adaptadas e validadas manualmente.
- As decisoes finais de modelagem e implementacao foram tomadas com base no contexto do teste.

## O que melhoraria com mais tempo
- Testes automatizados (Feature e Unit) cobrindo regras criticas de pedidos e vinculos.
- Historico de alteracao de status dos pedidos.
- Politicas de autorizacao (Policies/Gates) por perfil.
- Observabilidade da fila (logs estruturados e metricas de processamento).
