# PostgreManager — Tech Stack

## Backend
- **Runtime**: PHP 8.4
- **Framework**: FlightPHP (micro-framework)
- **Database (app data)**: PostgreSQL
- **Auth**: Session-based with CSRF protection
- **PG Interaction**: PDO with pgsql driver
- **Code Style**: OOP, PSR-4 autoloading, PSR-12 coding standard
- **Namespaces**: `PostgreManager\` root namespace
- **Templating**: Twig

---

## Frontend
- **UI Framework**: AdminLTE 3.x
- **Base**: Bootstrap 4 (bundled with AdminLTE)
- **Scripting**: Vanilla JavaScript (ES6+)
- **Charts**: Chart.js (bundled with AdminLTE)
- **SQL Editor**: CodeMirror

---

## Infrastructure
- **Web Server**: Nginx + PHP-FPM
- **Containerization**: Docker (optional)
- **Deployment**: Self-hosted

---

## Development Tools
- **Dependency Manager**: Composer (PSR-4 autoload)
- **Version Control**: Git
