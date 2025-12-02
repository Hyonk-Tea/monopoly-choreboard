/**
 * A constant representing the base path for the API endpoints.
 * It is used as the root segment for constructing API URLs.
 * This helps in standardizing and centralizing the API path configuration.
 *
 * @constant {string} API_BASE
 */
const API_BASE = "api";

/**
 * An array that holds a list of chores.
 * Each chore represents a task or duty to be performed.
 * This variable is initialized as an empty array and can be populated with chores as needed.
 */
let chores = [];
/**
 * An object used to store and manage chores indexed by their unique identifier.
 * The keys represent the unique IDs of chores, and the values are the corresponding chore data.
 */
let choreById = {};
/**
 * Represents the identifier of the currently selected chore.
 * This variable is used to track which chore is selected by the user.
 *
 * The value can be:
 * - A valid identifier for a selected chore (usually a unique value like an integer or string).
 * - `null` if no chore is selected.
 *
 * This helps in managing the state of selected chores in applications such as task trackers or chore management systems.
 */
let selectedChoreId = null;
/**
 * A boolean flag indicating whether the chore is newly created.
 *
 * - `true`: The chore is newly created and has not been processed or marked as existing.
 * - `false`: The chore is not new and already exists in the system.
 */
let isNewChore = false;

/**
 * A constant array containing a predefined list of user names.
 * Represents a collection of string values, each corresponding to a user.
 * This array is immutable and cannot be modified after initialization.
 *
 * Each element in the array is a string value representing a user:
 * - "ash"
 * - "vast"
 * - "sephy"
 * - "hope"
 * - "cylis"
 * - "phil"
 * - "selina"
 *
 * Suitable for use cases where a fixed list of user identifiers is required.
 */
const USERS = ["ash", "vast", "sephy", "hope", "cylis", "phil", "selina"];

document.addEventListener("DOMContentLoaded", () => {
    setupUI();
    loadChores();
});

// ---------------------------------------------------------------
// UI SETUP
// ---------------------------------------------------------------
/**
 * Initializes and sets up the user interface by attaching event listeners
 * to various UI elements for handling user interactions.
 *
 * @return {void} This function does not return any value.
 */
function setupUI() {
    const addBtn = document.getElementById("addChoreBtn");
    const form = document.getElementById("choreForm");
    const searchInput = document.getElementById("searchInput");

    addBtn.addEventListener("click", () => startNewChore());
    form.addEventListener("submit", onFormSubmit);

    document.getElementById("deleteChoreBtn")
        .addEventListener("click", onDeleteChore);

    document.getElementById("resetStatsBtn")
        .addEventListener("click", onResetStats);

    document.getElementById("frequencyType")
        .addEventListener("change", updateFrequencyVisibility);

    document.getElementById("skipChoreBtn")
        .addEventListener("click", skipSelectedChore);

    document.getElementById("undesirableCheckbox")
        .addEventListener("change", updateUndesirableVisibility);

    const visRadios = document.querySelectorAll("input[name='visibility']");
    visRadios.forEach(r => r.addEventListener("change", updateVisibilitySection));

    searchInput.addEventListener("input", () => renderChoreList());

    document.getElementById("resetPointsBtn")
        .addEventListener("click", resetPoints);

    document.getElementById("undoChoreBtn")
        .addEventListener("click", undoSelectedChore);
}

// ---------------------------------------------------------------
// LOAD & RENDER CHORES
// ---------------------------------------------------------------
/**
 * Loads chores from the server and processes the data to populate chore-related structures.
 * The method fetches the list of chores, organizes them by ID, and triggers UI updates.
 *
 * @return {Promise<void>} A promise that resolves when chores are successfully loaded and processed.
 */
