console.log("%c[ChoreBoard] Starting script.js...", "color:#9cf; font-weight:bold;");

// ---------------------------------------------------------------
// CONSTANTS & GLOBAL STATE
// ---------------------------------------------------------------
const USERS = ["ash", "vast", "sephy", "hope", "cylis", "phil", "selina"];
const API_BASE = "api";

let allChores = [];
let choresById = {};
let selectedChore = null;
let selectedUser = new Set();
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

function showErrorBanner(message) {
    lastError = message;
    console.error("[ChoreBoard ERROR]", message);
    const banner = getOrCreateBanner();
    banner.style.background = "#922";
    banner.style.color = "#fff";
    banner.textContent = message;
}

function clearError() {
    lastError = null;
}

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

let bannerTapCount = 0;
let bannerLastTap = 0;

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
const CRON_MONTHS = {
    "JAN": 1, "FEB": 2, "MAR": 3, "APR": 4, "MAY": 5, "JUN": 6,
    "JUL": 7, "AUG": 8, "SEP": 9, "OCT": 10, "NOV": 11, "DEC": 12
};

const CRON_DAYS = {
    "SUN": 0, "MON": 1, "TUE": 2, "WED": 3,
    "THU": 4, "FRI": 5, "SAT": 6
};

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
function isDoneToday(chore, todayStr) {
    return chore.lastMarkedDate === todayStr;
}

// ---------------------------------------------------------------
// COMPUTE CHORE STATUS
// ---------------------------------------------------------------
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
function toYMDHM(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    const hh = String(date.getHours()).padStart(2, "0");
    const mm = String(date.getMinutes()).padStart(2, "0");
    return `${y}-${m}-${d} ${hh}:${mm}`; // matches PHP 'Y-m-d H:i'
}

function toYMD(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
}

function parseYMD(str) {
    const [y, m, d] = str.split("-").map(Number);
    return new Date(y, m - 1, d);
}

function dateDiffInDays(a, b) {
    const ms = 1000 * 60 * 60 * 24;
    const utcA = Date.UTC(a.getFullYear(), a.getMonth(), a.getDate());
    const utcB = Date.UTC(b.getFullYear(), b.getMonth(), b.getDate());
    return Math.floor((utcB - utcA) / ms);
}

// ---------------------------------------------------------------
// USER MODAL
// ---------------------------------------------------------------
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


function closeUserModal() {
    selectedChore = null;
    selectedUser = new Set();

    clearUserSelection();
    updateConfirmButtonState();

    const modal = document.getElementById("userModal");
    if (modal) modal.classList.add("hidden");
}

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



function clearUserSelection() {
    selectedUser = new Set();
    document.querySelectorAll(".user-button").forEach(b => b.classList.remove("selected"));
}


function updateConfirmButtonState() {
    const btn = document.getElementById("confirmUserSelect");
    if (btn) btn.disabled = selectedUser.size === 0;
}


// ---------------------------------------------------------------
// MARK CHORE COMPLETE
// ---------------------------------------------------------------
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
