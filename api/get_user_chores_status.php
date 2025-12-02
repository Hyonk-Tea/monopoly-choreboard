<?php
header('Content-Type: application/json');
date_default_timezone_set('America/Chicago');

// -----------------------------------------------------------------------------
// CONFIG
// -----------------------------------------------------------------------------
$choreFile = __DIR__ . '/../data/chores.json';
$userDir   = __DIR__ . '/../data/users';

// -----------------------------------------------------------------------------
// LOAD CHORES
// -----------------------------------------------------------------------------
if (!file_exists($choreFile)) {
    echo json_encode(["error" => "Chores file missing"]);
    exit;
}

$raw  = file_get_contents($choreFile);
$chores = json_decode($raw, true);

if (!is_array($chores)) {
    echo json_encode(["error" => "Invalid chores JSON"]);
    exit;
}

// Supported users
$users = ["ash", "vast", "sephy", "hope", "cylis", "phil", "selina"];

// Today
$today    = new DateTime();
$todayStr = $today->format("Y-m-d");

// -----------------------------------------------------------------------------
// HELPERS
// -----------------------------------------------------------------------------

/**
 * Build chore map by id (used for after-chores).
 */
$choreMap = [];
foreach ($chores as $c) {
    if (!empty($c['id'])) {
        $choreMap[$c['id']] = $c;
    }
}

/**
 * Least-used user for undesirable chores
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
 * Check if a chore is due today, using logic aligned with script.js computeChoreStatus.
 * Returns true if the chore should be considered due (or overdue).
 * Does NOT check spawn, inPool, afterDinner, or assignment, the caller handles that.
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
