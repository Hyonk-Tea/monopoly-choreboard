console.log("%c[ChoreBoard] Starting script.js...", "color:#9cf; font-weight:bold;");

// ---------------------------------------------------------------
// CONSTANTS & GLOBAL STATE
// ---------------------------------------------------------------
/**
 * An array containing a predefined list of user names.
 *
 * This list is immutable and used for scenarios where a specific set
 * of user identifiers is required, such as testing, data population,
 * or lookup operations. The names in this array are represented as
 * strings and serve as unique identifiers for each user.
 *
 * Variable properties:
 * - Type: Array of strings
 * - Purpose: Stores a fixed list of user identifiers
 * - Allowed Values: Predefined human-readable names
 */
const USERS = ["ash", "vast", "sephy", "hope", "cylis", "phil", "selina"];
/**
 * A constant variable representing the base path for an API.
 *
 * This variable can be used as the foundational endpoint for building relative paths to API resources.
 * It helps in maintaining consistency and centralizing the base API path configuration.
 *
 * @constant {string}
 */
const API_BASE = "api";

/**
 * Represents a list of all chores.
 * Can be used to store and manage chores or tasks within an application.
 *
 * @type {Array}
 */
let allChores = [];
/**
 * An object that acts as a container to store chores,
 * where each chore is keyed by a unique identifier.
 *
 * This variable allows easy and direct access to individual
 * chores using their respective ID as the key.
 *
 * The keys are expected to be unique identifiers (e.g., strings or numbers),
 * and the associated values can represent the details of the chore
 * (e.g., an object or data structure containing the description,
 * status, priority, etc.).
 */
let choresById = {};
/**
 * Represents the currently selected chore within a chore tracking or task management system.
 * This variable holds an object or identifier indicating the user's selected chore.
 * It may initially be set to null when no chore is selected.
 *
 * @type {Object|null}
 */
let selectedChore = null;
/**
 * A set object representing the currently selected user(s).
 * This variable can hold unique user identifiers or user-related objects.
 * It is initialized as an empty set and can be modified to add or remove users dynamically.
 */
let selectedUser = new Set();
/**
 * Represents the most recent error encountered during execution.
 * This variable is used to store error information for debugging
 * or error-handling purposes.
 *
 * The value is initially set to null and can be updated with
 * error objects or messages when an error occurs.
 *
 * Expected to be cleared or reset as part of proper error-handling workflow
 * to ensure it doesn't hold stale error information.
 *
 * @type {Error | string | null}
 */
let lastError = null;

console.log(
    "[ChoreBoard Time]",
    "Local:", new Date().toString(),
    "| ISO:", new Date().toISOString()
);
fetch("api/debug_time.php")
    .then(r => r.json())
    .then(t => {
        console.log(
            "%c[PHP Time]",
            "color:#fa0; font-weight:bold;",
            "Local:", t.php_server_time_local,
            "| ISO:", t.php_server_time_iso,
            "| TZ:", t.php_timezone
        );
    });

// ---------------------------------------------------------------
// TOP BANNER (ERROR / POINTS)
// ---------------------------------------------------------------
/**
 * Retrieves the existing banner with the ID "topBanner" or creates and returns a new banner element
 * if it does not already exist. The new banner is styled and added to the top of the document body.
 *
 * @return {HTMLDivElement} The existing or newly created banner element.
 */
function getOrCreateBanner() {
    let banner = document.getElementById("topBanner");
    if (!banner) {
        banner = document.createElement("div");
        banner.id = "topBanner";
        banner.style.padding = "8px 10px";
        banner.style.textAlign = "center";
        banner.style.fontSize = "0.95rem";
        banner.style.fontWeight = "600";
        banner.style.position = "sticky";
        banner.style.top = "0";
        banner.style.zIndex = "10000";
        banner.style.borderBottom = "1px solid #333";
        document.body.prepend(banner);
    }
    return banner;
}

/**
 * Displays an error banner with the provided error message.
 *
 * @param {string} message - The error message to display on the banner.
 * @return {void} This function does not return a value.
 */
function showErrorBanner(message) {
    lastError = message;
    console.error("[ChoreBoard ERROR]", message);
    const banner = getOrCreateBanner();
    banner.style.background = "#922";
    banner.style.color = "#fff";
    banner.textContent = message;
}

/**
 * Clears the last encountered error by setting the lastError variable to null.
 *
 * @return {void} This method does not return a value.
 */
function clearError() {
    lastError = null;
}

/**
 * Displays a banner with user points and changes the appearance based on specific conditions.
 *
 * @param {Object} pointsMap - A mapping of users to their points and meta-information.
 *        This object includes an optional `_meta` property with metadata like `lastReset`.
 * @return {void} This function does not return a value.
 */
function showPointsBanner(pointsMap) {
    if (lastError) return; // don't override error banners

    const banner = getOrCreateBanner();
    banner.style.color = "#eee";

    const meta = pointsMap["_meta"] || {};
    const lastReset = meta.lastReset || null;

    const today = new Date();
    const isSunday = today.getDay() === 0;
    const ymd = toYMD(today);

    const shouldWarn = isSunday && lastReset !== ymd;
    banner.style.background = shouldWarn ? "#a33" : "#181818";

    const parts = USERS.map(u => {
        const info = pointsMap[u] || {};
        const pts = info.points ?? 0;
        return `${u} ${pts}`;
    });

    banner.textContent = parts.join(" | ");
}

// ---------------------------------------------------------------
// SAFE JSON PARSE 
// ---------------------------------------------------------------
/**
 * Safely parses a JSON string, attempting to recover from initial parsing errors by stripping leading noise
 * before retrying the parse. Logs warnings and errors to provide context for failures.
 *
 * @param {string} raw - The raw JSON string to parse.
 * @param {string} contextLabel - Contextual label for logging purposes, indicating the source or intent of the JSON data.
 * @return {*} Returns the parsed JavaScript object or value if successful. Will throw an error and log issues if parsing fails.
 */
