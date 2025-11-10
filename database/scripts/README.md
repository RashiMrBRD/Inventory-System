# Database Scripts

This directory contains utility scripts for database management and migrations.

## Scripts

### run_migrations.php

**Purpose:** Runs database migrations to set up or update the database schema.

**Usage:**
```bash
php database/scripts/run_migrations.php
```

**What it does:**
- Creates the `custom_fonts` table in the database
- Runs migration file: `database/migrations/005_create_custom_fonts_table.sql`
- Verifies table creation and displays table structure
- Shows success/error messages

**When to use:**
- Setting up a new database
- Adding the custom fonts feature to an existing installation
- After pulling updates that include new migrations

**Requirements:**
- Database configuration must be set in `config/database.php`
- Migration file must exist in `database/migrations/`

**Output:**
```
Connected to database: inventory_db
Running migration: 005_create_custom_fonts_table.sql
✓ Migration completed successfully!
✓ custom_fonts table created
✓ Table verified in database
...
✓ All done! You can now upload custom fonts in Settings → Regional.
```

## Notes

- Always backup your database before running migrations
- This script is safe to run multiple times (it will create the table if it doesn't exist)
- If you encounter errors, check your database credentials in `config/database.php`
