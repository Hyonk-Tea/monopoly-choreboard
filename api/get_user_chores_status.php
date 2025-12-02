<?php
header('Content-Type: application/json');
date_default_timezone_set('America/Chicago');

// -----------------------------------------------------------------------------
// CONFIG
// -----------------------------------------------------------------------------
/**
 * Represents the file location or path associated with a chore record or task.
 * This variable is typically intended to store the name, path, or reference
 * to a file used for storing chore details or configurations.
 *
 * @var string Path or name of the file related to chore records.
 */
$choreFile = __DIR__ . '/../data/chores.json';
/**
 * Represents the directory path of the user's folder.
 *
 * This variable stores the absolute or relative path
 * to the location designated for user-specific files
 * or operations within the application. The path
 * should conform to the file system structure of the
 * operating environment.
 *
 * Typical use cases include determining where to save
 * user-generated content, accessing configuration files,
 * or managing user-specific resources.
 *
 * It is expected that the value assigned to this variable
 * is properly sanitized to avoid any potential security
 * vulnerabilities, such as directory traversal attacks.
 *
 * @var string Absolute or relative path to the user's directory.
 */
$userDir   = __DIR__ . '/../data/users';

// -----------------------------------------------------------------------------
// LOAD CHORES
// -----------------------------------------------------------------------------
if (!file_exists($choreFile)) {
    echo json_encode(["error" => "Chores file missing"]);
    exit;
}

/**
 * The $raw variable is intended to hold raw, unprocessed data.
 * It is generally used when dealing with unformatted or unvalidated input,
 * such as data received from an external source (e.g., user input, API response).
 *
 * Developers should ensure proper validation and sanitization are performed
 * before using the content of this variable in sensitive operations or output.
 */
$raw  = file_get_contents($choreFile);
/**
 * An array containing a list of chores.
 *
 * This variable is used to store tasks or responsibilities that need to be performed.
 * Each element in the array represents a specific chore and can be managed accordingly.
 */
$chores = json_decode($raw, true);

if (!is_array($chores)) {
    echo json_encode(["error" => "Invalid chores JSON"]);
    exit;
}

// Supported users
/**
 * @var array $users
 *
 * This variable stores a collection of user data, where each element represents
 * an individual user. The structure of each user's data within the array may
 * vary depending on the application's requirements but typically includes fields
 * such as user ID, name, email, and other related attributes.
 *
 * It is intended for managing and processing user-related information throughout
 * the application.
 */
$users = ["ash", "vast", "sephy", "hope", "cylis", "phil", "selina"];

// Today
/**
 * Represents the current date. This variable is expected to hold the current date
 * and can be used for date-based operations such as comparisons, formatting,
 * or other date-related functionalities.
 *
 * Type: DateTime|string
 */
$today    = new DateTime();
/**
 * Represents the current date as a string.
 *
 * The string is formatted based on the current system date and may
 * follow a specific date format (e.g., 'Y-m-d' for "2023-10-12").
 * Typically used for displaying or processing the date in string form.
 */
$todayStr = $today->format("Y-m-d");

// -----------------------------------------------------------------------------
// HELPERS
// -----------------------------------------------------------------------------

/**
 * An associative array that maps chore identifiers to their corresponding details.
 *
 * This variable is used to manage and store information about various chores/tasks
 * in an organized manner. Each key in the array represents a unique identifier
 * for a chore, while the value contains details related to that chore,
 * such as its description, status, or other metadata.
 *
 * Example structure:
 * - The key can be a string or integer representing the chore ID.
 * - The value can be an array or object containing the chore's attributes.
 */
$choreMap = [];
foreach ($chores as $c) {
    if (!empty($c['id'])) {
        $choreMap[$c['id']] = $c;
    }
}

/**
 * Finds the user with the least number of times assigned to a specific chore from a list of eligible users.
 *
 * @param string $choreName The name of the chore to evaluate.
 * @param array $eligibleUsers An array of user identifiers who are eligible for the chore.
 * @param string $userDir The directory path where user JSON files containing chore statistics are stored.
 *
 * @return string The identifier of the user with the least usage for the specified chore. Defaults to the first eligible user if no data is found.
 */
