# PostgreManager — Feature Plan

## Initial Setup Wizard (First Load)
- Detect if app is not yet configured, redirect to setup page
- Step 1: Enter PostgreSQL server details (host, port, database, credentials)
- Step 2: Test & validate the connection
- Step 3: Create the first PostgreManager app account (auto-assigned Super Admin)
- Lock setup page after completion (only accessible on factory reset)

---

## PostgreManager User Authentication
- App-level user accounts (separate from PostgreSQL users)
- Register / login / logout
- Role-based access: Admin, Viewer, Editor
- Password change & profile management
- Session management with auto-logout

---

## Dashboard / Overview
- Active connections count
- Server version & uptime
- Database count, total size
- Recent activity feed

---

## Database Management
- List all databases with size & owner
- Create / rename / drop database
- Duplicate database
- Export database (SQL dump)
- Import database (SQL file upload)
- Set connection limits per database

---

## Server Connection Management
- Add / edit / delete PostgreSQL server profiles (host, port, db credentials)
- Test connection before saving
- Support multiple servers per app user

---

## PostgreSQL User & Role Management
- List all PostgreSQL users/roles with attributes
- Create / edit / drop PG user or role
- Assign/revoke privileges per database
- Change PG user password
- Toggle superuser / login / replication flags

---

## Table & Schema Management
- Browse schemas and tables
- View table structure (columns, types, constraints)
- Create / alter / drop table
- Manage indexes, foreign keys, sequences
- Run VACUUM / ANALYZE on table

---

## Data Browser
- Paginated table data view
- Filter, sort, search rows
- Inline row edit, insert, delete
- Export table data (CSV, JSON)

---

## Query Editor
- SQL editor with syntax highlighting
- Query execution & result grid
- Query history
- Save & manage named queries

---

## Statistics & Monitoring
- Database size over time (chart)
- Table row counts & bloat info
- Active queries / long-running queries
- Cache hit ratio, index usage stats
- Locks viewer

---

## Backup & Maintenance
- Schedule / trigger on-demand backup
- Download backup files
- Run REINDEX / CLUSTER / VACUUM FULL
- View PostgreSQL logs

---

## Settings
- Manage saved server profiles
- UI theme (light / dark)
- Query timeout & row limit preferences
