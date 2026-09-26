<?php
/**
 * Database Configuration & Connection Handler
 * Student & Semester-Wise Fee Management System
 * PHP 8+ PDO with Prepared Statements & UTF-8 Charset
 */

declare(strict_types=1);

// Prevent direct execution outside app if needed
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Database Credentials (MySQL 8+)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'fee_management_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a configured PDO instance connected directly to MySQL 8+.
 * Auto-provisions database and schema from database.sql if not yet created.
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Try standard MySQL host or unix socket
    $connectionAttempts = [
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
        sprintf('mysql:host=127.0.0.1;port=%s;dbname=%s;charset=%s', DB_PORT, DB_NAME, DB_CHARSET),
        sprintf('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=%s;charset=%s', DB_NAME, DB_CHARSET),
        sprintf('mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=%s;charset=%s', DB_NAME, DB_CHARSET)
    ];

    $lastError = null;
    foreach ($connectionAttempts as $dsn) {
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (PDOException $e) {
            $lastError = $e;
        }
    }

    // If database does not exist, connect without dbname and create it
    try {
        $rootDsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $tempPdo = new PDO($rootDsn, DB_USER, DB_PASS, $options);
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        
        // Re-attempt with newly created db
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        // Run database.sql if tables are missing
        $stmt = $pdo->query("SHOW TABLES LIKE 'students'");
        if ($stmt->rowCount() === 0) {
            $sqlFile = dirname(__DIR__) . '/database.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                $pdo->exec($sqlContent);
            }
        }
        return $pdo;
    } catch (PDOException $e) {
        die("<div style='font-family:sans-serif;padding:2rem;background:#fee2e2;border:1px solid #ef4444;color:#991b1b;border-radius:8px;'>
            <h3>MySQL Database Connection Failed</h3>
            <p><strong>Error:</strong> " . htmlspecialchars($lastError ? $lastError->getMessage() : $e->getMessage()) . "</p>
            <p>Ensure MySQL 8+ service is running with host <code>" . DB_HOST . "</code> and user <code>" . DB_USER . "</code>.</p>
        </div>");
    }
}