function findLeastUsedUserForChore($choreName, $eligibleUsers, $userDir) {
    $lowest = PHP_INT_MAX;
    $winner = null;

    foreach ($eligibleUsers as $u) {
        $file = $userDir . '/' . $u . '.json';
        if (!file_exists($file)) {
            $count = 0;
        } else {
            $stats = json_decode(file_get_contents($file), true);
            if (!is_array($stats)) {
                $count = 0;
            } else {
                $count = $stats[$choreName] ?? 0;
            }
        }

        if ($count < $lowest) {
            $lowest = $count;
            $winner = $u;
        }
    }

    return $winner ?? $eligibleUsers[0];
}

/**
 * Determines whether a chore is due based on its frequency type, last marked date,
 * and additional configuration details.
 *
 * @param array $chore An array containing details about the chore, such as its frequency type,
 *                     last marked date, and optional custom configurations like cron expressions
 *                     or dependent chores.
 * @param DateTime $today A DateTime object representing the current date.
 * @param string $todayStr A string representation of the current date in the format 'Y-m-d'.
 * @param array $choreMap An associative array where keys are chore IDs, and values are chore data
 *                        (used for dependent chore calculations).
 *
 * @return bool True if the chore is due today, false otherwise.
 */
function isChoreDue(array $chore, DateTime $today, string $todayStr, array $choreMap): bool
{
    // Frequency type
    $rawFreq = $chore['frequencyType'] ?? 'daily';
    $freq = strtolower(trim((string)$rawFreq));

    // Basic last-marked handling
    $hasLast = !empty($chore['lastMarkedDate']);
    $lastDate = null;
    $diffDays = null;

    if ($hasLast) {
        $lastDate = DateTime::createFromFormat('Y-m-d', $chore['lastMarkedDate']);
        if ($lastDate instanceof DateTime) {
            $diffDays = $lastDate->diff($today)->days;
        } else {
            // Bad date format – treat as never done
            $hasLast = false;
            $lastDate = null;
            $diffDays = null;
        }
    }

    // Already done today -> not due
    if ($hasLast && $chore['lastMarkedDate'] === $todayStr) {
        return false;
    }

    // -----------------------------------------------------------------
    // 1) CRON FREQUENCY
    // -----------------------------------------------------------------
    if ($freq === 'cron') {
        $cronExpr = isset($chore['cron']) ? trim((string)$chore['cron']) : '';
        if ($cronExpr === '') {
            // Empty cron expression – treat as always due (until fixed)
            return true;
        }

        // Use existing cron_eval.php helper
        $cronEvalPath = __DIR__ . '/cron_eval.php';
        if (file_exists($cronEvalPath)) {
            require_once $cronEvalPath;
            if (function_exists('doesCronMatchToday')) {
                return doesCronMatchToday($cronExpr, $today);
            }
        }

        // Fallback
        return false;
    }

    // -----------------------------------------------------------------
    // 2) AFTER-CHORE FREQUENCY
    // -----------------------------------------------------------------
    if ($freq === 'after') {
        $parentId = $chore['afterChoreId'] ?? '';
        if (!$parentId || !isset($choreMap[$parentId])) {
            return false;
        }

        $parent = $choreMap[$parentId];

        // If explicitly reset to empty -> due only when parent done today
        if (empty($chore['lastMarkedDate'])) {
            return (($parent['lastMarkedDate'] ?? '') === $todayStr);
        }

        $parentDoneToday = (($parent['lastMarkedDate'] ?? '') === $todayStr);
        $selfDoneToday   = (($chore['lastMarkedDate']  ?? '') === $todayStr);

        return $parentDoneToday && !$selfDoneToday;
    }

    // -----------------------------------------------------------------
    // 3) WEEKLY FREQUENCY (only due on configured weekday)
    // -----------------------------------------------------------------
    if ($freq === 'weekly') {
        $todayDow = (int)$today->format('w'); // 0=Sun..6=Sat

        $scheduledDow = null;
        if (isset($chore['weeklyDay'])) {
            $scheduledDow = (int)$chore['weeklyDay'];
        }

        if ($scheduledDow < 0 || $scheduledDow > 6) {
            // Invalid weeklyDay -> never due
            return false;
        }

        // Only consider due on the scheduled weekday
        if ($todayDow !== $scheduledDow) {
            return false;
        }

        // Never done and today is the correct weekday -> due
        if (!$hasLast || !$lastDate) {
            return true;
        }

        // Done before: due if 7+ days have passed
        $days = $diffDays ?? $lastDate->diff($today)->days;
        return ($days >= 7);
    }

    // -----------------------------------------------------------------
    // 4) MONTHLY / CUSTOM / DAILY (day-based)
    // -----------------------------------------------------------------
    $freqDays = 1; // default daily
    if ($freq === 'monthly') {
        $freqDays = 30; // simple approximation
    } elseif ($freq === 'custom') {
        $freqDays = isset($chore['customDays']) && (int)$chore['customDays'] > 0
            ? (int)$chore['customDays']
            : 1;
    }

    // Never done -> due
    if (!$hasLast || !$lastDate) {
        return true;
    }

    $d = $diffDays ?? $lastDate->diff($today)->days;
    return ($d >= $freqDays);
}

