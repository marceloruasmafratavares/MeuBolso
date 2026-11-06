# 💰 MeuBolso

Aplicativo web simples para **controle de orçamento doméstico**: registre seus gastos em poucos toques, organize por categorias, acompanhe o mês e receba alertas ao se aproximar (ou ultrapassar) o limite que você definiu.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?logo=javascript&logoColor=black)
![Responsivo](https://img.shields.io/badge/Layout-Responsivo%20(mobile--first)-1D9E75)

> 🤖 **Aviso:** este projeto foi desenvolvido com o **auxílio de inteligência artificial** (Claude, da Anthropic). A IA contribuiu na geração e revisão de código, na arquitetura e na documentação; os requisitos, as decisões de produto, os testes e o feedback de usabilidade foram conduzidos pelo autor.

---

## 📋 Sumário

- [Sobre o projeto](#-sobre-o-projeto)
- [Funcionalidades](#-funcionalidades)
- [Tecnologias](#-tecnologias)
- [Instalação](#-instalação)
- [Conta de demonstração](#-conta-de-demonstração)
- [Recuperação de senha](#-recuperação-de-senha)
- [Uso no celular](#-uso-no-celular)
- [Estrutura do projeto](#-estrutura-do-projeto)
- [Banco de dados](#-banco-de-dados)
- [API](#-api)
- [Segurança](#-segurança)
- [Melhorias futuras](#-melhorias-futuras)

---

## 🎯 Sobre o projeto

O foco do sistema é ser **fácil de usar**, ter **interface simples** e **informações claras**, pensado para quem nunca usou um app financeiro.

**Público-alvo:** pessoas que querem controlar gastos básicos — estudantes, trabalhadores informais, jovens e adultos com pouca organização financeira e conhecimento tecnológico baixo ou médio.

**Personas que guiaram o design**

| | Ana Souza, 22 anos (estudante) | Carlos Oliveira, 38 anos (motorista) |
|---|---|---|
| **Objetivo** | Controlar os gastos mensais | Não ultrapassar o orçamento do mês |
| **Dores** | Não sabe para onde o dinheiro vai; esquece despesas pequenas | Gasta sem perceber; não usa apps complicados |
| **Uso** | Todo dia, para gastos pequenos (lanche, transporte) | Gastos principais + consulta do saldo |

**Fluxo principal:** abrir o app → *Adicionar despesa* → valor → categoria → salvar → o sistema atualiza o saldo, mostra o resumo do mês, verifica o limite e exibe um alerta se necessário.

---

## ✨ Funcionalidades

**Controle de gastos**
- Registro rápido de despesas (valor, descrição, data e categoria em grade visual)
- Edição e exclusão com confirmação
- 8 categorias com ícone e cor (Alimentação, Transporte, Moradia, Saúde, Educação, Lazer, Mercado, Outros)
- Histórico completo com filtro por mês, ano e categoria

**Orçamento**
- **Orçamento padrão:** defina renda e limite uma vez e ele vale para todos os meses
- **Exceção mensal:** num mês diferente do normal, defina um valor específico; no mês seguinte volta automaticamente ao padrão (e dá para remover a exceção)
- **Receitas extras:** registre dinheiro que não é salário (freelance, vendas, presentes), somado à renda do mês
- **Alertas:** aviso ao atingir 80% do limite e alerta vermelho ao ultrapassá-lo

**Dashboard**
- Saldo disponível, total gasto, limite, renda total e receitas extras
- Barra de progresso do limite
- Gráfico de rosca por categoria e gráfico de evolução acumulada do mês
- Navegação entre meses e atalho para o histórico completo

**Conta e experiência**
- Cadastro/login com sessão segura
- **Tutorial inicial** (onboarding) que guia o novo usuário a definir o orçamento
- **Recuperação de senha por chave de recuperação** (sem depender de e-mail) — veja [abaixo](#-recuperação-de-senha)
- Tela *Minha conta*: gerar nova chave e alterar senha
- Mensagens de confirmação (toasts) a cada ação
- **Layout responsivo** com navegação inferior no celular

---

## 🛠 Tecnologias

| Camada | Tecnologia |
|---|---|
| Front-end | HTML5, CSS3 (variáveis, Grid, Flexbox), JavaScript puro (ES6+, sem frameworks) |
| Gráficos | [Chart.js 4](https://www.chartjs.org/) (via CDN) |
| Back-end | PHP 8+ (API REST em arquivo único, PDO) |
| Banco de dados | MySQL 5.7+ / MariaDB 10.3+ |
| Fonte | Nunito (Google Fonts) |

Arquitetura: **SPA simples** — `index.php` entrega a página, `app.js` troca as telas e conversa com `api.php` via `fetch` (JSON).

---

## 🚀 Instalação

### Pré-requisitos
- PHP 8.0 ou superior, com a extensão `pdo_mysql`
- MySQL 5.7+ ou MariaDB 10.3+
- Um servidor local (XAMPP, Laragon, WAMP…) **ou** o servidor embutido do PHP

### Passo a passo

**1. Clone o repositório**
```bash
git clone https://github.com/marceloruasmafratavares/meubolso.git
cd meubolso
```

**2. Crie o banco de dados**
```bash
mysql -u root -p < database.sql
```
Isso cria o banco `meubolso`, as tabelas e as categorias padrão.

**3. Configure as credenciais**

Copie o arquivo de exemplo e preencha com os dados do **seu** MySQL:
```bash
cp config.local.example.php config.local.php
```
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'meubolso');
define('DB_USER', 'root');
define('DB_PASS', 'sua-senha-aqui');
```
> 🔒 O `config.local.php` está no `.gitignore` e **nunca** vai para o GitHub.

**4. Rode o projeto**

*Opção A — servidor embutido do PHP (mais rápido):*
```bash
php -S localhost:8000
```
Acesse **http://localhost:8000**

*Opção B — XAMPP/Laragon:* copie a pasta para `htdocs/` (ou `www/`) e acesse `http://localhost/meubolso`.

---

## 🧪 Conta de demonstração

Para ver o sistema funcionando com dados reais ao longo do tempo, rode **uma vez**:

```bash
php setup_demo.php
```
(ou abra `http://localhost:8000/setup_demo.php` no navegador — só funciona em localhost)

| | |
|---|---|
| **E-mail** | `demo@meubolso.com` |
| **Senha** | `demo123` |
| **Chave de recuperação** | `DEMO-DEMO-DEMO` |

A conta vem com **6 meses de histórico**: ~100 despesas, receitas extras, orçamento padrão (renda R$ 4.800 / limite R$ 4.000) e uma **exceção mensal** (um mês de viagem com limite maior). Os meses variam de propósito para demonstrar os alertas: um estoura o limite, vários ficam perto dele (80–90%) e o mês atual está só no começo. As datas são geradas **a partir de hoje**, então o dashboard já abre com dados no mês atual. O script pode ser executado de novo para recriar a conta.

> Em servidor público, **apague** o `setup_demo.php`.

---

## 🔑 Recuperação de senha

Como o sistema não valida e-mails (não há como garantir que o endereço cadastrado é real), a recuperação **não depende de e-mail**. Ela usa uma **chave de recuperação**:

1. **Ao criar a conta**, o sistema gera uma chave no formato `XXXX-XXXX-XXXX` e a exibe **uma única vez**, com botões para *copiar* e *baixar .txt*. O usuário só continua depois de confirmar que a guardou.
2. **Se esquecer a senha**, clica em *Esqueci minha senha* e informa: e-mail da conta + chave + nova senha.
3. **Depois de usada, a chave é trocada por uma nova** (a antiga deixa de valer) e exibida de novo.
4. Em **Minha conta** é possível gerar uma nova chave a qualquer momento (exige a senha atual) — útil para contas antigas, criadas antes desse recurso.

**Proteções**
- No banco fica só o **hash** da chave (bcrypt), como uma senha — nunca o texto puro
- 12 caracteres de um alfabeto sem letras ambíguas (sem `I`, `O`, `0`, `1`) ≈ 60 bits de entropia
- **5 tentativas erradas bloqueiam a recuperação por 15 minutos**
- A mensagem de erro é genérica ("e-mail ou chave incorretos"), sem revelar se o e-mail existe

**Perdeu a senha *e* a chave?** O administrador do sistema pode redefinir pelo terminal:
```bash
php tools/reset_senha.php email@exemplo.com NovaSenha123
```
O script só roda via linha de comando e gera uma nova chave para o usuário.

---

## 📱 Uso no celular

O layout é totalmente responsivo e **nenhuma função some no celular**:

- **Barra de navegação inferior** (Início, Histórico, Orçamento, Conta) com botão **+** central para adicionar despesa com um toque
- Saldo disponível em destaque no topo do dashboard; cards em grade de 2 colunas
- A **tabela do histórico vira uma lista de cards** (descrição, valor, categoria, data e ações)
- Modais em formato *bottom sheet*, botões com alvos de toque amplos (~44 px) e teclado numérico nos campos de valor
- Campos com 16 px (evita o zoom automático do iOS), suporte a *notch* / áreas seguras e respeito a `prefers-reduced-motion`

**Testando no seu celular (mesma rede Wi-Fi):**
```bash
php -S 0.0.0.0:8000
```
Descubra o IP do computador (`ipconfig` / `ifconfig`) e acesse `http://SEU-IP:8000` no celular.

---

## 📁 Estrutura do projeto

```
meubolso/
├── index.php                  # Página principal (HTML) + estado inicial vindo do PHP
├── api.php                    # API REST (roteador por ?action=)
├── config.php                 # Conexão PDO, sessão, helpers e funções da chave de recuperação
├── config.local.example.php   # Modelo das credenciais (copie para config.local.php)
├── config.local.php           # Suas credenciais (NÃO versionado)
├── app.js                     # Lógica do front-end (telas, fetch, gráficos)
├── style.css                  # Estilos (desktop + celular)
├── database.sql               # Criação do banco e tabelas
├── setup_demo.php             # Cria a conta de demonstração
├── tools/
│   └── reset_senha.php        # Reset de senha pelo administrador (somente terminal)
├── .gitignore
└── README.md
```

---

## 🗄 Banco de dados

| Tabela | Função |
|---|---|
| `usuarios` | Contas (senha e chave de recuperação sempre como hash; controle de tentativas) |
| `categorias` | Categorias de despesa (compartilhadas) |
| `despesas` | Gastos do usuário |
| `receitas_avulsas` | Rendas extras, fora do salário |
| `orcamento_padrao` | Renda e limite recorrentes (1 por usuário) |
| `orcamentos` | Exceção de um mês específico (sobrescreve o padrão) |

**Regra do orçamento:** para um mês, usa-se a linha de `orcamentos` se existir; caso contrário, `orcamento_padrao`.

---

## 🔌 API

Endpoint único: `api.php?action=<ação>`. Todas as respostas são JSON; erros trazem `{ "erro": "mensagem" }`. Exceto as de acesso (`registrar`, `login`, `recuperar_senha`, `sessao`, `categorias`), exigem usuário logado.

| Método | Ação | Descrição |
|---|---|---|
| POST | `registrar` | Cria conta e devolve a chave de recuperação |
| POST | `login` | Autentica |
| POST | `logout` | Encerra a sessão |
| GET | `sessao` | Dados da sessão atual |
| POST | `recuperar_senha` | Redefine a senha com e-mail + chave |
| POST | `chave_gerar` | Gera nova chave (exige senha atual) |
| POST | `senha_alterar` | Altera a senha (exige senha atual) |
| POST | `onboarding_concluir` | Conclui o tutorial e salva o orçamento padrão |
| GET | `categorias` | Lista categorias |
| GET | `despesas` | Despesas do mês (`mes`, `ano`) |
| POST | `despesa_add` | Cria despesa (devolve alerta de limite, se houver) |
| PUT | `despesa_edit` | Edita despesa (`id`) |
| DELETE | `despesa_del` | Remove despesa (`id`) |
| GET | `receitas` | Receitas extras do mês |
| POST | `receita_add` | Cria receita extra |
| DELETE | `receita_del` | Remove receita extra (`id`) |
| GET | `orcamento` | Orçamento efetivo do mês (exceção ou padrão) |
| POST | `orcamento_set` | Define exceção para um mês |
| DELETE | `orcamento_limpar` | Remove a exceção do mês |
| GET | `orcamento_padrao_get` | Lê o orçamento padrão |
| POST | `orcamento_padrao_set` | Define o orçamento padrão |
| GET | `resumo` | Totais, saldo, por categoria, evolução e alerta do mês |
| GET | `historico` | Total gasto nos últimos 12 meses |

---

## 🔒 Segurança

**O que já está implementado**
- Senhas com `password_hash` (bcrypt) e `password_verify`
- Consultas com **PDO e prepared statements** (proteção contra SQL Injection)
- Saída escapada no front-end (proteção contra XSS)
- Cookie de sessão `HttpOnly` + `SameSite=Strict` (mitiga CSRF), flag `Secure` automática em HTTPS, e `session_regenerate_id` no login
- Credenciais do banco fora do repositório (`config.local.php` no `.gitignore`)
- Recuperação de senha com hash, bloqueio por tentativas e rotação da chave
- Scripts utilitários restritos a localhost / terminal

**Limitações conhecidas (projeto educacional)**
- Não há limite de tentativas no **login** (apenas na recuperação de senha)
- O e-mail do cadastro não é verificado
- Sem token CSRF dedicado (a proteção vem do `SameSite=Strict`)
- Para uso real, publique **sempre com HTTPS**

---

## 🗺 Melhorias futuras

- [ ] Limite de tentativas também no login
- [ ] Exportar relatórios em PDF/CSV
- [ ] Despesas recorrentes (aluguel, assinaturas)
- [ ] Metas por categoria
- [ ] PWA (instalar no celular e usar offline)
- [ ] Tema escuro
- [ ] Verificação de e-mail e recuperação por e-mail (com SMTP configurado)
- [ ] Testes automatizados

---

## 👤 Autor

Desenvolvido por **Marcelo Ruas** — [github.com/marceloruasmafratavares](https://github.com/marceloruasmafratavares)

Projeto criado com o auxílio de IA (Claude — Anthropic) para fins de estudo e portfólio.
