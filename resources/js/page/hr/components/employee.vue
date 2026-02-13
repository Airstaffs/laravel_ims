<template>
    <div class="employee-container">
        <div class="employee-header">
            <h4>Employees</h4>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <!-- Status Filter -->
                <Select
                    v-model="statusFilter"
                    :options="statusOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Status"
                    class="filter-dropdown"
                />

                <!-- Location Filter -->
                <Select
                    v-model="locationFilter"
                    :options="locationOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Location"
                    class="filter-dropdown"
                />

                <Button
                    label="Add Employee"
                    @click="$parent.openAddEmployeeModal()"
                    severity="info"
                    size="small"
                >
                    Add Employee
                </Button>
            </div>
        </div>

        <!-- Filter Summary -->
        <div class="mb-3 text-secondary small" v-if="$parent.employees">
            Showing {{ filteredEmployees.length }} of
            {{ $parent.employees.length }} employees
        </div>

        <!-- Mobile: card list -->
        <div class="d-md-none">
            <div
                class="emp-card shadow-sm rounded-3 mb-2 p-3"
                v-for="(emp, index) in filteredEmployees"
                :key="emp.id"
            >
                <div
                    class="d-flex justify-content-between align-items-start mb-1"
                >
                    <div class="emp-title">
                        <div class="emp-name fw-semibold text-truncate">
                            {{ emp.name || emp.username }}
                        </div>
                        <div
                            class="emp-position text-secondary small text-truncate"
                        >
                            {{ emp.position || "—" }}
                        </div>
                        <div class="mt-1">
                            <span :class="['badge', getStatusClass(emp)]">
                                {{ getStatusLabel(emp) }}
                            </span>
                            <span class="badge bg-primary ms-1">{{
                                getLocation(emp)
                            }}</span>
                        </div>
                    </div>
                    <span class="emp-index badge text-bg-light">{{
                        index + 1
                    }}</span>
                </div>

                <div class="emp-rates row g-2 my-2">
                    <div class="col-6">
                        <div class="rate-label text-secondary small">
                            Monthly
                        </div>
                        <div class="rate-value fw-semibold">
                            {{
                                emp.current_monthly_rate != null
                                    ? "₱" +
                                      Number(emp.current_monthly_rate).toFixed(
                                          2,
                                      )
                                    : "-"
                            }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="rate-label text-secondary small">
                            Hourly
                        </div>
                        <div class="rate-value fw-semibold">
                            {{
                                emp.current_hourly_rate != null
                                    ? "₱" +
                                      Number(emp.current_hourly_rate).toFixed(2)
                                    : "-"
                            }}
                        </div>
                    </div>
                </div>

                <div class="d-grid">
                    <Button
                        size="small"
                        severity="info"
                        @click="$parent.hrContext.openEmployeeModal(emp)"
                    >
                        Employee Details
                    </Button>
                </div>
            </div>

            <div
                v-if="filteredEmployees.length === 0"
                class="text-center text-secondary py-4"
            >
                <p>No employees found matching the selected filters.</p>
            </div>
        </div>

        <!-- Desktop: table -->
        <DataTable
            :value="filteredEmployees"
            responsiveLayout="stack"
            breakpoint="960px"
            class="p-datatable-sm d-none d-md-block"
        >
            <Column field="id" header="#" />

            <Column field="username" header="Name">
                <template #body="{ data }">
                    <span class="fw-bolder">{{
                        data.name || data.username
                    }}</span>
                </template>
            </Column>

            <Column field="position" header="Position">
                <template #body="{ data }">
                    {{ data.position || "—" }}
                </template>
            </Column>

            <Column header="Status">
                <template #body="{ data }">
                    <span :class="['badge', getStatusClass(data)]">
                        {{ getStatusLabel(data) }}
                    </span>
                </template>
            </Column>

            <Column header="Location">
                <template #body="{ data }">
                    <span class="badge bg-primary">{{
                        getLocation(data)
                    }}</span>
                </template>
            </Column>

            <Column>
                <template #header>
                    <div class="d-flex flex-column align-items-start">
                        <div>Current Rate</div>
                        <div
                            class="text-secondary fw-bolder"
                            style="font-size: 12px"
                        >
                            Monthly | Hourly
                        </div>
                    </div>
                </template>
                <template #body="{ data }">
                    <span class="rate-chip">
                        {{
                            data.current_monthly_rate != null
                                ? "₱" +
                                  Number(data.current_monthly_rate).toFixed(2)
                                : "-"
                        }}
                    </span>
                    <span class="mx-1 text-secondary">|</span>
                    <span class="rate-chip">
                        {{
                            data.current_hourly_rate != null
                                ? "₱" +
                                  Number(data.current_hourly_rate).toFixed(2)
                                : "-"
                        }}
                    </span>
                </template>
            </Column>

            <Column header="Actions">
                <template #body="{ data }">
                    <Button
                        size="small"
                        severity="info"
                        outlined
                        label="Employee Details"
                        @click="$parent.hrContext.openEmployeeModal(data)"
                    />
                </template>
            </Column>

            <template #empty>
                <div class="text-center text-secondary py-3">
                    No employees found matching the selected filters.
                </div>
            </template>
        </DataTable>

        <!-- Add Employee Modal (unchanged) -->
        <Dialog
            v-model:visible="$parent.showAddEmployeeModal"
            modal
            header="Add Employee"
            :draggable="false"
            :style="{ width: '25rem' }"
        >
            <div>
                <form @submit.prevent>
                    <fieldset>
                        <InputText
                            v-model="$parent.newEmployee.name"
                            placeholder="Name"
                            fluid
                            size="small"
                            class="mb-2"
                        />
                        <InputText
                            v-model="$parent.newEmployee.position"
                            placeholder="Position"
                            fluid
                            size="small"
                        />
                        <Select
                            v-model="$parent.newEmployee.accounttype"
                            :options="accountTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Account type"
                            fluid
                            size="small"
                            class="mb-2"
                        />
                    </fieldset>
                </form>
            </div>
            <template #footer>
                <Button
                    label="Save"
                    severity="info"
                    @click="$parent.addEmployee()"
                    autofocus
                    size="small"
                />
                <Button
                    label="Cancel"
                    text
                    severity="secondary"
                    @click="$parent.closeAddEmployeeModal()"
                    autofocus
                    size="small"
                />
            </template>
        </Dialog>

        <!-- <small class="text-muted">
            debug:
            {{ $parent.hrContext.employeeModal.show ? "open" : "closed" }}
        </small> -->

        <!-- NEW: Employee Details modal with header tabs (like your Profile modal) -->
        <Dialog
            v-model:visible="$parent.hrContext.employeeModal.show"
            modal
            :header="`Employee — ${$parent.hrContext.employeeModal.selectedEmployee?.name || ''}`"
            :draggable="false"
            :style="{ width: '60rem', maxWidth: '95vw' }"
        >
            <Tabs value="0" class="responsive-tabs">
                <TabList>
                    <Tab value="0">Employee Details</Tab>
                    <Tab value="1">Edit Employee Rate</Tab>
                    <Tab value="2">Permissions</Tab>
                </TabList>
                <TabPanels>
                    <!-- DETAILS TAB -->
                    <TabPanel value="0">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label text-secondary"
                                    >Full Name</label
                                >
                                <div class="form-control-plaintext fw-semibold">
                                    {{
                                        $parent.hrContext.profile.full_name ||
                                        "—"
                                    }}
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label text-secondary"
                                    >Work Email</label
                                >
                                <div class="form-control-plaintext">
                                    {{
                                        $parent.hrContext.profile.work_email ||
                                        "—"
                                    }}
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label text-secondary"
                                    >Contact Phone</label
                                >
                                <div class="form-control-plaintext">
                                    {{
                                        $parent.hrContext.profile
                                            .contact_phone || "—"
                                    }}
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label text-secondary"
                                    >Birthdate</label
                                >
                                <div class="form-control-plaintext">
                                    {{
                                        $parent.hrContext.profile.birthdate ||
                                        "—"
                                    }}
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-secondary"
                                    >Address</label
                                >
                                <div class="form-control-plaintext">
                                    {{
                                        $parent.hrContext.profile.address || "—"
                                    }}
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-secondary"
                                    >ICE Name</label
                                >
                                <div class="form-control-plaintext">
                                    {{
                                        $parent.hrContext.profile.ice_name ||
                                        "—"
                                    }}
                                </div>
                            </div>

                            <div class="col-6">
                                <label class="form-label text-secondary"
                                    >ICE Phone</label
                                >
                                <div class="form-control-plaintext">
                                    {{
                                        $parent.hrContext.profile.ice_phone ||
                                        "—"
                                    }}
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label text-secondary"
                                    >Relationship</label
                                >
                                <div class="form-control-plaintext">
                                    {{
                                        $parent.hrContext.profile
                                            .ice_relationship || "—"
                                    }}
                                </div>
                            </div>
                        </div>
                    </TabPanel>

                    <TabPanel value="1">
                        <!-- RATE TAB -->

                        <form @submit.prevent>
                            <fieldset>
                                <label>Effective Start</label>
                                <InputText
                                    type="date"
                                    class="form-control"
                                    v-model="
                                        $parent.hrContext.rateForm
                                            .effective_start
                                    "
                                />
                            </fieldset>

                            <fieldset>
                                <label>Effective End (optional)</label>
                                <InputText
                                    type="date"
                                    class="form-control"
                                    v-model="
                                        $parent.hrContext.rateForm.effective_end
                                    "
                                />
                            </fieldset>

                            <fieldset>
                                <label>Monthly Rate (PHP)</label>
                                <InputText
                                    type="number"
                                    step="0.01"
                                    class="form-control"
                                    v-model.number="
                                        $parent.hrContext.rateForm.monthly_rate
                                    "
                                />
                            </fieldset>

                            <fieldset>
                                <label>Hourly Rate (PHP)</label>
                                <InputText
                                    type="number"
                                    step="0.01"
                                    class="form-control"
                                    v-model.number="
                                        $parent.hrContext.rateForm.hourly_rate
                                    "
                                />
                            </fieldset>

                            <fieldset>
                                <label>Currency</label>
                                <InputText
                                    type="text"
                                    maxlength="3"
                                    class="form-control"
                                    @input="
                                        ($event) => {
                                            $event.target.value =
                                                $event.target.value.toUpperCase();
                                        }
                                    "
                                    v-model="
                                        $parent.hrContext.rateForm.currency
                                    "
                                />
                            </fieldset>
                        </form>
                        <div class="d-flex justify-content-end gap-2 mt-2">
                            <Button
                                size="small"
                                :disabled="$parent.hrContext.savingRate"
                                @click="$parent.hrContext.submitRate()"
                            >
                                <span
                                    v-if="$parent.hrContext.savingRate"
                                    class="spinner-border spinner-border-sm me-1"
                                ></span>
                                Save
                            </Button>
                            <Button
                                size="small"
                                severity="secondary"
                                outline
                                @click="$parent.hrContext.closeEmployeeModal()"
                            >
                                Close
                            </Button>
                        </div>
                    </TabPanel>

                    <TabPanel value="2">
                        <div class="row g-3">
                            <div class="col-12">
                                <div
                                    class="d-flex align-items-center justify-content-between"
                                >
                                    <h6 class="m-0">Module Access</h6>
                                    <small
                                        class="text-secondary"
                                        v-if="
                                            $parent.hrContext.permissionsLoading
                                        "
                                        >loading…</small
                                    >
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <label
                                        v-for="key in $parent.hrContext
                                            .permissions.module_keys || []"
                                        :key="key"
                                        class="form-check form-check-inline px-2 py-1 d-flex align-items-center"
                                        style="min-width: 180px"
                                    >
                                        <Checkbox
                                            v-model="
                                                $parent.hrContext.permissions
                                                    .modules[key]
                                            "
                                            binary
                                            @change="
                                                (e) =>
                                                    $parent.hrContext.toggleModule(
                                                        key,
                                                        e.target.checked,
                                                    )
                                            "
                                            class="me-2"
                                            size="small"
                                        />
                                        <span class="text-capitalize">{{
                                            key
                                        }}</span>
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-secondary mt-2"
                                    >Main Module</label
                                >
                                <select
                                    class="form-select"
                                    v-model="
                                        $parent.hrContext.permissions
                                            .main_module
                                    "
                                >
                                    <option :value="null">— None —</option>
                                    <option
                                        v-for="key in $parent.hrContext
                                            .permissions.module_keys"
                                        :key="key"
                                        :value="key"
                                        :disabled="
                                            $parent.hrContext.permissions
                                                .modules[key] !== true
                                        "
                                        class="text-capitalize"
                                    >
                                        {{ key }}
                                    </option>
                                </select>
                                <small class="text-secondary"
                                    >Only enabled modules can be set as
                                    <em>main</em>.</small
                                >
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <Button
                                    class="text-white"
                                    :disabled="
                                        $parent.hrContext.permissionsSaving
                                    "
                                    size="small"
                                    @click="$parent.hrContext.savePermissions()"
                                >
                                    <span
                                        v-if="
                                            $parent.hrContext.permissionsSaving
                                        "
                                        class="spinner-border spinner-border-sm me-1"
                                    ></span>
                                    Save
                                </Button>
                                <Button
                                    size="small"
                                    severity="secondary"
                                    outline
                                    @click="
                                        $parent.hrContext.closeEmployeeModal()
                                    "
                                    >Close</Button
                                >
                            </div>
                        </div>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </Dialog>
    </div>
</template>

<script>
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Tabs from "primevue/tabs";
import TabList from "primevue/tablist";
import Tab from "primevue/tab";
import TabPanel from "primevue/tabpanel";
import TabPanels from "primevue/tabpanels";
import Checkbox from "primevue/checkbox";
import Select from "primevue/select";
export default {
    components: {
        Button,
        Dialog,
        InputText,
        DataTable,
        Column,
        Tab,
        TabList,
        Tabs,
        TabPanel,
        TabPanels,
        Checkbox,
        Select,
    },
    data() {
        return {
            statusFilter: "all",
            locationFilter: "all",
            statusOptions: [
                { label: "All Status", value: "all" },
                { label: "Active", value: "active" },
                { label: "Inactive", value: "inactive" },
            ],
            locationOptions: [
                { label: "All Locations", value: "all" },
                { label: "Philippines", value: "PH" },
                { label: "United States", value: "US" },
            ],
            accountTypeOptions: [
                { label: "Philippines (PH)", value: "PH" },
                { label: "United States (US)", value: "US" },
            ],
        };
    },
    computed: {
        filteredEmployees() {
            let employees = this.$parent?.employees || [];

            // Filter by status
            if (this.statusFilter !== "all") {
                employees = employees.filter((emp) => {
                    // If no status field exists, assume active
                    if (
                        !emp.hasOwnProperty("status") &&
                        !emp.hasOwnProperty("is_active") &&
                        !emp.hasOwnProperty("active")
                    ) {
                        return this.statusFilter === "active";
                    }

                    const isActive =
                        emp.status === "active" ||
                        emp.is_active === true ||
                        emp.active === true ||
                        emp.status === 1 ||
                        emp.is_active === 1;
                    return this.statusFilter === "active"
                        ? isActive
                        : !isActive;
                });
            }

            // Filter by location
            if (this.locationFilter !== "all") {
                employees = employees.filter((emp) => {
                    // Check accounttype field and convert to uppercase for comparison
                    const empLocation = (
                        emp.accounttype ||
                        emp.location ||
                        emp.country ||
                        ""
                    ).toUpperCase();
                    return empLocation === this.locationFilter;
                });
            }

            return employees;
        },
    },
    methods: {
        getStatusLabel(emp) {
            // If no status field, show "Active" by default
            if (
                !emp.hasOwnProperty("status") &&
                !emp.hasOwnProperty("is_active") &&
                !emp.hasOwnProperty("active")
            ) {
                return "Active";
            }

            const isActive =
                emp.status === "active" ||
                emp.is_active === true ||
                emp.active === true ||
                emp.status === 1 ||
                emp.is_active === 1 ||
                emp.active === 1;
            return isActive ? "Active" : "Inactive";
        },
        getStatusClass(emp) {
            // If no status field, show green badge by default
            if (
                !emp.hasOwnProperty("status") &&
                !emp.hasOwnProperty("is_active") &&
                !emp.hasOwnProperty("active")
            ) {
                return "bg-success";
            }

            const isActive =
                emp.status === "active" ||
                emp.is_active === true ||
                emp.active === true ||
                emp.status === 1 ||
                emp.is_active === 1 ||
                emp.active === 1;
            return isActive ? "bg-success" : "bg-secondary";
        },
        getLocation(emp) {
            const location =
                emp.accounttype || emp.location || emp.country || "";
            return location.toUpperCase() || "-";
        },
    },
};
</script>

<style scoped src="../hr.css"></style>