// -----------------------------------------------------------------------------
// MAIN: DETERMINE IF EACH USER HAS ANY DUE USER-SPECIFIC CHORES
// -----------------------------------------------------------------------------

/**
 * Stores the outcome or output of a specific operation, function, or process.
 *
 * This variable is typically used to hold the result of a computation, database query,
 * API call, or any other operation, and its type and content may vary.
 *
 * The exact usage and data type of $result depend on the context in which it is used.
 *
 * It can represent:
 * - A numerical computation result
 * - A boolean status indicating success or failure
 * - A string or array containing data or response
 * - Other forms of processed or returned data
 */
$result = [];

foreach ($users as $u) {
    $hasChores = false;

    foreach ($chores as $c) {

        // -----------------------------------------------------------------
        // Skip chores that are not part of the user's personal pool
        // -----------------------------------------------------------------

        // 1) Ignore public chores entirely, integration is user-specific only
        if (!empty($c['inPool'])) {
            continue;
        }

        // 2) Ignore after-dinner chores in ALL cases (A, B, C)
        if (!empty($c['afterDinner'])) {
            continue;
        }

        // 3) Skip non-spawning chores
        if (isset($c['spawn']) && $c['spawn'] === false) {
            continue;
        }

        $freqType = $c['frequencyType'] ?? 'daily';

        // -----------------------------------------------------------------
        // CASE A: AFTER-CHORE FOLLOWING PARENT'S DOER
        // -----------------------------------------------------------------
        if ($freqType === 'after') {
            $parentId = $c['afterChoreId'] ?? '';
            if (!$parentId || !isset($choreMap[$parentId])) {
                continue;
            }

            $parent = $choreMap[$parentId];

            // Parent must have been completed today
            if (($parent['lastMarkedDate'] ?? '') !== $todayStr) {
                continue;
            }

            // Automatically assigned to whoever completed the parent
            $assignedToday = strtolower((string)($parent['lastMarkedBy'] ?? ''));
            if ($assignedToday !== $u) {
                continue;
            }

            if (isChoreDue($c, $today, $todayStr, $choreMap)) {
                $hasChores = true;
                break;
            }

            continue;
        }

        // -----------------------------------------------------------------
        // CASE B: UNDESIRABLE CHORE DYNAMIC ASSIGNMENT
        // -----------------------------------------------------------------
        if (!empty($c['undesirable']) && !empty($c['eligibleUsers']) && is_array($c['eligibleUsers'])) {

            // Determine who should get this chore today
            $assignedToday = findLeastUsedUserForChore(
                $c['name'] ?? ($c['id'] ?? 'chore'),
                $c['eligibleUsers'],
                $userDir
            );

            if ($assignedToday !== $u) {
                continue;
            }

            if (isChoreDue($c, $today, $todayStr, $choreMap)) {
                $hasChores = true;
                break;
            }

            continue;
        }

        // -----------------------------------------------------------------
        // CASE C: NORMAL ASSIGNED CHORES (assignedTo == user)
        // -----------------------------------------------------------------
        $assignedTo = strtolower((string)($c['assignedTo'] ?? ''));
        if ($assignedTo !== $u) {
            continue;
        }

        if (isChoreDue($c, $today, $todayStr, $choreMap)) {
            $hasChores = true;
            break;
        }
    }

    // true  -> this user HAS chores due
    // false -> this user has NO chores due
    $result[$u] = $hasChores;
}

echo json_encode($result);
