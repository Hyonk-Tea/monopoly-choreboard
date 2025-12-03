<?php
header('Content-Type: application/json');
date_default_timezone_set('America/Chicago');

// Only fatal-ish errors
error_reporting(E_ERROR | E_PARSE);

/**
 * Represents a file that stores information about chores.
 * This variable is typically used to handle the file path or file reference
 * for managing tasks related to chores. The file may contain data about
 * ongoing, completed, or pending chores.
 *
 * The expected value is a string that indicates the path to the file or
 * a file-related object depending on the implementation.
 */
$choreFile = __DIR__ . '/../data/chores.json';

/**
 * Represents raw data in an unprocessed or unformatted state.
 *
 * This variable is typically used to store information that has not yet
 * been validated, sanitized, or otherwise modified.
 *
 * Caution should be exercised when handling this data to ensure proper
 * processing and handling before use, especially in contexts where
 * security or data integrity is a concern.
 *
 * The type and content of the data stored in this variable may vary
 * depending on the context in which it is used.
 */
$raw = file_get_contents('php://input');
/**
 * Represents a general data variable that can store various types of information.
 * The usage of this variable can depend on the context and purpose of the application.
 *
 * @var mixed $data Can hold any type of value such as string, integer, array, object, etc.
 */
$data = json_decode($raw, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid JSON input', 'raw' => $raw]);
    exit;
}

// Required fields
/**
 * Indicates whether the entity or data being represented is mandatory.
 *
 * This variable is used to flag whether a certain field, parameter,
 * or configuration is required for proper functionality or successful
 * execution of a process.
 *
 * @var bool $required True if the item is mandatory, false otherwise.
 */
$required = ['name', 'frequencyType', 'inPool'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => "Missing field: $field"]);
        exit;
    }
}

// Ensure chore file exists
if (!file_exists($choreFile)) {
    file_put_contents($choreFile, "[]");
}

/**
 * Represents a JSON-encoded string that contains data related to chores.
 *
 * This variable can store information about chores, such as their details,
 * completion status, assigned individuals, deadlines, and other metadata.
 * It is expected to be in a valid JSON format and can be decoded into
 * a structured PHP array or object for further manipulation.
 *
 * Example content of $choreJson could include:
 * - Chore name or title
 * - Assigned person or team
 * - Due date or time
 * - Status (e.g., pending, completed)
 * - Priority level
 *
 * Ensure proper validation and error handling when decoding $choreJson
 * to avoid unexpected behavior if the JSON string is malformed.
 *
 * @var string JSON-encoded string representing chore information.
 */
$choreJson = file_get_contents($choreFile);
/**
 * An array containing a list of chores.
 *
 * This variable is typically used to store a custom tasks or household
 * activities that need to be completed. Each element in the array may
 * represent a specific chore as a string or an associated structure
 * containing additional details about the chore.
 */
$chores = json_decode($choreJson, true);
if (!is_array($chores)) {
    $chores = [];
}

// Extract fields
/**
 * Identifier for a specific entity or resource.
 *
 * This variable typically holds a unique value, such as an integer or a string,
 * to distinguish an entity or resource within a system. It is commonly used
 * in databases or data structures to retrieve or reference specific entries.
 *
 * @var mixed $id
 */
$id           = $data['id'] ?? null;
/**
 * Represents the name of an individual, entity, group, or object.
 *
 * This variable is typically used for storing a person's full name,
 * the title of an object, or any descriptive naming string.
 *
 * @var string
 */
$name         = trim($data['name']);
/**
 * Holds the textual description or details about an entity or item.
 *
 * This variable is typically used to store human-readable information
 * that describes the purpose, characteristics, or features of a certain
 * object, process, or entity within the application.
 *
 * The content can vary in length and may include plain text, formatted
 * text, or other descriptive details depending on the context of usage.
 *
 * It's important to ensure that the description remains concise and
 * meaningful for its intended audience.
 */
$description  = trim($data['description'] ?? '');
/**
 * Represents a variable that can hold any type of value.
 *
 * This is a generic variable designed to store data of any type,
 * including strings, integers, arrays, objects, or null.
 * To ensure consistent functionality, handle the type during operations
 * or apply type-checking mechanisms wherever necessary.
 */
