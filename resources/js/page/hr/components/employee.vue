<template>
    <div class="employee-container">
        <div class="employee-header">
            <h4>Employees</h4>
            <Button label="Employee" @click="$parent.openAddEmployeeModal()" severity="info" size="small">
                Add Employee
            </Button>
        </div>

        <!-- Mobile: card list -->
        <div class="d-md-none">
            <div class="emp-card shadow-sm rounded-3 mb-2 p-3" v-for="(emp, index) in $parent.employees" :key="emp.id">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <div class="emp-title">
                        <div class="emp-name fw-semibold text-truncate">
                            {{ emp.name }}
                        </div>
                        <div class="emp-position text-secondary small text-truncate">
                            {{ emp.position || "—" }}
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
                                        2
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
                    <Button size="small" severity="info" @click="$parent.hrContext.openEmployeeModal(emp)">
                        Employee Details
                    </Button>
                </div>
            </div>
        </div>

        <!-- Desktop: table -->
        <DataTable :value="$parent.employees" responsiveLayout="stack" breakpoint="960px"
            class="p-datatable-sm d-none d-md-block">
            <Column field="id" header="#" />

            <Column field="username" header="Name">
                <template #body="{ data }">
                    <span class="fw-bolder">{{ data.username }}</span>
                </template>
            </Column>

            <Column field="position" header="Position">
                <template #body="{ data }">
                    {{ data.position || '—' }}
                </template>
            </Column>

            <Column>
                <template #header>
                    <div class="d-flex flex-column align-items-start">
                        <div>Current Rate</div>
                        <div class="text-secondary fw-bolder" style="font-size: 12px;">
                            Monthly | Hourly
                        </div>
                    </div>
                </template>
                <template #body="{ data }">
                    <span class="rate-chip">
                        {{
                            data.current_monthly_rate != null
                                ? '₱' + Number(data.current_monthly_rate).toFixed(2)
                                : '-'
                        }}
                    </span>
                    <span class="mx-1 text-secondary">|</span>
                    <span class="rate-chip">
                        {{
                            data.current_hourly_rate != null
                                ? '₱' + Number(data.current_hourly_rate).toFixed(2)
                                : '-'
                        }}
                    </span>
                </template>
            </Column>

            <Column header="Actions">
                <template #body="{ data }">
                    <Button size="small" severity="info" outlined label="Employee Details"
                        @click="$parent.hrContext.openEmployeeModal(data)" />
                </template>
            </Column>
        </DataTable>

        <!-- Add Employee Modal (unchanged) -->
        <Dialog v-model:visible="$parent.showAddEmployeeModal" modal header="Add Employee" :draggable="false"
            :style="{ width: '25rem' }">
            <div>
                <form @submit.prevent>
                    <fieldset>
                        <InputText v-model="$parent.newEmployee.name" placeholder="Name" fluid size="small"
                            class="mb-2" />
                        <InputText v-model="$parent.newEmployee.position" placeholder="Position" fluid size="small" />
                    </fieldset>
                </form>
            </div>
            <template #footer>
                <Button label="Save" severity="info" @click="$parent.addEmployee()" autofocus size="small" />
                <Button label="Cancel" text severity="secondary" @click="$parent.closeAddEmployeeModal()" autofocus
                    size="small" />

            </template>
        </Dialog>


        <small class="text-muted">
            debug:
            {{ $parent.hrContext.employeeModal.show ? "open" : "closed" }}
        </small>

        <!-- NEW: Employee Details modal with header tabs (like your Profile modal) -->
        <Dialog v-model:visible="$parent.hrContext.employeeModal.show" modal
            :header="`Employee — ${$parent.hrContext.employeeModal.selectedEmployee?.name || ''}`" :draggable="false"
            :style="{ width: '60rem', maxWidth: '95vw' }">
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
                                <label class="form-label text-secondary">Full Name</label>
                                <div class="form-control-plaintext fw-semibold">
                                    {{ $parent.hrContext.profile.full_name || "—" }}
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label text-secondary">Work Email</label>
                                <div class="form-control-plaintext">
                                    {{ $parent.hrContext.profile.work_email || "—" }}
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label text-secondary">Contact Phone</label>
                                <div class="form-control-plaintext">
                                    {{ $parent.hrContext.profile.contact_phone || "—" }}
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label text-secondary">Birthdate</label>
                                <div class="form-control-plaintext">
                                    {{ $parent.hrContext.profile.birthdate || "—" }}
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-secondary">Address</label>
                                <div class="form-control-plaintext">
                                    {{ $parent.hrContext.profile.address || "—" }}
                                </div>
                            </div>

                            <div class="col-12 ">
                                <label class="form-label text-secondary">ICE Name</label>
                                <div class="form-control-plaintext">
                                    {{ $parent.hrContext.profile.ice_name || "—" }}
                                </div>
                            </div>



                            <div class="col-6 ">
                                <label class="form-label text-secondary">ICE Phone</label>
                                <div class="form-control-plaintext">
                                    {{ $parent.hrContext.profile.ice_phone || "—" }}
                                </div>
                            </div>

                            <div class="col-12 col-md-6 ">
                                <label class="form-label text-secondary">Relationship</label>
                                <div class="form-control-plaintext">
                                    {{ $parent.hrContext.profile.ice_relationship || "—" }}
                                </div>
                            </div>
                        </div>



                    </TabPanel>

                    <TabPanel value="1">
                        <!-- RATE TAB -->

                        <form @submit.prevent>
                            <fieldset>
                                <label>Effective Start</label>
                                <InputText type="date" class="form-control" v-model="$parent.hrContext.rateForm
                                    .effective_start
                                    " />
                            </fieldset>

                            <fieldset>
                                <label>Effective End (optional)</label>
                                <InputText type="date" class="form-control" v-model="$parent.hrContext.rateForm.effective_end
                                    " />
                            </fieldset>

                            <fieldset>
                                <label>Monthly Rate (PHP)</label>
                                <InputText type="number" step="0.01" class="form-control" v-model.number="$parent.hrContext.rateForm.monthly_rate
                                    " />
                            </fieldset>

                            <fieldset>
                                <label>Hourly Rate (PHP)</label>
                                <InputText type="number" step="0.01" class="form-control" v-model.number="$parent.hrContext.rateForm.hourly_rate
                                    " />
                            </fieldset>

                            <fieldset>
                                <label>Currency</label>
                                <InputText type="text" maxlength="3" class="form-control" @input="
                                    ($event) => {
                                        $event.target.value =
                                            $event.target.value.toUpperCase();
                                    }
                                " v-model="$parent.hrContext.rateForm.currency
                                    " />
                            </fieldset>
                        </form>
                        <div class="d-flex justify-content-end gap-2 mt-2">
                            <Button size="small" :disabled="$parent.hrContext.savingRate"
                                @click="$parent.hrContext.submitRate()">
                                <span v-if="$parent.hrContext.savingRate"
                                    class="spinner-border spinner-border-sm me-1"></span>
                                Save
                            </Button>
                            <Button size="small" severity="secondary" outline
                                @click="$parent.hrContext.closeEmployeeModal()">
                                Close
                            </Button>
                        </div>
                    </TabPanel>

                    <TabPanel value="2">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="m-0">Module Access</h6>
                                    <small class="text-secondary" v-if="
                                        $parent.hrContext.permissionsLoading
                                    ">loading…</small>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <label v-for="key in $parent.hrContext.permissions.module_keys || []" :key="key"
                                        class="form-check form-check-inline px-2 py-1 d-flex align-items-center"
                                        style="min-width: 180px">
                                        <Checkbox v-model="$parent.hrContext.permissions.modules[key]" binary
                                            @change="(e) => $parent.hrContext.toggleModule(key, e.target.checked)"
                                            class="me-2" size="small" />
                                        <span class="text-capitalize">{{ key }}</span>
                                    </label>
                                </div>

                            </div>

                            <div class="col-12">
                                <label class="form-label text-secondary mt-2">Main Module</label>
                                <select class="form-select" v-model="$parent.hrContext.permissions
                                    .main_module
                                    ">
                                    <option :value="null">— None —</option>
                                    <option v-for="key in $parent.hrContext
                                        .permissions.module_keys" :key="key" :value="key" :disabled="$parent.hrContext.permissions
                                            .modules[key] !== true
                                            " class="text-capitalize">
                                        {{ key }}
                                    </option>
                                </select>
                                <small class="text-secondary">Only enabled modules can be set as
                                    <em>main</em>.</small>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <Button class=" text-white" :disabled="$parent.hrContext.permissionsSaving" size="small"
                                    @click="$parent.hrContext.savePermissions()">
                                    <span v-if="$parent.hrContext.permissionsSaving"
                                        class="spinner-border spinner-border-sm me-1"></span>
                                    Save
                                </Button>
                                <Button size="small" severity="secondary" outline
                                    @click="$parent.hrContext.closeEmployeeModal()">Close</Button>
                            </div>
                        </div>

                    </TabPanel>
                </TabPanels>
            </Tabs>

        </Dialog>

    </div>
</template>

<script>
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Checkbox from 'primevue/checkbox';
import Select from 'primevue/select';
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
        Select
    },
};
</script>

<style scoped src="../hr.css"></style>