async function loadChores() {
    try {
        const res = await fetch(`${API_BASE}/get_all_chores.php`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        chores = Array.isArray(data) ? data : [];
        choreById = {};
        chores.forEach(c => {
            if (c.id) choreById[c.id] = c;
        });

        renderChoreList();
        refreshAfterChoreOptions();
    } catch (err) {
        console.error("Error loading chores:", err);
        alert("Failed to load chores for admin.");
    }
}

/**
 * Renders a list of chores into the DOM element with the ID "choreList".
 * Filters the chores based on a search term entered in the input field with the ID "searchInput".
 * Displays each chore with its details and hierarchical structure.
 * Adds event listeners to each chore for selection and interaction.
 *
 * @return {void} This method does not return a value.
 */
function renderChoreList() {
    const list = document.getElementById("choreList");
    const searchTerm = (document.getElementById("searchInput").value || "").toLowerCase();
    list.innerHTML = "";

    const childrenMap = {};
    chores.forEach(c => {
        if (c.frequencyType === "after" && c.afterChoreId) {
            if (!childrenMap[c.afterChoreId]) childrenMap[c.afterChoreId] = [];
            childrenMap[c.afterChoreId].push(c);
        }
    });

    function renderChoreRecursive(chore, depth = 0) {
        const searchable = `${chore.name || ""} ${chore.id || ""}`.toLowerCase();
        const kids = childrenMap[chore.id] || [];

        if (searchTerm && !searchable.includes(searchTerm)) {
            const childMatches = kids.some(k =>
                `${k.name || ""} ${k.id || ""}`.toLowerCase().includes(searchTerm)
            );
            if (!childMatches) return;
        }

        const item = document.createElement("div");
        item.className = "chore-list-item";
        if (chore.id === selectedChoreId) {
            item.classList.add("selected");
        }

        item.style.paddingLeft = `${depth * 20 + 10}px`;

        const nameEl = document.createElement("div");
        nameEl.className = "chore-name";
        nameEl.textContent = chore.name || "(unnamed)";

        const metaEl = document.createElement("div");
        metaEl.className = "chore-meta";

        let visText;
        if (chore.frequencyType === "after") {
            const parent = choreById[chore.afterChoreId] || null;
            visText = parent
                ? `After "${parent.name || parent.id}"`
                : `After (missing parent: ${chore.afterChoreId || "?"})`;
        } else {
            visText = chore.inPool
                ? "Public"
                : `Assigned to ${chore.assignedTo || "?"}`;
        }

        metaEl.textContent =
            `${visText} • ${chore.frequencyType || "unknown"} • id: ${chore.id}`;

        item.appendChild(nameEl);
        item.appendChild(metaEl);

        item.addEventListener("click", () => {
            selectedChoreId = chore.id;
            isNewChore = false;
            loadChoreIntoForm(chore);
            highlightSelectedChore();
        });

        list.appendChild(item);

        const children = [...kids].sort((a, b) =>
            (a.name || "").localeCompare(b.name || "")
        );

        for (const child of children) {
            renderChoreRecursive(child, depth + 1);
        }
    }

    const topLevel = chores
        .filter(c => c.frequencyType !== "after")
        .sort((a, b) => (a.name || "").localeCompare(b.name || ""));

    for (const chore of topLevel) {
        renderChoreRecursive(chore, 0);
    }
}

/**
 * Highlights the selected chore from a list of chores by adding the "selected" class
 * to the corresponding list item. Clears the "selected" class from all other list items.
 * The selected chore is determined based on a comparison of its ID with the `selectedChoreId`.
 *
 * @return {void} Does not return any value.
 */
function highlightSelectedChore() {
    const items = document.querySelectorAll(".chore-list-item");
    items.forEach(item => item.classList.remove("selected"));

    const list = document.getElementById("choreList");
    [...list.children].forEach(item => {
        const meta = item.querySelector(".chore-meta");
        if (!meta) return;
        if (selectedChoreId && meta.textContent.includes(`id: ${selectedChoreId}`)) {
            item.classList.add("selected");
        }
    });
}

// ===============================
// ADD POINTS MODAL
// ===============================

/**
 * Opens the "Add Points" modal by making it visible and resetting the input value to an empty string.
 *
 * @return {void} Does not return a value.
 */
function openAddPointsModal() {
    const modal = document.getElementById("addPointsModal");
    document.getElementById("addPointsValue").value = "";
    modal.classList.remove("hidden");
}

/**
 * Closes the "Add Points" modal by adding a "hidden" class to its element.
 *
 * @return {void} This method does not return any value.
 */
function closeAddPointsModal() {
    document.getElementById("addPointsModal").classList.add("hidden");
}

// Wire buttons
document.getElementById("addPointsButton")?.addEventListener("click", openAddPointsModal);
document.getElementById("addPointsCancel")?.addEventListener("click", closeAddPointsModal);

document.getElementById("addPointsConfirm")?.addEventListener("click", async () => {
    const user = document.getElementById("addPointsUser").value;
    const value = parseFloat(document.getElementById("addPointsValue").value);

    if (isNaN(value) || value <= 0) {
        alert("Please enter a valid number of points.");
        return;
    }

    try {
        const resp = await fetch("api/add_points.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ user, value })
        });

        const data = await resp.json();

        if (data.status === "ok") {
            console.log(`[ADMIN] Added ${value} points to ${user}`);
            closeAddPointsModal();
            
            // If the admin panel shows points, refresh it
            if (typeof loadPoints === "function") loadPoints();

        } else {
            alert("Error adding points: " + data.error);
        }

    } catch (err) {
        console.error("[ADMIN] Add points error:", err);
        alert("Network error or invalid server response");
    }
});


