<?php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');

// -------------------------------------------------------------
// Daily Logger
// -------------------------------------------------------------
if (!function_exists('log_event')) {
    /**
     * Logs an event message with a timestamp to a daily log file in the logs directory.
     *
     * @param string $msg The message to log.
     * @return void
     */
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

/**
 * Represents the file path or file name associated with the list or record of chores.
 *
 * This variable may be used to store the name of a file or a complete path to a file
 * where chore-related data is saved or retrieved.
 *
 * Ensure the value assigned is a valid file path or name supported by the operating system.
 *
 * @var string
 */
$choreFile  = __DIR__ . '/../data/chores.json';
/**
 * Represents the directory path associated with a specific user.
 *
 * This variable is intended to store the file system path to a user's directory.
 * It is typically used for operations such as reading, writing, or managing
 * user-specific files and data within the application.
 *
 * The value assigned to this variable should be a valid string representing
 * an absolute or relative path on the filesystem, depending on the application's
 * configuration or requirements.
 */
$userDir    = __DIR__ . '/../data/users';
/**
 * Directory path where point-related data or files are stored.
 *
 * This variable is used to define the location on the filesystem
 * where specific resources related to "points" are saved. It is
 * expected to hold a valid directory path as a string.
 *
 * Example data that might be stored in this directory include:
 * - Configurations for points system
 * - Temporary or permanent files related to point calculations
 * - Logs or backups associated with points
 *
 * Ensure the directory path provided has the necessary read/write
 * permissions for the application to function correctly.
 *
 * @var string $pointsDir The directory path for points resources.
 */
$pointsDir  = __DIR__ . '/../data/points';
/**
 * Specifies the directory path where historical data or logs are stored.
 *
 * This variable typically holds the file path to a directory on the filesystem
 * that contains archived records, logs, or other historical information. It is
 * used for organizing and accessing such data within the application. The value
 * should be a valid directory path that the application has permission to access.
 *
 * @var string
 */
$historyDir = __DIR__ . '/../data/history';

log_event("[mark_chore.php] ---- Called mark_chore.php ----");

// ----------------------------------------------------------------------
// 1. INPUT
// ----------------------------------------------------------------------
/**
 * Represents the unprocessed input data received from an external source,
 * such as user input, API request payload, or a raw data stream.
 * It may require validation, sanitization, or parsing before use.
 *
 * @var mixed $rawInput The raw, unprocessed input data.
 */
$rawInput = file_get_contents('php://input');
/**
 * Represents input data that can be used within a processing function or application.
 *
 * This variable is expected to store the input provided by a user or a data source.
 * The format, type, and content of the input should be validated before use to ensure
 * compatibility and security within the context it is applied.
 *
 * @var mixed The input data, which may vary in type and structure.
 */
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

/**
 * Identifier for a specific chore.
 *
 * Represents the unique ID assigned to a chore entity.
 * This ID is commonly used to perform operations such as retrieval, updating, or deletion
 * of the chore in a database or other storage mechanism.
 *
 * @var int|string The unique identifier for the chore, typically an integer or string.
 */
$choreId = $input['choreId'];
/**
 * @var array $users
 *
 * Represents a collection of users. Each element in the array typically contains
 * detailed information about an individual user, such as their name, email, or
 * other associated attributes. The structure and content of each element depend
 * on the specific application context and implementation.
 */
$users   = $input['users'];

log_event("[mark_chore.php] choreId=$choreId | users=" . json_encode($users));

// must be an array
if (!is_array($users) || count($users) === 0) {
    log_event("[mark_chore.php] ERROR: No users provided.");
    echo json_encode(['status' => 'error', 'error' => 'No users provided']);
    exit;
}

/**
 * An array or collection that contains user information.
 *
 * This variable is typically used to store and manage a list of users, where each
 * user may be represented as an associative array or object containing details
 * such as their ID, name, email, or other related attributes.
 *
 * Expected structure for each user entry and additional details will depend
 * on the specific application requirements and implementation context.
 *
 * @var array|iterable $users
 */
$users = array_unique(array_map('strtolower', $users));

/**
 * Represents the date provided by the client.
 *
 * This variable holds the date value typically received from
 * client-side input or an external source. It is expected to
 * be in a format that complies with date-handling standards
 * (e.g., ISO 8601), or it should be cast/validated before use.
 *
 * Usage of this variable often involves date-related
 * operations such as parsing, validation, formatting, or
 * calculations, depending on the application's requirements.
 */
$clientDate       = $input['clientDate'] ?? date('Y-m-d');
/**
 * Represents a task or duty to be performed, often part of a list of responsibilities
 * such as household tasks or routine activities.
 *
 * This variable typically holds information about a specific chore,
 * which might include details such as the task name, description, priority,
 * status, or assigned person, depending on the context of its usage.
 *
 * The purpose of this variable is to track or manage individual tasks
 * within a system designed for task management or organizational processes.
 *
 * @var mixed $chore The specific chore or task to be performed.
 */
$chore['lastCronRun'] = date('Y-m-d'); // store only the date now


// ----------------------------------------------------------------------
// 2. LOAD CHORES
// ----------------------------------------------------------------------
if (!file_exists($choreFile)) {
    log_event("[mark_chore.php] ERROR: chores.json missing.");
    echo json_encode(['status'=>'error','error'=>'Chores file missing']);
    exit;
}

/**
 * @var array $chores
 *
 * Represents a list of household chores or tasks to be completed.
 * Each element in the array corresponds to a single chore or task,
 * typically represented as a string describing the activity.
 */
$chores = json_decode(file_get_contents($choreFile), true);
if (!is_array($chores)) {
    log_event("[mark_chore.php] ERROR: chores.json invalid.");
    echo json_encode(['status'=>'error','error'=>'Invalid chores.json']);
    exit;
}

// ----------------------------------------------------------------------
// 3. MODIFY CHORE
// ----------------------------------------------------------------------
/**
 * Indicates whether a chore has been completed.
 *
 * This variable typically stores a boolean value.
 * A value of `true` denotes that the chore has been successfully completed,
 * whereas `false` represents an incomplete chore.
 */
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
/**
 * Represents a specific task or duty that needs to be performed.
 *
 * This variable may be used to store the details of a chore, such as
 * its name, description, status, or any other related information
 * depending on the implementation.
 *
 * It could be utilized in a task management or scheduling application
 * to track and manage responsibilities effectively.
 */
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
/**
 * Represents the unique identifier for a completed task or process.
 *
 * This variable stores the ID associated with a task or process that has been successfully completed.
 * It is typically used to reference or retrieve data related to the completed entity.
 *
 * @var int|string Identifier for completed tasks or processes. It may be an integer or string, depending on implementation.
 */
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
/**
 * Represents a child entity or object which may contain data or properties
 * related to a specific child entry. The exact structure or type of the $child
 * variable is determined by the context in which it is used.
 *
 * This variable can hold various data types such as an object, array, or scalar
 * depending on its usage in the application. It is commonly used to define or
 * interact with a child component, item, or instance within a parent-child
 * structure.
 *
 * @var mixed $child The child entity, data, or object.
 */
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
/**
 * Represents the name of a chore.
 *
 * This variable is used to store a descriptive name or title for a specific chore
 * or task in a task management or organization system.
 *
 * Expected to be a string value.
 */
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
/**
 * Represents the total calculated value.
 *
 * This variable is used to store the aggregate value derived
 * from a computation or summation. It can be used to represent
 * financial totals, quantities, or other cumulative values, depending
 * on the application's context.
 *
 * @var mixed The data type of $totalValue depends on the use case,
 *            often expected to be an integer or a float.
 */
$totalValue = floatval($completedChore['value'] ?? 0);
/**
 * Represents the number of points assigned to each unit or item in a process.
 *
 * This variable is used to define the value or score attributed per individual unit/item
 * and may be utilized for calculations, evaluations, or scoring systems. The value of this
 * variable is typically numeric.
 *
 * @var int|float The points assigned per unit or item.
 */
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
