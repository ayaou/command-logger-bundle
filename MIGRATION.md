# Command Logger Bundle - Database Migration Guide

## Overview
This bundle includes a Doctrine migration to create the required `command_log` table automatically.

## Migration File
- **File**: `src/Migrations/Version20240101000000.php`
- **Purpose**: Creates the `command_log` table with proper indexes and constraints

## Database Schema
The migration creates a table with the following structure:

```sql
CREATE TABLE command_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    command_name VARCHAR(255) NOT NULL,
    arguments JSON,
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    exit_code INTEGER,
    error_message TEXT,
    execution_token VARCHAR(36) NOT NULL UNIQUE
);

-- Indexes for performance
CREATE INDEX IDX_command_name ON command_log (command_name);
CREATE INDEX IDX_start_time ON command_log (start_time);
CREATE INDEX IDX_command_name_start_time ON command_log (command_name, start_time);
CREATE INDEX IDX_exit_code ON command_log (exit_code);
CREATE UNIQUE INDEX UNIQ_execution_token ON command_log (execution_token);
```

## How to Run the Migration

### Option 1: Automatic Migration (Recommended)
The migration will run automatically when you execute:
```bash
php bin/console doctrine:migrations:migrate
```

### Option 2: Manual Table Creation
If you prefer to create the table manually, you can use the SQL schema above or use Doctrine's schema tools:
```bash
php bin/console doctrine:schema:update --force
```

## Important Notes

1. **Backup Your Database**: Always backup your database before running migrations in production.

2. **Check for Existing Table**: The migration includes a check for existing `command_log` table to avoid conflicts.

3. **Rollback**: The migration includes a `down()` method to remove the table if needed:
   ```bash
   php bin/console doctrine:migrations:execute --down Version20240101000000
   ```

4. **Performance**: The migration includes optimized indexes for common query patterns used by the bundle.

## Troubleshooting

### Table Already Exists
If you already have a `command_log` table, the migration will skip creation. Ensure your existing table has the correct structure or drop it before running the migration.

### Permission Issues
Ensure your database user has CREATE, DROP, and INDEX privileges for the migration to run successfully.

### Different Database Platforms
The migration is written to be database-agnostic and should work with MySQL, PostgreSQL, SQLite, and other platforms supported by Doctrine DBAL.