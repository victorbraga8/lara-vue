# 📦 Sistema de Gestão de Produtos, Compras e Vendas

Este projeto foi desenvolvido com **Laravel** (backend/API) e **Vue.js** (frontend) para oferecer uma solução estável e escalável de controle de estoque, compras e vendas.

---

## 🚀 Tecnologias

- **Backend:** Laravel, Eloquent ORM, MySQL/PostgreSQL
- **Frontend:** Vue, Vite, Vue Query, TailwindCSS, shadcn-vue
- **Infraestrutura:** Deploy Back-End em Hostinger e Front-End Vercel

---

## 📌 Funcionalidades

### Produtos
- Cadastro de produtos com nome e preço sugerido
- Listagem de produtos com custo médio, preço e estoque atual

### Compras
- Registro de compras com fornecedor, produtos, quantidades e preço unitário
- Atualização automática de estoque
- Recalculo do custo médio de cada produto

### Vendas
- Registro de vendas com cliente, produtos, quantidades e preço unitário
- Validação de estoque disponível
- Saída de estoque após venda
- Cálculo de receita e lucro
- Cancelamento de venda (opcional), revertendo estoque

---

## 🔗 Endpoints Principais

### Produtos
- `POST /api/produtos` → cadastrar produto
- `GET /api/produtos` → listar produtos

### Compras
- `POST /api/compras` → registrar compra

### Vendas
- `POST /api/vendas` → registrar venda
- `DELETE /api/vendas/{id}` → cancelar venda 

---

## 💻 Telas no Frontend (Vue)

- **Cadastro de Produto** → formulário + listagem
- **Cadastro de Compra** → formulário com produtos, quantidades e preço
- **Cadastro de Venda** → seleção de produtos e cálculo de lucro
- **Listagens** → visão geral de compras e vendas registradas
- **Feedback ao usuário** → mensagens de sucesso/erro (ex: “Estoque insuficiente”)

---

## ⚡ Diferenciais

- Estrutura mobile-first
- Cache e sincronização automática com Vue Query
- UI responsiva e moderna
- Fácil manutenção e escalabilidade

---

---

## ⚙️ Melhorias Imediatas/Sugeridas

- Componentização/Reutilização de itens
- Centralização de Funções e Métodos (Arquitetura useCases)
- Centratalização de Query Client
- Criação de layers complementares (Types - Interfaces - Providers)

---


## 📦 Instalação

### Backend (Laravel)
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
