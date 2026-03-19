import axios from "axios";
import Swal from "sweetalert2";

import Employee from "./components/employee.vue";
import TimeRecord from "./components/timerecord.vue";
import Violations from "./components/violations.vue";
import Payroll from "./components/payroll.vue";
import EWH from "./components/EWH.vue";

import HolidayModal from "./components/holidaymodal.vue";
import bootstrap from "bootstrap/dist/js/bootstrap.bundle.min.js";
import AnnouncementModal from "./components/announcementmodal.vue";
import scheduling from "./components/scheduling.vue";
import History from "./components/history.vue";
import Menu from "primevue/menu";
import Select from "primevue/select";
import { PrimeIcons } from "@primevue/core/api";
import Button from "primevue/button";
const API_BASE_URL = import.meta.env.VITE_API_URL;

const DAYS = [
    { v: 1, label: "Mon" },
    { v: 2, label: "Tue" },
    { v: 4, label: "Wed" },
    { v: 8, label: "Thu" },
    { v: 16, label: "Fri" },
    { v: 32, label: "Sat" },
    { v: 64, label: "Sun" },
];

const DATETIME_FIELDS = [
    "TimeIn",
    "TimeOut",
    "shortbreak_start",
    "shortbreak_end",
];

function toLocalInput(dt) {
    if (!dt) return "";
    const d = dt.replace(" ", "T");
    return d.slice(0, 16); // YYYY-MM-DDTHH:MM
}
function fromLocalInput(dtLocal) {
    return dtLocal ? dtLocal.replace("T", " ") + ":00" : null;
}
function clone(obj) {
    return JSON.parse(JSON.stringify(obj || {}));
}

function debounce(fn, t = 300) {
    let to;
    return (...args) => {
        clearTimeout(to);
        to = setTimeout(() => fn(...args), t);
    };
}

const emptyAnnouncementForm = () => ({
    id: null,
    title: "",
    content: "",
    start_at: "",
    end_at: "",
    status: "draft",
    user_ids: [],
    groupPH: false,
    groupUS: false,
    _mode: null,
});

const SCHED_EP = {
    timesched: `${API_BASE_URL}/hr/timesched`,
    usersched: `${API_BASE_URL}/hr/usersched`,
};

function toLegacyClockPayload(f) {
    return {
        clock_id: f.clock_id || undefined,
        edited_by: f.editor_id || f.edited_by || undefined, // map name mismatch
        from: f.from || undefined,
        to: f.to || undefined,
    };
}

// take whatever the legacy API returns and normalize to your unified row shape
function adaptClockEditRow(r, i = 0) {
    // 'when' field fallbacks
    const when =
        r.when || r.edit_timestamp || r.created_at || r.updated_at || null;

    // 'edited_by' as object if possible
    const edited_by =
        r.edited_by && typeof r.edited_by === "object"
            ? r.edited_by
            : {
                  id:
                      r.edited_by_id ??
                      r.editor_id ??
                      r.user_id ??
                      r.userId ??
                      null,
                  name:
                      r.edited_by_name ??
                      r.editor_name ??
                      r.username ??
                      r.user ??
                      r.editor ??
                      r.edited_by ??
                      null,
              };

    // prefer explicit changes; else compute from before/after
    const changes =
        r.changes && r.changes !== ""
            ? r.changes
            : Array.isArray(this?.prettyDiff)
              ? []
              : null; // placeholder when no helper
    // if you have prettyDiff on hrContext (you do), use it:
    const computedChanges =
        !changes && this.prettyDiff
            ? this.prettyDiff(r.before || r.old || {}, r.after || r.new || {})
            : changes || [];

    return {
        id:
            r.id ??
            r.ID ??
            `clock-${r.clock_id ?? r.ClockID ?? i}-${when ?? i}`,
        clock_id: r.clock_id ?? r.ClockID ?? r.id ?? r.ID ?? null,
        edited_by,
        when,
        changes: computedChanges,
    };
}

const historyAdapters = {
    time: (r, i, ctx) => {
        const when =
            r.when || r.edit_timestamp || r.created_at || r.updated_at || null;
        const edited_by =
            typeof r.edited_by === "object"
                ? r.edited_by
                : {
                      id: r.edited_by_id ?? r.editor_id ?? null,
                      name:
                          r.edited_by_name ??
                          r.editor_name ??
                          r.edited_by ??
                          null,
                  };

        const changes =
            r.changes && r.changes !== ""
                ? r.changes
                : ctx.prettyDiff
                  ? ctx.prettyDiff(
                        r.before || r.old || {},
                        r.after || r.new || {},
                    )
                  : [];

        return {
            id:
                r.id ??
                r.ID ??
                `clock-${r.clock_id ?? r.ClockID ?? i}-${when ?? i}`,
            clock_id: r.clock_id ?? r.ClockID ?? null,
            edited_by,
            when,
            changes,
        };
    },

    // stubs for future types; fill these when you wire endpoints
    leave: (r) => ({
        id: r.id,
        employee: r.employee,
        edited_by: r.edited_by,
        when: r.when,
        changes: r.changes,
    }),
    rate: (r) => ({
        id: r.id,
        employee: r.employee,
        edited_by: r.edited_by,
        when: r.when,
        changes: r.changes,
    }),
    violation: (r) => ({
        id: r.id,
        employee: r.employee,
        edited_by: r.edited_by,
        when: r.when,
        changes: r.changes,
    }),
};

// --- fetchers per type ---
const historyFetchers = {
    // live today
    async time(ctx, page) {
        // legacy endpoint (no pagination yet)
        const f = ctx.history.filters;
        const payload = {
            clock_id: f.clock_id || undefined,
            edited_by: f.editor_id || f.edited_by || undefined,
            from: f.from || undefined,
            to: f.to || undefined,
        };
        const { data } = await axios.post(
            `${API_BASE_URL}/hr/time-records/edit-history`,
            payload,
        );
        const raw = Array.isArray(data)
            ? data
            : data?.data || data?.items || [];
        const rows = raw.map((r, i) => historyAdapters.time(r, i, ctx));
        return { rows, nextPage: null };
    },

    // future paths (replace stubs with real calls when ready)
    async leave(ctx, page) {
        const f = ctx.history.filters;
        const { data } = await axios.get(`${API_BASE_URL}/hr/history`, {
            params: {
                type: "leave",
                from: f.from || undefined,
                to: f.to || undefined,
                page,
            },
        });
        const raw = Array.isArray(data?.data) ? data.data : data?.items || [];
        return {
            rows: raw.map((r, i) => historyAdapters.leave(r, i, ctx)),
            nextPage: data?.next_page ?? null,
        };
    },

    async rate(ctx, page) {
        const f = ctx.history.filters;
        const { data } = await axios.get(`${API_BASE_URL}/hr/history`, {
            params: {
                type: "rate",
                from: f.from || undefined,
                to: f.to || undefined,
                page,
            },
        });
        const raw = Array.isArray(data?.data) ? data.data : data?.items || [];
        return {
            rows: raw.map((r, i) => historyAdapters.rate(r, i, ctx)),
            nextPage: data?.next_page ?? null,
        };
    },

    async violation(ctx, page) {
        const f = ctx.history.filters;
        const { data } = await axios.get(`${API_BASE_URL}/hr/history`, {
            params: {
                type: "violation",
                from: f.from || undefined,
                to: f.to || undefined,
                page,
            },
        });
        const raw = Array.isArray(data?.data) ? data.data : data?.items || [];
        return {
            rows: raw.map((r, i) => historyAdapters.violation(r, i, ctx)),
            nextPage: data?.next_page ?? null,
        };
    },
};

