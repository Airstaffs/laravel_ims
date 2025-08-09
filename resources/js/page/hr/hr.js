import axios from "axios";

import Employee from "./components/employee.vue";
import LeaveHistory from "./components/leavehistory.vue";
import TimeRecord from "./components/timerecord.vue";
import Violations from "./components/violations.vue";
import TimeRecordHistory from "./components/timerecordhistory.vue";
import RateHistory from "./components/ratehistory.vue";
import ViolationsHistory from "./components/violationshistory.vue";

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

export default {
    components: {
        Employee,
        TimeRecord,
        TimeRecordHistory,
        LeaveHistory,
        RateHistory,
        ViolationsHistory,
        Violations,
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

            // UI State
            showAddEmployeeModal: false,

            // Employees
            employees: [],
            newEmployee: { name: "", position: "" },

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
        };
    },

    async mounted() {
        try {
            const [empRes, leaveRes, violRes] = await Promise.all([
                fetch(`${API_BASE_URL}/hr/employees`),
                fetch(`${API_BASE_URL}/hr/leave-history`),
                fetch(`${API_BASE_URL}/hr/violations`),
            ]);

            this.employees = await empRes.json();
            this.leaveHistory = await leaveRes.json();
            this.violations = await violRes.json();
        } catch (err) {
            console.error("Failed to load HR data", err);
        }

        await this.fetchRecords();
    },

    methods: {
        setView(view) {
            // Switch view but ensure only one main tab at a time
            this.currentView = view;
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
    },

    computed: {
        hrContext() {
            return {
                // state
                timeRecords: this.timeRecords,
                filters: this.filters,
                employeeNames: this.employeeNames,
                page: this.page,
                totalPages: this.totalPages,
                showEditModal: this.showEditModal,
                editForm: this.editForm,
                editOriginal: this.editOriginal,
                submittingEdit: this.submittingEdit,
                // actions
                fetchRecords: this.fetchRecords,
                sort: this.sort,
                formatDate: this.formatDate,
                openEdit: this.openEdit,
                closeEdit: this.closeEdit,
                submitEdit: this.submitEdit,
                nextPage: this.nextPage,
                prevPage: this.prevPage,
            };
        },
    },
};