// ---------------------------------------------------------------
// FORM LOADING / NEW CHORE
// ---------------------------------------------------------------
/**
 * Populates the chore editing form with the data from the provided chore object and updates the UI elements accordingly.
 *
 * @param {Object} chore The chore object containing the data to be loaded into the form.
 * @param {string} [chore.id] The unique identifier of the chore.
 * @param {string} [chore.name] The name of the chore.
 * @param {string} [chore.description] A description of the chore.
 * @param {number} [chore.value=1] The value or reward points associated with the chore.
 * @param {boolean} [chore.spawn=true] Indicates whether the chore should regularly regenerate.
 * @param {string} [chore.frequencyType="daily"] The type of frequency for the chore (e.g., daily, weekly).
 * @param {number} [chore.customDays=1] The custom number of days for the frequency, if applicable.
 * @param {string} [chore.afterChoreId] The ID of the chore that must be completed before this chore can be done.
 * @param {number} [chore.weeklyDay=0] The day of the week (0-6) for weekly chores.
 * @param {boolean} [chore.afterDinner=false] Indicates if it is an after-dinner chore.
 * @param {string} [chore.cron] The cron expression for scheduling the chore.
 * @param {boolean} [chore.inPool] Indicates if the chore is public and in the shared pool.
 * @param {boolean} [chore.undesirable=false] Indicates if the chore is undesirable.
 * @param {Array<string>} [chore.eligibleUsers] List of user IDs eligible for undesirable chores.
 * @param {string} [chore.assignedTo] The user ID of the person assigned to the chore.
 * @param {string} [chore.lastMarkedDate] The last date when the chore was marked as complete.
 * @param {string} [chore.lastMarkedBy] The ID of the user who last marked the chore as complete.
 * @param {number} [chore.timesMarkedOff=0] The total number of times the chore has been marked off.
 *
 * @return {void} This function does not return a value.
 */