function safeJsonParse(raw, contextLabel) {
    try {
        return JSON.parse(raw);
    } catch (e) {
        console.warn(`[ChoreBoard] Initial JSON.parse failed in ${contextLabel}:`, e);

        // Try to strip leading noise like PHP warnings / HTML before the first JSON-looking char
        const firstJsonCharIndex = raw.search(/[{[]|"/);
        if (firstJsonCharIndex > 0) {
            const trimmed = raw.slice(firstJsonCharIndex);
            try {
                const parsed = JSON.parse(trimmed);
                console.warn(`[ChoreBoard] Parsed JSON after stripping leading noise in ${contextLabel}`);
                return parsed;
            } catch (e2) {
                console.error(`[ChoreBoard] Retry JSON.parse failed in ${contextLabel}:`, e2);
            }
        } else {
            console.error(`[ChoreBoard] No JSON-looking characters found in ${contextLabel} response.`);
        }

        throw e;
    }
}

// ---------------------------------------------------------------
// INIT
// ---------------------------------------------------------------
document.addEventListener("DOMContentLoaded", () => {
    console.log("%c[ChoreBoard] DOM Loaded", "color:#0f0");
    initDateDisplay();
    initUserModal();
    loadChores();

    // Auto-refresh page every 15 minutes
    const FIFTEEN_MINUTES = 15 * 60 * 1000;
    setInterval(() => {
        console.log("[ChoreBoard] Auto-refreshing page");
        window.location.reload();
    }, FIFTEEN_MINUTES);
});

// ---------------------------------------------------------------
// SECRET PIANO TILES GAME
// ---------------------------------------------------------------

/**
 * Tracks the number of times a banner has been tapped.
 * This variable is used to keep count of user interactions with the banner.
 * It is initialized to 0 and can be incremented as needed.
 *
 * @type {number}
 */
let bannerTapCount = 0;
/**
 * Tracks the timestamp of the last interaction with a banner element.
 * This variable is initialized to 0 and is typically updated with the
 * current timestamp (in milliseconds) whenever a user taps or interacts
 * with the banner. It can be used to implement functionality like
 * throttling interactions or detecting double-tap gestures.
 *
 * @type {number}
 */
let bannerLastTap = 0;

/**
 * Enables a triple-tap functionality on the banner element.
 * When the banner is triple-tapped within a short time interval, it navigates to a specified URL.
 * The method ensures that text selection, highlighting, and accidental scrolls are prevented during interaction.
 *
 * @return {void} Does not return any value.
 */
function enableBannerTripleTap() {
    const banner = getOrCreateBanner();
    if (!banner) {
        console.error("[ChoreBoard] Triple-tap: banner not found.");
        return;
    }

    // Avoids selecting text
    banner.style.userSelect = "none";
    banner.style.webkitUserSelect = "none";
    banner.style.touchAction = "manipulation";
    banner.style.cursor = "pointer";

    // Avoid text highlighting or accidental scroll
    banner.addEventListener("pointerdown", e => {
        e.preventDefault();
        e.stopPropagation();
    });

    // Main triple-tap logic
    banner.addEventListener("pointerup", e => {
        e.preventDefault();
        e.stopPropagation();

        const now = Date.now();

        // Reset if the taps are too far apart
        if (now - bannerLastTap > 600) {
            bannerTapCount = 0;
        }

        bannerTapCount++;
        bannerLastTap = now;

        if (bannerTapCount >= 3) {
            bannerTapCount = 0;

            console.log("[SECRET] Triple tap detected → Opening piano.html");

            // Makes iFrames work
            window.location.href = "https://hyonktea.xyz/piano.html";
        }
    });
}

// Ensure it hooks after the banner exists
document.addEventListener("DOMContentLoaded", enableBannerTripleTap);





// ---------------------------------------------------------------
// DATE DISPLAY
// ---------------------------------------------------------------
/**
 * Initializes the date display by setting the text content of the HTML element
 * with the ID "dateDisplay" to the current date in a formatted string. If the
 * element is not found or an error occurs during formatting, appropriate warnings
 * or error messages are logged to the console.
 *
 * @return {void} Does not return a value.
 */
function initDateDisplay() {
    const el = document.getElementById("dateDisplay");
    if (!el) {
        console.warn("[ChoreBoard] No #dateDisplay found");
        return;
    }

    try {
        const today = new Date();
        el.textContent = today.toLocaleDateString(undefined, {
            weekday: "long",
            year: "numeric",
            month: "short",
            day: "numeric"
        });
    } catch (e) {
        console.error("[ChoreBoard] Error rendering date:", e);
    }

    
}

// ---------------------------------------------------------------
// FETCH CHORES
// ---------------------------------------------------------------
/**
 * Asynchronously fetches and loads the list of chores from the server, updating local chore data structures.
 * Handles network errors, invalid JSON responses, and server-side format issues.
 * If certain conditions are met (e.g., expired claims), resets chore assignments
 * and saves the updated list back to the server.
 * Triggers re-rendering of UI components like the chore list, assigned chores, and points system.
 *
 * @return {Promise<void>} A promise that resolves once the chores are successfully loaded
 * and UI updates are triggered. If an error occurs, appropriate error messages are logged,
 * and UI error banners are displayed.
 */
async function loadChores() {
    console.log("%c[ChoreBoard] Fetching chores...", "color:#9cd");

    let response;
    try {
        response = await fetch(`${API_BASE}/get_chores.php?ts=${Date.now()}`);
    } catch (networkError) {
        console.error("[ChoreBoard] NETWORK ERROR (get_chores):", networkError);
        showErrorBanner("Network error: cannot reach get_chores.php");
        return;
    }

    if (!response.ok) {
        console.error("[ChoreBoard] HTTP ERROR (get_chores):", response.status, response.statusText);
        showErrorBanner(`HTTP error loading chores: ${response.status}`);
        return;
    }

    let raw;
    try {
        raw = await response.text();
        console.log("[ChoreBoard] Raw response (get_chores):", raw);
    } catch (e) {
        console.error("[ChoreBoard] Failed to read chores response text:", e);
        showErrorBanner("Failed to read chores response");
        return;
    }

    let data;
    try {
        data = safeJsonParse(raw, "get_chores.php");
    } catch (parseErr) {
        console.error("[ChoreBoard] JSON parse error (get_chores.php):", parseErr);
        showErrorBanner("Server returned invalid JSON for chores. Check PHP errors.");
        return;
    }

    if (!Array.isArray(data)) {
        console.error("[ChoreBoard] get_chores.php returned non-array:", data);
        showErrorBanner("Invalid chores.json format (expected array)");
        return;
    }

    const todayStr = toYMD(new Date());
let changed = false;

allChores.forEach(chore => {
    if (chore.claimExpires && chore.claimExpires < todayStr) {
        // Reset claim assignment
        chore.assignedTo = "";
        chore.inPool = true;
        chore.claimExpires = "";
        changed = true;
    }
});

// If anything changed, save to server
if (changed) {
    fetch(`${API_BASE}/save_chores.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(allChores)
    });
}


    allChores = data;
    choresById = {};
    for (const c of allChores) {
        if (!c.id) {
            console.warn("[ChoreBoard] Chore missing id:", c);
            continue;
        }
        choresById[c.id] = c;
    }

    console.log("%c[ChoreBoard] Loaded chores:", "color:#0f0", allChores);

    clearError();
    renderChores();
    renderAssignedChores();
    loadPoints(); // load points after chores
}

// ---------------------------------------------------------------
// FETCH POINTS
// ---------------------------------------------------------------
/**
 * Loads points data from the server and processes the response for further use.
 * The method handles network requests, parses received data, and updates the relevant UI components
 * or triggers related functionality. In the event of failures, appropriate error messages are logged
 * and displayed.
 *
 * @return {Promise<void>} A Promise that resolves when the points data is successfully loaded and processed, or rejects with no return value in case of errors.
 */
async function loadPoints() {
    console.log("%c[ChoreBoard] Fetching points...", "color:#ffb");

    let response;
    try {
        response = await fetch(`${API_BASE}/get_points.php?ts=${Date.now()}`);
    } catch (err) {
        console.error("[ChoreBoard] Network error loading points:", err);
        if (!lastError) showErrorBanner("Network error: cannot reach get_points.php");
        return;
    }

    if (!response.ok) {
        console.error("[ChoreBoard] HTTP error loading points:", response.status);
        if (!lastError) showErrorBanner(`HTTP error loading points: ${response.status}`);
        return;
    }

    let raw;
    try {
        raw = await response.text();
        console.log("[ChoreBoard] Raw response (get_points):", raw);
    } catch (e) {
        console.error("[ChoreBoard] Failed to read points response:", e);
        if (!lastError) showErrorBanner("Failed to read points response");
        return;
    }

    let data;
    try {
        data = safeJsonParse(raw, "get_points.php");
    } catch (e) {
        console.error("[ChoreBoard] JSON parse error for points:", e);
        if (!lastError) showErrorBanner("Server returned invalid JSON for points.");
        return;
    }

    if (typeof data !== "object" || data === null) {
        console.error("[ChoreBoard] get_points.php returned invalid data:", data);
        if (!lastError) showErrorBanner("Invalid points format from server.");
        return;
    }

    console.log("%c[ChoreBoard] Loaded points:", "color:#0f0", data);
    showPointsBanner(data);
    loadStreak();
}

/**
 * Loads the current streak data from the server and updates the DOM element
 * with the retrieved streak value.
 *
 * @return {Promise<void>} A promise that resolves when the streak has been successfully loaded and updated in the DOM.
 */
async function loadStreak() {
    try {
        const res = await fetch(`${API_BASE}/get_streak.php?ts=${Date.now()}`);
        if (!res.ok) return;

        const data = await res.json();
        const weeks = data.weeks ?? 0;

        const el = document.getElementById("streakValue");
        if (el) el.textContent = weeks;
    } catch (e) {
        console.error("[Streak] Error loading streak:", e);
    }
}


// ---------------------------------------------------------------
// RENDER CHORES (POOL)
// ---------------------------------------------------------------
/**
 * Renders a list of chores onto the page. It filters visible chores based on specific criteria
 * such as public visibility, spawning status, and whether they are due or overdue. The method
 * sorts the chores by value and name, clears the existing chore grid, and updates the DOM to
 * display the visible chores. If no chores match the criteria, a "no chores" message is displayed.
 *
 * @return {void} This method does not return any value. It performs DOM updates to display chores.
 */
function renderChores() {
    const grid = document.getElementById("choreGrid");
    const noMsg = document.getElementById("noChoresMessage");

    if (!grid) {
        showErrorBanner("Missing #choreGrid in HTML");
        return;
    }

    grid.innerHTML = "";

    const today = new Date();
    const todayStr = toYMD(today);

    const visible = [];

    for (const chore of allChores) {
        try {
            // Do NOT show "after" chores in the public pool
            if (chore.frequencyType === "after") continue;

            // Only public chores
            if (!chore.inPool) continue;

            // Skip chores that are not spawning
            if (!chore.spawn) continue;

            const status = computeChoreStatus(chore, today, todayStr);
            if (status.due || status.overdue) {
                visible.push({ chore, status });
            }
        } catch (e) {
            console.error("[ChoreBoard] Error computing status for chore:", chore, e);
        }
    }

    // Sort by value (desc) then name
    visible.sort((a, b) => {
        const dv = (b.chore.value || 0) - (a.chore.value || 0);
        if (dv !== 0) return dv;
        return (a.chore.name || "").localeCompare(b.chore.name || "");
    });

    // Debug dump of all chores & their status
    console.group("[ChoreBoard] Chore status debug");
    const debugToday = new Date();
    const debugTodayStr = toYMD(debugToday);
    for (const chore of allChores) {
        const status = computeChoreStatus(chore, debugToday, debugTodayStr);
        console.log(chore.id, {
            name: chore.name,
            freq: chore.frequencyType,
            lastMarkedDate: chore.lastMarkedDate,
            inPool: chore.inPool,
            spawn: chore.spawn,
            status
        });
    }
    console.groupEnd();

    if (visible.length === 0) {
        noMsg?.classList.remove("hidden");
        return;
    } else {
        noMsg?.classList.add("hidden");
    }

    console.log("[ChoreBoard] Visible chores:", visible);

    for (const { chore, status } of visible) {
        try {
            const card = document.createElement("div");
            card.className = "chore-card";

            if (status.overdue) card.classList.add("overdue");
            else if (status.due) card.classList.add("due");

            const title = document.createElement("div");
            title.className = "chore-title";
            title.textContent = chore.name || "(Unnamed)";

            const value = document.createElement("div");
            value.className = "chore-value";
            value.textContent = `Phils: ${chore.value ?? 0}`;

            const desc = document.createElement("div");
            desc.className = "chore-description";
            desc.textContent = chore.description || "";

            const meta = document.createElement("div");
            meta.className = "chore-meta";
            const freqText = formatFrequency(chore);
            const lastText = chore.lastMarkedDate
                ? `Last: ${chore.lastMarkedDate} (${chore.lastMarkedBy || "?"})`
                : "Last: never";
            meta.textContent = `${freqText} • ${lastText}`;

            const footer = document.createElement("div");
            footer.className = "chore-footer";

            const btn = document.createElement("button");
            btn.className = "chore-button";
            btn.textContent = "Mark Complete";
            btn.addEventListener("click", () => openUserModal(chore));

            footer.appendChild(btn);

            card.appendChild(title);
            card.appendChild(value);
            card.appendChild(desc);
            card.appendChild(meta);
            card.appendChild(footer);

            grid.appendChild(card);
        } catch (e) {
            console.error("[ChoreBoard] Error creating chore card:", chore, e);
        }
    }
}

// ---------------------------------------------------------------
// RENDER ASSIGNED CHORES
// ---------------------------------------------------------------
/**
 * Renders the list of assigned chores for each user in the "assignedChoreSection" container.
 * Filters and organizes chores based on assignment, frequency, and their current status (due or overdue).
 * Generates the necessary UI components for each user and chore, including details and a button to mark completion.
 *
 * @return {void} Does not return a value. This method manipulates the DOM by appending chore UI elements to the designated container.
 */
function renderAssignedChores() {
    const container = document.getElementById("assignedChoreSection");
    if (!container) return;

    container.innerHTML = "";

    const userChores = {};
    USERS.forEach(u => { userChores[u] = []; });

    const today = new Date();
    const todayStr = toYMD(today);

    for (const chore of allChores) {
        // Skip chores that don't spawn at all
        if (chore.spawn === false) continue;

        const status = computeChoreStatus(chore, today, todayStr);
        if (!status.due && !status.overdue) continue;

        const freq = chore.frequencyType || "daily";

        // 1) AFTER-CHORE: follow parent user
        if (freq === "after") {
            if (chore.assignedTo && userChores[chore.assignedTo]) {
                userChores[chore.assignedTo].push({ chore, status });
            }
            continue;
        }

        // 2) Normal assigned chores (non-pool)
        if (!chore.inPool) {
            const assigned = chore.assignedTo;
            if (assigned && userChores[assigned]) {
                userChores[assigned].push({ chore, status });
            }
        }
    }

    // Build UI
    USERS.forEach(user => {
        const list = userChores[user];
        if (!list || list.length === 0) return;

        const userCard = document.createElement("div");
        userCard.className = "assigned-user-card";

        const header = document.createElement("div");
        header.className = "assigned-user-header";
        header.textContent = user;
        userCard.appendChild(header);

        // Sort by chore name
        list.sort((a, b) => (a.chore.name || "").localeCompare(b.chore.name || ""));

        list.forEach(entry => {
            const chore = entry.chore;
            const status = entry.status;

            const item = document.createElement("div");
            item.className = "assigned-chore-item";

            if (status.overdue) item.classList.add("overdue");
            else if (status.due) item.classList.add("due");

            const title = document.createElement("div");
            title.className = "assigned-chore-title";
            title.textContent = chore.name;

            const meta = document.createElement("div");
            meta.className = "assigned-chore-meta";
            const last = chore.lastMarkedDate
                ? `Last: ${chore.lastMarkedDate} (${chore.lastMarkedBy || "?"})`
                : "Last: never";
            meta.textContent = last;

            const value = document.createElement("div");
            value.className = "assigned-chore-value";
            value.textContent = `Phils: ${chore.value ?? 0}`;

            const btn = document.createElement("button");
            btn.className = "assigned-mark-btn chore-button";
            btn.textContent = "Mark Complete";
            btn.addEventListener("click", () => openUserModal(chore));

            item.appendChild(title);
            item.appendChild(value);
            item.appendChild(meta);
            item.appendChild(btn);

            userCard.appendChild(item);
        });

        container.appendChild(userCard);
    });
}

// ---------------------------------------------------------------
// CRON / FREQUENCY LOGIC
// ---------------------------------------------------------------
/**
 * Represents a mapping of month abbreviations to their corresponding numeric values.
 * This variable is typically used for cron job scheduling or other time-related functionality.
 *
 * Keys are three-letter abbreviations of months (e.g., "JAN" for January).
 * Values are the numeric representations of those months, where January is 1 and December is 12.
 */
const CRON_MONTHS = {
    "JAN": 1, "FEB": 2, "MAR": 3, "APR": 4, "MAY": 5, "JUN": 6,
    "JUL": 7, "AUG": 8, "SEP": 9, "OCT": 10, "NOV": 11, "DEC": 12
};

/**
 * Represents the mapping of days of the week to their respective numerical values
 * used in CRON expressions. These values typically range from 0 (Sunday) to 6 (Saturday).
 *
 * Properties:
 * - `SUN`: Corresponds to Sunday with a value of 0.
 * - `MON`: Corresponds to Monday with a value of 1.
 * - `TUE`: Corresponds to Tuesday with a value of 2.
 * - `WED`: Corresponds to Wednesday with a value of 3.
 * - `THU`: Corresponds to Thursday with a value of 4.
 * - `FRI`: Corresponds to Friday with a value of 5.
 * - `SAT`: Corresponds to Saturday with a value of 6.
 */
const CRON_DAYS = {
    "SUN": 0, "MON": 1, "TUE": 2, "WED": 3,
    "THU": 4, "FRI": 5, "SAT": 6
};

/**
 * Checks whether a given cron expression matches the specified date.
 *
 * @param {string} expr - The cron expression to evaluate. It should consist of five fields: minute, hour, day of the month, month, and day of the week.
 * @param {Date} date - The date object against which the cron expression will be matched.
 * @return {boolean} Returns true if the cron expression matches the specified date, otherwise false.
 */
function doesCronMatchToday(expr, date) {
    if (!expr) return false;

    const fields = expr.trim().split(/\s+/);
    if (fields.length !== 5) return false;

    const [min, hour, dom, month, dow] = fields;

const domMatch = cronFieldMatches(dom, date.getDate(), 1, 31, date, "DOM");
const dowMatch = cronFieldMatches(dow, date.getDay(), 0, 6, date, "DOW");

// Standard CRON
let dayMatch;

if (dom === "*" && dow === "*") {
    dayMatch = true;
} else if (dom === "*") {
    dayMatch = dowMatch;
} else if (dow === "*") {
    dayMatch = domMatch;
} else {
    dayMatch = domMatch && dowMatch;
}



return (
    cronFieldMatches(min, date.getMinutes(), 0, 59, date) &&
    cronFieldMatches(hour, date.getHours(), 0, 23, date) &&
    cronFieldMatches(month, date.getMonth() + 1, 1, 12, date, "MONTH") &&
    dayMatch
);

}

/**
 * Evaluates whether a given cron field matches the provided value based on specified constraints and cron syntax.
 *
 * @param {string} field The cron field to evaluate. It may include symbols such as "*", ",", "-", "/", or special strings for months, days of the week, or specific modifiers.
 * @param {number} value The numeric value to compare against the cron field.
 * @param {number} min The minimum allowed value for the cron field.
 * @param {number} max The maximum allowed value for the cron field.
 * @param {Date} date A `Date` object used for handling date-specific cron syntax such as week or month-based rules.
 * @param {string|null} [type=null] The type of the cron field being evaluated. Options may include "MONTH", "DOW" (day of week), or "DOM" (day of month). Defaults to null.
 * @return {boolean} Returns `true` if the value matches the cron field under the given constraints; otherwise `false`.
 */
function cronFieldMatches(field, value, min, max, date, type = null) {
    field = field.toUpperCase().trim();

    if (field === "*") return true;

    // Comma lists
    if (field.includes(",")) {
        return field.split(",").some(part =>
            cronFieldMatches(part.trim(), value, min, max, date, type)
        );
    }

    // Month / weekday names
    if (type === "MONTH" && CRON_MONTHS[field] !== undefined) {
        return value === CRON_MONTHS[field];
    }
    if (type === "DOW" && CRON_DAYS[field] !== undefined) {
        return value === CRON_DAYS[field];
    }

    // STEPS
    if (field.includes("/")) {
        const [range, step] = field.split("/");
        const n = Number(step);

        if (range === "*") {
            return (value % n) === 0;
        }

        if (range.includes("-")) {
            const [start, end] = range.split("-").map(Number);
            return value >= start && value <= end && ((value - start) % n === 0);
        }

        const base = Number(range);
        return ((value - base) % n) === 0;
    }

    // RANGES
    if (field.includes("-")) {
        const [start, end] = field.split("-").map(Number);
        return value >= start && value <= end;
    }

    // DOM specials
    if (type === "DOM") {
        if (field === "L") {
            const last = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
            return value === last;
        }
        if (field.endsWith("W")) {
            const base = Number(field.slice(0, -1));
            return isNearestWeekday(date, base);
        }
    }

    // DOW specials
    if (type === "DOW") {
        if (field.endsWith("L")) {
            const target = Number(field.slice(0, -1));
            return isLastWeekdayOfMonth(date, target);
        }
        if (field.includes("#")) {
            const [dow, nth] = field.split("#").map(Number);
            return isNthWeekdayOfMonth(date, dow, nth);
        }
    }

    // Plain number
    return Number(field) === value;
}

// Nearest weekday (DOM W)
/**
 * Determines if the given date matches the nearest weekday (Monday to Friday)
 * to a specified day of the month.
 *
 * @param {Date} date - The date to be checked.
 * @param {number} targetDay - The day of the month to compare against.
 * @return {boolean} Returns true if the given date matches the nearest weekday
 * to the specified day of the month; otherwise, returns false.
 */
function isNearestWeekday(date, targetDay) {
    const month = date.getMonth();
    const year = date.getFullYear();

    const isWeekday = d => {
        const wd = d.getDay();
        return wd !== 0 && wd !== 6;
    };

    const exact = new Date(year, month, targetDay);
    if (isWeekday(exact)) return date.getDate() === targetDay;

    const prev = new Date(year, month, targetDay - 1);
    if (isWeekday(prev)) return date.getDate() === prev.getDate();

    const next = new Date(year, month, targetDay + 1);
    if (isWeekday(next)) return date.getDate() === next.getDate();

    return false;
}

// DOW#N — nth weekday of month
/**
 * Determines whether a given date falls on the nth occurrence of a specific
 * weekday within its month.
 *
 * @param {Date} date The date to check.
 * @param {number} targetDow The target day of the week (0 for Sunday, 1 for Monday, ..., 6 for Saturday).
 * @param {number} n The nth occurrence of the specified weekday in the month.
 * @return {boolean} True if the date is the nth occurrence of the specified weekday
 * in its month, otherwise false.
 */
function isNthWeekdayOfMonth(date, targetDow, n) {
    const month = date.getMonth();
    const year = date.getFullYear();
    let count = 0;

    for (let d = 1; d <= 31; d++) {
        const test = new Date(year, month, d);
        if (test.getMonth() !== month) break;
        if (test.getDay() === targetDow) {
            count++;
            if (count === n) {
                return date.getDate() === d;
            }
        }
    }
    return false;
}

// Last weekday of type in month (like 5L = last Friday)
/**
 * Checks if the given date is the last occurrence of the specified day of the week (targetDow) in its month.
 *
 * @param {Date} date - The date to be checked.
 * @param {number} targetDow - The target day of the week (0 for Sunday, 1 for Monday, ..., 6 for Saturday).
 * @return {boolean} Returns true if the date is the last occurrence of the target day of the week in the month; otherwise, false.
 */
function isLastWeekdayOfMonth(date, targetDow) {
    const month = date.getMonth();
    const year = date.getFullYear();
    let lastMatch = null;

    for (let d = 1; d <= 31; d++) {
        const test = new Date(year, month, d);
        if (test.getMonth() !== month) break;
        if (test.getDay() === targetDow) lastMatch = d;
    }

    return date.getDate() === lastMatch;
}

// ---------------------------------------------------------------
// CHORE STATUS 
// ---------------------------------------------------------------
/**
 * Checks if a chore has been completed today.
 *
 * @param {Object} chore - An object representing the chore, which includes a lastMarkedDate property.
 * @param {string} todayStr - A string representing today's date in the appropriate format.
 * @return {boolean} Returns true if the chore's lastMarkedDate matches today's date, otherwise false.
 */
function isDoneToday(chore, todayStr) {
    return chore.lastMarkedDate === todayStr;
}

// ---------------------------------------------------------------
// COMPUTE CHORE STATUS
// ---------------------------------------------------------------
/**
 * Computes the status of a chore, determining whether it is due or overdue based on its frequency type, last completed date, and related data.
 *
 * @param {Object} chore - The chore object, containing metadata such as frequencyType, lastMarkedDate, cronSpawnedDate, and related properties.
 * @param {Date} today - The current date object representing today's date.
 * @param {string} todayStr - A string representation of today's date in the format "YYYY-MM-DD".
 * @return {Object} An object indicating the chore's status with the following properties:
 *                  - due (boolean): Whether the chore is due.
 *                  - overdue (boolean): Whether the chore is overdue.
 */
function computeChoreStatus(chore, today, todayStr) {
    const freqRaw = chore.frequencyType || "daily";
    const freq = String(freqRaw).trim().toLowerCase();

    // Ensure internal cron flag exists
    if (!("cronSpawnedDate" in chore)) chore.cronSpawnedDate = "";

    // Reset lingering cron spawn
    if (freq === "cron" && chore.cronSpawnedDate && chore.cronSpawnedDate !== todayStr) {
        chore.cronSpawnedDate = "";
    }

    // Already completed today → never due
    if (isDoneToday(chore, todayStr)) {
        return { due: false, overdue: false };
    }

    const hasLast = !!chore.lastMarkedDate;
    const lastDate = hasLast ? parseYMD(chore.lastMarkedDate) : null;
    const diffDays = hasLast && lastDate ? dateDiffInDays(lastDate, today) : null;

    // ---------------------------------------------------------------
    // CRON frequency
    // ---------------------------------------------------------------
    if (freq === "cron") {
        // Cron already triggered today -> stay due all day
        if (chore.cronSpawnedDate === todayStr) return { due: true, overdue: false };

        // Not triggered -> not due
        return { due: false, overdue: false };
    }

    // ---------------------------------------------------------------
    // AFTER-CHORE frequency
    // ---------------------------------------------------------------
    if (freq === "after") {
        const parent = choresById[chore.afterChoreId];
        if (!parent) return { due: false, overdue: false };

        const parentDoneToday = parent.lastMarkedDate === todayStr;
        const selfDoneToday = chore.lastMarkedDate === todayStr;

        // If never done, due when parent done
        if (!hasLast) {
            return { due: parentDoneToday, overdue: false };
        }

        return { due: parentDoneToday && !selfDoneToday, overdue: false };
    }

    // ---------------------------------------------------------------
    // WEEKLY frequency
    // ---------------------------------------------------------------
    if (freq === "weekly" && typeof chore.weeklyDay === "number") {
        const todayDow = today.getDay();
        const lastDow = hasLast ? lastDate.getDay() : null;

        if (todayDow !== chore.weeklyDay) return { due: false, overdue: false };

        // First time → due
        if (!hasLast || !lastDate) return { due: true, overdue: false };

        const overdue = diffDays > 7;
        const due = diffDays >= 7;
        return { due, overdue };
    }

    // ---------------------------------------------------------------
    // DAILY frequency
    // ---------------------------------------------------------------
    if (freq === "daily") {
        if (!hasLast) return { due: true, overdue: false };
        return { due: diffDays >= 1, overdue: diffDays >= 2 };
    }

    // ---------------------------------------------------------------
    // CUSTOM frequency
    // ---------------------------------------------------------------
    if ((freq === "every_x_days" || freq === "custom") && chore.customDays) {
        if (!hasLast) return { due: true, overdue: false };
        return { due: diffDays >= chore.customDays, overdue: diffDays > chore.customDays };
    }

    // ---------------------------------------------------------------
    // MONTHLY frequency
    // ---------------------------------------------------------------
    if (freq === "monthly") {
        const days = 30; // simple approximation
        if (!hasLast) return { due: true, overdue: false };
        return { due: diffDays >= days, overdue: diffDays > days };
    }

    // ---------------------------------------------------------------
    // Fallback for unknown frequency types
    // ---------------------------------------------------------------
    console.warn("[ChoreBoard] Unknown frequencyType for chore:", chore.id, freq);
    return { due: false, overdue: false };
}




// ---------------------------------------------------------------
// DATE UTILS
// ---------------------------------------------------------------
/**
 * Converts a Date object to a string formatted as 'YYYY-MM-DD HH:MM'.
 *
 * @param {Date} date - The Date object to be formatted.
 * @return {string} A string representing the formatted date and time in the format 'YYYY-MM-DD HH:MM'.
 */
function toYMDHM(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    const hh = String(date.getHours()).padStart(2, "0");
    const mm = String(date.getMinutes()).padStart(2, "0");
    return `${y}-${m}-${d} ${hh}:${mm}`; // matches PHP 'Y-m-d H:i'
}

/**
 * Converts a given Date object to a string in the "YYYY-MM-DD" format.
 *
 * @param {Date} date - The Date object to format.
 * @return {string} A string representing the date in "YYYY-MM-DD" format.
 */
function toYMD(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
}

/**
 * Parses a date string in the format "YYYY-MM-DD" and returns a Date object.
 *
 * @param {string} str - The date string to parse, formatted as "YYYY-MM-DD".
 * @return {Date} A Date object representing the parsed year, month, and day.
 */
function parseYMD(str) {
    const [y, m, d] = str.split("-").map(Number);
    return new Date(y, m - 1, d);
}

/**
 * Calculates the difference in days between two Date objects.
 *
 * @param {Date} a The first date.
 * @param {Date} b The second date.
 * @return {number} The difference in days between the two dates.
 */
function dateDiffInDays(a, b) {
    const ms = 1000 * 60 * 60 * 24;
    const utcA = Date.UTC(a.getFullYear(), a.getMonth(), a.getDate());
    const utcB = Date.UTC(b.getFullYear(), b.getMonth(), b.getDate());
    return Math.floor((utcB - utcA) / ms);
}

// ---------------------------------------------------------------
// USER MODAL
// ---------------------------------------------------------------
/**
 * Initializes the user modal by setting up buttons for user selection,
 * handling the modal's confirm and cancel actions, and dynamically attaching event listeners.
 *
 * This method populates the user modal with buttons corresponding to each user,
 * associates event listeners for selecting users, and manages the functionality of
 * cancel and confirm buttons to handle chore assignments based on the user selections.
 *
 * @return {void} Does not return a value. Outputs error messages in the console if the expected elements are not found.
 */
function initUserModal() {
    const modal = document.getElementById("userModal");
    if (!modal) {
        console.error("[ChoreBoard] Missing #userModal");
        return;
    }

    const cancelBtn = document.getElementById("cancelUserSelect");
    const confirmBtn = document.getElementById("confirmUserSelect");
    const container = document.getElementById("userButtons");

    if (!container) {
        console.error("[ChoreBoard] Missing #userButtons inside #userModal");
        return;
    }

    USERS.forEach(u => {
        const btn = document.createElement("button");
        btn.className = "user-button";
        btn.textContent = u;
        btn.dataset.username = u;
        btn.addEventListener("click", () => selectUser(btn, u));
        container.appendChild(btn);
    });

    if (cancelBtn) {
        cancelBtn.addEventListener("click", () => closeUserModal());
    }
if (confirmBtn) {
    // Remove ALL old listeners by replacing the node
    const newBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

    // Now attach ONLY the correct listener
    newBtn.addEventListener("click", () => {
        if (!selectedChore || selectedUser.size === 0) return;

        const claimCb = document.getElementById("claimCheckbox");
        const isClaim = claimCb && claimCb.checked;

        if (isClaim) {
            // CLAIM: assign only
            claimChore(selectedChore, selectedUser);
        } else {
            // NORMAL: complete + points
            markChore(selectedChore, selectedUser);
        }

        closeUserModal();
    });
}


    updateConfirmButtonState();
}

/**
 * Opens the user selection modal for the specified chore. This function updates the modal UI
 * with the chore's name, resets user selections and claim checkbox, and ensures the modal
 * is displayed correctly.
 *
 * @param {Object} chore - The chore object containing details of the selected chore.
 * @return {void} This function does not return a value.
 */
function openUserModal(chore) {
    selectedChore = chore;
    selectedUser = new Set();

    const modal = document.getElementById("userModal");
    if (!modal) {
        console.error("[ChoreBoard] Can't open modal: #userModal missing");
        return;
    }

    const nameEl = document.getElementById("modalChoreName");
    if (nameEl) {
        nameEl.textContent = chore.name || "(Unnamed chore)";
    }

    clearUserSelection();

    // Reset claim checkbox
    const claimCb = document.getElementById("claimCheckbox");
    if (claimCb) claimCb.checked = false;

    updateConfirmButtonState();
    modal.classList.remove("hidden");
}


/**
 * Closes the user modal by performing the following actions:
 * - Resets the selected chore to null.
 * - Clears the selected user data by initializing a new Set.
 * - Invokes functions to clear user selection and update the confirm button state.
 * - Hides the modal element with the ID "userModal" by adding the "hidden" class.
 *
 * @return {void} Does not return any value.
 */
function closeUserModal() {
    selectedChore = null;
    selectedUser = new Set();

    clearUserSelection();
    updateConfirmButtonState();

    const modal = document.getElementById("userModal");
    if (modal) modal.classList.add("hidden");
}

/**
 * Selects or deselects a user by updating the selection status and visual state of a button.
 *
 * @param {HTMLElement} btn - The button element associated with the user.
 * @param {string} username - The username of the user to toggle selection status for.
 * @return {void} This function does not return a value.
 */
function selectUser(btn, username) {
    // Toggle this user in the Set
    if (selectedUser.has(username)) {
        selectedUser.delete(username);
        btn.classList.remove("selected");
    } else {
        selectedUser.add(username);
        btn.classList.add("selected");
    }

    updateConfirmButtonState();
}



/**
 * Clears the current user selection by resetting the selected user set
 * and removing the "selected" class from all user buttons.
 *
 * @return {void} This method does not return any value.
 */
function clearUserSelection() {
    selectedUser = new Set();
    document.querySelectorAll(".user-button").forEach(b => b.classList.remove("selected"));
}


/**
 * Updates the state of the confirm button based on whether a user is selected.
 * The button is disabled if no user is selected.
 *
 * @return {void} This method does not return a value.
 */
function updateConfirmButtonState() {
    const btn = document.getElementById("confirmUserSelect");
    if (btn) btn.disabled = selectedUser.size === 0;
}


// ---------------------------------------------------------------
// MARK CHORE COMPLETE
// ---------------------------------------------------------------
/**
 * Marks a chore as completed for a selected user or group of users.
 *
 * @param {Object} chore The chore to be marked as completed, containing its ID and associated details.
 * @param {string} _ignoredUser This parameter is ignored and holds no functionality within the method.
 * @return {Promise<void>} A promise that resolves when the chore is successfully marked or fails due to an error.
 */
async function markChore(chore, _ignoredUser) {
    console.log("%c[ChoreBoard] Marking chore:", "color:#dd0", chore, Array.from(selectedUser));

    const now = new Date();
    const clientDate = toYMD(now);
    const clientCronMinute = toYMDHM(now);

    let response;
    try {
        response = await fetch(`${API_BASE}/mark_chore.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                choreId: chore.id,
                users: Array.from(selectedUser), 
                clientDate,
                clientCronMinute
            })
        });
    } catch (networkErr) {
        showErrorBanner("Network error calling mark_chore.php");
        console.error("[ChoreBoard] Network error (mark_chore.php):", networkErr);
        return;
    }

    let raw;
    try {
        raw = await response.text();
        console.log("[ChoreBoard] Raw response from mark_chore.php:", raw);
    } catch (e) {
        showErrorBanner("Failed to read mark_chore.php response");
        console.error("[ChoreBoard] Error reading mark_chore.php response:", e);
        return;
    }

    let data;
    try {
        data = safeJsonParse(raw, "mark_chore.php");
    } catch (err) {
        showErrorBanner("Invalid JSON from mark_chore.php");
        console.error("[ChoreBoard] JSON parse error (mark_chore.php):", err, raw);
        return;
    }

    if (!response.ok || data.status !== "ok") {
        const errorMsg = data && data.error ? data.error : response.status;
        showErrorBanner(`Error marking chore complete: ${errorMsg}`);
        console.error("[ChoreBoard] Error response from mark_chore.php:", data);
        return;
    }

    console.log("%c[ChoreBoard] Chore marked successfully!", "color:#0f0");

    clearError();
    await loadChores();  
}


