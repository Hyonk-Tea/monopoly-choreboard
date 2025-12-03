<?php
header("Content-Type: application/json");
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');

/**
 * Variable $input
 *
 * Represents the input data provided to the program, which can be of any type
 * depending on the context of its usage. This variable typically holds user input
 * or data passed into a function or script for processing.
 *
 * The specific structure, type, or format of $input should be well-documented
 * in the implementation or its associated function/method to ensure clarity and
 * proper usage.
 *
 * It is recommended to validate and sanitize the value of $input to ensure
 * its compatibility and safety when utilized within the application.
 */
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input["choreId"])) {
    echo json_encode(["status" => "error", "error" => "Missing choreId"]);
    exit;
}

/**
 * Unique identifier for a specific chore.
 *
 * This variable holds the identifier used to distinguish
 * a particular chore from others in a system. It is typically
 * utilized in CRUD operations or when referencing a specific
 * chore in the application.
 *
 * @var int|string
 */
$choreId = $input["choreId"];

/**
 * Represents the file or path related to a chore or task.
 *
 * This variable typically holds the filepath or name of a file
 * associated with a specific chore or task in the application. It
 * may be used for file management, logging, or data storage purposes.
 *
 * Expected to be a string value indicating the path or filename.
 */
$choreFile   = __DIR__ . '/../data/chores.json';
/**
 * The directory path where historical records or files are stored.
 *
 * This variable is typically used to define the location of
 * historical data on the filesystem, which can include logs,
 * backups, or other archived resources. The path should be a string
 * representing a valid directory path relative to the application's
 * base directory or as an absolute path.
 *
 * Ensure that the directory has appropriate read/write permissions
 * for the application to manage its contents effectively.
 *
 * @var string
 */
$historyDir  = __DIR__ . '/../data/history';
/**
 * Directory path where points data or files are stored.
 *
 * This variable typically holds the path to the directory
 * that contains files or information related to points
 * for a specific application functionality. It is used
 * for file management, data reading, or writing operations
 * associated with points.
 *
 * @var string $pointsDir
 */
$pointsDir   = __DIR__ . '/../data/points';

// Ensure directories exist
if (!is_dir($historyDir)) mkdir($historyDir, 0775, true);
if (!is_dir($pointsDir)) mkdir($pointsDir, 0775, true);

/**
 * Represents the file path or file name used to store historical data or logs.
 * This variable typically holds the location of a file where history-related
 * information is saved or retrieved.
 *
 * @var string
 */
$historyFile = $historyDir . "/undo_" . $choreId . ".json";

if (!file_exists($historyFile)) {
    echo json_encode([
        "status" => "error",
        "error" => "No undo history for this chore"
    ]);
    exit;
}

// Load undo snapshot
/**
 * Holds the previous value or state in a sequence or iteration.
 * Can be used to keep track of the last processed or visited item
 * in a loop or an operation.
 *
 * The value of this variable may vary depending on the context where
 * it is used and should be properly initialized before use.
 */
$prev = json_decode(file_get_contents($historyFile), true);
if (!$prev) {
    echo json_encode([
        "status" => "error",
        "error" => "Undo data corrupt"
    ]);
    exit;
}

// Load chores
/**
 * @var array $chores
 *
 * Represents a list of chores or tasks that need to be performed.
 * Each element in the array typically corresponds to a specific chore.
 */
$chores = json_decode(file_get_contents($choreFile), true);

/**
 * Indicates whether a specific search or lookup operation was successful.
 *
 * The $found variable is typically a boolean value that represents the
 * outcome of a search-related operation. A value of true indicates that
 * the desired item or result was found, while false indicates that it was
 * not found.
 */
$found = false;
/**
 * Represents the value associated with a specific chore.
 *
 * This variable is used to store a numeric or string representation
 * of the importance, effort level, or any other metric defining a chore.
 *
 * It can be used to determine the priority or classification
 * of the respective chore in a task management system.
 *
 * Type: mixed
 */
$choreValue = 0;
/**
 * An array that stores the most recently accessed or interacted users.
 *
 * This variable can hold user data such as their IDs, names, or other
 * relevant information. The structure and type of data stored within
 * the array depend on the implementation of the application.
 */
$lastUsers = [];

foreach ($chores as &$chore) {
    if ($chore["id"] === $choreId) {
        // Store chore value for points rollback
        $choreValue = floatval($chore["value"] ?? 0);

        // Determine who originally got the points
        if (!empty($chore["lastMarkedBy"])) {
            $lastUsers = array_map('trim', explode(",", strtolower($chore["lastMarkedBy"])));
        }

        // Restore stats
        $chore["lastMarkedDate"] = $prev["lastMarkedDate"];
        $chore["lastMarkedBy"] = $prev["lastMarkedBy"];
        $chore["timesMarkedOff"] = max(0, intval($prev["timesMarkedOff"]));

        $found = true;
        break;
    }
}
/**
 * Represents a task or responsibility to be completed.
 *
 * The $chore variable is intended to store information related to an activity
 * or duty that needs to be carried out. It may include details such as
 * the name, description, priority level, and status of the chore.
 *
 * This variable can be used in task management systems, to-do lists, or
 * other applications dealing with task tracking and completion.
 */
unset($chore);

if (!$found) {
    echo json_encode(["status" => "error", "error" => "Chore not found"]);
    exit;
}

// ----------------------------------------------
// ROLLBACK POINTS
// ----------------------------------------------
if (!empty($lastUsers) && $choreValue > 0) {
    $pointsEach = $choreValue / count($lastUsers);

    foreach ($lastUsers as $u) {
        if (!$u) continue;

        $file = "$pointsDir/$u.json";
        $info = file_exists($file)
            ? json_decode(file_get_contents($file), true)
            : ["points" => 0];

        if (!is_array($info)) $info = ["points" => 0];

        // Subtract safely (no negatives)
        $info["points"] = max(0, ($info["points"] ?? 0) - $pointsEach);

        file_put_contents($file, json_encode($info, JSON_PRETTY_PRINT));
    }
}

// ----------------------------------------------
// Save updated chores
// ----------------------------------------------
file_put_contents($choreFile, json_encode($chores, JSON_PRETTY_PRINT));

// Delete undo file (single undo)
unlink($historyFile);

echo json_encode(["status" => "ok"]);
