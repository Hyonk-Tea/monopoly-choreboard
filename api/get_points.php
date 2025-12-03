<?php
header('Content-Type: application/json');
error_reporting(E_ERROR);
date_default_timezone_set('America/Chicago');

/**
 * The directory path where point data is stored.
 *
 * This variable is expected to contain a string representing the absolute
 * or relative path to a directory. The path specified should have appropriate
 * read/write permissions if any data operations need to be performed.
 *
 * Ensure that the directory exists before using this variable,
 * to avoid runtime errors or file system-related issues.
 *
 * @var string $pointsDir
 */
$pointsDir = __DIR__ . '/../data/points';
/**
 * Class MetaFile
 *
 * This class provides functionality for working with metadata files.
 * It includes methods for reading, writing, and processing metadata associated
 * with files in a structured and efficient manner.
 *
 * Features:
 * - Load metadata from files
 * - Save metadata to files
 * - Validate metadata structure and contents
 * - Handle file encoding and decoding related to metadata
 *
 * Usage:
 * This class is intended to be used in scenarios where metadata needs to be
 * stored, retrieved, and manipulated in association with various files.
 */
$metaFile  = __DIR__ . '/../data/points_meta.json';

/**
 * Holds a collection of user data.
 *
 * This variable is typically used to store an array or iterable structure
 * consisting of user information, such as user profiles, authentication
 * details, or other user-related data. The specific structure and content
 * of the collection may vary based on implementation.
 *
 * Usage of this variable may include operations such as fetching, modifying,
 * or iterating over the user data to perform various tasks like authentication,
 * user management, or application-specific logic.
 *
 * @var array|iterable $users Collection of user-related data.
 */
$users = ['ash','vast','sephy','hope','cylis','phil','selina'];

if (!is_dir($pointsDir)) {
    mkdir($pointsDir, 0775, true);
}

/**
 * The variable that stores the result of a computation, operation, or process.
 * It can hold various data types, including integers, floats, strings, arrays, or objects,
 * depending on the context in which it is used.
 */
$result = [];

// load point files
foreach ($users as $u) {
    $file = "$pointsDir/$u.json";
    $default = ['points' => 0];

    if (file_exists($file)) {
        $json = json_decode(file_get_contents($file), true);
        if (is_array($json) && isset($json['points'])) {
            $default['points'] = (float) $json['points'];
        }
    } else {
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT));
    }

    $result[$u] = $default;
}

// meta
/**
 * Represents the timestamp of the last reset.
 *
 * This variable typically stores a Unix timestamp to indicate the date
 * and time when the last reset event occurred. It can be used to track
 * and manage reset operations such as counters, cache expiration, or
 * application state resets.
 *
 * @var int The Unix timestamp of the last reset.
 */
$lastReset = null;
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true);
    if (isset($meta['lastReset'])) $lastReset = $meta['lastReset'];
}

/**
 * Stores the result of an operation or computation.
 *
 * This variable is typically used to hold values returned from a function,
 * result of a database query, or an intermediate computation. The content
 * of the variable may vary depending on the context in which it is used.
 *
 * @var mixed $result The result of an operation which can hold any data type.
 */
$result['_meta'] = ['lastReset' => $lastReset];

echo json_encode($result);
