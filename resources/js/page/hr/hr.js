import axios from "axios";

import Employee from "./components/employee.vue";
import LeaveHistory from "./components/leavehistory.vue";
import TimeRecord from "./components/timerecord.vue";
import Violations from "./components/violations.vue";
import TimeRecordHistory from "./components/timerecordhistory.vue";
import RateHistory from "./components/ratehistory.vue";
import ViolationsHistory from "./components/violationshistory.vue";
import HolidayModal from "./components/holidaymodal.vue";
import bootstrap from "bootstrap/dist/js/bootstrap.bundle.min.js";
import AnnouncementModal from "./components/announcementmodal.vue";

const API_BASE_URL = import.meta.env.VITE_API_URL;

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

export default {
    components: {
        Employee,
        TimeRecord,
        TimeRecordHistory,
        LeaveHistory,
        RateHistory,
        ViolationsHistory,
        Violations,
        HolidayModal,
        AnnouncementModal,
    },

    data() {
        return {
            // navbar controller
            currentView: "Employee",
            tabs: [
                "Employee",
                "Time Record",
                {
                    label: "History",
                    dropdown: [
                        "Time Record Edit History",
                        "Employee Leave History",
                        "Employee Rate History",
                        "Violations History",
                    ],
                },
                "Violations",
            ],

            loading: {
                employees: false,
                leave: false,
                violations: false,
                rateHistory: false,
                clockHistory: false,
            },
            loaded: {
                employees: false,
                leave: false,
                violations: false,
                rateHistory: false,
                clockHistory: false,
            },

            // UI State
            showAddEmployeeModal: false,

            // Employees
            employees: [],
            newEmployee: { name: "", position: "" },

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

            // Time Record
            timeRecords: [],
            filters: { employee: "", dateFrom: "", dateTo: "" },
            sortKey: "DateToday",
            sortOrder: "desc",
            page: 1,
            limit: 25,
            totalPages: 1,
            currentPage: 1,

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
            holidayModal: null,
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
        };
    },

    async mounted() {
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
            // Switch view but ensure only one main tab at a time
            this.currentView = view;
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
                }
            } catch (e) {
                console.error("loadView error:", e);
            }
        },

        // Employees
        addEmployee() {
            if (!this.newEmployee.name || !this.newEmployee.position) {
                alert("Please fill in all fields.");
                return;
            }
            this.employees.push({
                id: Date.now(),
                name: this.newEmployee.name,
                position: this.newEmployee.position,
            });
            this.newEmployee = { name: "", position: "" };
            this.showAddEmployeeModal = false;
        },

        async fetchEmployeesOnce() {
            if (this.loaded.employees || this.loading.employees) return;
            this.loading.employees = true;
            try {
                const res = await fetch(`${API_BASE_URL}/hr/employees`);
                const data = await res.json().catch(() => ({}));
                this.employees = Array.isArray(data)
                    ? data
                    : data?.employees ?? [];
            } catch (e) {
                console.error("Failed to load employees", e);
                this.employees = [];
            } finally {
                this.loading.employees = false;
                this.loaded.employees = true;
            }
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
            // basic validation
            if (!this.rateForm.employee_id) {
                return Swal.fire(
                    "Missing Employee",
                    "Please select an employee.",
                    "warning"
                );
            }
            if (!this.rateForm.effective_start) {
                return Swal.fire(
                    "Effective Start Required",
                    "Please provide an effective start date.",
                    "warning"
                );
            }
            if (!this.rateForm.monthly_rate && !this.rateForm.hourly_rate) {
                return Swal.fire(
                    "Rate Required",
                    "Please provide at least a monthly or hourly rate.",
                    "warning"
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

                await axios.post(url, payload);

                if (this.selectedEmployee) {
                    if (payload.monthly_rate != null) {
                        this.selectedEmployee.employee_rate =
                            payload.monthly_rate;
                    }
                }

                this.loaded.employees = false;
                this.loaded.rateHistory = false;

                this.closeRateModal();

                Swal.fire(
                    "Success",
                    "Employee rate has been saved successfully.",
                    "success"
                ).then(() => {
                    this.fetchEmployeesOnce();
                });
            } catch (e) {
                console.error("Failed to save rate", e);
                Swal.fire(
                    "Error",
                    "Failed to save rate. Please try again.",
                    "error"
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
                            this.employees.map((e) => e.name).filter(Boolean)
                        ),
                    ].sort((a, b) => a.localeCompare(b));
                } else {
                    this.employeeNames = [
                        ...new Set(
                            (result.data || [])
                                .map((r) => r.Employee)
                                .filter(Boolean)
                        ),
                    ].sort((a, b) => a.localeCompare(b));
                }
            } catch (err) {
                console.error("Failed to fetch records", err);
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

        // Time Records Edit History
        async fetchClockEditHistoryOnce(params = {}) {
            if (this.loaded.clockHistory || this.loading.clockHistory) return;
            this.loading.clockHistory = true;
            try {
                const { data } = await axios.post(
                    `${API_BASE_URL}/hr/time-records/edit-history`,
                    { ...this.histFilters, ...params }
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
                    {}
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
                    this.data_sent
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
            if (this.holidayModal) this.holidayModal.show();
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

        async fetchHolidays() {
            try {
                const { data } = await axios.post(
                    `${API_BASE_URL}/hr/holidays/list`,
                    {
                        year: this.holidayYear,
                    }
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
                const { data } = await axios.post(url, payload);
                if (data?.success) {
                    await this.fetchHolidays();
                    if (!this.holidayForm.holidayID) this.resetHolidayForm(); // manglimpyo after creation
                } else {
                    alert("Validation failed. Please check your inputs.");
                }
            } catch (err) {
                console.error("saveHoliday error:", err);
                alert("Save failed.");
            }
        },

        async deleteHoliday(holidayID) {
            if (!confirm("Delete this holiday?")) return;
            try {
                const { data } = await axios.post(
                    `${API_BASE_URL}/hr/holidays/delete`,
                    {
                        holidayID,
                    }
                );
                if (data?.success) {
                    await this.fetchHolidays();
                }
            } catch (err) {
                console.error("deleteHoliday error:", err);
                alert("Delete failed.");
            }
        },

        // Announcement modal
        openAnnouncementModal() {
            // make sure employees (with accounttype) are loaded for the recipient list
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
                const ANN_API = `${API_BASE_URL}/hr/announcements`; // <-- if your routes are under /hr, change to `${API_BASE_URL}/hr/announcements`
                const body = {
                    id: this.announcementForm.id || null,
                    title: this.announcementForm.title,
                    message: this.announcementForm.content,
                    start_at: this.announcementForm.start_at || null, // datetime-local (local tz)
                    end_at: this.announcementForm.end_at || null,
                    save_mode: saveMode, // 'draft' | 'active'
                    // send selected recipients; backend can map ids->usernames if needed
                    recipients: Array.isArray(this.announcementForm.user_ids)
                        ? this.announcementForm.user_ids
                        : [],
                };

                const { data } = await axios.post(`${ANN_API}/save`, body, {
                    withCredentials: true,
                });
                if (!data?.success)
                    throw new Error(data?.message || "Save failed");

                // auto-refresh Manage list if open
                if (this.showManageAnnouncements)
                    await this.refreshManageAnnouncements();

                alert(
                    saveMode === "active"
                        ? "Saved & Activated"
                        : "Saved as Draft"
                );

                // if new create, clear; if editing, keep form (optional)
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
        debouncedRefreshManage: debounce(function () {
            this.refreshManageAnnouncements();
        }, 300),

        async refreshManageAnnouncements() {
            try {
                const ANN_API = `${API_BASE_URL}/hr/announcements`; // or `/hr/announcements`
                const params = new URLSearchParams();
                if (this.manageFilter.status !== "all")
                    params.set("status", this.manageFilter.status);
                if (this.manageFilter.q) params.set("q", this.manageFilter.q);

                const url = `${ANN_API}/admin${
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
            const toLocalInput = (s) => {
                if (!s) return "";
                return s.replace(" ", "T").slice(0, 16);
            };

            // if manage rows contain usernames, map to IDs; if already IDs, just set directly
            const idsFromUsernames = (arr) => {
                if (!Array.isArray(arr)) return [];
                const usernameToId = new Map(
                    this.employees.map((e) => [
                        String(e.username),
                        Number(e.id),
                    ])
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

        async toggleAnnouncementActive(row) {
            try {
                const ANN_API = `${API_BASE_URL}/hr/announcements`; // or `/hr/announcements`
                const { data } = await axios.post(
                    `${ANN_API}/toggle-active`,
                    {
                        id: row.id,
                        make_active: !row.is_active,
                    },
                    { withCredentials: true }
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
                alert(e?.message || "Failed to update status.");
            }
        },
    },

    computed: {
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
                timeRecords: this.timeRecords,

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

                // time-record editor
                openEdit: (row) => this.openEdit(row),
                closeEdit: () => this.closeEdit(),
                submitEdit: () => this.submitEdit(),

                // edit history
                clockEditHistory: this.clockEditHistory,
                histFilters: this.histFilters,
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

                // announcement modal
                showAnnouncementModal: this.showAnnouncementModal,
                announcementForm: this.announcementForm,
                annSubmitting: this.annSubmitting,
                // announcement actions
                openManageAnnouncements: () => this.openManageAnnouncements(),
                closeManageAnnouncements: () => this.closeManageAnnouncements(),
                refreshManageAnnouncements: () =>
                    this.refreshManageAnnouncements(),
                debouncedRefreshManage: () => this.debouncedRefreshManage(),
                prefillAnnouncementForm: (row) =>
                    this.prefillAnnouncementForm(row),
                toggleAnnouncementActive: (row) =>
                    this.toggleAnnouncementActive(row),
                submitAnnouncement: (mode) => this.submitAnnouncement(mode),
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
                    (r) => String(r.employee_id) === String(empId)
                );
            }
            if (onlyActive) {
                list = list.filter(
                    (r) =>
                        r.effective_start <= today &&
                        (!r.effective_end || r.effective_end >= today)
                );
            }
            return list;
        },
    },

    watch: {
        currentView: {
            immediate: true,
            handler(view) {
                this.loadView(view);
            },
        },
    },
};
