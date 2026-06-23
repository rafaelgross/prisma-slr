# PRISMA-SLR

> Free, open-source web system for conducting Systematic Literature Reviews based on the **PRISMA 2020** protocol.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)
![PRISMA](https://img.shields.io/badge/Protocol-PRISMA%202020-6c63ff)

---

## About the project

**PRISMA-SLR** is a complete platform for managing every stage of a systematic literature review, from importing bibliographic references to generating the PRISMA 2020 flow diagram and exporting formatted references. It is built to run on any web server with PHP and MySQL, with no paid external dependencies.

The system supports multiple users via Google account login, each with their own projects and isolated data.

---

## Features

- **Project management** — create and manage multiple systematic reviews
- **Reference import** — supports BibTeX (`.bib`), RIS (`.ris`), PubMed XML (`.xml`) and plain text (`.txt`)
- **Automatic deduplication** — detects and flags duplicate articles across databases
- **Title and abstract screening** — interface to include/exclude articles with configurable reasons
- **Full-text eligibility** — second assessment phase with customizable reasons
- **PRISMA 2020 flow diagram** — automatic SVG flowchart generation with PNG and SVG export
- **Bibliometrics** — charts of publications by year, journal, author, country, and co-authorship maps
- **PRISMA 2020 checklist** — all 27 mandatory items with compliance tracking
- **Formatted references** — automatic generation in **ABNT NBR 6023:2018** and **APA 7th edition**
- **Export** — export selected articles to CSV, BibTeX and JSON
- **Multi-language** — interface translatable via gtranslate.io (PT / EN / ES)
- **Light/dark theme** — per-user theme toggle
- **Google OAuth 2.0 authentication** — secure login with a Google account; each user has isolated projects

---

## Supported bibliographic databases

| Database | Export format |
| --- | --- |
| Scopus | BibTeX, RIS |
| Web of Science | BibTeX, RIS |
| PubMed | XML, RIS |
| Embase | RIS |
| Mendeley / Zotero / EndNote | RIS, BibTeX |
| IEEE Xplore | BibTeX |
| Others | BibTeX, RIS |

---

## Technologies

- **Back-end:** PHP 8.1+ (no frameworks)
- **Database:** MySQL 5.7+ / MariaDB 10.4+
- **Front-end:** HTML5, CSS3, vanilla JavaScript
- **JS libraries:** Chart.js, vis.js Network, WordCloud2.js, FileSaver.js
- **Authentication:** Google OAuth 2.0

---

## Requirements

- PHP 8.1 or higher with extensions: `pdo_mysql`, `curl`, `simplexml`, `mbstring`
- MySQL 5.7+ or MariaDB 10.4+
- Web server: Apache or Nginx (or MAMP/XAMPP for local use)
- A [Google Cloud Console](https://console.cloud.google.com/) account to configure OAuth

---

## Installation

### 1. Clone the repository

```
git clone https://github.com/rafaelgross/prisma-slr.git
cd prisma-slr
```

### 2. Set up the database

Create the database and run the SQL scripts in order:

```
mysql -u root -p < sql/schema.sql
mysql -u root -p prisma_slr < sql/add_users_table.sql
mysql -u root -p prisma_slr < sql/add_user_id_to_projects.sql
```

### 3. Configure the database connection

Edit `config/database.php` with your credentials:

```
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');   // 8889 for MAMP
define('DB_NAME', 'prisma_slr');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
```

### 4. Configure Google OAuth 2.0

#### 4.1 Create credentials in the Google Cloud Console

1. Go to [console.cloud.google.com](https://console.cloud.google.com)
2. Create or select a project
3. Go to **APIs & Services** → **Credentials** → **+ Create Credentials** → **OAuth 2.0 Client ID**
4. Application type: **Web application**
5. Under **Authorized redirect URIs**, add:
   - `http://localhost/prisma-slr/auth/callback.php` (local environment)
   - `https://yourdomain.com/prisma-slr/auth/callback.php` (production)
6. Copy the **Client ID** and the **Client Secret**

#### 4.2 Paste the credentials into the system

Edit `config/auth.php`:

```
define('GOOGLE_CLIENT_ID',     'YOUR_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI',  'http://localhost/prisma-slr/auth/callback.php');
```

### 5. Configure abstract translation (optional)

The **Translate** button in screening uses the free [MyMemory](https://mymemory.translated.net/) API. Without an email the limit is 1,000 words/day; with a registered email it rises to 10,000 words/day.

Add to the end of your `config/database.php`:

```
// Email to raise the translation limit (free at mymemory.translated.net)
define('MYMEMORY_EMAIL', 'you@email.com');
```

> This file is in `.gitignore` — your email is never pushed to the repository.

### 6. Access the system

Point your web server to the project folder and open it in the browser. On first launch you will be redirected to the Google login screen.

> **First access:** the first user to log in will have all existing projects in the database automatically assigned to their account.

---

## Local use with MAMP (macOS)

1. Copy the `prisma-slr` folder to `/Applications/MAMP/htdocs/`
2. Start MAMP and open `http://localhost/prisma-slr/`
3. Use phpMyAdmin (`http://localhost:8888/phpMyAdmin`) to run the SQL scripts
4. Set `DB_PORT` to `8889` in `config/database.php`

---

## Project structure

```
prisma-slr/
├── api/                 # REST endpoints (PHP)
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
│   ├── css/style.css    # Global styles
│   ├── js/              # Per-page scripts
│   └── img/             # Logo and icons
├── auth/                # OAuth 2.0 flow
│   ├── callback.php
│   ├── google.php
│   └── logout.php
├── config/
│   ├── auth.php         # OAuth config and session helpers
│   └── database.php     # PDO connection
├── lib/                 # Reference parsers
│   ├── BibTexParser.php
│   ├── RisParser.php
│   └── PubMedXmlParser.php
├── pages/               # PHP views per page
├── sql/                 # Database scripts
│   ├── schema.sql
│   ├── add_users_table.sql
│   └── add_user_id_to_projects.sql
├── index.php            # Main router
└── login.php            # Login page
```

---

## Multi-user

The system supports multiple researchers in isolation:

- Each user logs in with their own Google account
- Each user only sees and manages their own projects
- New users start with an empty project list
- No user limit

---

## Security

- Authentication via Google OAuth 2.0 (no passwords stored locally)
- CSRF protection with a state token in all OAuth flows
- Sessions with automatic expiration (8 hours)
- Parameterized queries via PDO in all endpoints
- Each user can only access projects they own

---

## License

Distributed under the **MIT** license. See the [LICENSE](https://github.com/rafaelgross/prisma-slr/blob/main/LICENSE) file for details.

---

## Contributing

Contributions are welcome! To contribute:

1. Fork the project
2. Create a branch for your feature (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -m 'feat: add my feature'`)
4. Push to the branch (`git push origin feature/my-feature`)
5. Open a Pull Request

---

## Citation

If you use this system in your research, please consider citing:

```
GROSS, R. PRISMA-SLR: Open-source web system for systematic literature reviews
based on the PRISMA 2020 protocol. GitHub, 2026.
Available at: https://github.com/rafaelgross/prisma-slr
```

---

## References

- Page, M. J. et al. *The PRISMA 2020 statement: an updated guideline for reporting systematic reviews.* BMJ, 2021. [doi:10.1136/bmj.n71](https://doi.org/10.1136/bmj.n71)
- ABNT NBR 6023:2018 — Information and documentation: References
- APA 7th edition — Publication Manual of the American Psychological Association