function loadChoreIntoForm(chore) {
    const form = document.getElementById("choreForm");
    const noMsg = document.getElementById("noChoreSelectedMessage");
    form.style.display = "block";
    noMsg.style.display = "none";

    document.getElementById("editorTitle").textContent = "Edit Chore";

    document.getElementById("choreId").value = chore.id || "";
    document.getElementById("choreName").value = chore.name || "";
    document.getElementById("choreDescription").value = chore.description || "";
    document.getElementById("choreValue").value = chore.value ?? 1;
    document.getElementById("choreSpawn").checked = chore.spawn !== false;

    document.getElementById("frequencyType").value = chore.frequencyType || "daily";
    document.getElementById("customDays").value = chore.customDays || 1;
    document.getElementById("afterChoreId").value = chore.afterChoreId || "";
    document.getElementById("weeklyDay").value = (chore.weeklyDay ?? 0).toString();

    document.getElementById("afterDinnerCheckbox").checked = chore.afterDinner === true;
    document.getElementById("cronExpression").value = chore.cron || "";

    const visRadios = document.querySelectorAll("input[name='visibility']");
    if (chore.inPool) {
        visRadios.forEach(r => { r.checked = (r.value === "public"); });
    } else {
        visRadios.forEach(r => { r.checked = (r.value === "assigned"); });
    }

    document.getElementById("undesirableCheckbox").checked =
        chore.undesirable === true;

    renderUndesirableUserList(chore.eligibleUsers || []);
    updateUndesirableVisibility();

    document.getElementById("assignedTo").value = chore.assignedTo || "";

    document.getElementById("lastMarkedDateDisplay").textContent =
        chore.lastMarkedDate || "—";
    document.getElementById("lastMarkedByDisplay").textContent =
        chore.lastMarkedBy || "—";
    document.getElementById("timesMarkedOffDisplay").textContent =
        chore.timesMarkedOff || 0;

    updateFrequencyVisibility();
    updateVisibilitySection();
    refreshAfterChoreOptions(chore.id);
    highlightSelectedChore();
}

/**
 * Initializes the UI and internal state for adding a new chore.
 * Resets relevant input fields, updates the form's visibility, and prepares the editor for creating a new chore.
 *
 * @return {void} This method does not return a value.
 */
function startNewChore() {
    selectedChoreId = null;
    isNewChore = true;

    const form = document.getElementById("choreForm");
    const noMsg = document.getElementById("noChoreSelectedMessage");
    form.style.display = "block";
    noMsg.style.display = "none";

    document.getElementById("editorTitle").textContent = "Add New Chore";

    document.getElementById("choreId").value = "";
    document.getElementById("choreName").value = "";
    document.getElementById("choreDescription").value = "";
    document.getElementById("choreValue").value = 1;
    document.getElementById("choreSpawn").checked = true;

    document.getElementById("frequencyType").value = "daily";
    document.getElementById("customDays").value = 1;
    document.getElementById("weeklyDay").value = "0";
    document.getElementById("afterChoreId").value = "";

    const visRadios = document.querySelectorAll("input[name='visibility']");
    visRadios.forEach(r => { r.checked = (r.value === "public"); });
    document.getElementById("assignedTo").value = "";

    document.getElementById("afterDinnerCheckbox").checked = false;
    document.getElementById("undesirableCheckbox").checked = false;
    renderUndesirableUserList([]);
    updateUndesirableVisibility();

    document.getElementById("lastMarkedDateDisplay").textContent = "—";
    document.getElementById("lastMarkedByDisplay").textContent = "—";
    document.getElementById("timesMarkedOffDisplay").textContent = "0";

    refreshAfterChoreOptions(null);
    updateFrequencyVisibility();
    updateVisibilitySection();
    highlightSelectedChore();
}

// ---------------------------------------------------------------
// FREQUENCY / VISIBILITY / UNDESIRABLE UI
// ---------------------------------------------------------------
/**
 * Updates the visibility of specific HTML elements based on the selected frequency type.
 * The function evaluates the value of an element with the ID "frequencyType" and toggles
 * the display style of related rows ("customDaysRow", "afterChoreRow", "weeklyDayRow", "cronField")
 * accordingly.
 *
 * @return {void} Does not return a value.
 */
function updateFrequencyVisibility() {
    const freq = document.getElementById("frequencyType").value;
    const customRow = document.getElementById("customDaysRow");
    const afterRow = document.getElementById("afterChoreRow");
    const weeklyRow = document.getElementById("weeklyDayRow");
    const cronRow = document.getElementById("cronField");

    customRow.style.display = (freq === "custom") ? "flex" : "none";
    afterRow.style.display  = (freq === "after") ? "flex" : "none";
    weeklyRow.style.display = (freq === "weekly") ? "flex" : "none";
    cronRow.style.display   = (freq === "cron") ? "flex" : "none";
}

