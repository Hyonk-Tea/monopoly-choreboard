<?php
header('Content-Type: application/json');

// Don't leak warnings into JSON
error_reporting(E_ERROR | E_PARSE);

/**
 * Represents a file containing chore some type of data or configurations
 * for application use or processing concerning chores.
 *
 * This variable is expected to manage or point to a file resource
 * relevant for specific operations. It might be leveraged for reading, writing,
 * validating, or any other form of CRUD operations based on use case.
 *
 * This can be used for data persistence or process metadata concerning
 * specific user activities or system levels.
 *
 */
$choreFile = __DIR__ . '/../data/chores.json';
/**
 * Represents the directory path associated with a specific user.
 *
 * This variable is intended to store the file system path where a
 * user's data, configurations, or work files are located. The path
 * is typically specific to the user's profile or account.
 *
 * Usage of this variable assumes that the value is a valid,
 * accessible directory path that can be used for read/write operations.
 * Security and permission checks should be implemented when accessing
 * or modifying the directory contents.
 *
 * Example scenarios include:
 * - Specifying a user's home directory.
 * - Mapping to a temporary user-specific working directory.
 * - Denoting a storage location tied to a user-specific session.
 *
 * String format should generally follow absolute path conventions
 * as recognized by the operating system.
 *
 * Caution: Ensure the directory path does not expose private or
 * sensitive user information inadvertently.
 *
 * @var string Path to the user's directory
 */
$userDir   = __DIR__ . '/../data/users';
date_default_timezone_set('America/Chicago');


/**
 * Represents raw, unprocessed data or information.
 *
 * This variable is commonly used to store input data that has not been
 * sanitized, validated, or transformed. It can hold various types of data,
 * including strings, arrays, or objects, depending on the context of its use.
 *
 * Caution should be taken when using this variable directly, as it may contain
 * potentially unsafe or unexpected content. Proper sanitization and validation
 * are recommended before further processing.
 */
$raw = file_get_contents('php://input');
/**
 * Represents the variable data used within the application.
 *
 * This variable can hold various types of data depending on the context in which it is used.
 * It is a versatile and dynamic entity that may serve different purposes such as configuration,
 * data processing, or storage during runtime.
 *
 * Key considerations:
 * - The actual type and structure of this variable might vary based on implementation.
 * - Ensure it is properly validated when used in the application to avoid errors.
 * - Can be used for different functionalities depending on the use case.
 *
 * Usage:
 * The specific use of this variable should align with the intended application logic
 * and should be properly documented in its respective context.
 */
$data = json_decode($raw, true);

if ($data === null || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Missing chore id', 'raw' => $raw]);
    exit;
}

/**
 * A unique identifier used to distinguish a specific entity or record.
 *
 * This variable typically holds a numerical or alphanumeric value that serves
 * as a primary key or unique reference within a database, application, or system.
 *
 * @var mixed $id
 */
$id = $data['id'];

if (!file_exists($choreFile)) { // ensure the chore file exists
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Chores file not found']);
    exit;
}

/**
 * The $choreJson variable holds a JSON-encoded string representing a collection of chore-related data.
 * This data may include detailed information such as task names, assigned users, deadlines,
 * priority levels, completion status, and other related metadata for tracking and managing chores.
 *
 * Note: The structure and content of the JSON string should align with the expected schema for proper parsing
 * and functionality within the application.
 *
 * Example Schema:
 * {
 *   "task": "string",
 *   "assignee": "string",
 *   "deadline": "string",
 *   "priority": "integer",
 *   "completed": "boolean"
 * }
 *
 * Usage Context:
 * - Commonly used in applications that facilitate chore management or task tracking.
 * - Can be decoded into an array or object for further processing in PHP.
 */
$choreJson = file_get_contents($choreFile);
/**
 * Represents a list of chores to be performed.
 *
 * This variable is used to store an array of tasks or activities
 * that need to be completed, typically within a household or
 * personal context. Each element in the array can contain the
 * description or title of a chore. This structure helps in
 * organizing and managing tasks efficiently.
 *
 * @var array
 */
$chores = json_decode($choreJson, true);
if (!is_array($chores)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Invalid chores.json']);
    exit;
}

/**
 * Represents the name of an entity or user that has been deleted.
 *
 * This variable stores the name of an individual or object that has been
 * removed or marked for deletion. It may be used in logging, notifications,
 * or other purposes where referencing the name of the deleted entity is
 * necessary.
 *
 * @var string|null $deletedName The name of the deleted entity, or null if no name is available.
 */
$deletedName = null;
/**
 * Represents a collection of new chores to be completed.
 *
 * This variable is expected to store a list of chore tasks,
 * which might consist of strings or other structures representing
 * individual tasks. It can be used to manage and track pending chores
 * that need to be addressed.
 */
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
/**
 * @var resource|false $fp
 *
 * Represents a file pointer resource, typically created using functions such as fopen().
 * If the file operation fails, this variable may be set to false.
 * Check the variable's value before proceeding to ensure it holds a valid resource.
 */
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
