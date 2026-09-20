<div align="center">

# 🚗 MyCar API

### API REST para gestão de oficina mecânica

Clientes, veículos, ordens de serviço e manutenções.

![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-003B57?style=for-the-badge&logo=sqlite&logoColor=white)
![PHPUnit](https://img.shields.io/badge/PHPUnit-tests-366488?style=for-the-badge&logo=php&logoColor=white)

**Backend** · [Ver frontend (React)](https://github.com/joaoalexandre2/mycar_frontend)

</div>

---

## 🧩 Modelo de dados

```mermaid
erDiagram
    CLIENTE ||--o{ VEICULO : possui
    VEICULO ||--o{ ORDEM_SERVICO : recebe
    VEICULO ||--o{ MANUTENCAO : tem

    CLIENTE {
        string nome
        string cpf UK
        string telefone UK
        boolean ativo
    }
    VEICULO {
        string placa UK
        string marca
        string modelo
        year ano
    }
    ORDEM_SERVICO {
        text descricao
        string status
        decimal valor
        date data_abertura
        datetime data_fechamento
    }
    MANUTENCAO {
        string tipo
        text descricao
        decimal valor
        date data_manutencao
        int quilometragem
        date proxima_data
        int proxima_quilometragem
    }
```

> 🗑️ Ao excluir um cliente, seus veículos são removidos; ao excluir um veículo, suas ordens de serviço e manutenções também (cascade).

## 🔐 Autenticação

```mermaid
sequenceDiagram
    participant C as Cliente
    participant A as API
    C->>A: POST /api/login
    A-->>C: token
    C->>A: GET /api/clientes (com token)
    A-->>C: 200 OK
    C->>A: POST /api/logout
    A-->>C: token invalidado
```

Todas as rotas, exceto o login, exigem token.

## 🛣️ Endpoints

| Recurso | Método | Rota |
|---|---|---|
| 🔐 Auth | `POST` | `/api/login` |
| 🔐 Auth | `POST` | `/api/logout` |
| 🔐 Auth | `GET` | `/api/me` |
| 👥 Clientes | `GET` `POST` | `/api/clientes` |
| 👥 Clientes | `GET` `PUT` `DELETE` | `/api/clientes/{id}` |
| 🚙 Veículos | `GET` `POST` | `/api/veiculos` |
| 🚙 Veículos | `GET` `PUT` `DELETE` | `/api/veiculos/{id}` |
| 🧾 Ordens de serviço | `GET` `POST` | `/api/ordens-servico` |
| 🧾 Ordens de serviço | `GET` `PUT` `DELETE` | `/api/ordens-servico/{id}` |
| 🔧 Manutenções | `GET` `POST` | `/api/manutencoes` |
| 🔧 Manutenções | `GET` `PUT` `DELETE` | `/api/manutencoes/{id}` |

## 🚀 Começando

### Pré-requisitos

- PHP 8.2+
- Composer
- Node.js (para o build de assets)

### Instalação

```bash
git clone https://github.com/joaoalexandre2/mycar.git
cd mycar
composer setup
```

O `setup` instala as dependências, cria o `.env`, gera a chave, roda as migrations e faz o build.

<details>
<summary>Instalação manual</summary>

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
```

</details>

### Executando

```bash
php artisan serve
```

A API fica em `http://localhost:8000/api`. Para subir servidor, fila, logs e Vite juntos:

```bash
composer dev
```

## 🧪 Testes

```bash
composer test
```

## 🔗 Frontend

O front-end React está em [joaoalexandre2/mycar_frontend](https://github.com/joaoalexandre2/mycar_frontend).

## 📄 Licença

MIT

---

<div align="center">
Feito com ☕ por <a href="https://github.com/joaoalexandre2">João Alexandre</a>
</div>