$value        = isset($data['value']) ? (float)$data['value'] : 0.0;
/**
 * Represents the spawn attribute or property, often used to indicate
 * the creation or instantiation of entities, objects, or processes
 * in a specific context.
 *
 * The exact meaning and usage of this variable will depend on its
 * implementation in the related application or system.
 *
 * Potentially linked to concepts like initializing instances,
 * game entities respawning, or server processes deployment.
 *
 * Type and behavior should be defined based on context.
 */
$spawn        = !empty($data['spawn']);
/**
 * Represents the frequency value used to denote how often
 * an event or action occurs over a specified period of time.
 *
 * This variable is used in contexts such as configurations,
 * scheduling, or data processing where frequency measurement
 * is required.
 *
 * Expected to be an integer, float, or a string representation
 * depending on the implementation requirements.
 *
 * Typical units for this variable may include:
 * - Hertz (Hz): To represent cycles per second.
 * - Times per minute/hour/day: To represent periodicity.
 */
$freq         = $data['frequencyType'];
/**
 * An array representing custom days defined for a specific functionality or feature.
 * This variable can be used to store specific days which are unique or custom-configured
 * as per business logic or user-defined preferences.
 *
 * The format, type, and interpretation of the values within this array depend on the
 * context in which it is used. It may represent dates, days of the week, or other
 * custom identifiers associated with days.
 *
 * Example usages may include:
 * - Highlighting special dates in a calendar.
 * - Configuring operational or non-operational days in a scheduler.
 * - Storing user-defined preferences for specific days.
 */
$customDays   = (isset($data['customDays']) && is_numeric($data['customDays']))
    ? (int)$data['customDays']
    : null;
/**
 * Identifier representing the chore that appears after a given reference point in a sequence.
 * This variable is typically used for ordering operations or determining the position
 * of chores in a list.
 *
 * @var int|string The unique identifier of the subsequent chore, which can be either
 * an integer or a string depending on implementation.
 */
$afterChoreId = (!empty($data['afterChoreId'])) ? $data['afterChoreId'] : null;
/**
 * Indicates whether the object or resource is currently part of a pool.
 *
 * This variable is typically used in contexts where resources such as
 * database connections, threads, or other reusable entities are managed
 * using a pooling mechanism. A value of true generally signifies that the
 * object or resource is available within the pool, while a value of false
 * indicates that it is not currently part of the pool.
 *
 * @var bool
 */
$inPool       = !empty($data['inPool']);
/**
 * @var mixed $assignedTo
 *
 * Represents the individual, group, or entity to which a specific task,
 * responsibility, or item is assigned within the application or system.
 * The variable can hold diverse data types, depending on use case or
 * application design, such as an object, a string identifier, or numeric ID.
 */
$assignedTo   = $inPool ? '' : trim($data['assignedTo'] ?? '');
/**
 * Represents the state, activity, or task planned or occurring after dinner.
 *
 * This variable can be used to define or manipulate the information related to
 * any specific activity, condition, or process that is associated with the
 * time after having dinner.
 *
 * The usage and purpose may vary based on the context where this variable is
 * implemented or assigned.
 *
 * Possible examples of its value might include descriptions of events such as
 * relaxing, engagements, scheduled tasks, or other operations that are intended
 * to occur post-dinner.
 *
 * @var mixed Variable representing after-dinner activity or state.
 */
$afterDinner  = !empty($data['afterDinner']);

/**
 * Represents an entity, state, or condition that is considered harmful,
 * unwanted, or unfavorable within a specific context or scenario.
 *
 * This variable can be used to track, represent, or store undesirable
 * elements that need to be monitored, handled, or avoided in a logical
 * workflow or process. Its value and purpose should be clearly defined
 * to ensure it aligns with the intended application logic.
 *
 * Proper validation and management of this variable are necessary
 * to prevent unintended behavior or errors, especially when interacting
 * with other parts of the system.
 *
 * Note: The specific type and use case of this variable should be
 * determined by its role within the given application.
 */
