<?php
session_start();
require_once('../config/database.php');

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Delete backup (GET with confirm in link)
if (isset($_GET['delete']) && preg_match('/^backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}$/', $_GET['delete'])) {
    $delDir = '../backup/' . basename($_GET['delete']);
    if (is_dir($delDir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($delDir, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::CATCH_GET_CHILD), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($delDir);
        header('Location: export.php?deleted=1');
        exit();
    }
}

function dirSize($directory) {
    $size = 0;
    if (!is_dir($directory)) return 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    return $size;
}

function copyDirectory($source, $destination) {
    if (!file_exists($destination)) {
        mkdir($destination, 0777, true);
    }
    
    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $sourcePath = $source . '/' . $file;
            $destPath = $destination . '/' . $file;
            
            if (is_dir($sourcePath)) {
                copyDirectory($sourcePath, $destPath);
            } else {
                copy($sourcePath, $destPath);
            }
        }
    }
    closedir($dir);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Create backup directory with timestamp
        $timestamp = date('Y-m-d_H-i-s');
        $backupDir = '../backup/backup_' . $timestamp;
        
        if (!file_exists('../backup')) {
            mkdir('../backup', 0777, true);
        }

        // Copy all directories and files
        $directories = ['admin', 'config', 'uploads'];
        foreach ($directories as $dir) {
            if (file_exists('../' . $dir)) {
                copyDirectory('../' . $dir, $backupDir . '/' . $dir);
            }
        }

        // Copy individual files
        $files = ['index.php', 'database.sql'];
        foreach ($files as $file) {
            if (file_exists('../' . $file)) {
                copy('../' . $file, $backupDir . '/' . $file);
            }
        }

        // Export database
        $tables = ['admins', 'family_members', 'member_parents', 'member_spouses', 'admin_suggestions', 'countries', 'states'];
        $dbBackup = '';
        
        // Add database creation
        $dbBackup .= "-- Create database\n";
        $dbBackup .= "CREATE DATABASE IF NOT EXISTS " . DB_NAME . ";\n";
        $dbBackup .= "USE " . DB_NAME . ";\n\n";

        foreach ($tables as $table) {
            // Get create table syntax
            $stmt = $db->query("SHOW CREATE TABLE $table");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $dbBackup .= $row['Create Table'] . ";\n\n";

            // Get table data
            $stmt = $db->query("SELECT * FROM $table");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_map(function($value) use ($db) {
                    return $value === null ? 'NULL' : $db->quote($value);
                }, $row);
                
                $dbBackup .= "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
            $dbBackup .= "\n";
        }

        // Save database backup
        file_put_contents($backupDir . '/database_backup.sql', $dbBackup);

        $success = "Backup created successfully in: backup_" . $timestamp;
    } catch (Exception $e) {
        $error = "Error creating backup: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data & DB - Family Tree Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Export Data & DB</h1>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error']) && $_GET['error'] === 'zip'): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    ZIP download is not available on this server. Use the SQL button to download the database.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    Backup deleted.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3 d-flex align-items-center">
                        <i class='bx bxs-cloud-upload text-primary me-2'></i> Create Backup
                    </h5>
                    <p class="text-muted mb-2">This will create a full backup of:</p>
                    <ul class="mb-4">
                        <li>Admin, config, and uploads folders</li>
                        <li>Database (admins, family_members, member_parents, member_spouses, admin_suggestions, countries, states)</li>
                        <li>Uploaded member images</li>
                    </ul>
                    <form method="POST" id="backup-form" onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerHTML='<span class=\"spinner-border spinner-border-sm me-1\" role=\"status\"></span> Creating...'; return true;">
                        <button type="submit" class="btn btn-primary" id="backup-btn">
                            <i class='bx bxs-download'></i> Create Backup
                        </button>
                    </form>
                </div>
            </div>

            <?php if (file_exists('../backup')): 
                $backups = glob('../backup/backup_*', GLOB_ONLYDIR);
                rsort($backups); // newest first
            ?>
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">Previous Backups</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Backup Name</th>
                                    <th>Date Created</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): 
                                    $name = basename($backup);
                                    $date = date('Y-m-d H:i:s', filemtime($backup));
                                    $size = round(dirSize($backup) / (1024 * 1024), 2);
                                    $sqlFile = $backup . '/database_backup.sql';
                                ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($name); ?></code></td>
                                    <td><?php echo htmlspecialchars($date); ?></td>
                                    <td><?php echo $size; ?> MB</td>
                                    <td>
                                        <?php if (file_exists($sqlFile)): ?>
                                        <a href="download_backup.php?name=<?php echo urlencode($name); ?>&file=sql" class="btn btn-sm btn-outline-primary" title="Download SQL">
                                            <i class='bx bxs-download'></i> SQL
                                        </a>
                                        <?php endif; ?>
                                        <a href="download_backup.php?name=<?php echo urlencode($name); ?>&file=zip" class="btn btn-sm btn-outline-secondary" title="Download full backup (ZIP)">
                                            <i class='bx bxs-archive'></i> ZIP
                                        </a>
                                        <a href="export.php?delete=<?php echo urlencode($name); ?>" class="btn btn-sm btn-outline-danger" title="Delete backup" onclick="return confirm('Delete this backup?');">
                                            <i class='bx bx-trash'></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 