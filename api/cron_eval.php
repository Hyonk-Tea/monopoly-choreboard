<?php


date_default_timezone_set('America/Chicago');

/**
 * Daily logger — readable output into /data/logs/YYYY-MM-DD.txt
 */
if (!function_exists('log_event')) {
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
 * MAIN CHECK
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


/******************************************************************
 * GENERIC FIELD MATCHER
 ******************************************************************/
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


/******************************************************************
 * DAY OF MONTH MATCH
 ******************************************************************/
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


/******************************************************************
 * DAY OF WEEK MATCH 
 ******************************************************************/
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