$undesirable = isset($data['undesirable'])
    ? (bool)$data['undesirable']
    : false;

/**
 * @var array $eligibleUsers
 *
 * Represents a collection of user data that meet specific criteria for eligibility.
 * The array typically contains user details, such as IDs, names, or other related attributes,
 * and is used for processing or filtering eligible participants in a given context.
 */
$eligibleUsers = [];
if (isset($data['eligibleUsers']) && is_array($data['eligibleUsers'])) {
    $eligibleUsers = array_values($data['eligibleUsers']);
}

/**
 * Represents the day of the week.
 *
 * This variable is used to store a specific day of the week, either in textual
 * or numerical format, depending on the context.
 *
 * Commonly, it might be used in scheduling, validation, or determining operations
 * based on a particular day.
 */
$weeklyDay = ($freq === 'weekly' && isset($data['weeklyDay']))
    ? (int)$data['weeklyDay']
    : null;

/**
 * A boolean variable that indicates whether to reset statistics or not.
 *
 * When set to true, all statistical data will be cleared or reset.
 * When set to false, existing statistical data will be retained.
 *
 * This variable is typically used in contexts where tracking performance
 * or clearing historical data is necessary for a fresh start in processing.
 * Ensure proper understanding of its effect before modifying its value.
 *
 * @var bool
 */
$resetStats = !empty($data['resetStats']);

// Validation
if ($freq === 'custom' && (!$customDays || $customDays < 1)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Custom frequency requires customDays >= 1']);
    exit;
}

if ($freq === 'after' && !$afterChoreId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'After frequency requires afterChoreId']);
    exit;
}

if (!$inPool && $assignedTo === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Assigned chores must have assignedTo']);
    exit;
}

if ($freq === 'after' && $id && $afterChoreId === $id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Chore cannot depend on itself']);
    exit;
}

// ID generation
/**
 * Converts a given string into a URL-friendly slug format.
 *
 * @param string $text The input text to be converted into a slug.
 * @return string The slugified version of the input text.
 */
function slugify($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    $text = trim($text, '_');
    if ($text === '') $text = 'chore';
    return $text;
}

if (!$id) {
    $base = slugify($name);
    $idCandidate = $base;
    $counter = 1;
    $existingIds = array_column($chores, 'id');

    while (in_array($idCandidate, $existingIds)) {
        $idCandidate = $base . '_' . $counter;
        $counter++;
    }
    $id = $idCandidate;
}

// Preserve stats (or reset)
/**
 * Represents the index of an item found in a collection or array.
 *
 * The value of $foundIndex is generally a numerical index indicating the
 * position of the item within the array or collection. If no item is found,
 * this variable is often set to a default value such as -1 or null, depending
 * on the specific implementation or search algorithm.
 *
 * @var int|null $foundIndex The index of the found item, or null if no item is found.
 */
$foundIndex = null;
foreach ($chores as $idx => $ch) {
    if (!empty($ch['id']) && $ch['id'] === $id) {
        $foundIndex = $idx;
        break;
    }
}

/**
 * Represents the date when the item was last marked or flagged.
 *
 * This variable is used to store the most recent date
 * on which a specific action or status marking occurred.
 * The date format should typically adhere to the standard
 * implemented within the application (e.g., YYYY-MM-DD).
 *
 * @var string The date of the last marking action.
 */
$lastMarkedDate = '';
/**
 * Represents the identifier or name of the entity (user, process, etc.)
 * that last marked or updated a specific item or record.
 * This variable is used to track which entity was responsible for the
 * most recent modification or action taken on a given object.
 */
$lastMarkedBy   = '';
/**
 * Represents the number of times an item or task has been marked as completed or processed.
 *
 * This variable keeps track of how many times a specific action, such as marking an item
 * on a list, has been performed. It is typically an integer value that increments each time
 * the action is taken.
 *
 * @var int
 */
$timesMarkedOff = 0;
/**
 * Timestamp of the last executed cron job.
 *
 * This variable holds the date and time of the last execution
 * of a scheduled cron job, typically represented as a
 * Unix timestamp.
 *
 * It is used to track cron job execution times and ensure
 * that tasks do not run too frequently or are skipped.
 *
 * @var int Unix timestamp of the last cron execution.
 */