/**
 * Renders a list of undesirable users with checkboxes, highlighting selected users.
 *
 * @param {string[]} selected - An array of usernames to be selected initially in the list.
 * @return {void} Does not return a value.
 */
function renderUndesirableUserList(selected = []) {
    const container = document.getElementById("undesirableUsersContainer");
    if (!container) return;
    container.innerHTML = "";

    USERS.forEach(u => {
        const div = document.createElement("div");
        div.style.display = "flex";
        div.style.alignItems = "center";

        const cb = document.createElement("input");
        cb.type = "checkbox";
        cb.value = u;
        cb.checked = selected.includes(u);

        const label = document.createElement("label");
        label.textContent = u;
        label.style.marginLeft = "6px";

        div.appendChild(cb);
        div.appendChild(label);
        container.appendChild(div);
    });
}

/**
 * Updates the display state of a visibility section based on the selected visibility option.
 * Adjusts the visibility of an element (typically a row) depending on whether the visibility
 * input is selected and its value.
 *
 * @return {void} This method does not return a value.
 */
function updateVisibilitySection() {
    const visRadio = document.querySelector("input[name='visibility']:checked");
    const row = document.getElementById("assignedToRow");
    if (!visRadio) {
        row.style.display = "none";
        return;
    }
    row.style.display = (visRadio.value === "assigned") ? "flex" : "none";
}

/**
 * Refreshes the list of selectable after-chore options in a dropdown element.
 * Updates the dropdown to exclude a specified chore and sorts the remaining chores alphabetically.
 *
 * @param {string|null} excludeId - The ID of the chore to exclude from the dropdown list. If null, no chore is excluded.
 * @return {void} Does not return a value.
 */
function refreshAfterChoreOptions(excludeId = null) {
    const select = document.getElementById("afterChoreId");
    if (!select) return;

    const prevValue = select.value;

    select.innerHTML = "";
    const emptyOption = document.createElement("option");
    emptyOption.value = "";
    emptyOption.textContent = "-- select chore --";
    select.appendChild(emptyOption);

    let list = chores.filter(ch =>
        ch.id &&
        ch.id !== excludeId &&
        ch.frequencyType !== "after" // don't allow after->after chains # comment-ception WHY DID I DO THIS
    );

    list.sort((a, b) =>
        (a.name || "").toLowerCase().localeCompare((b.name || "").toLowerCase())
    );

    for (const chore of list) {
        const opt = document.createElement("option");
        opt.value = chore.id;
        opt.textContent = `${chore.name} (id: ${chore.id})`;
        select.appendChild(opt);
    }

    if (prevValue) {
        select.value = prevValue;
    }
}

/**
 * Toggles the visibility of the "undesirableUsersRow" element based on the checked state of the "undesirableCheckbox" element.
 *
 * @return {void} This function does not return a value.
 */
function updateUndesirableVisibility() {
    const isU = document.getElementById("undesirableCheckbox").checked;
    document.getElementById("undesirableUsersRow").style.display = isU ? "flex" : "none";
}

// ---------------------------------------------------------------
// PAYLOAD BUILDER 
// ---------------------------------------------------------------
/**
 * Constructs a chore payload object based on values from a form.
 * The payload contains information such as chore ID, name, description,
 * value, visibility settings, frequency type, and additional details
 * relevant to chore scheduling and assignment.
 *
 * @return {Object} An object containing the payload for the chore, populated with values from form fields.
 */
