<?php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');

// -------------------------------------------------------------
// Daily Logger
// -------------------------------------------------------------
if (!function_exists('log_event')) {
    function log_event($msg) {
        $logDir = __DIR__ . '/../data/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $file = $logDir . '/' . date('Y-m-d') . '.txt';
        $timestamp = date('Y-m-d H:i:s');

        @file_put_contents($file, "[$timestamp] $msg\n", FILE_APPEND);
    }
}

$choreFile  = __DIR__ . '/../data/chores.json';
$userDir    = __DIR__ . '/../data/users';
$pointsDir  = __DIR__ . '/../data/points';
$historyDir = __DIR__ . '/../data/history';

log_event("[mark_chore.php] ---- Called mark_chore.php ----");

// ----------------------------------------------------------------------
// 1. INPUT
// ----------------------------------------------------------------------
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

log_event("[mark_chore.php] Raw input: " . $rawInput);

if (!$input) {
    log_event("[mark_chore.php] ERROR: Invalid JSON input.");
    echo json_encode(['status' => 'error', 'error' => 'Invalid JSON', 'raw' => $rawInput]);
    exit;
}

if (!isset($input['choreId'], $input['users'])) {
    log_event("[mark_chore.php] ERROR: Missing choreId or users.");
    echo json_encode(['status' => 'error', 'error' => 'Missing choreId or users']);
    exit;
}

$choreId = $input['choreId'];
$users   = $input['users'];

log_event("[mark_chore.php] choreId=$choreId | users=" . json_encode($users));

// must be an array
if (!is_array($users) || count($users) === 0) {
    log_event("[mark_chore.php] ERROR: No users provided.");
    echo json_encode(['status' => 'error', 'error' => 'No users provided']);
    exit;
}

$users = array_unique(array_map('strtolower', $users));

$clientDate       = $input['clientDate'] ?? date('Y-m-d');
$chore['lastCronRun'] = date('Y-m-d'); // store only the date now


// ----------------------------------------------------------------------
// 2. LOAD CHORES
// ----------------------------------------------------------------------
if (!file_exists($choreFile)) {
    log_event("[mark_chore.php] ERROR: chores.json missing.");
    echo json_encode(['status'=>'error','error'=>'Chores file missing']);
    exit;
}

$chores = json_decode(file_get_contents($choreFile), true);
if (!is_array($chores)) {
    log_event("[mark_chore.php] ERROR: chores.json invalid.");
    echo json_encode(['status'=>'error','error'=>'Invalid chores.json']);
    exit;
}

// ----------------------------------------------------------------------
// 3. MODIFY CHORE
// ----------------------------------------------------------------------
$completedChore = null;

if (!is_dir($historyDir)) mkdir($historyDir, 0777, true);

foreach ($chores as &$chore) {
    if (($chore['id'] ?? null) === $choreId) {

        log_event("[mark_chore.php] Found chore '{$chore['name']}' (ID: $choreId).");

        // Undo snapshot
        $undo = [
            'lastMarkedDate' => $chore['lastMarkedDate'] ?? '',
            'lastMarkedBy'   => $chore['lastMarkedBy'] ?? '',
            'timesMarkedOff' => $chore['timesMarkedOff'] ?? 0
        ];

        file_put_contents("$historyDir/undo_$choreId.json", json_encode($undo, JSON_PRETTY_PRINT));
        log_event("[mark_chore.php] Saved undo snapshot for $choreId");

        // Update chore
        $chore['lastMarkedDate'] = $clientDate;
        $chore['lastMarkedBy']   = implode(", ", $users);
        $chore['timesMarkedOff'] = ($chore['timesMarkedOff'] ?? 0) + 1;

        if (($chore['frequencyType'] ?? '') === 'cron') {
            $chore['lastCronRun'] = $clientCronMinute;
            log_event("[mark_chore.php] Cron chore updated lastCronRun=$clientCronMinute");
        }

        $completedChore = &$chore;
        break;
    }
}
unset($chore);

if (!$completedChore) {
    log_event("[mark_chore.php] ERROR: Chore not found: $choreId");
    echo json_encode(['status'=>'error','error'=>"Chore not found: $choreId"]);
    exit;
}

log_event("[mark_chore.php] Marked off chore '$choreId' for users: " . implode(", ", $users));

// ----------------------------------------------------------------------
// 4. AFTER-CHORE SPAWNING
// ----------------------------------------------------------------------
$completedId = $completedChore['id'];

foreach ($chores as &$child) {
    if (
        ($child['frequencyType'] ?? '') === 'after' &&
        ($child['afterChoreId'] ?? '') === $completedId
    ) {
        $child['assignedTo'] = $users[0];
        $child['inPool'] = false;
        $child['lastMarkedDate'] = "";

        log_event("[mark_chore.php] Spawned after-chore '{$child['id']}' assigned to {$users[0]}");
    }
}
unset($child);

// ----------------------------------------------------------------------
// 5. SAVE chores.json (with locking)
// ----------------------------------------------------------------------
if ($fp = fopen($choreFile, 'c+')) {
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($chores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    log_event("[mark_chore.php] Saved updated chores.json");
} else {
    log_event("[mark_chore.php] ERROR: Failed writing chores.json");
    echo json_encode(['status'=>'error','error'=>'Cannot write chores.json']);
    exit;
}

// ----------------------------------------------------------------------
// 6. UPDATE PER-USER STATS
// ----------------------------------------------------------------------
$choreName = $completedChore['name'] ?? 'unknown chore';

if (!is_dir($userDir)) mkdir($userDir, 0777, true);

foreach ($users as $u) {
    $file = "$userDir/$u.json";
    $stats = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

    if (!is_array($stats)) $stats = [];

    $stats[$choreName] = ($stats[$choreName] ?? 0) + 1;

    file_put_contents($file, json_encode($stats, JSON_PRETTY_PRINT));

    log_event("[mark_chore.php] Incremented stat for user '$u' on chore '$choreName'");
}

// ----------------------------------------------------------------------
// 7. UPDATE WEEKLY POINTS
// ----------------------------------------------------------------------
$totalValue = floatval($completedChore['value'] ?? 0);
$pointsEach = $totalValue / count($users);

foreach ($users as $u) {
    $pf = "$pointsDir/$u.json";
    $pdata = file_exists($pf)
        ? json_decode(file_get_contents($pf), true)
        : ["points" => 0];

    $pdata["points"] = ($pdata["points"] ?? 0) + $pointsEach;

    file_put_contents($pf, json_encode($pdata, JSON_PRETTY_PRINT));

    log_event("[mark_chore.php] Added {$pointsEach} points to '$u' (value=$totalValue)");
}

// ----------------------------------------------------------------------
// 8. DONE
// ----------------------------------------------------------------------
log_event("[mark_chore.php] DONE OK marking chore '$choreId'");
echo json_encode(['status' => 'ok']);
