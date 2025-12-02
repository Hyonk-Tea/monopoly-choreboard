<?php
header('Content-Type: application/json');
date_default_timezone_set('America/Chicago');

// Only fatal-ish errors
error_reporting(E_ERROR | E_PARSE);

$choreFile = __DIR__ . '/../data/chores.json';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid JSON input', 'raw' => $raw]);
    exit;
}

// Required fields
$required = ['name', 'frequencyType', 'inPool'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => "Missing field: $field"]);
        exit;
    }
}

// Ensure chore file exists
if (!file_exists($choreFile)) {
    file_put_contents($choreFile, "[]");
}

$choreJson = file_get_contents($choreFile);
$chores = json_decode($choreJson, true);
if (!is_array($chores)) {
    $chores = [];
}

// Extract fields
$id           = $data['id'] ?? null;
$name         = trim($data['name']);
$description  = trim($data['description'] ?? '');
$value        = isset($data['value']) ? (float)$data['value'] : 0.0;
$spawn        = !empty($data['spawn']);
$freq         = $data['frequencyType'];
$customDays   = (isset($data['customDays']) && is_numeric($data['customDays']))
    ? (int)$data['customDays']
    : null;
$afterChoreId = (!empty($data['afterChoreId'])) ? $data['afterChoreId'] : null;
$inPool       = !empty($data['inPool']);
$assignedTo   = $inPool ? '' : trim($data['assignedTo'] ?? '');
$afterDinner  = !empty($data['afterDinner']);

$undesirable = isset($data['undesirable'])
    ? (bool)$data['undesirable']
    : false;

$eligibleUsers = [];
if (isset($data['eligibleUsers']) && is_array($data['eligibleUsers'])) {
    $eligibleUsers = array_values($data['eligibleUsers']);
}

$weeklyDay = ($freq === 'weekly' && isset($data['weeklyDay']))
    ? (int)$data['weeklyDay']
    : null;

$resetStats = !empty($data['resetStats']);

// Validation
if ($freq === 'custom' && (!$customDays || $customDays < 1)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Custom frequency requires customDays >= 1']);
    exit;
}

if ($freq === 'after' && !$afterChoreId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'After frequency requires afterChoreId']);
    exit;
}

if (!$inPool && $assignedTo === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Assigned chores must have assignedTo']);
    exit;
}

if ($freq === 'after' && $id && $afterChoreId === $id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Chore cannot depend on itself']);
    exit;
}

// ID generation
function slugify($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    $text = trim($text, '_');
    if ($text === '') $text = 'chore';
    return $text;
}

if (!$id) {
    $base = slugify($name);
    $idCandidate = $base;
    $counter = 1;
    $existingIds = array_column($chores, 'id');

    while (in_array($idCandidate, $existingIds)) {
        $idCandidate = $base . '_' . $counter;
        $counter++;
    }
    $id = $idCandidate;
}

// Preserve stats (or reset)
$foundIndex = null;
foreach ($chores as $idx => $ch) {
    if (!empty($ch['id']) && $ch['id'] === $id) {
        $foundIndex = $idx;
        break;
    }
}

$lastMarkedDate = '';
$lastMarkedBy   = '';
$timesMarkedOff = 0;
$lastCronRun    = null;

$forceReset = !empty($data['forceResetStats']);

if ($foundIndex !== null) {
    $existing = $chores[$foundIndex];

    if ($forceReset) {
        // Full reset
        $lastMarkedDate = '';
        $lastMarkedBy   = '';
        $timesMarkedOff = 0;
    } else {
        // Normal preserve
        $lastMarkedDate = $existing['lastMarkedDate'] ?? '';
        $lastMarkedBy   = $existing['lastMarkedBy'] ?? '';
        $timesMarkedOff = (int)($existing['timesMarkedOff'] ?? 0);
    }
} else {
    // New chore
    $lastMarkedDate = '';
    $lastMarkedBy   = '';
    $timesMarkedOff = 0;
}


// Build final chore object
$newChore = [
    'id'             => $id,
    'name'           => $name,
    'description'    => $description,
    'frequencyType'  => $freq,
    'customDays'     => ($freq === 'custom' ? $customDays : null),
    'afterChoreId'   => ($freq === 'after'  ? $afterChoreId : null),
    'weeklyDay'      => ($freq === 'weekly' ? $weeklyDay : null),

    'spawn'          => $spawn,
    'lastMarkedDate' => $lastMarkedDate,
    'lastMarkedBy'   => $lastMarkedBy,
    'timesMarkedOff' => $timesMarkedOff,

    'inPool'         => $inPool,
    'assignedTo'     => $assignedTo,
    'value'          => $value,

    'afterDinner'    => $afterDinner,
    'undesirable'    => $undesirable,
    'eligibleUsers'  => $eligibleUsers,
];

// Cron support
if ($freq === 'cron') {
    $newChore['cron'] = $data['cron'] ?? "";
    if ($lastCronRun !== null) {
        $newChore['lastCronRun'] = $lastCronRun;
    }
} else {
    // no cron fields necessary for non-cron chores
    unset($newChore['cron']);
    unset($newChore['lastCronRun']);
}

// Save into array
if ($foundIndex !== null) {
    $chores[$foundIndex] = $newChore;
} else {
    $chores[] = $newChore;
}

// Safe write
$backupFile = $choreFile . '.bak';
@copy($choreFile, $backupFile);

$fp = fopen($choreFile, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to open chores.json for writing']);
    exit;
}

flock($fp, LOCK_EX);
ftruncate($fp, 0);
fwrite($fp, json_encode($chores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['status' => 'ok', 'id' => $id]);