function buildChorePayloadFromForm() {
    const id = document.getElementById("choreId").value.trim();
    const name = document.getElementById("choreName").value.trim();
    const description = document.getElementById("choreDescription").value.trim();
    const value = parseFloat(document.getElementById("choreValue").value) || 0;
    const spawn = document.getElementById("choreSpawn").checked;

    const freq = document.getElementById("frequencyType").value;
    const customDaysRaw = document.getElementById("customDays").value;
    const customDays = customDaysRaw ? parseInt(customDaysRaw, 10) : null;

    const weeklyDaySelect = document.getElementById("weeklyDay").value;
    const weeklyDay = weeklyDaySelect !== "" ? parseInt(weeklyDaySelect, 10) : null;

    const afterChoreIdVal = document.getElementById("afterChoreId").value;
    const afterChoreId = afterChoreIdVal ? afterChoreIdVal : null;

    const vis = document.querySelector("input[name='visibility']:checked").value;
    const assignedTo = document.getElementById("assignedTo").value || "";

    const undesirable = document.getElementById("undesirableCheckbox").checked;
    const afterDinner = document.getElementById("afterDinnerCheckbox").checked;

    let eligibleUsers = [];
    if (undesirable) {
        document
            .querySelectorAll("#undesirableUsersContainer input[type=checkbox]")
            .forEach(cb => {
                if (cb.checked) eligibleUsers.push(cb.value);
            });
    }

    const payload = {
        id: id || null,
        name,
        description,
        value,
        spawn,
        frequencyType: freq,
        customDays: (freq === "custom") ? customDays : null,
        weeklyDay: (freq === "weekly") ? weeklyDay : null,
        afterChoreId: (freq === "after") ? afterChoreId : null,
        afterDinner,
        inPool: (vis === "public"),
        assignedTo: (vis === "assigned") ? assignedTo : "",
        undesirable,
        eligibleUsers,
        
    };

    if (freq === "cron") {
        payload.cron = document.getElementById("cronExpression").value.trim();
    }

    return payload;
}

// ---------------------------------------------------------------
// SAVE CHORE (FORM SUBMIT)
// ---------------------------------------------------------------
/**
 * Handles the form submission event for saving a chore. Validates the input data,
 * constructs the chore payload, sends it to the server, and updates the UI upon success.
 * Displays relevant error messages for validation or server errors.
 *
 * @param {Event} event - The form submission event.
 * @return {Promise<void>} Resolves when the chore has been successfully saved and the UI updated.
 */
async function onFormSubmit(event) {
    event.preventDefault();

    const payload = buildChorePayloadFromForm();

    const freq = payload.frequencyType;
    const name = payload.name;
    const customDays = payload.customDays;
    const afterChoreId = payload.afterChoreId;
    const visAssigned = !payload.inPool;
    const assignedTo = payload.assignedTo;
    const id = payload.id;
    const cronExpr = payload.cron || "";

    // Validation
    if (!name) {
        alert("Chore name is required.");
        return;
    }

    if (freq === "custom" && (!customDays || customDays < 1)) {
        alert("Custom frequency requires a positive number of days.");
        return;
    }

    if (freq === "after" && !afterChoreId) {
        alert("Please select a chore for 'After' frequency.");
        return;
    }

    if (visAssigned && !assignedTo) {
        alert("Please choose who this chore is assigned to.");
        return;
    }

    if (freq === "after" && id && afterChoreId === id) {
        alert("A chore cannot depend on itself.");
        return;
    }

    if (freq === "cron" && !cronExpr.trim()) {
        alert("Cron chores must have a cron expression.");
        return;
    }

    try {
        const res = await fetch(`${API_BASE}/save_chore.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const text = await res.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            throw new Error(`Non-JSON response (${res.status}): ${text}`);
        }

        if (!res.ok || result.status !== "ok") {
            throw new Error(result.error || `HTTP ${res.status}`);
        }

        await loadChores();

        if (result.id) {
            selectedChoreId = result.id;
        } else if (id) {
            selectedChoreId = id;
        }

        const saved = selectedChoreId ? choreById[selectedChoreId] : null;
        if (saved) {
            loadChoreIntoForm(saved);
            highlightSelectedChore();
        }

        alert("Chore saved.");
    } catch (err) {
        console.error("Error saving chore:", err);
        alert("Failed to save chore: " + err.message);
    }
}

// ---------------------------------------------------------------
// DELETE CHORE
// ---------------------------------------------------------------
/**
 * Handles the deletion of a chore by its identifier. Prompts the user for confirmation
 * before proceeding to delete the chore, removes the chore from the system, and updates
 * the user interface accordingly. This also removes associated statistics from user files.
 *
 * The method interacts with a backend service for deletion, handles error scenarios,
 * and ensures the UI reflects the updated state after successful deletion.
 *
 * @return {Promise<void>} Resolves when the chore is successfully deleted and the UI is updated.
 * Rejects and logs an error if the deletion or subsequent operations fail.
 */
async function onDeleteChore() {
    const id = document.getElementById("choreId").value.trim();
    if (!id) {
        alert("No chore selected to delete.");
        return;
    }

    const chore = choreById[id];
    const name = chore ? chore.name : id;
    if (!confirm(`Delete chore "${name}"?\nThis will also remove its stats from all user files.`)) {
        return;
    }

    try {
        const res = await fetch(`${API_BASE}/delete_chore.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id })
        });

        const text = await res.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            throw new Error(`Non-JSON response (${res.status}): ${text}`);
        }

        if (!res.ok || result.status !== "ok") {
            throw new Error(result.error || `HTTP ${res.status}`);
        }

        await loadChores();
        selectedChoreId = null;
        isNewChore = false;

        document.getElementById("choreForm").style.display = "none";
        document.getElementById("noChoreSelectedMessage").style.display = "block";
        document.getElementById("editorTitle").textContent = "Select a chore to edit";

        alert("Chore deleted and stats removed.");
    } catch (err) {
        console.error("Error deleting chore:", err);
        alert("Failed to delete chore: " + err.message);
    }
}

