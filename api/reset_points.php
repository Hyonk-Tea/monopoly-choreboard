<?php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');

/**
 * The $pointsDir variable represents the directory path where point data files are stored.
 * This could be used to read, write, or manage files related to point-orientated operations
 * within the application.
 *
 * It is typically defined as a string containing the absolute or relative path
 * to the intended directory.
 */
$pointsDir = __DIR__ . '/../data/points';
/**
 * Represents metadata information associated with a file.
 *
 * This variable is expected to hold metadata details about a file,
 * such as file type, size, creation date, or other associated properties.
 *
 * The exact structure and content of the metadata information stored in
 * this variable may vary based on the needs or implementation context.
 *
 * @var mixed $metaFile The metadata details of the file.
 */
$metaFile  = __DIR__ . '/../data/points_meta.json';

if (!is_dir($pointsDir)) {
    mkdir($pointsDir, 0775, true);
}

/**
 * @var array $files
 *
 * Represents a collection of file information. This variable is typically used
 * for storing details about files, such as file paths, file names, or metadata
 * related to files. It is commonly used in contexts where file handling or
 * processing is required.
 */
$files = glob($pointsDir . '/*.json');
if ($files !== false) {
    foreach ($files as $file) {
        $data = [
            'points' => 0,
            'weekStart' => ""  // deprecated technically but fuck you
        ];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

// Update lastReset meta
/**
 * The $meta variable typically holds metadata associated with a given context.
 * Metadata is often used to provide additional information about a resource,
 * object, or entity.
 *
 * This variable can include key-value pairs representing attributes such as
 * names, descriptions, identifiers, timestamps, or any other contextual information.
 *
 * Use cases of $meta depend on the specific implementation and project requirements,
 * but its general purpose is to offer supplementary data useful for processing,
 * describing, or categorizing the primary content or object involved in the workflow.
 *
 * Note: Ensure that the structure and content of $meta are properly documented
 * and standardized as per the project's requirements.
 */
$meta = [
    'lastReset' => (new DateTime())->format("Y-m-d")
];

file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode(['status' => 'ok']);
