<?php
// Cron sweep & latch endpoint
// - Iterates all chores
// - For frequencyType === 'cron':
//   * If expression matches "now" -> set cronSpawnedDate to today (latch for the day)
//   * If previously latched for another day -> clear cronSpawnedDate
// - Persists changes to data/chores.json

header('Content-Type: application/json');
date_default_timezone_set('America/Chicago');

// ------------------------------------------------------------
// Simple daily logger -> data/logs/YYYY-MM-DD.txt
// ------------------------------------------------------------
if (!function_exists('log_event')) {
    function log_event($msg) {
        $logDir = __DIR__ . '/../data/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $file = $logDir . '/' . date('Y-m-d') . '.txt';
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($file, "[$timestamp] [cron_spawn.php] $msg\n", FILE_APPEND);
    }
}

// ------------------------------------------------------------
// Load helpers
// ------------------------------------------------------------
$cronEvalPath = __DIR__ . '/cron_eval.php';
if (!file_exists($cronEvalPath)) {
    log_event('ERROR: Missing api/cron_eval.php');
    echo json_encode([
        'status' => 'error',
        'error'  => 'Missing cron_eval.php'
    ]);
    exit;
}
require_once $cronEvalPath;
if (!function_exists('doesCronMatchToday')) {
    log_event('ERROR: doesCronMatchToday() not found after including cron_eval.php');
    echo json_encode([
        'status' => 'error',
        'error'  => 'Cron eval function missing'
    ]);
    exit;
}

// ------------------------------------------------------------
// Load chores.json
// ------------------------------------------------------------
$choreFile = __DIR__ . '/../data/chores.json';
if (!file_exists($choreFile)) {
    log_event('ERROR: data/chores.json not found');
    echo json_encode([
        'status' => 'error',
        'error'  => 'chores.json not found'
    ]);
    exit;
}

$raw = file_get_contents($choreFile);
$chores = json_decode($raw, true);
if (!is_array($chores)) {
    log_event('ERROR: data/chores.json invalid JSON');
    echo json_encode([
        'status' => 'error',
        'error'  => 'Invalid chores.json'
    ]);
    exit;
}

// ------------------------------------------------------------
// Sweep & latch
// ------------------------------------------------------------
$todayStr = date('Y-m-d');
$now = new DateTime('now');

$changed = false;
$latched = [];
$cleared = [];
$checked = 0;

foreach ($chores as &$c) {
    $freq = strtolower(trim((string)($c['frequencyType'] ?? '')));
    if ($freq !== 'cron') continue;

    // Ensure field exists for client logic
    if (!array_key_exists('cronSpawnedDate', $c)) {
        $c['cronSpawnedDate'] = '';
    }

    $expr = trim((string)($c['cron'] ?? ''));
    $checked++;

    // Clear stale latch (yesterday or earlier)
    if (!empty($c['cronSpawnedDate']) && $c['cronSpawnedDate'] !== $todayStr) {
        log_event("Clearing stale cronSpawnedDate for chore '{$c['id']}' (was {$c['cronSpawnedDate']})");
        $c['cronSpawnedDate'] = '';
        $changed = true;
        $cleared[] = $c['id'] ?? '(unknown)';
    }

    if ($expr === '') {
        // If no expression, skip matching; do not latch
        continue;
    }

    $match = doesCronMatchToday($expr, $now);
    if ($match) {
        if ($c['cronSpawnedDate'] !== $todayStr) {
            $c['cronSpawnedDate'] = $todayStr;
            $changed = true;
            $latched[] = $c['id'] ?? '(unknown)';
            log_event("Latched cron chore '{$c['id']}' for today ({$todayStr}) using expr '$expr'");
        }
    }
}
unset($c);

// Persist if changed
if ($changed) {
    file_put_contents(
        $choreFile,
        json_encode($chores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    log_event('Saved updated chores.json after cron sweep');
}

echo json_encode([
    'status'   => 'ok',
    'date'     => $todayStr,
    'checked'  => $checked,
    'latched'  => $latched,
    'cleared'  => $cleared,
    'changed'  => $changed
]);