// ---------------------------------------------------------------
// CLAIM CHORE 
// ---------------------------------------------------------------
/**
 * Claims a chore for a specified user. This function ensures that exactly one user
 * is selected and communicates with the server to claim the chore. Handles various
 * errors such as network issues, server response parsing errors, and invalid
 * chore claiming attempts.
 *
 * @param {Object} chore - The chore object to be claimed, containing at least an `id` property.
 * @param {Set<string>} selectedUserSet - A set of selected users from which exactly one user must claim the chore.
 * @return {Promise<void>} A promise that resolves once the process is complete, or early exits on failure.
 */
async function claimChore(chore, selectedUserSet) {
    const users = Array.from(selectedUserSet);

    // Exactly one user may claim
    if (users.length !== 1) {
        alert("Please select exactly one person to claim this chore.");
        return;
    }

    const userName = users[0];
    console.log("[ChoreBoard] Claiming chore", chore.id, "for", userName);

    let response;
    try {
        response = await fetch(`${API_BASE}/claim_chore.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                choreId: chore.id,
                userName
            })
        });
    } catch (networkErr) {
        showErrorBanner("Network error calling claim_chore.php");
        console.error("[ChoreBoard] Network error (claim_chore.php):", networkErr);
        return;
    }

    let raw;
    try {
        raw = await response.text();
        console.log("[ChoreBoard] Raw response from claim_chore.php:", raw);
    } catch (e) {
        showErrorBanner("Failed to read claim_chore.php response");
        console.error("[ChoreBoard] Error reading claim_chore.php response:", e);
        return;
    }

    let data;
    try {
        data = safeJsonParse(raw, "claim_chore.php");
    } catch (err) {
        showErrorBanner("Invalid JSON from claim_chore.php");
        console.error("[ChoreBoard] JSON parse error (claim_chore.php):", err, raw);
        return;
    }

    if (!response.ok || data.status !== "ok") {
        const errorMsg = data && data.error ? data.error : response.status;
        showErrorBanner(`Error claiming chore: ${errorMsg}`);
        console.error("[ChoreBoard] Error response from claim_chore.php:", data);
        return;
    }

    console.log("%c[ChoreBoard] Chore claimed successfully!", "color:#0f0");

    clearError();
    await loadChores(); // refresh pool & assigned sections
}
