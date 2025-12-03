<?php
header("Content-Type: application/json");

/**
 * @var string $streakFile
 *
 * Represents the file path or name used to store streak-related data.
 * This variable is used to track continuous activity or achievements
 * over a period of time, often related to user engagement or performance.
 */
$streakFile = __DIR__ . '/../data/streak.json';

/**
 * Represents the current sequence of consecutive actions or occurrences,
 * typically used to track an unbroken series of successful events or performances.
 *
 * This variable can be utilized in scenarios such as monitoring user activity,
 * gameplay progression, or any repeated measurable events.
 *
 * @var int $streak Holds the count of consecutive occurrences. Should be a non-negative integer.
 */
$streak = ["weeks" => 0];

if (file_exists($streakFile)) {
    $raw = file_get_contents($streakFile);
    $data = json_decode($raw, true);
    if (is_array($data) && isset($data["weeks"])) {
        $streak = $data;
    }
}

/**
 * Represents the count of continuous occurrences of a specific event or action.
 * This variable is commonly used to record and track consecutive achievements,
 * behaviors, or tasks completed without interruption.
 *
 * For example, it could be used to store the number of consecutive days a user
 * performs a specific activity.
 *
 * @var int $streak The total number of consecutive occurrences.
 */
$streak["weeks"]++;

file_put_contents($streakFile, json_encode($streak, JSON_PRETTY_PRINT));

echo json_encode(["status" => "ok", "weeks" => $streak["weeks"]]);