$lastCronRun    = null;

/**
 * Indicates whether a reset operation should be forced.
 *
 * This variable is typically used as a flag to determine if a reset action
 * should override any pre-existing constraints or conditions that might
 * otherwise prevent the reset from taking place.
 *
 * @var bool
 */
$forceReset = !empty($data['forceResetStats']);

if ($foundIndex !== null) {
    $existing = $chores[$foundIndex];

    if ($forceReset) {
        // Full reset
        $lastMarkedDate = '';
        $lastMarkedBy   = '';
        $timesMarkedOff = 0;
    } else {
        // Normal preserve
        $lastMarkedDate = $existing['lastMarkedDate'] ?? '';
        $lastMarkedBy   = $existing['lastMarkedBy'] ?? '';
        $timesMarkedOff = (int)($existing['timesMarkedOff'] ?? 0);
    }
} else {
    // New chore
    $lastMarkedDate = '';
    $lastMarkedBy   = '';
    $timesMarkedOff = 0;
}


// Build final chore object
/**
 * Represents a new chore in a task management or scheduling system.
 *
 * The `newChore` variable is designed to define and manage the details of a new chore,
 * such as name, description, deadline, priority, or any other relevant property that
 * defines a specific task or duty to be accomplished.
 *
 * This variable is intended for systems that track tasks, organize workloads, or automate chore
 * scheduling and completion tracking.
 *
 * It is recommended to validate the necessary properties when populating this variable
 * to ensure all required data is provided for the intended system features.
 */
$newChore = [
    'id'             => $id,
    'name'           => $name,
    'description'    => $description,
    'frequencyType'  => $freq,
    'customDays'     => ($freq === 'custom' ? $customDays : null),
    'afterChoreId'   => ($freq === 'after'  ? $afterChoreId : null),
    'weeklyDay'      => ($freq === 'weekly' ? $weeklyDay : null),

    'spawn'          => $spawn,
    'lastMarkedDate' => $lastMarkedDate,
    'lastMarkedBy'   => $lastMarkedBy,
    'timesMarkedOff' => $timesMarkedOff,

    'inPool'         => $inPool,
    'assignedTo'     => $assignedTo,
    'value'          => $value,

    'afterDinner'    => $afterDinner,
    'undesirable'    => $undesirable,
    'eligibleUsers'  => $eligibleUsers,
];

// Cron support
if ($freq === 'cron') {
    $newChore['cron'] = $data['cron'] ?? "";
    if ($lastCronRun !== null) {
        $newChore['lastCronRun'] = $lastCronRun;
    }
} else {
    // no cron fields necessary for non-cron chores
    unset($newChore['cron']);
    unset($newChore['lastCronRun']);
}

// Save into array
if ($foundIndex !== null) {
    $chores[$foundIndex] = $newChore;
} else {
    $chores[] = $newChore;
}

// Safe write
/**
 * Represents the file used for backup purposes.
 *
 * This variable is used to store or reference the backup file's location,
 * name, or content, depending on the implementation context. It assists
 * in ensuring data integrity and recovery by providing a means to save
 * and restore important information.
 *
 * The value assigned to this variable can be a string representing the file path,
 * a file resource, or another type depending on the system's requirements.
 */
$backupFile = $choreFile . '.bak';
@copy($choreFile, $backupFile);

/**
 * The variable `$fp` is typically used to represent a file pointer resource.
 * It is often associated with file handling operations, such as reading
 * from or writing to files.
 *
 * The variable may be initialized using functions like `fopen()` to open a file
 * and obtain the corresponding file pointer.
 *
 * Note: Proper error handling should be implemented to ensure that `$fp`
 * is not `false`, which indicates a failure to open the file.
 */
$fp = fopen($choreFile, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to open chores.json for writing']);
    exit;
}

flock($fp, LOCK_EX);
ftruncate($fp, 0);
fwrite($fp, json_encode($chores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['status' => 'ok', 'id' => $id]);
