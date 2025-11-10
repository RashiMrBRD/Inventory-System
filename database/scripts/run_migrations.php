<?php
/**
 * Database Migration Runner
 * Run this script to create the custom_fonts table
 * 
 * Usage: php database/scripts/run_migrations.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$config = require __DIR__ . '/../../config/database.php';

try {
    // Connect to database
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Connected to database: {$config['database']}\n";
    
    // Read migration file
    $migrationFile = __DIR__ . '/../migrations/005_create_custom_fonts_table.sql';
    
    if (!file_exists($migrationFile)) {
        die("Migration file not found: $migrationFile\n");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Remove comments (lines starting with --)
    $sql = preg_replace('/^--.*$/m', '', $sql);
    
    // Execute migration
    echo "Running migration: 005_create_custom_fonts_table.sql\n";
    $pdo->exec($sql);
    
    echo "✓ Migration completed successfully!\n";
    echo "✓ custom_fonts table created\n";
    
    // Verify table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'custom_fonts'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Table verified in database\n";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE custom_fonts");
        $columns = $stmt->fetchAll();
        
        echo "\nTable structure:\n";
        echo str_repeat('-', 80) . "\n";
        printf("%-20s %-20s %-10s %-10s\n", "Column", "Type", "Null", "Key");
        echo str_repeat('-', 80) . "\n";
        
        foreach ($columns as $column) {
            printf(
                "%-20s %-20s %-10s %-10s\n",
                $column['Field'],
                $column['Type'],
                $column['Null'],
                $column['Key']
            );
        }
        echo str_repeat('-', 80) . "\n";
    }
    
    echo "\n✓ All done! You can now upload custom fonts in Settings → Regional.\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
