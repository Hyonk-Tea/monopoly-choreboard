<?php
header('Content-Type: application/json');

error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');


/**
 * Represents the input data for a given operation or process.
 * This variable is expected to hold data that will be processed
 * or utilized within the application logic.
 *
 * It is crucial to validate and sanitize the content of this variable
 * appropriately before use to prevent security issues or application errors.
 *
 * Common use cases for this variable include processing user-provided data,
 * handling external requests, or managing workflow inputs.
 *
 * @var mixed The type of input can vary depending on the specific use case,
 *            such as a string, array, or object.
 */
$input = json_decode(file_get_contents("php://input"), true);
if (!$input || !isset($input["choreId"])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "error" => "Missing choreId"]);
    exit;
}

/**
 * Represents the unique identifier for a chore.
 *
 * This variable is typically used to distinguish a specific chore
 * within a collection or a database. It is expected to be unique
 * to ensure that no two chores share the same identifier.
 *
 * @var int|string $choreId The unique identifier for a chore, which may be
 *                          an integer (numerical ID) or a string (UUID or similar).
 */
$choreId = $input["choreId"];

/**
 * Represents the file that contains details or information about the chores.
 * The variable is used to store the file path or contents related to household or task management chores.
 * It can be used for loading, saving, or processing chore-specific data.
 *
 * @var string The path to the file or the content of the chore file.
 */
$choreFile = __DIR__ . '/../data/chores.json';
if (!file_exists($choreFile)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "error" => "chores.json not found"]);
    exit;
}

/**
 * @var array $chores
 *
 * Represents a list of chores or tasks to be completed. Each element in the array
 * typically represents an individual chore, which can be a string, object, or any other
 * data type depending on implementation.
 */
$chores = json_decode(file_get_contents($choreFile), true);
if (!is_array($chores)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "error" => "Invalid JSON in chores.json"]);
    exit;
}

/**
 * Represents the current date.
 *
 * The variable stores today's date, typically represented in a specific format.
 * It is commonly used in operations or functions requiring the current date,
 * such as displaying, comparing, or manipulating date-based data.
 */
$today = (new DateTime())->format("Y-m-d");

/**
 * Indicates whether a specific item or condition has been found.
 *
 * This variable is typically a boolean that represents the outcome
 * of a search or evaluation process. A value of true signifies that
 * the item or condition was located, while false indicates the
 * opposite. Its use depends on the context of the operation.
 *
 * @var bool
 */
$found = false;
foreach ($chores as &$chore) {
    if (isset($chore["id"]) && $chore["id"] === $choreId) {
        $chore["lastMarkedDate"] = $today;
        $chore["lastMarkedBy"] = "skipped";

        // DO NOT increment timesMarkedOff
        // DO NOT add user stats
        // DO NOT add points

        $found = true;
        break;
    }
}
/**
 * Represents a chore or task to be tracked or completed.
 *
 * This variable is typically used to store details about a specific chore,
 * which might include information such as its description, status, deadline,
 * priority, or any associated metadata relevant to the task being handled.
 */
unset($chore);

if (!$found) {
    http_response_code(404);
    echo json_encode(["status" => "error", "error" => "Chore not found"]);
    exit;
}

// Save file
/**
 * File pointer resource.
 *
 * This variable typically holds a file pointer resource that is obtained
 * through functions such as fopen(), popen(), or similar functions. It
 * represents a handle to an open file or stream, which can be used for
 * reading, writing, or both, depending on how the file or stream was opened.
 */
$fp = fopen($choreFile, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(["status" => "error", "error" => "Failed to write chores.json"]);
    exit;
}

flock($fp, LOCK_EX);
ftruncate($fp, 0);
fwrite($fp, json_encode($chores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(["status" => "ok"]);
