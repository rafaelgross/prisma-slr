# PRISMA-SLR

> Sistema web gratuito e open-source para condução de Revisões Sistemáticas da Literatura com base no protocolo **PRISMA 2020**.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)
![PRISMA](https://img.shields.io/badge/Protocolo-PRISMA%202020-6c63ff)

---

## Sobre o projeto

O **PRISMA-SLR** é uma plataforma completa para gerenciar todas as etapas de uma revisão sistemática da literatura, desde a importação de referências bibliográficas até a geração do diagrama PRISMA 2020 e exportação de referências formatadas. Foi desenvolvido para rodar em qualquer servidor web com PHP e MySQL, sem dependências externas pagas.

O sistema suporta múltiplos usuários via login com conta Google, cada um com seus próprios projetos e dados isolados.

---

## Funcionalidades

- **Gestão de projetos** — crie e gerencie múltiplas revisões sistemáticas
- **Importação de referências** — suporte a BibTeX (`.bib`), RIS (`.ris`), PubMed XML (`.xml`) e arquivos de texto (`.txt`)
- **Deduplicação automática** — identifica e marca artigos duplicados entre bases
- **Triagem de títulos e resumos** — interface para incluir/excluir artigos com motivos configuráveis
- **Elegibilidade em texto completo** — segunda fase de avaliação com motivos personalizados
- **Diagrama PRISMA 2020** — geração automática do fluxograma SVG com exportação em PNG e SVG
- **Bibliometria** — gráficos de publicações por ano, periódico, autor, país e mapa de coautoria
- **Checklist PRISMA 2020** — todos os 27 itens obrigatórios com controle de cumprimento
- **Referências formatadas** — geração automática em **ABNT NBR 6023:2018** e **APA 7ª edição**
- **Exportação** — exporta artigos selecionados em CSV, BibTeX e JSON
- **Multi-idioma** — interface traduzível via gtranslate.io (PT / EN / ES)
- **Tema claro/escuro** — alternância de tema salva por usuário
- **Autenticação Google OAuth 2.0** — login seguro com conta Google; cada usuário tem seus projetos isolados

---

## Bases bibliográficas suportadas

| Base | Formato de exportação |
|---|---|
| Scopus | BibTeX, RIS |
| Web of Science | BibTeX, RIS |
| PubMed | XML, RIS |
| Embase | RIS |
| Mendeley / Zotero / EndNote | RIS, BibTeX |
| IEEE Xplore | BibTeX |
| Outras | BibTeX, RIS |

---

## Tecnologias

- **Back-end:** PHP 8.1+ (sem frameworks)
- **Banco de dados:** MySQL 5.7+ / MariaDB 10.4+
- **Front-end:** HTML5, CSS3, JavaScript puro
- **Bibliotecas JS:** Chart.js, vis.js Network, WordCloud2.js, FileSaver.js
- **Autenticação:** Google OAuth 2.0

---

## Requisitos

- PHP 8.1 ou superior com extensões: `pdo_mysql`, `curl`, `simplexml`, `mbstring`
- MySQL 5.7+ ou MariaDB 10.4+
- Servidor web: Apache ou Nginx (ou MAMP/XAMPP para uso local)
- Conta no [Google Cloud Console](https://console.cloud.google.com/) para configurar o OAuth

---

## Instalação

### 1. Clone o repositório

```bash
git clone https://github.com/SEU_USUARIO/prisma-slr.git
cd prisma-slr
```

### 2. Configure o banco de dados

Crie o banco e execute os scripts SQL na ordem:

```bash
mysql -u root -p < sql/schema.sql
mysql -u root -p prisma_slr < sql/add_users_table.sql
mysql -u root -p prisma_slr < sql/add_user_id_to_projects.sql
```

### 3. Configure a conexão com o banco

Edite o arquivo `config/database.php` com suas credenciais:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');   // 8889 para MAMP
define('DB_NAME', 'prisma_slr');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
```

### 4. Configure o Google OAuth 2.0

#### 4.1 Crie as credenciais no Google Cloud Console

1. Acesse [console.cloud.google.com](https://console.cloud.google.com)
2. Crie ou selecione um projeto
3. Vá em **APIs e serviços** → **Credenciais** → **+ Criar Credenciais** → **ID do cliente OAuth 2.0**
4. Tipo de aplicativo: **Aplicativo Web**
5. Em **URIs de redirecionamento autorizados**, adicione:
   - `http://localhost/prisma-slr/auth/callback.php` (ambiente local)
   - `https://seudominio.com/prisma-slr/auth/callback.php` (produção)
6. Copie o **ID do cliente** e o **Segredo do cliente**

#### 4.2 Cole as credenciais no sistema

Edite `config/auth.php`:

```php
define('GOOGLE_CLIENT_ID',     'SEU_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'SEU_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI',  'http://localhost/prisma-slr/auth/callback.php');
```

### 5. Acesse o sistema

Aponte seu servidor web para a pasta do projeto e acesse pelo navegador. Na primeira vez, você será redirecionado para a tela de login com Google.

> **Primeiro acesso:** o usuário que fizer login primeiro terá todos os projetos existentes no banco atribuídos automaticamente à sua conta.

---

## Uso local com MAMP (macOS)

1. Copie a pasta `prisma-slr` para `/Applications/MAMP/htdocs/`
2. Inicie o MAMP e acesse `http://localhost/prisma-slr/`
3. Use o phpMyAdmin (`http://localhost:8888/phpMyAdmin`) para executar os scripts SQL
4. Configure `DB_PORT` como `8889` em `config/database.php`

---

## Estrutura do projeto

```
prisma-slr/
├── api/                 # Endpoints REST (PHP)
│   ├── articles.php
│   ├── bibliometrics.php
│   ├── duplicates.php
│   ├── export.php
│   ├── import.php
│   ├── prisma-flow.php
│   ├── projects.php
│   ├── references.php
│   └── screening.php
├── assets/
│   ├── css/style.css    # Estilos globais
│   ├── js/              # Scripts por página
│   └── img/             # Logo e ícones
├── auth/                # Fluxo OAuth 2.0
│   ├── callback.php
│   ├── google.php
│   └── logout.php
├── config/
│   ├── auth.php         # Configuração OAuth e helpers de sessão
│   └── database.php     # Conexão PDO
├── lib/                 # Parsers de referência
│   ├── BibTexParser.php
│   ├── RisParser.php
│   └── PubMedXmlParser.php
├── pages/               # Views PHP por página
├── sql/                 # Scripts de banco de dados
│   ├── schema.sql
│   ├── add_users_table.sql
│   └── add_user_id_to_projects.sql
├── index.php            # Roteador principal
└── login.php            # Página de login
```

---

## Multi-usuário

O sistema suporta múltiplos pesquisadores de forma isolada:

- Cada usuário faz login com sua própria conta Google
- Cada um vê e gerencia apenas seus próprios projetos
- Novos usuários começam com a lista de projetos vazia
- Não há limite de usuários

---

## Segurança

- Autenticação via Google OAuth 2.0 (sem senhas armazenadas localmente)
- Proteção CSRF com state token em todos os fluxos OAuth
- Sessões com expiração automática (8 horas)
- Queries parametrizadas via PDO em todos os endpoints
- Cada usuário só acessa projetos de sua propriedade

---

## Licença

Distribuído sob a licença **MIT**. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

---

## Contribuindo

Contribuições são bem-vindas! Para contribuir:

1. Faça um fork do projeto
2. Crie uma branch para sua feature (`git checkout -b feature/minha-feature`)
3. Faça commit das suas alterações (`git commit -m 'feat: adiciona minha feature'`)
4. Faça push para a branch (`git push origin feature/minha-feature`)
5. Abra um Pull Request

---

## Citação

Se você usar este sistema em sua pesquisa, considere citar:

```
GROSS, R. PRISMA-SLR: Sistema web open-source para revisões sistemáticas
baseado no protocolo PRISMA 2020. GitHub, 2026.
Disponível em: https://github.com/SEU_USUARIO/prisma-slr
```

---

## Referências

- Page, M. J. et al. *The PRISMA 2020 statement: an updated guideline for reporting systematic reviews.* BMJ, 2021. [doi:10.1136/bmj.n71](https://doi.org/10.1136/bmj.n71)
- ABNT NBR 6023:2018 — Informação e documentação: Referências
- APA 7ª edição — Publication Manual of the American Psychological Association
