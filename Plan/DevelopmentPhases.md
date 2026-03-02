# PostgreManager — Development Phases

## Phase 1: Foundation & Setup
- Project scaffolding (backend + frontend structure)
- Database schema for app users, sessions, server profiles
- Initial Setup Wizard (PG server config + first Super Admin creation)
- Basic login / logout / session management

---

## Phase 2: Core App User Management
- App user CRUD (Super Admin only)
- Role-based access control (Super Admin, Admin, Viewer)
- Password change & profile page
- Server connection profiles (add / edit / delete / test)

---

## Phase 3: Dashboard & Database Management
- Dashboard overview (connections, uptime, DB count/size)
- List, create, rename, drop databases
- Export (SQL dump) & import (SQL file upload)
- Set connection limits per database

---

## Phase 4: PostgreSQL User & Role Management
- List PG users/roles with attributes
- Create / edit / drop PG user or role
- Assign/revoke privileges per database
- Toggle superuser / login / replication flags

---

## Phase 5: Table, Schema & Data Browser
- Browse schemas and tables
- View table structure (columns, types, constraints)
- Create / alter / drop table, manage indexes & foreign keys
- Paginated data view with filter, sort, search
- Inline row edit, insert, delete
- Export table data (CSV, JSON)

---

## Phase 6: Query Editor
- SQL editor with syntax highlighting
- Execute queries & display result grid
- Query history & save named queries

---

## Phase 7: Statistics & Monitoring
- Database size chart
- Active / long-running queries viewer
- Cache hit ratio, index usage stats
- Locks viewer
- Table row counts & bloat info

---

## Phase 8: Backup & Maintenance
- On-demand backup trigger & download
- Run VACUUM / REINDEX / CLUSTER / VACUUM FULL
- View PostgreSQL logs

---

## Phase 9: Polish & Settings
- UI theme (light / dark)
- Query timeout & row limit preferences
- General UX improvements, error handling, loading states
- Security review & hardening
