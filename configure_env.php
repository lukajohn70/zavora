<?php
/**
 * Database Environment Configurator (.env)
 * John Luka Media (JLM) Digital Ecosystem
 *
 * Supports both CLI (Terminal / SSH) and Web Browser execution.
 * Safely tests MySQL PDO connection, updates .env without breaking other settings,
 * and creates a timestamped backup before writing.
 *
 * CLI Usage:
 *   php configure_env.php
 *   php configure_env.php --host=localhost --name=gwylvxeo_zavora --user=gwylvxeo_zavora --pass="myPass123"
 *
 * Web Browser Usage:
 *   Upload to your site root and navigate to https://zavora.jlm.com.ng/configure_env.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$dir = __DIR__;
$envFile = $dir . '/.env';
$envExample = file_exists($dir . '/.env.production') ? $dir . '/.env.production' : (file_exists($dir . '/.env.example') ? $dir . '/.env.example' : null);

// If .env doesn't exist, bootstrap from .env.production or .env.example
if (!file_exists($envFile) && $envExample) {
    copy($envExample, $envFile);
}

// Determine target key prefix based on file content or directory
function detect_db_key_mapping($envPath) {
    $content = file_exists($envPath) ? file_get_contents($envPath) : '';
    
    if (strpos($content, 'ZAVORA_DB_') !== false || file_exists(__DIR__ . '/config/database.php')) {
        return [
            'type' => 'ZAVORA Marketplace',
            'host' => 'ZAVORA_DB_HOST',
            'port' => 'ZAVORA_DB_PORT',
            'name' => 'ZAVORA_DB_NAME',
            'user' => 'ZAVORA_DB_USER',
            'pass' => 'ZAVORA_DB_PASS',
        ];
    } elseif (strpos($content, 'JLM_DB_') !== false) {
        return [
            'type' => 'JLM Corporate',
            'host' => 'JLM_DB_HOST',
            'port' => 'JLM_DB_PORT',
            'name' => 'JLM_DB_NAME',
            'user' => 'JLM_DB_USER',
            'pass' => 'JLM_DB_PASS',
        ];
    } else {
        // Standard Laravel / Learnerium mapping
        return [
            'type' => 'Learnerium / Standard Laravel',
            'host' => 'DB_HOST',
            'port' => 'DB_PORT',
            'name' => 'DB_DATABASE',
            'user' => 'DB_USERNAME',
            'pass' => 'DB_PASSWORD',
        ];
    }
}

$mapping = detect_db_key_mapping($envFile);

// Read current values from .env
function read_env_values($path) {
    if (!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $vals = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ((substr($v, 0, 1) === '"' && substr($v, -1) === '"') || (substr($v, 0, 1) === "'" && substr($v, -1) === "'")) {
            $v = substr($v, 1, -1);
        }
        $vals[$k] = $v;
    }
    return $vals;
}

$currentVals = read_env_values($envFile);

// Test Database Connection via PDO
function test_db_connection($host, $port, $name, $user, $pass, &$errorMsg = '') {
    $port = $port ?: 3306;
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        return true;
    } catch (\Throwable $e) {
        $errorMsg = $e->getMessage();
        return false;
    }
}

// Atomically Update .env with new values
function update_env_file($path, array $newValues) {
    if (!file_exists($path)) {
        touch($path);
    }
    
    // Create timestamped backup
    $backupPath = $path . '.bak.' . date('Ymd_His');
    copy($path, $backupPath);

    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    $updatedKeys = [];

    foreach ($lines as $idx => $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0 || strpos($trimmed, '=') === false) {
            continue;
        }
        list($key) = explode('=', $trimmed, 2);
        $key = trim($key);
        if (array_key_exists($key, $newValues)) {
            $val = $newValues[$key];
            // Quote value if it contains spaces, hashes, or quotes
            if (preg_match('/[\s#"\']/', $val)) {
                $val = '"' . addcslashes($val, '"') . '"';
            }
            $lines[$idx] = "{$key}={$val}";
            $updatedKeys[$key] = true;
        }
    }

    // Append any keys that did not exist
    foreach ($newValues as $key => $val) {
        if (!isset($updatedKeys[$key])) {
            if (preg_match('/[\s#"\']/', $val)) {
                $val = '"' . addcslashes($val, '"') . '"';
            }
            $lines[] = "{$key}={$val}";
        }
    }

    $newContent = implode("\n", $lines);
    file_put_contents($path, $newContent);
    return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. CLI Execution Mode
// ─────────────────────────────────────────────────────────────────────────────
if (PHP_SAPI === 'cli') {
    echo "================================================================\n";
    echo "      JLM DIGITAL ECOSYSTEM — DATABASE .ENV CONFIGURATOR        \n";
    echo "================================================================\n";
    echo "App Detected: {$mapping['type']}\n";
    echo "Target File:  {$envFile}\n\n";

    // Parse options from CLI flags if passed
    $opts = getopt('', ['host::', 'port::', 'name::', 'user::', 'pass::', 'skip-test']);

    $host = $opts['host'] ?? null;
    $port = $opts['port'] ?? null;
    $name = $opts['name'] ?? null;
    $user = $opts['user'] ?? null;
    $pass = $opts['pass'] ?? null;
    $skipTest = isset($opts['skip-test']);

    // If options not provided, prompt interactively
    if ($host === null || $name === null || $user === null || $pass === null) {
        $defHost = $currentVals[$mapping['host']] ?? 'localhost';
        $defPort = $currentVals[$mapping['port']] ?? '3306';
        $defName = $currentVals[$mapping['name']] ?? 'gwylvxeo_zavora';
        $defUser = $currentVals[$mapping['user']] ?? 'gwylvxeo_zavora';

        echo "Enter Database Host [{$defHost}]: ";
        $inHost = trim(fgets(STDIN));
        $host = $inHost !== '' ? $inHost : $defHost;

        echo "Enter Database Port [{$defPort}]: ";
        $inPort = trim(fgets(STDIN));
        $port = $inPort !== '' ? $inPort : $defPort;

        echo "Enter Database Name [{$defName}]: ";
        $inName = trim(fgets(STDIN));
        $name = $inName !== '' ? $inName : $defName;

        echo "Enter Database User [{$defUser}]: ";
        $inUser = trim(fgets(STDIN));
        $user = $inUser !== '' ? $inUser : $defUser;

        echo "Enter Database Password: ";
        $inPass = trim(fgets(STDIN));
        $pass = $inPass;
    }

    $port = $port ?: '3306';

    echo "\nTesting connection to MySQL ({$user}@{$host}:{$port}/{$name})...\n";
    $connError = '';
    $connOk = test_db_connection($host, $port, $name, $user, $pass, $connError);

    if ($connOk) {
        echo "✓ Connection SUCCESSFUL!\n";
    } else {
        echo "⚠ Connection FAILED: {$connError}\n";
        if (!$skipTest) {
            echo "Save credentials to .env anyway? (y/n) [n]: ";
            $ans = strtolower(trim(fgets(STDIN)));
            if ($ans !== 'y' && $ans !== 'yes') {
                echo "Aborted. .env was not modified.\n";
                exit(1);
            }
        }
    }

    $updates = [
        $mapping['host'] => $host,
        $mapping['port'] => $port,
        $mapping['name'] => $name,
        $mapping['user'] => $user,
        $mapping['pass'] => $pass,
    ];

    update_env_file($envFile, $updates);
    echo "✓ Successfully updated {$envFile}!\n";
    echo "================================================================\n\n";
    exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Web Browser Execution Mode
// ─────────────────────────────────────────────────────────────────────────────
$msg = '';
$msgType = '';
$tested = false;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'self_destruct') {
        @unlink(__FILE__);
        echo "<!DOCTYPE html><html><body style='font-family:sans-serif;text-align:center;padding:50px;'>";
        echo "<h2>Script Successfully Deleted</h2>";
        echo "<p>This configurator file has been permanently removed from the server for security.</p>";
        echo "<p><a href='./'>Go to Application</a></p>";
        echo "</body></html>";
        exit;
    }

    if ($action === 'save_env') {
        $host = trim($_POST['db_host'] ?? 'localhost');
        $port = trim($_POST['db_port'] ?? '3306');
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = $_POST['db_pass'] ?? '';
        $forceSave = isset($_POST['force_save']);

        $tested = true;
        $connErr = '';
        $connOk = test_db_connection($host, $port, $name, $user, $pass, $connErr);

        if ($connOk || $forceSave) {
            $updates = [
                $mapping['host'] => $host,
                $mapping['port'] => $port,
                $mapping['name'] => $name,
                $mapping['user'] => $user,
                $mapping['pass'] => $pass,
            ];
            update_env_file($envFile, $updates);
            $currentVals = read_env_values($envFile);

            if ($connOk) {
                $msg = "Database connection verified and .env saved successfully!";
                $msgType = "success";
            } else {
                $msg = ".env updated, but MySQL test failed: " . htmlspecialchars($connErr);
                $msgType = "warning";
            }
        } else {
            $msg = "Connection Failed: " . htmlspecialchars($connErr) . ". Check credentials or tick 'Save Anyway'.";
            $msgType = "error";
        }
    }
}

$curHost = $currentVals[$mapping['host']] ?? 'localhost';
$curPort = $currentVals[$mapping['port']] ?? '3306';
$curName = $currentVals[$mapping['name']] ?? 'gwylvxeo_zavora';
$curUser = $currentVals[$mapping['user']] ?? 'gwylvxeo_zavora';
$curPass = $currentVals[$mapping['pass']] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database .env Configurator — <?= htmlspecialchars($mapping['type']) ?></title>
    <style>
        :root {
            --primary: #0284c7;
            --primary-hover: #0369a1;
            --bg: #0f172a;
            --card: #1e293b;
            --border: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }
        .container {
            width: 100%;
            max-width: 520px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .badge {
            display: inline-block;
            background: rgba(2, 132, 199, 0.15);
            color: #38bdf8;
            border: 1px solid rgba(2, 132, 199, 0.3);
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }
        h1 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        p.subtitle {
            font-size: 13px;
            color: var(--text-muted);
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.4;
        }
        .alert-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .alert-error { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .alert-warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 12px;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            background: #0b1120;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            color: var(--text);
            outline: none;
            transition: border-color 0.15s ease;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            border-color: var(--primary);
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 20px;
            cursor: pointer;
            text-transform: none;
        }
        .checkbox-label input {
            accent-color: var(--primary);
        }
        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        .btn-submit:hover {
            background: var(--primary-hover);
        }
        .self-destruct {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
        }
        .btn-danger {
            background: transparent;
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.15);
        }
        .file-info {
            background: #0b1120;
            border-radius: 8px;
            padding: 10px;
            font-family: monospace;
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 20px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="badge"><?= htmlspecialchars($mapping['type']) ?></span>
            <h1>Database Configurator</h1>
            <p class="subtitle">Test MySQL connection and update .env in 1 click</p>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?= $msgType ?>">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="file-info">
            Writing to: <?= htmlspecialchars($envFile) ?>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="save_env">

            <div class="grid-2">
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" value="<?= htmlspecialchars($curHost) ?>" required>
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="text" name="db_port" value="<?= htmlspecialchars($curPort) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Database Name</label>
                <input type="text" name="db_name" value="<?= htmlspecialchars($curName) ?>" required placeholder="e.g. gwylvxeo_zavora">
            </div>

            <div class="form-group">
                <label>Database User</label>
                <input type="text" name="db_user" value="<?= htmlspecialchars($curUser) ?>" required placeholder="e.g. gwylvxeo_zavora">
            </div>

            <div class="form-group">
                <label>Database Password</label>
                <input type="password" name="db_pass" value="<?= htmlspecialchars($curPass) ?>" placeholder="Enter MySQL Password" id="pwdInput">
                <div style="margin-top: 6px;">
                    <label class="checkbox-label" style="margin-bottom: 0;">
                        <input type="checkbox" onclick="document.getElementById('pwdInput').type = this.checked ? 'text' : 'password'"> Show password
                    </label>
                </div>
            </div>

            <label class="checkbox-label">
                <input type="checkbox" name="force_save" value="1"> Save to .env even if connection test fails
            </label>

            <button type="submit" class="btn-submit">Test Connection &amp; Update .env</button>
        </form>

        <div class="self-destruct">
            <form method="POST" onsubmit="return confirm('Permanently delete this setup script from the server?');">
                <input type="hidden" name="action" value="self_destruct">
                <button type="submit" class="btn-danger">🗑 Delete This Script for Security</button>
            </form>
        </div>
    </div>
</body>
</html>