export default {
    components: {
        Employee,
        TimeRecord,
        Violations,
        HolidayModal,
        AnnouncementModal,
        scheduling,
        History,
        Payroll,
        EWH,
        Menu,
        Select,
        Button,
    },

    data() {
        return {
            // navbar controller
            currentView: "Employee",
            newTabs: [
                {
                    label: "Employee",
                    icon: PrimeIcons.USERS,
                    command: () => this.setView("Employee"),
                },
                {
                    label: "Time Record",
                    icon: PrimeIcons.CLOCK,
                    command: () => this.setView("Time Record"),
                },
                {
                    label: "Violations",
                    icon: PrimeIcons.EXCLAMATION_TRIANGLE,
                    command: () => this.setView("Violations"),
                },
                {
                    label: "Announcement",
                    icon: PrimeIcons.BELL,
                    command: () => this.setView("Announcement"),
                },
                {
                    label: "Holiday",
                    icon: PrimeIcons.CALENDAR_TIMES,
                    command: () => this.setView("Holiday"),
                },
                {
                    label: "History",
                    icon: PrimeIcons.HISTORY,
                    command: () => this.setView("History"),
                },
                {
                    label: "Scheduling",
                    icon: PrimeIcons.CALENDAR,
                    command: () => this.setView("Scheduling"),
                },
                {
                    label: "Payroll",
                    icon: PrimeIcons.MONEY_BILL,
                    command: () => this.setView("Payroll"),
                },
                {
                    label: "EWH",
                    icon: PrimeIcons.FILE_CHECK,
                    command: () => this.setView("EWH"),
                },
            ],
            tabs: [
                "Employee",
                "Time Record",
                "Violations",
                "Announcement",
                "Holiday",
                "History",
                "Scheduling",
                // "EWH",
                "Payroll",
            ],
            dropdownOpen: false,
            showFilters: true,

            localYear: this.year,

            loading: {
                employees: false,
                leave: false,
                violations: false,
                rateHistory: false,
                clockHistory: false,
                scheduling: false,
            },
            loaded: {
                employees: false,
                leave: false,
                violations: false,
                rateHistory: false,
                clockHistory: false,
                scheduling: false,
            },

            // UI State
            showAddEmployeeModal: false,
            showAddAnnouncementModal: false,

            // Employees
            employees: [],
            newEmployee: { name: "", position: "", accounttype: "" },
            employeeModal: {
                show: false,
                tab: "details", // 'details' | 'rate'
                selectedEmployee: null,
            },

            // Read-only profile pulled from tbluser_profile
            profile: {
                full_name: null,
                work_email: null,
                contact_phone: null,
                birthdate: null,
                address: null,
                ice_name: null,
                ice_relationship: null,
                ice_phone: null,
            },

            // Rate editor modal state
            showRateModal: false,
            selectedEmployee: null,
            savingRate: false,
            rateForm: {
                employee_id: null,
                employee_username: null, // snapshot
                effective_start: "",
                effective_end: null,
                monthly_rate: null,
                hourly_rate: null,
                currency: "PHP",
            },

            permissions: {
                user_id: null,
                modules: {}, // { order: true, labeling: false, ... }
                main_module: null, // "order" | null
                module_keys: [], // backend-provided list for rendering
            },
            permissionsLoading: false,
            permissionsSaving: false,

            // Time Record
            timeRecords: [],
            loadingTimeRecords: false,
            filters: { employee: "", dateFrom: "", dateTo: "" },
            sortKey: "DateToday",
            sortOrder: "desc",
            page: 1,
            limit: 25,
            totalPages: 1,
            currentPage: 1,
            expandedClockId: null,
            historyLoading: false,

            // Edit modal
            showEditModal: false,
            editOriginal: null,
            editForm: null,

            // Dropdown source
            employeeNames: [],

            // Histories
            leaveHistory: [],
            violations: [],

            // universal outbound payload bucket
            data_sent: null,

            // button spinner
            submittingEdit: false,

            // rate history
            rateHistory: [],
            rateHistoryFilterEmployeeId: "",
            rateHistoryFilterOnlyActive: false,

            // Holiday modal
            showHolidayModal: false,
            holidayModal: false,
            holidays: [],
            holidayYear: new Date().getFullYear(),
            holidayForm: {
                holidayID: null,
                title: "",
                status: "Regular Holiday",
                holidate: "", // 'YYYY-MM-DD'
                is_recurring: false, // boolean in UI; convert to 0/1 for backend
            },

            // Announcement modal
            showAnnouncementModal: false,
            showManageAnnouncements: false,
            annSubmitting: false,
            announcementForm: {
                id: null,
                title: "",
                content: "",
                start_at: "",
                end_at: "",
                status: "draft",
                user_ids: [],
                groupPH: false,
                groupUS: false,
                _mode: null,
            },
            // Manage modal table
            manageFilter: { status: "all", q: "" },
            manageRows: [],

            // Scheduling
            sched_times: [],
            sched_filter: { day: "", active: "" },
            sched_preset: "Everyday",
            sched_tForm: {
                _days_bits: [1, 2, 4, 8, 16, 32, 64], // default = everyday
                day_of_week: 0, // legacy, ignore
                start_time: "",
                end_time: "",
                end_next_day: false,
                unpaid_break_minutes: 60,
                title: "",
                is_active: true,
                early_login_mins: 0,
                early_clockin_mins: 15,
                grace_clockout_mins: 10,
            },
            sched_editTId: null,

            sched_userlinks: [],
            sched_uForm: {
                userId: null,
                schedId: "",
                schednote: "",
                effective_from: "",
                effective_to: "",
            },
            sched_editUId: null,
            sched_selectedUserId: null,

            // history states
            history: {
                type: "time", // 'time' | 'leave' | 'rate' | 'violation'
                filters: { clock_id: "", editor_id: "", from: "", to: "" },
                rows: [],
                nextPage: null,
                loading: false,
                columnsByType: {
                    time: [
                        { key: "#", width: 60 },
                        { key: "clock_id", label: "Clock ID" },
                        { key: "edited_by.name", label: "Edited By" },
                        { key: "when", label: "When" },
                        { key: "changes", label: "Changes", render: "diff" },
                    ],
                    leave: [
                        { key: "#", width: 60 },
                        { key: "employee", label: "Employee" },
                        { key: "edited_by.name", label: "Edited By" },
                        { key: "when", label: "When" },
                        { key: "changes", label: "Changes", render: "diff" },
                    ],
                    rate: [
                        { key: "#", width: 60 },
                        { key: "employee", label: "Employee" },
                        { key: "edited_by.name", label: "Edited By" },
                        { key: "when", label: "When" },
                        { key: "changes", label: "Rate Δ", render: "diff" },
                    ],
                    violation: [
                        { key: "#", width: 60 },
                        { key: "employee", label: "Employee" },
                        { key: "edited_by.name", label: "Edited By" },
                        { key: "when", label: "When" },
                        { key: "changes", label: "Details", render: "diff" },
                    ],
                },
            },
            historyFlags: {
                time: true,
                leave: false,
                rate: false,
                violation: false,
            },
            get historyTabs() {
                return Object.entries(this.historyFlags)
                    .filter(([, on]) => on)
                    .map(([t]) => t); // ['time', ...]
            },
        };
    },

    created() {
        if (!this.currentView && this.newTabs.length)
            this.currentView = this.newTabs[0];
    },

    mounted() {
        console.log("Auth check:", {
            store: this.$store?.state?.user,
            window: window.username,
            localStorage: localStorage.getItem("username"),
            session: sessionStorage.getItem("username"),
        });

        this.setView("Employee");
        // Initialize Holiday modal
        const el = document.getElementById("holidayModal");
        if (el && typeof bootstrap !== "undefined") {
            this.holidayModal = new bootstrap.Modal(el, {
                backdrop: "static",
                keyboard: false,
            });
        }
    },

    methods: {
        setView(view) {
            // 1) Derive label
            const viewLabel = (
                typeof view === "string"
                    ? view
                    : (view?.label ?? view?.name ?? view?.text ?? "")
            )
                .toString()
                .trim();

            this.currentView = viewLabel;

            if (viewLabel === "Scheduling") {
                this.loadView("Scheduling");
                return;
            }

            if (viewLabel === "History") {
                this.openHistory();
                return;
            }

            // 2) Map views -> method names
            const actions = {
                employee: "fetchEmployeesOnce",
                "time record": "fetchRecords",
                violations: "fetchViolationsOnce",
                announcement: "refreshManageAnnouncements",
                holiday: "fetchHolidays",
            };

            // 3) Resolve callable from this or parent, then run
            const fnName = actions[viewLabel.toLowerCase()];
            if (!fnName) return;

            const getFn = (ctx, name) =>
                ctx && typeof ctx[name] === "function" ? ctx[name] : null;

            const run = getFn(this, fnName) || getFn(this.$parent, fnName);

            if (run) {
                run();
            } else {
                console.warn(
                    `${fnName}() not found on this component or parent.`,
                );
            }
        },

        toggleDropdown() {
            this.dropdownOpen = !this.dropdownOpen;
        },

        toggleFilters() {
            this.showFilters = !this.showFilters;
        },

        yearChanged() {
            this.$emit("update:year", this.localYear);
            this.$emit("changed", this.localYear); // optional event
        },

        async loadView(view) {
            try {
                switch (view) {
                    case "Employee":
                        await Promise.all([
                            this.fetchEmployeesOnce(),
                            this.fetchEmployeeRateHistoryOnce(), // ⬅️ add this
                        ]);
                        break;

                    case "Employee Rate History":
                        await this.fetchEmployeeRateHistoryOnce(); // ⬅️ only history
                        break;

                    case "Time Record":
                        await this.fetchRecords();
                        break;

                    case "Time Record Edit History":
                        await this.fetchClockEditHistoryOnce(this.histFilters);
                        break;

                    case "Employee Leave History":
                        await this.fetchLeaveOnce();
                        break;

                    case "Violations":
                    case "Violations History":
                        await this.fetchViolationsOnce();
                        break;

                    case "Scheduling":
                        await Promise.all([
                            this.fetchEmployeesOnce(),
                            this.fetchSchedulingOnce(),
                        ]);
                        // pick first employee if none selected yet
                        if (
                            !this.sched_selectedUserId &&
                            Array.isArray(this.employees) &&
                            this.employees.length
                        ) {
                            this.schedOnSelectUser(this.employees[0].id);
                        }
                        break;
                }
            } catch (e) {
                console.error("loadView error:", e);
            }
        },

        openAddEmployeeModal() {
            this.showAddEmployeeModal = true;
        },

        closeAddEmployeeModal() {
            this.showAddEmployeeModal = false;
        },

        async addEmployee() {
            if (
                !this.newEmployee.name ||
                !this.newEmployee.position ||
                !this.newEmployee.accounttype
            ) {
                Swal.fire({
                    icon: "warning",
                    title: "Missing Information",
                    text: "Please fill in all fields.",
                    confirmButtonColor: "#3085d6",
                });
                return;
            }

            try {
                // Get CSRF token
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content");

                // API call to save to database
                const response = await fetch("/hr/employees", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrfToken,
                    },
                    body: JSON.stringify({
                        username: this.newEmployee.name,
                        office_role: this.newEmployee.position,
                        accounttype: this.newEmployee.accounttype,
                    }),
                });

                if (response.ok) {
                    const newEmp = await response.json();

                    // Close modal and reset form
                    this.newEmployee = {
                        name: "",
                        position: "",
                        accounttype: "",
                    };
                    this.showAddEmployeeModal = false;

                    // Success message
                    Swal.fire({
                        icon: "success",
                        title: "Success!",
                        text: "Employee added successfully!",
                        confirmButtonColor: "#28a745",
                    });

                    this.loaded.employees = false;
                    this.fetchEmployeesOnce();
                } else {
                    // Failed message
                    Swal.fire({
                        icon: "error",
                        title: "Failed",
                        text: "Failed to add employee. Please try again.",
                        confirmButtonColor: "#dc3545",
                    });
                }
            } catch (error) {
                console.error("Error adding employee:", error);
                // Error message
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "An error occurred while adding the employee.",
                    confirmButtonColor: "#dc3545",
                });
            }
        },

        async fetchEmployeesOnce() {
            if (this.loaded.employees || this.loading.employees) return;
            this.loading.employees = true;
            try {
                const res = await fetch(`${API_BASE_URL}/hr/employees`);
                const data = await res.json().catch(() => ({}));
                this.employees = Array.isArray(data)
                    ? data
                    : (data?.employees ?? []);
            } catch (e) {
                console.error("Failed to load employees", e);
                this.employees = [];
            } finally {
                this.loading.employees = false;
                this.loaded.employees = true;
            }
        },

        async openEmployeeModal(emp) {
            this.employeeModal.selectedEmployee = emp || null;
            this.employeeModal.tab = "details";
            this.employeeModal.show = true;

            // ── Profile ──────────────────────────────────────────
            try {
                const res = await fetch(`${API_BASE_URL}/hr/profile/${emp.id}`);
                const data = await res.json().catch(() => ({}));
                this.profile = data?.profile || {};
                this.employeeModal.selectedEmployeeUser = data?.user || null;
            } catch (e) {
                console.error("Failed to load profile", e);
                this.profile = {};
            }

            // ── Permissions ───────────────────────────────────────
            this.permissionsLoading = true;
            try {
                const { data } = await axios.get(
                    `${API_BASE_URL}/hr/employees/${emp.id}/permissions`,
                );
                this.permissions = {
                    user_id: Number(emp.id),
                    modules: data?.modules || {},
                    main_module: data?.main_module ?? null,
                    module_keys: Array.isArray(data?.module_keys)
                        ? data.module_keys
                        : Object.keys(data?.modules || {}),
                };
            } catch (e) {
                console.error("Failed to load permissions", e);
                this.permissions = {
                    user_id: Number(emp.id),
                    modules: {},
                    main_module: null,
                    module_keys: [],
                };
            } finally {
                this.permissionsLoading = false;
            }

            // ── Rate form — pre-fill from current rate ────────────
            const today = new Date().toISOString().slice(0, 10);

            // Start with defaults
            this.rateForm = {
                employee_id: emp.id,
                employee_username: emp.username || emp.name || null,
                effective_start: today,
                effective_end: null,
                monthly_rate: null,
                hourly_rate: null,
                currency: "PHP",
            };

            try {
                const { data } = await axios.get(
                    `${API_BASE_URL}/hr/employees/${emp.id}/rates/current`,
                );

                const rate = data?.data;

                if (rate) {
                    this.rateForm.effective_start = rate.effective_start
                        ? rate.effective_start.split("T")[0]
                        : today;
                    this.rateForm.effective_end = rate.effective_end
                        ? rate.effective_end.split("T")[0]
                        : null;
                    this.rateForm.monthly_rate = rate.monthly_rate ?? null;
                    this.rateForm.hourly_rate = rate.hourly_rate ?? null;
                    this.rateForm.currency = rate.currency ?? "PHP";
                }
            } catch (e) {
                console.warn(
                    "Could not fetch current rate, form left at defaults.",
                    e,
                );
            }
        },

        setEmployeeModalTab(tab) {
            if (["details", "rate", "perms"].includes(tab)) {
                this.employeeModal.tab = tab;
            }
        },

        toggleModule(key, checked) {
            // flip the boolean in place
            this.permissions.modules = {
                ...this.permissions.modules,
                [key]: !!checked,
            };
            // if we turned the current main_module off, clear it
            if (!checked && this.permissions.main_module === key) {
                this.permissions.main_module = null;
            }
        },

        async savePermissions() {
            if (!this.permissions.user_id) return;

            // validate: main_module must be one of the enabled modules (or null)
            const mm = this.permissions.main_module;
            if (mm !== null && this.permissions.modules[mm] !== true) {
                return Swal.fire(
                    "Invalid main module",
                    "Main module must be one of the enabled modules.",
                    "warning",
                );
            }

            try {
                this.permissionsSaving = true;

                const payload = {
                    modules: this.permissions.modules, // { key: true/false }
                    main_module: this.permissions.main_module, // "order" | null
                };

                await axios.post(
                    `${API_BASE_URL}/hr/employees/${this.permissions.user_id}/permissions`,
                    payload,
                );

                Swal.fire("Saved", "Permissions updated.", "success");
            } catch (e) {
                console.error("savePermissions error:", e);
                const msg =
                    e?.response?.data?.message ||
                    e?.message ||
                    "Failed to save permissions.";
                Swal.fire("Error", msg, "error");
            } finally {
                this.permissionsSaving = false;
            }
        },

        closeEmployeeModal() {
            this.employeeModal.show = false;
            this.employeeModal.selectedEmployee = null;

            this.profile = {
                full_name: null,
                work_email: null,
                contact_phone: null,
                birthdate: null,
                address: null,
                ice_name: null,
                ice_relationship: null,
                ice_phone: null,
            };

            this.permissions = {
                user_id: null,
                modules: {},
                main_module: null,
                module_keys: [],
            };

            // neutral reset; no out-of-scope vars
            this.rateForm = {
                employee_id: null,
                employee_username: null,
                effective_start: "",
                effective_end: null,
                monthly_rate: null,
                hourly_rate: null,
                currency: "PHP",
            };
        },

        // edit employee rate
        openRateModal(emp) {
            const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
            this.selectedEmployee = emp;

            this.rateForm = {
                employee_id: emp.id,
                employee_username: emp.username || emp.name || null,
                effective_start: today,
                effective_end: null,
                monthly_rate: null,
                hourly_rate: null,
                currency: "PHP",
            };

            this.showRateModal = true;
        },

        closeRateModal() {
            this.showRateModal = false;
            this.selectedEmployee = null;
            this.savingRate = false;
        },

        async submitRate() {
            if (!this.rateForm.employee_id) {
                return Swal.fire(
                    "Missing Employee",
                    "Please select an employee.",
                    "warning",
                );
            }
            if (!this.rateForm.effective_start) {
                return Swal.fire(
                    "Effective Start Required",
                    "Please provide an effective start date.",
                    "warning",
                );
            }
            if (!this.rateForm.monthly_rate && !this.rateForm.hourly_rate) {
                return Swal.fire(
                    "Rate Required",
                    "Please provide at least a monthly or hourly rate.",
                    "warning",
                );
            }

            try {
                this.savingRate = true;

                const url = `${API_BASE_URL}/hr/employees/${this.rateForm.employee_id}/rates`;
                const payload = {
                    employee_username: this.rateForm.employee_username,
                    effective_start: this.rateForm.effective_start,
                    effective_end: this.rateForm.effective_end || null,
                    monthly_rate: this.rateForm.monthly_rate,
                    hourly_rate: this.rateForm.hourly_rate,
                    currency: this.rateForm.currency || "PHP",
                };

                await axios.put(url, payload);

                if (this.selectedEmployee && payload.monthly_rate != null) {
                    this.selectedEmployee.employee_rate = payload.monthly_rate;
                }

                this.loaded.employees = false;
                this.loaded.rateHistory = false;

                await Swal.fire(
                    "Success",
                    "Employee rate has been saved successfully.",
                    "success",
                );

                this.closeEmployeeModal();
                this.fetchEmployeesOnce();
            } catch (e) {
                console.error("Failed to save rate", e);
                Swal.fire(
                    "Error",
                    "Failed to save rate. Please try again.",
                    "error",
                );
            } finally {
                this.savingRate = false;
            }
        },

        // employee rate history
        async fetchEmployeeRateHistoryOnce(employeeId = null) {
            if (this.loaded.rateHistory || this.loading.rateHistory) return;
            this.loading.rateHistory = true;
            try {
                const url = employeeId
                    ? `${API_BASE_URL}/hr/employee-rate-history?employee_id=${employeeId}`
                    : `${API_BASE_URL}/hr/employee-rate-history`;

                const res = await fetch(url);
                const data = await res.json();
                this.rateHistory = Array.isArray(data) ? data : data.data || [];
            } catch (e) {
                console.error("Failed to load rate history", e);
            } finally {
                this.loading.rateHistory = false;
                this.loaded.rateHistory = true;
            }
        },

        isActiveRate(row) {
            const today = new Date().toISOString().slice(0, 10);
            return (
                row.effective_start <= today &&
                (!row.effective_end || row.effective_end >= today)
            );
        },

        async refreshRateHistory(employeeId = null) {
            // force a refetch regardless of loaded flag
            this.loaded.rateHistory = false;
            await this.fetchEmployeeRateHistoryOnce(employeeId);
        },

        // Time Records
        async fetchRecords() {
            this.loadingTimeRecords = true;
            try {
                const res = await axios.get(`${API_BASE_URL}/hr/time-records`, {
                    params: {
                        ...this.filters,
                        sortKey: this.sortKey,
                        sortOrder: this.sortOrder,
                        page: this.page,
                        limit: this.limit,
                    },
                });

                const result = res.data;

                this.timeRecords = result.data;
                this.totalPages = result.last_page;
                this.currentPage = result.current_page;

                // Build employeeNames from employees (fallback to page data)
                if (Array.isArray(this.employees) && this.employees.length) {
                    this.employeeNames = [
                        ...new Set(
                            this.employees.map((e) => e.name).filter(Boolean),
                        ),
                    ].sort((a, b) => a.localeCompare(b));
                } else {
                    this.employeeNames = [
                        ...new Set(
                            (result.data || [])
                                .map((r) => r.Employee)
                                .filter(Boolean),
                        ),
                    ].sort((a, b) => a.localeCompare(b));
                }
            } catch (err) {
                console.error("Failed to fetch records", err);
            } finally {
                this.loadingTimeRecords = false;
            }
        },

        sort(key) {
            if (this.sortKey === key) {
                this.sortOrder = this.sortOrder === "asc" ? "desc" : "asc";
            } else {
                this.sortKey = key;
                this.sortOrder = "asc";
            }
            this.fetchRecords();
        },

        formatDate(datetime) {
            if (!datetime) return "-";
            const d = new Date(datetime);
            return d.toLocaleString();
        },

        async fetchLeaveOnce() {
            if (this.loaded.leave || this.loading.leave) return;
            this.loading.leave = true;
            try {
                const res = await fetch(`${API_BASE_URL}/hr/leave-history`);
                const data = await res.json();
                this.leaveHistory = Array.isArray(data)
                    ? data
                    : data.leaveHistory || [];
                this.loaded.leave = true;
            } catch (e) {
                console.error("Failed to load leave history", e);
            } finally {
                this.loading.leave = false;
            }
        },

        async fetchViolationsOnce() {
            if (this.loaded.violations || this.loading.violations) return;
            this.loading.violations = true;
            try {
                const res = await fetch(`${API_BASE_URL}/hr/violations`);
                const data = await res.json();
                this.violations = Array.isArray(data)
                    ? data
                    : data.violations || [];
                this.loaded.violations = true;
            } catch (e) {
                console.error("Failed to load violations", e);
            } finally {
                this.loading.violations = false;
            }
        },

        async toggleHistory(clockId) {
            // collapse if clicking the same row
            if (this.expandedClockId === clockId) {
                this.expandedClockId = null;
                return;
            }

            // open new row and load history
            this.expandedClockId = clockId;
            this.historyLoading = true;

            // reuse your existing API call
            await this.fetchClockEditHistoryByClock(clockId)
                .catch((e) => console.error("load history error", e))
                .finally(() => {
                    this.historyLoading = false;
                });
        },

        // tiny helper to compute changed keys when backend returns before/after objects
        prettyDiff(before = {}, after = {}) {
            const keys = Array.from(
                new Set([
                    ...Object.keys(before || {}),
                    ...Object.keys(after || {}),
                ]),
            );
            return keys
                .filter(
                    (k) =>
                        String(before?.[k] ?? "") !== String(after?.[k] ?? ""),
                )
                .map((k) => ({
                    key: k,
                    from: before?.[k] ?? "",
                    to: after?.[k] ?? "",
                }));
        },

        // Time Records Edit History
        async fetchClockEditHistoryOnce(params = {}) {
            if (this.loaded.clockHistory || this.loading.clockHistory) return;
            this.loading.clockHistory = true;
            try {
                const { data } = await axios.post(
                    `${API_BASE_URL}/hr/time-records/edit-history`,
                    { ...this.histFilters, ...params },
                );

                // accept several backend shapes
                this.clockEditHistory = Array.isArray(data)
                    ? data
                    : data.data || data.items || [];

                this.loaded.clockHistory = true;
            } catch (e) {
                console.error("Failed to load clock edit history", e);
            } finally {
                this.loading.clockHistory = false;
            }
        },

        async refreshClockEditHistory(params = {}) {
            this.loaded.clockHistory = false;
            await this.fetchClockEditHistoryOnce(params);
        },

        async fetchClockEditHistoryByClock(clockId) {
            this.loading.clockHistory = true;
            try {
                const { data } = await axios.post(
                    `${API_BASE_URL}/hr/time-records/${clockId}/edit-history`,
                    {},
                );
                this.clockEditHistory = Array.isArray(data)
                    ? data
                    : data.data || data.items || [];
                this.loaded.clockHistory = true;
            } catch (e) {
                console.error("Failed to load clock edit history by clock", e);
            } finally {
                this.loading.clockHistory = false;
            }
        },

        // Edit modal
        openEdit(row) {
            this.data_sent = null;
            this.editOriginal = clone(row);
            this.editForm = clone(row);

            DATETIME_FIELDS.forEach((k) => {
                this.editForm[`${k}_local`] = toLocalInput(this.editForm[k]);
            });

            this.showEditModal = true;
        },

        closeEdit() {
            this.showEditModal = false;
            this.editOriginal = null;
            this.editForm = null;
            this.data_sent = null;
        },

        buildAfterPayload() {
            const f = this.editForm || {};
            const after = clone(f);

            DATETIME_FIELDS.forEach((k) => {
                after[k] = fromLocalInput(f[`${k}_local`]);
                delete after[`${k}_local`];
            });

            const allowed = [
                "ID",
                "userid",
                "Employee",
                "DateToday",
                "TimeIn",
                "TimeOut",
                "shortbreak_start",
                "shortbreak_end",
                "shortbreak_totaltime",
                "Notes",
                "AdminNote",
            ];
            const cleaned = {};
            allowed.forEach((k) => {
                if (k in after) cleaned[k] = after[k] ?? null;
            });
            return cleaned;
        },

        async submitEdit() {
            try {
                const id = this.editOriginal?.ID || this.editForm?.ID;
                const after = this.buildAfterPayload();

                this.data_sent = { after };

                this.submittingEdit = true;
                await axios.post(
                    `${API_BASE_URL}/hr/time-records/${id}/edit`,
                    this.data_sent,
                );

                await this.fetchRecords();
                this.closeEdit();
            } catch (err) {
                console.error("Failed to save edit", err);
            } finally {
                this.submittingEdit = false;
                this.data_sent = null;
            }
        },

        // (Optional) helpers for pagination buttons if you want to call them from child templates
        nextPage() {
            if (this.page < this.totalPages) {
                this.page += 1;
                this.fetchRecords();
            }
        },

        prevPage() {
            if (this.page > 1) {
                this.page -= 1;
                this.fetchRecords();
            }
        },

        // Holiday modal
        openHolidayModal() {
            this.resetHolidayForm();
            this.fetchHolidays();

            this.showHolidayModal = true;
            // if (this.holidayModal) this.holidayModal.show();
        },

        resetHolidayForm() {
            this.holidayForm = {
                holidayID: null,
                title: "",
                status: "Regular Holiday",
                holidate: "",
                is_recurring: false,
            };
        },

        closeHolidayModal() {
            this.resetHolidayForm();
            this.showHolidayModal = false;
        },

        async fetchHolidays() {
            try {
                const year = this.holidayYear || new Date().getFullYear();

                const { data } = await axios.post(
                    `${API_BASE_URL}/hr/holidays/list`,
                    { year },
                );

                if (data?.success) {
                    this.holidays = data.items || [];
                } else {
                    console.warn("Failed to load holidays payload:", data);
                }
            } catch (err) {
                console.error("fetchHolidays error:", err);
                alert("Failed to load holidays.");
            }
        },

        editHoliday(row) {
            this.holidayForm = {
                holidayID: row.holidayID,
                title: row.title,
                status: row.status,
                holidate: row.holidate,
                is_recurring: !!row.is_recurring,
            };
            if (this.holidayModal) this.holidayModal.show();
        },

        // import Swal from 'sweetalert2'

        async saveHoliday() {
            const payload = {
                holidayID: this.holidayForm.holidayID,
                title: (this.holidayForm.title || "").trim(),
                status: this.holidayForm.status,
                holidate: this.holidayForm.holidate,
                is_recurring: this.holidayForm.is_recurring ? 1 : 0,
            };

            const url = this.holidayForm.holidayID
                ? `${API_BASE_URL}/hr/holidays/update`
                : `${API_BASE_URL}/hr/holidays/store`;

            try {
                // Removed loading modal
                const { data } = await axios.post(url, payload);

                if (data?.success) {
                    await this.fetchHolidays();
                    if (!this.holidayForm.holidayID) this.resetHolidayForm(); // manglimpyo after creation

                    await Swal.fire({
                        icon: "success",
                        title: this.holidayForm.holidayID
                            ? "Holiday updated!"
                            : "Holiday created!",
                        text: "Your changes have been saved.",
                        confirmButtonText: "OK",
                    });

                    this.showHolidayModal = false;
                } else {
                    const details =
                        data?.message ||
                        (data?.errors &&
                            Object.values(data.errors).flat().join("\n")) ||
                        "Please check your inputs.";

                    await Swal.fire({
                        icon: "warning",
                        title: "Validation failed",
                        text: details,
                    });
                }
            } catch (err) {
                console.error("saveHoliday error:", err);

                const details =
                    err?.response?.data?.message ||
                    (err?.response?.data?.errors &&
                        Object.values(err.response.data.errors)
                            .flat()
                            .join("\n")) ||
                    err?.message ||
                    "Something went wrong.";

                await Swal.fire({
                    icon: "error",
                    title: "Save failed",
                    text: details,
                });
            }
        },

        async deleteHoliday(holidayID) {
            // SweetAlert confirmation (replaces window.confirm)
            const { isConfirmed } = await Swal.fire({
                title: "Delete this holiday?",
                text: "This action cannot be undone.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, delete",
                cancelButtonText: "Cancel",
                reverseButtons: true,
                focusCancel: true,
            });

            if (!isConfirmed) return;

            try {
                const { data } = await axios.post(
                    `${API_BASE_URL}/hr/holidays/delete`,
                    { holidayID },
                );

                if (data?.success) {
                    await this.fetchHolidays();

                    // Centered success dialog (not a toast)
                    await Swal.fire({
                        icon: "success",
                        title: "Holiday deleted",
                        text: "The holiday has been removed.",
                        confirmButtonText: "OK",
                    });
                } else {
                    const details =
                        data?.message ||
                        (data?.errors &&
                            Object.values(data.errors).flat().join("\n")) ||
                        "Please try again.";

                    await Swal.fire({
                        icon: "error",
                        title: "Delete failed",
                        text: details,
                    });
                }
            } catch (err) {
                console.error("deleteHoliday error:", err);
                const details =
                    err?.response?.data?.message ||
                    (err?.response?.data?.errors &&
                        Object.values(err.response.data.errors)
                            .flat()
                            .join("\n")) ||
                    err?.message ||
                    "Something went wrong.";

                await Swal.fire({
                    icon: "error",
                    title: "Delete failed",
                    text: details,
                });
            }
        },

        openAddAnnouncementModal() {
            this.showAddAnnouncementModal = true;
            Object.assign(this.announcementForm, emptyAnnouncementForm());
        },

        closeAddAnnouncementModal() {
            this.showAddAnnouncementModal = false; // was true
            Object.assign(this.announcementForm, emptyAnnouncementForm());
        },

        // Announcement modal
        openAnnouncementModal() {
            this.fetchEmployeesOnce().finally(() => {
                this.showAnnouncementModal = true;
            });
        },

        closeAnnouncementModal() {
            this.showAnnouncementModal = false;
            this.annSubmitting = false;
            this.announcementForm = {
                id: null,
                title: "",
                content: "",
                start_at: "",
                end_at: "",
                status: "draft",
                user_ids: [],
                groupPH: false,
                groupUS: false,
                _mode: null,
            };
        },

        applyAnnouncementGroupSelection() {
            // recompute recipients from group toggles (users can still tweak manually after)
            const phIds = this.employees
                .filter((e) => e.accounttype === "PH")
                .map((e) => e.id);
            const usIds = this.employees
                .filter((e) => e.accounttype === "US")
                .map((e) => e.id);

            const { groupPH, groupUS } = this.announcementForm;

            if (groupPH && !groupUS) {
                this.announcementForm.user_ids = [...new Set(phIds)];
            } else if (!groupPH && groupUS) {
                this.announcementForm.user_ids = [...new Set(usIds)];
            } else if (groupPH && groupUS) {
                this.announcementForm.user_ids = [
                    ...new Set([...phIds, ...usIds]),
                ];
            }
            // if neither is checked, leave manual selection as-is
        },

        toggleGroup(groupKey) {
            if (groupKey === "PH") {
                this.announcementForm.groupPH = !this.announcementForm.groupPH;
            } else if (groupKey === "US") {
                this.announcementForm.groupUS = !this.announcementForm.groupUS;
            }
            this.applyAnnouncementGroupSelection();
        },

        async submitAnnouncement(mode = null) {
            // allow buttons to pass 'draft' | 'active'; or use form.status
            const saveMode = mode || this.announcementForm.status || "draft";

            if (!this.announcementForm.title.trim()) {
                alert("Title is required.");
                return;
            }
            if (
                this.announcementForm.start_at &&
                this.announcementForm.end_at
            ) {
                if (
                    new Date(this.announcementForm.start_at) >
                    new Date(this.announcementForm.end_at)
                ) {
                    alert("Start must be before End.");
                    return;
                }
            }

            this.annSubmitting = true;
            this.announcementForm._mode = saveMode;

            try {
                const SAVE_URL = `${API_BASE_URL}/hr/announcements/save`;
                const csrf =
                    (typeof window !== "undefined" && window.csrfToken) ||
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content") ||
                    null;

                if (!csrf) {
                    console.warn(
                        'CSRF token not found. Add window.csrfToken or a <meta name="csrf-token"> tag.',
                    );
                }

                const body = {
                    id: this.announcementForm.id || null,
                    title: this.announcementForm.title,
                    message: this.announcementForm.content,
                    start_at: this.announcementForm.start_at || null, // datetime-local (local tz)
                    end_at: this.announcementForm.end_at || null,
                    save_mode: saveMode, // 'draft' | 'active'
                    recipients: Array.isArray(this.announcementForm.user_ids)
                        ? this.announcementForm.user_ids
                        : [],
                };

                const { data } = await axios.post(SAVE_URL, body, {
                    withCredentials: true,
                    headers: { "X-CSRF-TOKEN": csrf },
                });

                if (!data?.success)
                    throw new Error(data?.message || "Save failed");

                if (this.showManageAnnouncements)
                    await this.refreshManageAnnouncements();

                alert(
                    saveMode === "active"
                        ? "Saved & Activated"
                        : "Saved as Draft",
                );

                if (!this.announcementForm.id) {
                    this.closeAnnouncementModal();
                }
            } catch (e) {
                console.error("submitAnnouncement error:", e);
                alert(e?.message || "Failed to save announcement.");
            } finally {
                this.annSubmitting = false;
                this.announcementForm._mode = null;
            }
        },

        openManageAnnouncements() {
            this.showManageAnnouncements = true;
            this.refreshManageAnnouncements();
        },
        closeManageAnnouncements() {
            this.showManageAnnouncements = false;
        },
        // NOTE: requires a debounce(fn, wait) helper in scope
        debouncedRefreshManage: debounce(function () {
            this.refreshManageAnnouncements();
        }, 300),

        async refreshManageAnnouncements() {
            try {
                const ADMIN_URL = `${API_BASE_URL}/hr/announcements/admin`;
                const params = new URLSearchParams();
                if (this.manageFilter.status !== "all")
                    params.set("status", this.manageFilter.status);
                if (this.manageFilter.q) params.set("q", this.manageFilter.q);

                const url = `${ADMIN_URL}${
                    params.toString() ? `?${params.toString()}` : ""
                }`;
                const { data } = await axios.get(url, {
                    withCredentials: true,
                });
                this.manageRows = Array.isArray(data) ? data : [];
            } catch (e) {
                console.error("refreshManageAnnouncements error:", e);
                this.manageRows = [];
            }
        },

        prefillAnnouncementForm(row) {
            // helper to convert 'YYYY-MM-DD HH:mm:ss' -> 'YYYY-MM-DDTHH:MM'
            const toLocalInput = (s) =>
                s ? s.replace(" ", "T").slice(0, 16) : "";

            // if manage rows contain usernames, map to IDs; if already IDs, just set directly
            const idsFromUsernames = (arr) => {
                if (!Array.isArray(arr)) return [];
                const usernameToId = new Map(
                    this.employees.map((e) => [
                        String(e.username),
                        Number(e.id),
                    ]),
                );
                return arr
                    .map((u) => usernameToId.get(String(u)))
                    .filter((v) => Number.isFinite(v));
            };

            this.showAnnouncementModal = true;

            this.announcementForm = {
                id: row.id,
                title: row.title || "",
                content: row.message || "",
                start_at: toLocalInput(row.start_at || ""),
                end_at: toLocalInput(row.end_at || ""),
                status: row.is_active ? "active" : "draft",
                user_ids: Array.isArray(row.recipients)
                    ? typeof row.recipients[0] === "number"
                        ? row.recipients.map(Number)
                        : idsFromUsernames(row.recipients)
                    : [],
                groupPH: false,
                groupUS: false,
                _mode: null,
            };
        },

        getCurrentUsername() {
            return (
                this.$store?.state?.user?.username ||
                window.username ||
                localStorage.getItem("username") ||
                sessionStorage.getItem("username") ||
                "admin"
            );
        },

        async toggleAnnouncementActive(row) {
            try {
                const username = this.getCurrentUsername();
                const TOGGLE_URL = `${API_BASE_URL}/hr/announcements/toggle-active`;
                const { data } = await axios.post(
                    TOGGLE_URL,
                    {
                        username: username,
                        id: row.id,
                        make_active: !row.is_active,
                    },
                    { withCredentials: true },
                );

                if (!data?.success)
                    throw new Error(data?.message || "Toggle failed");

                await this.refreshManageAnnouncements();

                if (this.announcementForm.id === row.id) {
                    this.announcementForm.status = !row.is_active
                        ? "active"
                        : "draft";
                }
            } catch (e) {
                console.error("toggleAnnouncementActive error:", e);
                Swal.fire(
                    "Error",
                    e?.message || "Failed to update status.",
                    "error",
                );
            }
        },

        toggleGroup(groupKey) {
            if (groupKey === "PH") {
                this.announcementForm.groupPH = !this.announcementForm.groupPH;
            } else if (groupKey === "US") {
                this.announcementForm.groupUS = !this.announcementForm.groupUS;
            }
            this.applyAnnouncementGroupSelection();
        },

        // Scheduling
        async fetchSchedulingOnce() {
            if (this.loaded.scheduling || this.loading.scheduling) return;
            this.loading.scheduling = true;
            try {
                await this.schedLoadTemplates();
            } finally {
                this.loading.scheduling = false;
                this.loaded.scheduling = true;
            }
        },
        // Scheduling helpers
        schedDayName(d) {
            const m = {
                0: "Everyday",
                1: "Mon",
                2: "Tue",
                3: "Wed",
                4: "Thu",
                5: "Fri",
                6: "Sat",
                7: "Sun",
            };
            return m[Number(d)] ?? "???";
        },
        schedHhmm(t) {
            return (t || "").slice(0, 5);
        },

        bitsToMask(arr) {
            return (arr || []).reduce((m, b) => m | Number(b), 0);
        },

        schedResetTForm() {
            this.sched_editTId = null;
            this.sched_preset = "Everyday"; // <- match initial default
            this.sched_tForm = {
                _days_bits: [1, 2, 4, 8, 16, 32, 64], // <- everyday bits
                day_of_week: 0, // legacy, 0 = multi/everyday
                start_time: "",
                end_time: "",
                end_next_day: false,
                unpaid_break_minutes: 60,
                title: "",
                is_active: true,
                early_login_mins: 0,
                early_clockin_mins: 15,
                grace_clockout_mins: 10,
            };
        },

        schedResetUForm() {
            this.sched_editUId = null;
            this.sched_uForm = {
                userId: this.sched_selectedUserId || null,
                schedId: "",
                schednote: "",
                effective_from: "",
                effective_to: "",
                is_active: true,
            };
        },

        schedDaysLabel(mask, dow = 0, compact = true) {
            const NAMES = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
            const bits = Number(mask || 0);

            // new style (days_mask)
            if (bits > 0) {
                if (bits === 127) return "Everyday";
                // collect chosen day indices
                const idxs = [];
                for (let i = 0; i < 7; i++) if (bits & (1 << i)) idxs.push(i);

                if (!compact) return idxs.map((i) => NAMES[i]).join("/");

                // compact consecutive runs: [0,1,2,4] -> "Mon–Wed/Fri"
                const parts = [];
                let start = idxs[0],
                    prev = idxs[0];
                for (let k = 1; k <= idxs.length; k++) {
                    const cur = idxs[k];
                    if (cur !== prev + 1) {
                        parts.push(
                            start === prev
                                ? NAMES[start]
                                : `${NAMES[start]}–${NAMES[prev]}`,
                        );
                        start = cur;
                    }
                    prev = cur;
                }
                return parts.join("/");
            }

            // legacy single-day (day_of_week 0..7, where 0=Everyday)
            if (Number(dow) === 0) return "Everyday";
            const i = Number(dow) - 1;
            return NAMES[i] ?? "—";
        },

        async schedLoadTemplates() {
            try {
                const p = new URLSearchParams();
                if (this.sched_filter.day !== "")
                    p.set("day_of_week", this.sched_filter.day);
                if (this.sched_filter.active !== "")
                    p.set("is_active", this.sched_filter.active);
                const url = `${SCHED_EP.timesched}${
                    p.toString() ? `?${p}` : ""
                }`;
                const { data } = await axios.get(url);
                this.sched_times = Array.isArray(data?.data) ? data.data : [];
            } catch (e) {
                console.error("schedLoadTemplates", e);
                Swal.fire("Error", "Failed to load templates.", "error");
            }
        },
        async schedSaveTemplate() {
            try {
                const f = this.sched_tForm;
                const days_mask = this.bitsToMask(f._days_bits);

                if (!days_mask) {
                    return Swal.fire(
                        "Days required",
                        "Pick at least one day.",
                        "warning",
                    );
                }

                // legacy: set single day 1..7 when only one bit is chosen, else 0
                const single = f._days_bits.length === 1 ? f._days_bits[0] : 0;
                const day_of_week = single
                    ? Math.log2(single) + 1 // 1..7
                    : 0; // 0 = “Everyday / multi”

                const payload = {
                    days_mask, // NEW
                    day_of_week, // legacy/compat
                    start_time: f.start_time,
                    end_time: f.end_time,
                    end_next_day: !!f.end_next_day,
                    unpaid_break_minutes: f.unpaid_break_minutes,
                    title: f.title || undefined,
                    is_active: !!f.is_active,
                    early_login_mins: Number(f.early_login_mins ?? 0),
                    early_clockin_mins: Number(f.early_clockin_mins ?? 0),
                    grace_clockout_mins: Number(f.grace_clockout_mins ?? 0),
                };

                if (this.sched_editTId) {
                    await axios.put(
                        `${SCHED_EP.timesched}/${this.sched_editTId}`,
                        payload,
                    );
                    Swal.fire("Updated", "Template updated.", "success");
                } else {
                    await axios.post(SCHED_EP.timesched, payload);
                    Swal.fire("Created", "Template created.", "success");
                }
                this.schedResetTForm();
                await this.schedLoadTemplates();
            } catch (e) {
                const msg =
                    e?.response?.data?.error || e?.message || "Save failed.";
                Swal.fire("Error", msg, "error");
            }
        },

        schedStartEditTemplate(row) {
            this.sched_editTId = row.timeschedId;

            const mask =
                Number(row.days_mask ?? 0) ||
                (Number(row.day_of_week) === 0
                    ? 127
                    : 1 << (Number(row.day_of_week) - 1));

            // expand bits
            const bits = [];
            for (let i = 0; i < 7; i++) if (mask & (1 << i)) bits.push(1 << i);

            // detect preset {Everyday | Mon | Tue | ... | Sun | Custom}
            const same = (a, b) =>
                a.length === b.length && a.every((v) => b.includes(v));
            const PRESETS = {
                Everyday: [1, 2, 4, 8, 16, 32, 64],
                Mon: [1],
                Tue: [2],
                Wed: [4],
                Thu: [8],
                Fri: [16],
                Sat: [32],
                Sun: [64],
            };
            let preset = "Custom";
            for (const [k, arr] of Object.entries(PRESETS)) {
                if (same(arr, bits)) {
                    preset = k;
                    break;
                }
            }

            this.sched_preset = preset;
            this.sched_tForm = {
                _days_bits: bits,
                day_of_week: Number(row.day_of_week) || 0, // legacy
                start_time: this.schedHhmm(row.start_time),
                end_time: this.schedHhmm(row.end_time),
                end_next_day: Number(row.end_next_day) === 1,
                unpaid_break_minutes: Number(row.unpaid_break_minutes),
                title: row.title || "",
                is_active: Number(row.is_active) === 1,

                early_login_mins: Number(row.early_login_mins || 0),
                early_clockin_mins: Number(row.early_clockin_mins || 0),
                grace_clockout_mins: Number(row.grace_clockout_mins || 0),
            };
        },

        async schedDeleteTemplate(row) {
            const ok = await Swal.fire({
                title: `Delete template #${row.timeschedId}?`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Delete",
            }).then((r) => r.isConfirmed);
            if (!ok) return;
            try {
                await axios.delete(`${SCHED_EP.timesched}/${row.timeschedId}`);
                if (this.sched_editTId === row.timeschedId)
                    this.schedResetTForm();
                await this.schedLoadTemplates();
                Swal.fire("Deleted", "Template removed.", "success");
            } catch (e) {
                Swal.fire("Error", "Delete failed.", "error");
            }
        },

        async schedLoadUserLinks(userId = null) {
            const uid =
                Number(
                    userId ??
                        this.sched_selectedUserId ??
                        this.sched_uForm.userId,
                ) || null;
            if (!uid) {
                return Swal.fire("Oops", "Select a user", "info");
            }
            try {
                const { data } = await axios.get(
                    `${SCHED_EP.usersched}?userId=${encodeURIComponent(uid)}`,
                );
                this.sched_userlinks = Array.isArray(data?.data)
                    ? data.data
                    : [];
                // keep forms in sync with the selected user
                this.sched_selectedUserId = uid;
                this.sched_uForm.userId = uid;
            } catch (e) {
                console.error("schedLoadUserLinks", e);
                Swal.fire("Error", "Failed to load user links.", "error");
            }
        },

        async schedSaveUserLink() {
            try {
                const f = this.sched_uForm;
                if (!f.userId || !f.schedId) {
                    Swal.fire(
                        "Error",
                        "User and Template are required.",
                        "warning",
                    );
                    return;
                }
                const payload = {
                    userId: f.userId,
                    schedId: f.schedId,
                    schednote: f.schednote || undefined,
                    effective_from: f.effective_from || undefined,
                    effective_to: f.effective_to || undefined,
                    is_active: f.is_active ? 1 : 0,
                };
                if (this.sched_editUId) {
                    await axios.put(
                        `${SCHED_EP.usersched}/${this.sched_editUId}`,
                        {
                            schedId: payload.schedId,
                            schednote: payload.schednote,
                            effective_from: payload.effective_from,
                            effective_to: payload.effective_to,
                            is_active: payload.is_active,
                        },
                    );
                    Swal.fire("Updated", "Link updated.", "success");
                } else {
                    await axios.post(SCHED_EP.usersched, payload);
                    Swal.fire("Linked", "Template linked to user.", "success");
                }
                this.schedResetUForm();
                await this.schedLoadUserLinks(this.sched_uForm.userId);
            } catch (e) {
                const msg =
                    e?.response?.data?.error || e?.message || "Save failed.";
                Swal.fire("Error", msg, "error");
            }
        },
        schedStartEditUserLink(row) {
            this.sched_editUId = row.userschedId;
            this.sched_uForm = {
                userId: row.userId,
                schedId: row.schedId,
                schednote: row.schednote || "",
                effective_from: row.effective_from || "",
                effective_to: row.effective_to || "",
                is_active: Number(row.is_active ?? 1) === 1,
            };
        },
        async schedDeleteUserLink(row) {
            const ok = await Swal.fire({
                title: `Delete link #${row.userschedId}?`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Delete",
            }).then((r) => r.isConfirmed);
            if (!ok) return;
            try {
                await axios.delete(`${SCHED_EP.usersched}/${row.userschedId}`);
                if (this.sched_editUId === row.userschedId)
                    this.schedResetUForm();
                await this.schedLoadUserLinks(
                    this.sched_selectedUserId || row.userId,
                );
                Swal.fire("Deleted", "Link removed.", "success");
            } catch (e) {
                Swal.fire("Error", "Delete failed.", "error");
            }
        },

        // when user is chosen in <select>
        schedOnSelectUser(id) {
            const val = Number(id) || null;
            this.sched_selectedUserId = val;
            this.sched_uForm.userId = val;
            if (val) this.schedLoadUserLinks(val); // auto-refresh list when you pick a user
        },

        // load links for the selected user
        async schedLoadUserLinksSelected() {
            if (!this.sched_selectedUserId)
                return Swal.fire("Oops", "Select a user", "info");
            await this.schedLoadUserLinks(); // this uses sched_uForm.userId or selectedUserId internally
        },

        schedSetPreset(key) {
            this.sched_preset = key;
            if (key === "Custom") return;

            const map = {
                Everyday: [1, 2, 4, 8, 16, 32, 64],
                Mon: [1],
                Tue: [2],
                Wed: [4],
                Thu: [8],
                Fri: [16],
                Sat: [32],
                Sun: [64],
            };
            this.sched_tForm._days_bits = map[key] || [];
        },

        schedApplyPreset(key) {
            const map = {
                Weekdays: [1, 2, 4, 8, 16],
                Weekends: [32, 64],
                Everyday: [1, 2, 4, 8, 16, 32, 64],
                MWF: [1, 4, 16],
                TTh: [2, 8],
                MonWed: [1, 2, 4], // If you intended only Mon & Wed: [1,4]
            };
            this.sched_tForm._days_bits = map[key] || [];
        },

        // history manager
        openHistory() {
            this.historyApply(); // initial fetch
        },

        switchHistoryType(t) {
            if (this.history.type === t) return;
            this.history.type = t;
            this.historyApply();
        },

        historyApply() {
            this.history.rows = [];
            this.history.nextPage = null;
            return this.historyFetch(1);
        },

        historyClear() {
            this.history.filters = {
                clock_id: "",
                editor_id: "",
                from: "",
                to: "",
            };
            return this.historyApply();
        },

        async historyFetch(page = 1) {
            if (this.history.loading) return;
            this.history.loading = true;
            try {
                const fetcher = historyFetchers[this.history.type];
                if (!fetcher)
                    throw new Error(
                        `No fetcher for type: ${this.history.type}`,
                    );
                const { rows, nextPage } = await fetcher(this, page);
                this.history.rows =
                    page === 1 ? rows : this.history.rows.concat(rows);
                this.history.nextPage = nextPage;
            } finally {
                this.history.loading = false;
            }
        },

        historyLoadMore() {
            if (this.history.nextPage) this.historyFetch(this.history.nextPage);
        },

        switchHistoryType(t) {
            if (this.history.type === t) return;
            this.history.type = t;

            // Load per sub-tab
            if (t === "time") {
                this.historyApply(); // legacy time edit history (already done)
            } else if (t === "leave") {
                if (!this.loaded.leave && !this.loading.leave)
                    this.fetchLeaveOnce();
            } else if (t === "rate") {
                if (!this.loaded.rateHistory && !this.loading.rateHistory) {
                    this.fetchEmployeeRateHistoryOnce(
                        this.rateHistoryFilterEmployeeId || null,
                    );
                }
            } else if (t === "violation") {
                if (!this.loaded.violations && !this.loading.violations)
                    this.fetchViolationsOnce();
            }
        },

        async openScheduling() {
            // force-load employees if never loaded or list is empty (handles earlier failures)
            if (
                !this.loaded.employees ||
                !Array.isArray(this.employees) ||
                this.employees.length === 0
            ) {
                this.loaded.employees = false;
                await this.fetchEmployeesOnce();
            }
            await this.fetchSchedulingOnce();

            // optional: preselect first user and load links
            if (!this.sched_selectedUserId && this.employees.length) {
                this.schedOnSelectUser(this.employees[0].id);
            }
        },
    },
    computed: {
        activeLabel() {
            if (!this.currentView) return "";
            return typeof this.currentView === "string"
                ? this.currentView
                : this.currentView.label;
        },

        hrContext() {
            return {
                // state
                currentView: this.currentView,
                employees: this.employees,
                rateHistory: this.rateHistory,
                timeRecords: this.timeRecords,
                filters: this.filters,
                employeeNames: this.employeeNames,
                page: this.page,
                totalPages: this.totalPages,
                showEditModal: this.showEditModal,
                editForm: this.editForm,
                editOriginal: this.editOriginal,
                submittingEdit: this.submittingEdit,

                employeeModal: this.employeeModal,
                profile: this.profile,
                openEmployeeModal: (emp) => this.openEmployeeModal(emp),
                setEmployeeModalTab: (tab) => this.setEmployeeModalTab(tab),
                closeEmployeeModal: () => this.closeEmployeeModal(),

                permissions: this.permissions,
                permissionsLoading: this.permissionsLoading,
                permissionsSaving: this.permissionsSaving,
                toggleModule: (k, v) => this.toggleModule(k, v),
                savePermissions: () => this.savePermissions(),

                // rate modal state
                showRateModal: this.showRateModal,
                selectedEmployee: this.selectedEmployee,
                rateForm: this.rateForm,
                savingRate: this.savingRate,

                // loading maps
                loading: this.loading,
                loaded: this.loaded,

                // actions (WRAPPED to preserve `this`)
                sort: (key) => this.sort(key),
                formatDate: (dt) => this.formatDate(dt),
                nextPage: () => this.nextPage(),
                prevPage: () => this.prevPage(),

                // time records
                fetchRecords: () => this.fetchRecords(),
                expandedClockId: this.expandedClockId,
                historyLoading: this.historyLoading,
                toggleHistory: (id) => this.toggleHistory(id),
                prettyDiff: (b, a) => this.prettyDiff(b, a),

                // time-record editor
                openEdit: (row) => this.openEdit(row),
                closeEdit: () => this.closeEdit(),
                submitEdit: () => this.submitEdit(),

                // edit history
                clockEditHistory: this.clockEditHistory,
                histFilters: this.histFilters ?? {
                    clock_id: "",
                    edited_by: "",
                    from: "",
                    to: "",
                },
                refreshClockEditHistory: (p = {}) =>
                    this.refreshClockEditHistory(p),
                fetchClockEditHistoryOnce: (p = {}) =>
                    this.fetchClockEditHistoryOnce(p),
                refreshClockEditHistory: (p = {}) =>
                    this.refreshClockEditHistory(p),
                fetchClockEditHistoryByClock: (id) =>
                    this.fetchClockEditHistoryByClock(id),

                // rate actions
                openRateModal: (emp) => this.openRateModal(emp),
                closeRateModal: () => this.closeRateModal(),
                submitRate: () => this.submitRate(),

                // lazy fetchers
                fetchEmployeesOnce: () => this.fetchEmployeesOnce(),
                fetchEmployeeRateHistoryOnce: (id = null) =>
                    this.fetchEmployeeRateHistoryOnce(id),
                loadView: (v) => this.loadView(v),

                // employee rate history
                filteredRateHistory: this.filteredRateHistory,
                rateHistoryFilterEmployeeId: this.rateHistoryFilterEmployeeId,
                rateHistoryFilterOnlyActive: this.rateHistoryFilterOnlyActive,
                isActiveRate: (row) => this.isActiveRate(row),
                refreshRateHistory: (id = null) => this.refreshRateHistory(id),

                // announcement + manage (PUT THIS INSIDE the object you return in hrContext)
                showAnnouncementModal: this.showAnnouncementModal,
                announcementForm: this.announcementForm,
                annSubmitting: this.annSubmitting,
                openAnnouncementModal: () => this.openAnnouncementModal(),
                closeAnnouncementModal: () => this.closeAnnouncementModal(),
                submitAnnouncement: (mode) => this.submitAnnouncement(mode),
                toggleGroup: (g) => this.toggleGroup(g),

                // 👇 NEW: manage modal exposure
                showManageAnnouncements: this.showManageAnnouncements,
                manageFilter: this.manageFilter,
                manageRows: this.manageRows,
                openManageAnnouncements: () => this.openManageAnnouncements(),
                closeManageAnnouncements: () => this.closeManageAnnouncements(),
                refreshManageAnnouncements: () =>
                    this.refreshManageAnnouncements(),

                // ⚠️ Important: pass the *function reference* so debounce stays stable
                debouncedRefreshManage: this.debouncedRefreshManage,

                // edit from manage table
                prefillAnnouncementForm: (row) =>
                    this.prefillAnnouncementForm(row),
                toggleAnnouncementActive: (row) =>
                    this.toggleAnnouncementActive(row),

                // Scheduling
                sched_times: this.sched_times,
                sched_filter: this.sched_filter,
                sched_tForm: this.sched_tForm,
                sched_editTId: this.sched_editTId,
                sched_userlinks: this.sched_userlinks,
                sched_uForm: this.sched_uForm,
                sched_editUId: this.sched_editUId,
                sched_selectedUserId: this.sched_selectedUserId,
                sched_preset: this.sched_preset,

                schedDaysLabel: (mask, dow, compact = true) =>
                    this.schedDaysLabel(mask, dow, compact),
                schedSetPreset: (k) => this.schedSetPreset(k),
                schedApplyPreset: (k) => this.schedApplyPreset(k),

                schedDayName: (d) => this.schedDayName(d),
                schedHhmm: (t) => this.schedHhmm(t),

                schedLoadTemplates: () => this.schedLoadTemplates(),
                schedSaveTemplate: () => this.schedSaveTemplate(),
                schedStartEditTemplate: (row) =>
                    this.schedStartEditTemplate(row),
                schedDeleteTemplate: (row) => this.schedDeleteTemplate(row),
                schedResetTForm: () => this.schedResetTForm(),

                schedLoadUserLinks: () => this.schedLoadUserLinks(),
                schedSaveUserLink: () => this.schedSaveUserLink(),
                schedStartEditUserLink: (row) =>
                    this.schedStartEditUserLink(row),
                schedDeleteUserLink: (row) => this.schedDeleteUserLink(row),
                schedResetUForm: () => this.schedResetUForm(),

                schedOnSelectUser: (id) => this.schedOnSelectUser(id),
                schedLoadUserLinksSelected: () =>
                    this.schedLoadUserLinksSelected(),

                // history manager
                history: this.history,
                switchHistoryType: (t) => this.switchHistoryType(t),
                historyApply: () => this.historyApply(),
                historyClear: () => this.historyClear(),
                historyLoadMore: () => this.historyLoadMore(),
            };
        },

        // employee Rate History
        filteredRateHistory() {
            let list = Array.isArray(this.rateHistory) ? this.rateHistory : [];
            const empId = this.rateHistoryFilterEmployeeId;
            const onlyActive = this.rateHistoryFilterOnlyActive;
            const today = new Date().toISOString().slice(0, 10);

            if (empId) {
                list = list.filter(
                    (r) => String(r.employee_id) === String(empId),
                );
            }
            if (onlyActive) {
                list = list.filter(
                    (r) =>
                        r.effective_start <= today &&
                        (!r.effective_end || r.effective_end >= today),
                );
            }
            return list;
        },
    },

    watch: {
        tabs: {
            immediate: true,
            handler(n) {
                if (
                    (!this.currentView || !n.includes(this.currentView)) &&
                    n.length
                ) {
                    this.currentView = n[0];
                }
            },
        },
    },
};
