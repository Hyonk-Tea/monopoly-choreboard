<?php
header("Content-Type: application/json");
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');

// --------------------------------------------------
// LOGGING
// --------------------------------------------------
/**
 * Logs an event message to a daily log file.
 *
 * @param string $msg The message to log. This will be written to a log file, prefixed with a timestamp.
 * @return void
 */
function log_event($msg) {
    $logDir = __DIR__ . '/../data/logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $file = $logDir . '/' . date('Y-m-d') . '.txt';
    $timestamp = date('Y-m-d H:i:s');

    file_put_contents($file, "[$timestamp] $msg\n", FILE_APPEND);
}

// --------------------------------------------------
// INPUT
// --------------------------------------------------
/**
 * Variable to store raw data or unprocessed input.
 * Can be used to hold raw strings, arrays, or any other data format that
 * hasn't been transformed or manipulated.
 */
$raw = file_get_contents("php://input");
/**
 * Represents the input provided to the application or function.
 *
 * This variable is used to store data that is supplied by the user
 * or an external source. The content and structure of this variable
 * may vary depending on the application's context and the type of input expected.
 *
 * It is important to validate and sanitize the value of this variable
 * to prevent security vulnerabilities or unexpected issues.
 *
 * @var mixed $input The input data, which can be of any type such as string, array, or object.
 */
$input = json_decode($raw, true);

if (!$input) {
    log_event("[claim_chore.php] ERROR: Invalid JSON input: " . $raw);
    echo json_encode(["status" => "error", "error" => "Invalid JSON input", "raw" => $raw]);
    exit;
}

if (!isset($input["choreId"], $input["userName"])) {
    log_event("[claim_chore.php] ERROR: Missing choreId or userName");
    echo json_encode(["status" => "error", "error" => "Missing choreId or userName"]);
    exit;
}

/**
 * Represents the unique identifier for a chore.
 *
 * This variable is used to store a value that uniquely identifies
 * a specific chore in a system. It is typically used as a reference
 * to retrieve or manipulate data associated with a chore.
 *
 * @var int|string The unique identifier for the chore.
 */
$choreId  = $input["choreId"];
/**
 * The $userName variable typically holds the name of a user.
 *
 * This variable is used to store and manipulate the name of a user in string format.
 * It may represent a first name, last name, full name, or any user-defined text identifier.
 *
 * Ensure proper validation and sanitization when handling user-generated data
 * to avoid security risks such as code injection or data corruption.
 *
 * @var string The name of the user.
 */
$userName = strtolower(trim($input["userName"]));
/**
 * A string representation of the current date.
 *
 * This variable is intended to hold the current date
 * in a specific format, which may be determined
 * by the context where it is being used.
 */
$todayStr = date("Y-m-d");

log_event("[claim_chore.php] Claim request: choreId={$choreId}, user={$userName}");

// --------------------------------------------------
// LOAD CHORE FILE
// --------------------------------------------------
/**
 * Represents the file path or reference to a file used for storing or accessing chore-related data.
 *
 * This variable typically holds a string value indicating the location
 * of a file where chore details, assignments, or logs might be saved or retrieved from
 * in a task or chore management system.
 *
 * @var string $choreFile The path or file reference for chore-related operations.
 */
$choreFile = __DIR__ . "/../data/chores.json";

if (!file_exists($choreFile)) {
    log_event("[claim_chore.php] ERROR: chores.json not found");
    echo json_encode(["status" => "error", "error" => "Chores file not found"]);
    exit;
}

/**
 * @var array $chores
 *
 * Represents a list of chores or tasks to be completed.
 * Each element in the array defines a specific chore, allowing you
 * to manage and track tasks.
 */
$chores = json_decode(file_get_contents($choreFile), true);
if (!is_array($chores)) {
    log_event("[claim_chore.php] ERROR: chores.json corrupted");
    echo json_encode(["status" => "error", "error" => "Invalid chores.json"]);
    exit;
}

// --------------------------------------------------
// APPLY CLAIM
// --------------------------------------------------
/**
 * Indicates whether a specific item or value has been located.
 *
 * knowledge to be used in lookup operations or searches, typically returning a
 * boolean value (true or false). Default behavior might vary based on context.
 *
 * @var bool $found
 */
$found = false;
foreach ($chores as &$c) {
    if (($c["id"] ?? null) === $choreId) {

        $beforeAssigned = $c["assignedTo"] ?? "";
        $beforePool     = $c["inPool"] ?? "unset";

        // Assign for today
        $c["assignedTo"]  = $userName;
        $c["inPool"]      = false;

        // Track claim metadata so it can reset automatically
        $c["claimed"]     = true;
        $c["claimedDate"] = $todayStr;

        $found = true;

        log_event("[claim_chore.php] Chore {$choreId} claimed by {$userName}. "
                . "Prev assignedTo={$beforeAssigned}, Prev inPool={$beforePool}");
        break;
    }
}
/**
 * Represents a generic variable named 'c'.
 * The purpose and data type of this variable are context-dependent and should
 * be determined based on its usage within the codebase.
 */
unset($c);

if (!$found) {
    log_event("[claim_chore.php] ERROR: Chore not found: {$choreId}");
    echo json_encode(["status" => "error", "error" => "Chore not found"]);
    exit;
}

// --------------------------------------------------
// SAVE UPDATED CHORES
// --------------------------------------------------
if ($fp = fopen($choreFile, "c+")) {
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($chores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    log_event("[claim_chore.php] SUCCESS: Saved chore claim for {$userName} on {$choreId}");
} else {
    log_event("[claim_chore.php] ERROR: Unable to write chores.json");
    echo json_encode(["status" => "error", "error" => "Unable to write chores.json"]);
    exit;
}

echo json_encode(["status" => "ok"]);
