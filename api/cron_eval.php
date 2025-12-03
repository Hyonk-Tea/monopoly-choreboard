<?php


date_default_timezone_set('America/Chicago');

/**
 * Daily logger — readable output into /data/logs/YYYY-MM-DD.txt
 */
if (!function_exists('log_event')) {
    /**
     * Logs a message to a daily log file with a timestamp.
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
 * Checks whether a given cron expression matches the current date and time.
 *
 * The method evaluates a cron expression consisting of five fields: minute, hour,
 * day of the month, month, and day of the week. It determines if the current time
 * as represented by the provided DateTime object matches the given cron expression.
 *
 * @param string $expr The cron expression to evaluate. It should consist of five space-separated fields.
 * @param DateTime $now The DateTime object representing the current time to compare against the cron expression.
 *
 * @return bool Returns true if the current time matches the cron expression; false otherwise.
 */
function doesCronMatchToday($expr, DateTime $now) {
    if (!$expr) {
        log_event("[CRON] Empty expression — treating as no match");
        return false;
    }

    $exprTrimmed = trim($expr);
    $parts = preg_split('/\s+/', $exprTrimmed);

    if (count($parts) !== 5) {
        log_event("[CRON] Invalid expression '$exprTrimmed' — expected 5 fields, got " . count($parts));
        return false;
    }

    [$min, $hour, $dom, $mon, $dow] = $parts;

    // Current time broken down neatly
    $current = [
        'minute' => intval($now->format('i')),
        'hour'   => intval($now->format('G')),
        'dom'    => intval($now->format('j')),
        'mon'    => intval($now->format('n')),
        'dow'    => intval($now->format('w'))
    ];

    log_event("[CRON] Checking expression: \"$exprTrimmed\"");
    log_event("[CRON] Current time: " . 
              $now->format('Y-m-d H:i') . 
              " (minute={$current['minute']}, hour={$current['hour']}, dom={$current['dom']}, mon={$current['mon']}, dow={$current['dow']})");

    // Each match result
    $rMin  = cronMatchField($min,  $current['minute'], $now);
    $rHour = cronMatchField($hour, $current['hour'],   $now);
    $rDom  = cronMatchDom($dom,    $now);
    $rMon  = cronMatchField($mon,  $current['mon'],    $now);
    $rDow  = cronMatchDow($dow,    $now);

    // For human logs
    $checkSymbol = fn($b) => $b ? 'YES' : 'NO';

    log_event("[CRON] Field results: " .
              "minute=" . $checkSymbol($rMin) . " " .
              "hour="   . $checkSymbol($rHour) . " " .
              "dom="    . $checkSymbol($rDom) . " " .
              "mon="    . $checkSymbol($rMon) . " " .
              "dow="    . $checkSymbol($rDow));

    $final = $rMin && $rHour && $rDom && $rMon && $rDow;

    log_event("[CRON] FINAL RESULT: " . ($final ? "TRUE (match!)" : "FALSE (no match)"));

    return $final;
}


/**
 * Checks if a given `value` matches a cron field expression.
 *
 * @param string $field The cron field expression to evaluate. It can contain:
 *                      - A wildcard '*' to match any value.
 *                      - A list like '1,5,10' to match any of the specified values.
 *                      - A range like '5-10' to match any value within the range.
 *                      - A step like '*/
function cronMatchField($field, $value, DateTime $now) {
    $field = trim($field);
    $value = intval($value);

    if ($field === '*') return true;

    // LIST: "1,5,10"
    if (strpos($field, ',') !== false) {
        foreach (explode(',', $field) as $part) {
            if (cronMatchField(trim($part), $value, $now)) return true;
        }
        return false;
    }

    // STEP: "*/5", "10/5"
    if (strpos($field, '/') !== false) {
        [$base, $step] = explode('/', $field);
        $step = intval($step) ?: 1;

        if ($base === '*') {
            return $value % $step === 0;
        }

        $base = intval($base);
        return ($value - $base) % $step === 0;
    }

    // RANGE: "5-10"
    if (strpos($field, '-') !== false) {
        [$start, $end] = explode('-', $field);
        return $value >= intval($start) && $value <= intval($end);
    }

    // Direct compare
    return intval($field) === $value;
}


/**
 * Determines if the current day of the month matches the provided cron expression field.
 *
 * @param string $field The cron expression field specific to the day of the month (e.g., "15W", "L", or "LW").
 * @param DateTime $now The current date and time used for evaluation.
 * @return bool Returns true if the day of the month matches the cron expression, otherwise false.
 */
function cronMatchDom($field, DateTime $now) {
    $field = trim($field);

    $dom   = intval($now->format('j')); 
    $dow   = intval($now->format('w')); 
    $month = intval($now->format('n'));
    $year  = intval($now->format('Y'));

    // Nearest weekday: "15W"
    if (preg_match('/^(\d+)W$/', $field, $m)) {
        $target = intval($m[1]);
        if ($target < 1 || $target > 31) return false;

        $d = new DateTime("$year-$month-$target");

        if ($d->format('w') == 6) $d->modify('-1 day');
        elseif ($d->format('w') == 0) $d->modify('+1 day');

        return intval($d->format('j')) === $dom;
    }

    // Last day of month: "L"
    if ($field === "L") {
        $last = intval((new DateTime("last day of $year-$month"))->format("j"));
        return $dom === $last;
    }

    // Last weekday of month: "LW"
    if ($field === "LW") {
        $d = new DateTime("last day of $year-$month");
        if ($d->format('w') == 6)      $d->modify('-1 day');
        elseif ($d->format('w') == 0) $d->modify('-2 days');
        return intval($d->format("j")) === $dom;
    }

    return cronMatchField($field, $dom, $now);
}


/**
 * Checks if the given cron day-of-week field matches the current day of the week.
 *
 * @param string $field The cron day-of-week expression (e.g., "5L" for last Friday, "2#1" for the first Tuesday).
 * @param DateTime $now The current date and time.
 *
 * @return bool True if the field matches the current day of the week, otherwise false.
 */
function cronMatchDow($field, DateTime $now) {
    $field = trim($field);
    $dow   = intval($now->format('w'));

    // "5L" → last Friday
    if (preg_match('/^([0-7])L$/', $field, $m)) {
        $targetDow = intval($m[1]);
        if ($targetDow === 7) $targetDow = 0; // treat 7 as Sunday

        $month = intval($now->format('n'));
        $year  = intval($now->format('Y'));

        $d = new DateTime("last day of $year-$month");
        while (intval($d->format('w')) !== $targetDow) {
            $d->modify('-1 day');
        }

        return $d->format('Y-m-d') === $now->format('Y-m-d');
    }

    // "2#1" → first Tuesday
    if (preg_match('/^([0-7])#([1-5])$/', $field, $m)) {
        $targetDow = intval($m[1]);
        if ($targetDow === 7) $targetDow = 0;

        $nth = intval($m[2]);
        $month = intval($now->format('n'));
        $year  = intval($now->format('Y'));

        $d = new DateTime("$year-$month-1");

        // first occurrence
        while (intval($d->format('w')) !== $targetDow) {
            $d->modify('+1 day');
        }

        // move to nth
        if ($nth > 1) {
            $d->modify('+' . ($nth-1) . ' week');
        }

        return $d->format('Y-m-d') === $now->format('Y-m-d');
    }

    // "7" is Sunday
    if ($field === "7") return $dow === 0;

    return cronMatchField($field, $dow, $now);
}
