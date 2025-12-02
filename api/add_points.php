<?php
header("Content-Type: application/json");

date_default_timezone_set('America/Chicago');

// --------------------------------------------------
// LOGGING
// --------------------------------------------------
/**
 * Logs an event message to a daily log file.
 *
 * @param string $msg The message to log.
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
 * Represents the input provided by the user or another source.
 *
 * This variable is intended to store raw or processed data that is
 * used as an input to a function, method, or process. The specific
 * type, format, and validation of the input may vary depending on
 * the context in which it is used.
 *
 * It is recommended to validate and sanitize the data stored in
 * this variable before using it to prevent unexpected behavior or
 * security vulnerabilities.
 *
 * @var mixed $input The data input, which can vary in type and structure.
 */
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    log_event("[add_points.php] ERROR: Invalid JSON input: " . file_get_contents("php://input"));
    echo json_encode(["status" => "error", "error" => "Invalid JSON"]);
    exit;
}

/**
 * The $user variable represents a user in the system.
 * This may contain user-related data, such as identification,
 * authentication information, or associated properties.
 * Commonly used to access or manipulate the state and attributes
 * of a particular user within an application.
 */
$user = $input["user"] ?? null;
/**
 * Represents a value that can hold any type of data.
 * It is a general-purpose variable that can store a number, string, array, object, or any other type of PHP data.
 * The specific type and purpose of this variable depend on its usage context.
 */
$value = floatval($input["value"] ?? 0);

if (!$user || $value <= 0) {
    log_event("[add_points.php] ERROR: Invalid parameters. user={$user}, value={$value}");
    echo json_encode(["status" => "error", "error" => "Invalid parameters"]);
    exit;
}

// --------------------------------------------------
// FILE PATHS
// --------------------------------------------------

// Path: data/points/{user}.json
/**
 * The file path or name where points data is stored or retrieved.
 * This variable is typically used to reference a file for reading or
 * writing data related to points, such as user scores, game points,
 * or other numeric data representations.
 *
 * @var string $pointsFile The path or name of the points file.
 */
$pointsFile = __DIR__ . "/../data/points/" . $user . ".json";

// Ensure directory exists
/**
 * Represents a directory path to be used for file system operations.
 *
 * This variable is expected to contain the path of a directory as a string.
 * It can be used for tasks such as reading directory contents, checking
 * directory existence, or writing files to the specified directory.
 */
$dir = dirname($pointsFile);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// --------------------------------------------------
// LOAD OR INIT USER POINTS
// --------------------------------------------------
if (!file_exists($pointsFile)) {
    $data = ["points" => 0];
} else {
    $raw = file_get_contents($pointsFile);
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = ["points" => 0];
}

// --------------------------------------------------
// UPDATE
// --------------------------------------------------
/**
 * A variable typically used to denote or represent a state, condition, or
 * time that occurs prior to a specific point or event.
 *
 * This variable may store values such as a timestamp, boolean flag,
 * string description, or other data indicating a "before" state.
 * Its exact type and usage depend on the context in which it is used.
 */
$before = $data["points"];
/**
 * Variable to hold data.
 *
 * This variable is a container for storing various types of data.
 * It can be utilized for dynamic data assignments and is capable
 * of holding mixed types of values depending on its use case.
 */
$data["points"] += $value;
/**
 * Specifies the point in time or reference after which an event, process or action should take place.
 * Often used to define a condition or sequence in relation to a particular timestamp, action, or event.
 * The value can typically be a string representation of a date/time, a timestamp, or other comparable data.
 *
 * @var mixed $after
 */
$after = $data["points"];

// Save
file_put_contents($pointsFile, json_encode($data, JSON_PRETTY_PRINT));

// --------------------------------------------------
// LOG SUCCESS
// --------------------------------------------------
log_event("[add_points.php] Added {$value} points to {$user}. Before={$before}, After={$after}");

// --------------------------------------------------
// RESPONSE
// --------------------------------------------------
echo json_encode([
    "status" => "ok",
    "user" => $user,
    "added" => $value,
    "newTotal" => $data["points"]
]);
