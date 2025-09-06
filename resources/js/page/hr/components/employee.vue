<template>
    <div class="employee-container">
        <div class="employee-header">
            <h4>Employees</h4>
            <button
                class="btn btn-primary text-white m-0"
                @click="$parent.openAddEmployeeModal()"
            >
                Add Employee
            </button>
        </div>

        <!-- Mobile: card list -->
        <div class="d-md-none">
            <div
                class="emp-card shadow-sm rounded-3 mb-2 p-3"
                v-for="(emp, index) in $parent.employees"
                :key="emp.id"
            >
                <div
                    class="d-flex justify-content-between align-items-start mb-1"
                >
                    <div class="emp-title">
                        <div class="emp-name fw-semibold text-truncate">
                            {{ emp.name }}
                        </div>
                        <div
                            class="emp-position text-secondary small text-truncate"
                        >
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
                    <button
                        class="btn btn-primary btn-sm"
                        @click="$parent.hrContext.openEmployeeModal(emp)"
                    >
                        Employee Details
                    </button>
                </div>
            </div>
        </div>

        <!-- Desktop: table -->
        <div class="table-responsive d-none d-md-block">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 64px">#</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>
                            Current Rate <br />
                            <small class="text-secondary"
                                >Monthly | Hourly</small
                            >
                        </th>
                        <th style="width: 220px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(emp, index) in $parent.employees" :key="emp.id">
                        <td class="text-secondary">{{ index + 1 }}</td>
                        <td class="fw-semibold">{{ emp.name }}</td>
                        <td class="text-secondary">
                            {{ emp.position || "—" }}
                        </td>
                        <td>
                            <span class="rate-chip">
                                {{
                                    emp.current_monthly_rate != null
                                        ? "₱" +
                                          Number(
                                              emp.current_monthly_rate
                                          ).toFixed(2)
                                        : "-"
                                }}
                            </span>
                            <span class="mx-1 text-secondary">|</span>
                            <span class="rate-chip">
                                {{
                                    emp.current_hourly_rate != null
                                        ? "₱" +
                                          Number(
                                              emp.current_hourly_rate
                                          ).toFixed(2)
                                        : "-"
                                }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <button
                                    class="btn btn-outline-primary btn-sm"
                                    @click="
                                        $parent.hrContext.openEmployeeModal(emp)
                                    "
                                >
                                    Employee Details
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Add Employee Modal (unchanged) -->
        <div
            v-if="$parent.showAddEmployeeModal"
            class="modal modal-addEmployee"
        >
            <div
                class="modal-overlay"
                @click="$parent.closeAddEmployeeModal()"
            ></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Employee</h5>
                </div>
                <div class="modal-body">
                    <form @submit.prevent>
                        <fieldset>
                            <input
                                v-model="$parent.newEmployee.name"
                                class="form-control"
                                placeholder="Name"
                            />
                            <input
                                v-model="$parent.newEmployee.position"
                                class="form-control"
                                placeholder="Position"
                            />
                        </fieldset>
                    </form>
                </div>
                <div class="modal-footer">
                    <button
                        class="btn btn-success text-white"
                        @click="$parent.addEmployee()"
                    >
                        Add
                    </button>
                    <button
                        class="btn btn-secondary text-white"
                        @click="$parent.closeAddEmployeeModal()"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <small class="text-muted">
            debug:
            {{ $parent.hrContext.employeeModal.show ? "open" : "closed" }}
        </small>

        <!-- NEW: Employee Details modal with header tabs (like your Profile modal) -->
        <div
            v-if="$parent.hrContext.employeeModal.show"
            class="modal modal-employeeDetails"
        >
            <div
                class="modal-overlay"
                @click="$parent.hrContext.closeEmployeeModal()"
            ></div>

            <div class="modal-content modal-xl">
                <!-- Modal Title -->
                <div class="modal-header">
                    <h5 class="modal-title">
                        Employee —
                        {{
                            $parent.hrContext.employeeModal.selectedEmployee
                                ?.name
                        }}
                    </h5>
                    <button
                        class="btn-close"
                        @click="$parent.hrContext.closeEmployeeModal()"
                    ></button>
                </div>

                <!-- Tab Header (styled like your screenshots) -->
                <div class="px-3 pt-2">
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <button
                                class="nav-link"
                                :class="{
                                    active:
                                        $parent.hrContext.employeeModal.tab ===
                                        'details',
                                }"
                                @click="
                                    $parent.hrContext.setEmployeeModalTab(
                                        'details'
                                    )
                                "
                            >
                                <!-- optional icon: 🧑 -->
                                Employee Details
                            </button>
                        </li>
                        <li class="nav-item">
                            <button
                                class="nav-link"
                                :class="{
                                    active:
                                        $parent.hrContext.employeeModal.tab ===
                                        'rate',
                                }"
                                @click="
                                    $parent.hrContext.setEmployeeModalTab(
                                        'rate'
                                    )
                                "
                            >
                                <!-- optional icon: ⏱ -->
                                Edit Employee Rate
                            </button>
                        </li>
                        <li class="nav-item">
                            <button
                                class="nav-link"
                                :class="{
                                    active:
                                        $parent.hrContext.employeeModal.tab ===
                                        'perms',
                                }"
                                @click="
                                    $parent.hrContext.setEmployeeModalTab(
                                        'perms'
                                    )
                                "
                            >
                                Permissions
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Body -->
                <div class="modal-body">
                    <!-- DETAILS TAB -->
                    <div
                        v-show="
                            $parent.hrContext.employeeModal.tab === 'details'
                        "
                    >
                        <div class="row g-3">
                            <div class="col-md-6">
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
                            <div class="col-md-6">
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

                            <div class="col-md-6">
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
                            <div class="col-md-6">
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

                            <div class="col-md-6">
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
                            <div class="col-md-3">
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
                            <div class="col-md-3">
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
                        </div>
                    </div>

                    <!-- RATE TAB -->
                    <div
                        v-show="$parent.hrContext.employeeModal.tab === 'rate'"
                    >
                        <form @submit.prevent>
                            <fieldset>
                                <label>Effective Start</label>
                                <input
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
                                <input
                                    type="date"
                                    class="form-control"
                                    v-model="
                                        $parent.hrContext.rateForm.effective_end
                                    "
                                />
                            </fieldset>

                            <fieldset>
                                <label>Monthly Rate (PHP)</label>
                                <input
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
                                <input
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
                                <input
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
                    </div>

                    <!-- PERMISSIONS TAB -->
                    <div
                        v-show="$parent.hrContext.employeeModal.tab === 'perms'"
                    >
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
                                <div class="mt-2 d-flex flex-wrap gap-2">
                                    <!-- render all known module keys from backend -->
                                    <label
                                        v-for="key in $parent.hrContext
                                            .permissions.module_keys || []"
                                        :key="key"
                                        class="form-check form-check-inline border rounded px-2 py-1"
                                        style="min-width: 180px"
                                    >
                                        <input
                                            class="form-check-input me-2"
                                            type="checkbox"
                                            :checked="
                                                $parent.hrContext.permissions
                                                    .modules[key] === true
                                            "
                                            @change="
                                                $parent.hrContext.toggleModule(
                                                    key,
                                                    $event.target.checked
                                                )
                                            "
                                        />
                                        <span
                                            class="form-check-label text-capitalize"
                                            >{{ key }}</span
                                        >
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
                                    <!-- only allow selecting modules that are enabled (checked) -->
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

                            <button
                                v-if="
                                    $parent.hrContext.employeeModal.tab ===
                                    'perms'
                                "
                                class="btn btn-success text-white"
                                :disabled="$parent.hrContext.permissionsSaving"
                                @click="$parent.hrContext.savePermissions()"
                            >
                                <span
                                    v-if="$parent.hrContext.permissionsSaving"
                                    class="spinner-border spinner-border-sm me-1"
                                ></span>
                                Save
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer">
                    <button
                        v-if="$parent.hrContext.employeeModal.tab === 'rate'"
                        class="btn btn-success text-white"
                        :disabled="$parent.hrContext.savingRate"
                        @click="$parent.hrContext.submitRate()"
                    >
                        <span
                            v-if="$parent.hrContext.savingRate"
                            class="spinner-border spinner-border-sm me-1"
                        ></span>
                        Save
                    </button>
                    <button
                        class="btn btn-secondary text-white"
                        @click="$parent.hrContext.closeEmployeeModal()"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped src="../hr.css"></style>
