<?php
header('Content-Type: application/json');

// Don't leak warnings into JSON
error_reporting(E_ERROR | E_PARSE);

$choreFile = __DIR__ . '/../data/chores.json';
$userDir   = __DIR__ . '/../data/users';
date_default_timezone_set('America/Chicago');


$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if ($data === null || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Missing chore id', 'raw' => $raw]);
    exit;
}

$id = $data['id'];

if (!file_exists($choreFile)) { // ensure the chore file exists
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Chores file not found']);
    exit;
}

$choreJson = file_get_contents($choreFile);
$chores = json_decode($choreJson, true);
if (!is_array($chores)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Invalid chores.json']);
    exit;
}

$deletedName = null;
$newChores = [];
foreach ($chores as $ch) {
    if (isset($ch['id']) && $ch['id'] === $id) {
        $deletedName = isset($ch['name']) ? $ch['name'] : null;
        continue;
    }
    $newChores[] = $ch;
}

if ($deletedName === null) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'error' => 'Chore not found']);
    exit;
}

// save the updated chore
$fp = fopen($choreFile, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to open chores.json for writing']);
    exit;
}

flock($fp, LOCK_EX);
ftruncate($fp, 0);
fwrite($fp, json_encode($newChores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

// delete chore; remove all user data
if ($deletedName !== null && is_dir($userDir)) {
    $files = glob($userDir . '/*.json');
    if ($files !== false) {
        foreach ($files as $userFile) {
            $uJson = file_get_contents($userFile);
            $stats = json_decode($uJson, true);
            if (!is_array($stats)) {
                continue;
            }
            if (isset($stats[$deletedName])) {
                unset($stats[$deletedName]);
                file_put_contents($userFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }
    }
}

echo json_encode(['status' => 'ok']);
