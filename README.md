# MyCar API

API REST em Laravel para gerenciar uma oficina mecânica: clientes, veículos, ordens de serviço e manutenções.

## Tecnologias

- PHP 8.2+
- Laravel 12
- SQLite (padrão, configurável no `.env`)
- Autenticação por token (middleware `auth.token`)
- PHPUnit

## Instalação

```bash
git clone <url-do-repositorio>
cd mycar_laravel
composer setup
```

O script `setup` instala as dependências, cria o `.env`, gera a chave da aplicação, roda as migrations e faz o build do front-end.

Se preferir manualmente:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
```

## Executando

```bash
php artisan serve
```

A API fica disponível em `http://localhost:8000/api`.

Para subir servidor, fila, logs e Vite juntos:

```bash
composer dev
```

## Testes

```bash
composer test
```

## Autenticação

Faça login em `POST /api/login` para obter um token e envie-o nas demais requisições. Todas as rotas, exceto o login, exigem autenticação.

## Endpoints

| Recurso | Método | Rota |
|---|---|---|
| Auth | POST | `/api/login` |
| Auth | POST | `/api/logout` |
| Auth | GET | `/api/me` |
| Clientes | GET, POST | `/api/clientes` |
| Clientes | GET, PUT, DELETE | `/api/clientes/{id}` |
| Veículos | GET, POST | `/api/veiculos` |
| Veículos | GET, PUT, DELETE | `/api/veiculos/{id}` |
| Ordens de serviço | GET, POST | `/api/ordens-servico` |
| Ordens de serviço | GET, PUT, DELETE | `/api/ordens-servico/{id}` |
| Manutenções | GET, POST | `/api/manutencoes` |
| Manutenções | GET, PUT, DELETE | `/api/manutencoes/{id}` |

## Modelo de dados

- **Cliente**: nome, cpf (único), telefone (único), ativo
- **Veículo**: pertence a um cliente; placa (única), marca, modelo, ano
- **Ordem de serviço**: pertence a um veículo; descrição, status (padrão `aberta`), valor, data de abertura, data de fechamento
- **Manutenção**: pertence a um veículo; tipo, descrição, valor, data, quilometragem, próxima data e próxima quilometragem

Ao excluir um cliente, seus veículos são removidos; ao excluir um veículo, suas ordens de serviço e manutenções também (cascade).

## Front-end

O front-end React deste projeto fica em `mycar_frontend_react`.

## Licença

MIT
