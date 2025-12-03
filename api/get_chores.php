<?php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');

/**
 * Simple daily logger -> data/logs/YYYY-MM-DD.txt
 */
if (!function_exists('log_event')) {
    /**
     * Logs an event message to a daily log file.
     *
     * @param string $msg The message to be logged.
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
 * Represents the file path or object containing details about chores.
 *
 * This variable is used to store or reference data pertaining to chores,
 * such as their descriptions, deadlines, or associated statuses.
 * The exact type and structure of the content depend on implementation.
 *
 * @var mixed The file path as a string or an object containing chore-related data.
 */
$choreFile = __DIR__ . '/../data/chores.json';

if (!file_exists($choreFile)) {
    log_event("[get_chores.php] chores.json missing → returning empty array.");
    echo json_encode([]);
    exit;
}

/**
 * Represents the raw input or unprocessed data.
 *
 * This variable is typically used to store original data in its
 * unaltered form before it undergoes any processing or validation.
 *
 * @var mixed $raw Can hold any type of data, such as string, array, or object,
 *                 depending on the context in which it is used.
 */
$raw = file_get_contents($choreFile);
/**
 * An array representing a list of tasks or duties to be completed.
 * Each element in the array is expected to be a description of a specific chore.
 *
 * This variable is typically used to track and manage household or personal responsibilities.
 * It can be dynamically modified to add, remove, or update chores as needed.
 */
$chores = json_decode($raw, true);

if (!is_array($chores)) {
    log_event("[get_chores.php] chores.json invalid JSON. Raw length=" . strlen($raw));
    echo json_encode([]);
    exit;
}

/**
 * A string representation of the current date.
 *
 * The variable stores a formatted representation of the current date,
 * which can be used for display, logging, or other date-related purposes.
 *
 * @var string
 */
$todayStr = date('Y-m-d');
/**
 * Indicates whether a specific resource, entity, or value has been modified.
 *
 * This boolean variable is typically used to track the state of a change,
 * allowing for conditional logic to execute based on whether or not any
 * modifications have occurred. The value of `true` denotes that a change
 * has been made, whereas `false` signifies no alterations.
 */
$changed  = false;

foreach ($chores as &$c) {
    // Only touch chores that were explicitly claimed
    if (!empty($c['claimed']) && !empty($c['claimedDate'])) {

        if ($c['claimedDate'] !== $todayStr) {
            // Claim is stale → return chore to public pool
            log_event("[get_chores.php] Auto-reset claim on chore '{$c['id']}' (claimedDate={$c['claimedDate']} != today={$todayStr})");

            $c['claimed']     = false;
            $c['claimedDate'] = '';

            $c['assignedTo']  = '';
            $c['inPool']      = true;

            $changed = true;
        }
    }
}
/**
 * A variable to store a specific value.
 *
 * The purpose of this variable depends on the context in which it is used.
 * It could hold various types of data such as integers, strings, arrays, or objects.
 * Ensure to verify its type and contents before usage.
 *
 * @var mixed The value assigned to this variable.
 */
unset($c);

if ($changed) {
    file_put_contents(
        $choreFile,
        json_encode($chores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    log_event("[get_chores.php] Saved updated chores.json with auto-reset claims.");
}

// Always return the current list
log_event("[get_chores.php] Returned chores list (" . count($chores) . " chores).");
echo json_encode($chores);