// ---------------------------------------------------------------
// RESET STATS 
// ---------------------------------------------------------------
/**
 * Resets the statistics for a specific chore, including last marked date,
 * user who last marked it, and the count of times it has been marked off.
 * Prompts the user for confirmation before proceeding. Constructs and submits
 * a payload to update the chore with the default stats and other properties.
 * Refreshes the chore data upon success to reflect the changes.
 *
 * @return {Promise<void>} A promise that resolves once the chore statistics
 *         have been reset and data reloaded, or rejects if an error occurs during the operation.
 */
async function onResetStats() {
    const id = document.getElementById("choreId").value.trim();
    if (!id) {
        alert("No chore selected.");
        return;
    }

    if (!confirm("Reset stats for this chore? (last date/by and times marked)")) {
        return;
    }

    // Build full chore object exactly like a normal save,
    // but force stats to reset.
const freq = document.getElementById("frequencyType").value;

const payload = {
    id,
    name: document.getElementById("choreName").value.trim(),
    description: document.getElementById("choreDescription").value.trim(),
    value: parseFloat(document.getElementById("choreValue").value) || 0,
    spawn: document.getElementById("choreSpawn").checked,
    frequencyType: freq,

    // Required frequency fields:
    customDays:
        (freq === "custom")
            ? parseInt(document.getElementById("customDays").value)
            : null,

    weeklyDay:
        (freq === "weekly")
            ? parseInt(document.getElementById("weeklyDay").value)
            : null,

    afterChoreId:
        (freq === "after")
            ? document.getElementById("afterChoreId").value || choreById[id]?.afterChoreId || null
            : null,

    cron:
        (freq === "cron")
            ? document.getElementById("cronExpression").value.trim()
            : "",

    afterDinner: document.getElementById("afterDinnerCheckbox").checked,
    undesirable: document.getElementById("undesirableCheckbox").checked,

    eligibleUsers: [...document.querySelectorAll("#undesirableUsersContainer input[type=checkbox]")]
        .filter(cb => cb.checked)
        .map(cb => cb.value),

    inPool: document.querySelector("input[name='visibility']:checked").value === "public",
    assignedTo: document.getElementById("assignedTo").value,

    forceResetStats: true,

    lastMarkedDate: "",
    lastMarkedBy: "",
    timesMarkedOff: 0
};



    try {
        const res = await fetch(`${API_BASE}/save_chore.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const resultText = await res.text();
        let result;
        try {
            result = JSON.parse(resultText);
        } catch {
            throw new Error("Server returned non-JSON: " + resultText);
        }

        if (result.status !== "ok") {
            throw new Error(result.error || "Save failed");
        }

        // Reload chores so the right panel updates from fresh data
        await loadChores();
        const saved = choreById[id];
        if (saved) loadChoreIntoForm(saved);

        alert("Stats reset.");
    } catch (err) {
        console.error("Reset stats failed:", err);
        alert("Failed to reset stats: " + err.message);
    }
}


// ---------------------------------------------------------------
// RESET ALL POINTS
// ---------------------------------------------------------------
/**
 * Resets the weekly points and optionally increments the streak if all chores were completed.
 * Prompts the user to confirm the reset and, if applicable, increments the streak before resetting points.
 *
 * @return {Promise<void>} A promise that resolves once the points have been reset and appropriate actions have been completed.
 */
async function resetPoints() {
    if (!confirm("Reset all weekly points?")) return;

    const inc = confirm("Did everyone finish their chores this week?");

    if (inc) {
        try {
            const s = await fetch("api/increment_streak.php", { method: "POST" });
            const t = await s.text();
            console.log("[Streak increment raw]:", t);
        } catch (e) {
            console.error("[Streak] Increment error:", e);
        }
    }


    try {
        const res = await fetch("api/reset_points.php", {
            method: "POST"
        });

        const text = await res.text();
        console.log("[ResetPoints raw]:", text);

        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            alert("Server error resetting points.");
            return;
        }

        if (data.status === "ok") {
            alert("Weekly points reset!");
        } else {
            alert("Failed to reset points.");
        }
    } catch (e) {
        console.error("Reset points error:", e);
        alert("Server error resetting points.");
    }
}

// ---------------------------------------------------------------
// SKIP & UNDO
// ---------------------------------------------------------------
/**
 * Skips the currently selected chore by marking it as done for the day without providing any credit.
 * Prompts the user for confirmation before proceeding. Sends a request to the server to skip the chore
 * and reloads the list of chores upon successful completion. Handles errors or invalid server responses.
 *
 * @return {Promise<void>} Resolves when the chore is successfully skipped and the list of chores is reloaded.
 *                         If an error occurs, displays an appropriate alert message.
 */
async function skipSelectedChore() {
    if (!selectedChoreId) {
        alert("No chore selected.");
        return;
    }

    if (!confirm("Skip this chore? (Marks it done today without credit)")) return;

    const res = await fetch("api/skip_chore.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ choreId: selectedChoreId })
    });

    const text = await res.text();
    console.log("[skipChore raw]:", text);

    let data;
    try { data = JSON.parse(text); }
    catch { return alert("Server returned invalid response."); }

    if (data.status === "ok") {
        alert("Chore skipped.");
        await loadChores();
    } else {
        alert("Failed to skip chore: " + (data.error || "unknown"));
    }
}

/**
 * Undoes the last completion for the currently selected chore.
 * If no chore is selected or the operation is not confirmed by the user, the process is aborted.
 * Communicates with the server to revert the chore's completion status, and reloads the chore list upon success.
 *
 * @return {Promise<void>} A promise that resolves once the chore undo operation completes and the chore list is reloaded, or rejects if an error occurs.
 */
async function undoSelectedChore() {
    if (!selectedChoreId) {
        alert("No chore selected.");
        return;
    }

    if (!confirm("Undo last completion of this chore?")) return;

    const res = await fetch("api/undo_chore.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ choreId: selectedChoreId })
    });

    const txt = await res.text();
    console.log("[undoChore raw]:", txt);

    let data;
    try { data = JSON.parse(txt); }
    catch { return alert("Invalid server response."); }

    if (data.status === "ok") {
        alert("Undo successful.");
        await loadChores();
    } else {
        alert("Undo failed: " + data.error);
    }
}
