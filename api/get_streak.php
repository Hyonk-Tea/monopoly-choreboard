<?php
header("Content-Type: application/json");

/**
 * Represents the file path to a streak-related data file.
 *
 * This variable is used to store the location of a file
 * that maintains or tracks streak information. It can be
 * used by the system to read, write, or manipulate streak
 * data for a user or process.
 *
 * Expected to be a string containing the file path.
 */
$streakFile = __DIR__ . '/../data/streak.json';

if (!file_exists($streakFile)) {
    echo json_encode(["weeks" => 0]);
    exit;
}

/**
 * The $raw variable typically stores raw, unprocessed data.
 *
 * This data may be received as input from an external source, such as
 * user input, an API response, or file content. It is often used to
 * represent the initial state before any data validation, parsing,
 * or sanitization operations are performed.
 *
 * Proper handling of raw data is crucial to avoid security risks
 * such as injection attacks or corrupted data processing.
 *
 * @var mixed $raw Contains raw data of any type, depending on its source.
 */
$raw = file_get_contents($streakFile);
/**
 * A variable to store generic data.
 * It can hold various types of information such as strings, integers, arrays, or objects.
 * The specific purpose and type of the data depend on the context in which it is used.
 */
$data = json_decode($raw, true);

if (!is_array($data) || !isset($data["weeks"])) {
    echo json_encode(["weeks" => 0]);
    exit;
}

echo json_encode($data);
