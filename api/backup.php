<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$user = getAuthenticatedUser();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'backup') {
    try {
        $db = getDbConnection();
        $db->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);

        $tables = [];
        $stmt = $db->query('SHOW TABLES');
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sql = "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $stmt = $db->query("SHOW CREATE TABLE `$table`");
            $createTable = $stmt->fetch(PDO::FETCH_NUM);
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createTable[1] . ";\n\n";

            $stmt = $db->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) > 0) {
                foreach ($rows as $row) {
                    $sql .= "INSERT INTO `$table` VALUES (";
                    $values = [];
                    foreach ($row as $value) {
                        if (is_null($value)) {
                            $values[] = "NULL";
                        } else {
                            $values[] = $db->quote($value);
                        }
                    }
                    $sql .= implode(", ", $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.sql"');
        echo $sql;
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Backup failed: ' . $e->getMessage()]);
        exit;
    }
} elseif ($action === 'restore') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded or upload error']);
        exit;
    }

    $fileTmpPath = $_FILES['backup_file']['tmp_name'];
    $sqlContent = file_get_contents($fileTmpPath);

    if (empty($sqlContent)) {
        http_response_code(400);
        echo json_encode(['error' => 'Empty backup file']);
        exit;
    }

    try {
        $db = getDbConnection();
        // Since backups can contain large and multiple queries, we can just execute the whole file
        // PDO exec can handle multiple statements if emulate prepares is enabled or driver supports it
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1);
        $db->exec($sqlContent);

        echo json_encode(['success' => true, 'message' => 'Database restored successfully']);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Restore failed: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}
