import axios from "axios";

import Employee from "./components/employee.vue";
import LeaveHistory from "./components/leavehistory.vue";
import TimeRecord from "./components/timerecord.vue";
import Violations from "./components/violations.vue";
import TimeRecordHistory from "./components/timerecordhistory.vue";
import RateHistory from "./components/ratehistory.vue";
import ViolationsHistory from "./components/violationshistory.vue";

const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
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
                        "Employee Leave History", // same component as Leave History
                        "Employee Rate History", // you can reuse or create new component
                        "Violations History", // same as Violations.vue
                    ],
                },
            ],

            // UI State
            showAddEmployeeModal: false,

            // Employees
            employees: [],
            newEmployee: {
                name: "",
                position: "",
            },

            // Time Record
            timeRecords: [],
            filters: {
                employee: "",
                dateFrom: "",
                dateTo: "",
            },
            sortKey: "DateToday",
            sortOrder: "desc",
            page: 1,
            limit: 25,
            totalPages: 1,
            currentPage: 1,

            employeeNames: [],

            // Leave History
            leaveHistory: [],

            // Violations
            violations: [],
        };
    },
    components: {
        Employee,
        "Time Record": TimeRecord,
        "Time Record Edit History": TimeRecordHistory,
        "Employee Leave History": LeaveHistory,
        "Employee Rate History": RateHistory,
        "Violations History": ViolationsHistory,
        Violations,
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

        // Load time records
        await this.fetchRecords();

    },
    methods: {
        // Employee Sheesh
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

                // Use Laravel pagination format
                this.timeRecords = result.data; // ← This is the array of records
                this.totalPages = result.last_page;
                this.currentPage = result.current_page;

                // Get employee names for filtering
                const names = [...new Set(result.data.map((r) => r.Employee))];
                this.employeeNames = names;
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
    },
    computed: {
        filteredTimeRecords() {
            if (this.selectedEmployee === "All") {
                return this.timeRecords;
            }
            return this.timeRecords.filter(
                (record) => record.Employee === this.selectedEmployee
            );
        },
        employeeOptions() {
            const names = [...new Set(this.timeRecords.map((r) => r.Employee))];
            return ["All", ...names];
        },
        componentMap() {
            return {
                Employee: "Employee",
                "Time Record": "Time Record",
                "Time Record Edit History": "Time Record Edit History",
                "Employee Leave History": "Employee Leave History",
                "Employee Rate History": "Employee Rate History",
                "Violations History": "Violations History",
                Violations: "Violations",
            };
        },
        currentViewComponent() {
            return this.componentMap[this.currentView] || "Employee";
        },
    },
};
