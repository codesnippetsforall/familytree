<?php
session_start();
require_once('../config/database.php');

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([]);
    exit;
}

$country_id = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
if ($country_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $db->prepare("SELECT id, name FROM states WHERE country_id = ? ORDER BY name");
$stmt->execute([$country_id]);
$states = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($states);
