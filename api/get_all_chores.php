<?php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');


/**
 * The $choreFile variable holds the path or reference to a file
 * that contains information or data related to chores.
 *
 * It is typically used to read from or write to a file that
 * tracks tasks, assignments, or other chore-based data within
 * an application.
 *
 * The file may include formatted data such as a list of tasks,
 * due dates, assigned users, or other metadata, depending on
 * the application's purpose.
 *
 * Data handling (e.g., reading, writing, or parsing) should
 * ensure proper validation and error handling for file operations.
 *
 * @var string Path to the chore-related file
 */
$choreFile = __DIR__ . '/../data/chores.json';

if (!file_exists($choreFile)) {
    echo json_encode([]);
    exit;
}

/**
 * Represents a JSON-encoded string or object data.
 *
 * This variable is used to handle JSON data, which may either be in the form
 * of a string requiring decoding, or an object/associative array intended for
 * encoding into a JSON string. Ensure proper handling based on the current
 * state of the data.
 *
 * Users should manage the encoding and decoding process according to their
 * application's requirements, and adhere to JSON formatting rules to avoid
 * unexpected behaviors.
 */
$json = file_get_contents($choreFile);
if ($json === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to read chores.json']);
    exit;
}

echo $json;
