<template>
    <Dialog
        :visible="visible"
        @update:visible="$emit('update:visible', $event)"
        modal
        header="Profile"
        :style="{ width: '90%', height: '80vh' }"
        class="profile-modal"
    >
        <TabView class="profile-tabs">
            <!-- Attendance Tab -->
            <TabPanel>
                <template #header>
                    <i class="pi pi-calendar mr-2"></i>
                    <span>Attendance</span>
                </template>

                <div class="scrollable-content">
                    <div class="attendance-container">
                        <div class="text-center mb-4">
                            <h3 class="mb-3">
                                Attendance / Clock-in & Clock-out
                            </h3>

                            <!-- Combined Time Display & Hours Summary -->
                            <Card class="combined-info-card mb-3">
                                <template #content>
                                    <!-- Time Display Section -->
                                    <div class="time-display">
                                        <div
                                            id="current-time"
                                            class="current-time"
                                        >
                                            {{ currentTime }}
                                        </div>
                                        <div
                                            id="current-day"
                                            class="current-day"
                                        >
                                            {{ currentDay }}
                                        </div>
                                    </div>

                                    <Divider />

                                    <!-- Hours Summary Section -->
                                    <div class="summary-grid">
                                        <div class="summary-item">
                                            <i
                                                class="pi pi-clock summary-icon"
                                            ></i>
                                            <div>
                                                <div class="summary-label">
                                                    Today's Hours
                                                </div>
                                                <div class="summary-value">
                                                    {{ todayHours }}
                                                </div>
                                            </div>
                                        </div>
                                        <Divider layout="vertical" />
                                        <div class="summary-item">
                                            <i
                                                class="pi pi-calendar summary-icon"
                                            ></i>
                                            <div>
                                                <div class="summary-label">
                                                    This Week's Hours
                                                </div>
                                                <div class="summary-value">
                                                    {{ weekHours }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </Card>

                            <!-- Clock In/Out Buttons -->
                            <div class="button-group mb-4">
                                <Button
                                    label="Clock In"
                                    icon="pi pi-sign-in"
                                    @click="confirmClockIn"
                                    :disabled="!canClockIn"
                                    :severity="
                                        canClockIn ? 'success' : 'secondary'
                                    "
                                    class="clock-button"
                                    size="large"
                                />

                                <Button
                                    label="Clock Out"
                                    icon="pi pi-sign-out"
                                    @click="confirmClockOut"
                                    :disabled="!canClockOut"
                                    :severity="
                                        canClockOut ? 'danger' : 'secondary'
                                    "
                                    class="clock-button"
                                    size="large"
                                />
                            </div>
                        </div>

                        <!-- Attendance DataTable -->
                        <DataTable
                            :value="attendanceRecords"
                            :paginator="true"
                            :rows="5"
                            :rowsPerPageOptions="[5, 10, 20]"
                            responsiveLayout="stack"
                            breakpoint="768px"
                            class="attendance-table"
                            stripedRows
                            :loading="loading"
                        >
                            <Column
                                field="timeIn"
                                header="Time In"
                                style="min-width: 150px"
                            >
                                <template #body="slotProps">
                                    <div class="time-cell">
                                        <div class="time-value">
                                            {{
                                                formatTime(
                                                    slotProps.data.timeIn
                                                )
                                            }}
                                        </div>
                                        <div class="date-value">
                                            {{
                                                formatDate(
                                                    slotProps.data.timeIn
                                                )
                                            }}
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column
                                field="timeOut"
                                header="Time Out"
                                style="min-width: 150px"
                            >
                                <template #body="slotProps">
                                    <div
                                        v-if="slotProps.data.timeOut"
                                        class="time-cell"
                                    >
                                        <div class="time-value">
                                            {{
                                                formatTime(
                                                    slotProps.data.timeOut
                                                )
                                            }}
                                        </div>
                                        <div class="date-value">
                                            {{
                                                formatDate(
                                                    slotProps.data.timeOut
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <Tag
                                        v-else
                                        severity="danger"
                                        value="Not yet timed out"
                                    />
                                </template>
                            </Column>

                            <Column
                                field="computedHours"
                                header="Hours Worked"
                                style="min-width: 120px"
                            >
                                <template #body="slotProps">
                                    <div
                                        v-if="slotProps.data.timeOut"
                                        class="computed-hours"
                                    >
                                        {{
                                            calculateHours(
                                                slotProps.data.timeIn,
                                                slotProps.data.timeOut
                                            )
                                        }}
                                    </div>
                                    <span v-else class="text-muted"
                                        >Not calculated</span
                                    >
                                </template>
                            </Column>

                            <Column
                                header="Notes"
                                style="width: 100px; text-align: center"
                            >
                                <template #body="slotProps">
                                    <Button
                                        icon="pi pi-pencil"
                                        size="small"
                                        severity="info"
                                        text
                                        rounded
                                        @click="openNotesModal(slotProps.data)"
                                        v-tooltip.top="
                                            slotProps.data.notes || 'Add notes'
                                        "
                                    />
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </TabPanel>

            <!-- Account Tab -->
            <TabPanel>
                <template #header>
                    <i class="pi pi-user mr-2"></i>
                    <span>Account</span>
                </template>

                <div class="scrollable-content">
                    <div class="account-content">
                        <!-- Sub-tabs for Account -->
                        <div class="account-tabs-nav">
                            <Button
                                label="Account Details"
                                :severity="
                                    activeAccountTab === 'details'
                                        ? 'primary'
                                        : 'secondary'
                                "
                                :outlined="activeAccountTab !== 'details'"
                                @click="switchAccountTab('details')"
                                size="small"
                            />
                            <Button
                                label="Change Password"
                                :severity="
                                    activeAccountTab === 'password'
                                        ? 'primary'
                                        : 'secondary'
                                "
                                :outlined="activeAccountTab !== 'password'"
                                @click="activeAccountTab = 'password'"
                                size="small"
                            />
                            <Button
                                label="Timezone Settings"
                                :severity="
                                    activeAccountTab === 'timezone'
                                        ? 'primary'
                                        : 'secondary'
                                "
                                :outlined="activeAccountTab !== 'timezone'"
                                @click="switchAccountTab('timezone')"
                                size="small"
                            />
                        </div>

                        <!-- Account Details -->
                        <div
                            v-show="activeAccountTab === 'details'"
                            class="account-tab-content"
                        >
                            <div class="account-details-wrapper">
                                <form @submit.prevent="saveAccountDetails">
                                    <!-- Read-Only Section -->
                                    <div class="info-section readonly-section">
                                        <div class="section-header">
                                            <i class="pi pi-shield"></i>
                                            <h5>Account Information</h5>
                                        </div>
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <label>Username</label>
                                                <div class="info-value">
                                                    <i class="pi pi-user"></i>
                                                    <span>{{
                                                        accountDetails.username ||
                                                        "N/A"
                                                    }}</span>
                                                </div>
                                            </div>
                                            <div class="info-item">
                                                <label>User Type</label>
                                                <div class="info-value">
                                                    <i
                                                        class="pi pi-briefcase"
                                                    ></i>
                                                    <span>{{
                                                        accountDetails.usertype ||
                                                        "N/A"
                                                    }}</span>
                                                </div>
                                            </div>
                                            <div class="info-item">
                                                <label>Account Type</label>
                                                <div class="info-value">
                                                    <i
                                                        class="pi pi-id-card"
                                                    ></i>
                                                    <span>{{
                                                        accountDetails.accounttype ||
                                                        "N/A"
                                                    }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Personal Information Section -->
                                    <div class="info-section editable-section">
                                        <div class="section-header">
                                            <i class="pi pi-user-edit"></i>
                                            <h5>Personal Information</h5>
                                        </div>
                                        <div class="form-grid">
                                            <div class="form-group">
                                                <label for="full_name">
                                                    <i class="pi pi-user"></i>
                                                    Full Name
                                                </label>
                                                <InputText
                                                    id="full_name"
                                                    v-model="
                                                        accountDetails.full_name
                                                    "
                                                    placeholder="Enter your full name"
                                                />
                                            </div>
                                            <div class="form-group">
                                                <label for="work_email">
                                                    <i
                                                        class="pi pi-envelope"
                                                    ></i>
                                                    Work Email
                                                </label>
                                                <InputText
                                                    id="work_email"
                                                    v-model="
                                                        accountDetails.work_email
                                                    "
                                                    type="email"
                                                    placeholder="your.email@company.com"
                                                />
                                            </div>
                                            <div class="form-group">
                                                <label for="contact_phone">
                                                    <i class="pi pi-phone"></i>
                                                    Contact Number
                                                </label>
                                                <InputText
                                                    id="contact_phone"
                                                    v-model="
                                                        accountDetails.contact_phone
                                                    "
                                                    placeholder="+1 234 567 8900"
                                                />
                                            </div>
                                            <div class="form-group">
                                                <label for="birthdate">
                                                    <i
                                                        class="pi pi-calendar"
                                                    ></i>
                                                    Birthdate
                                                </label>
                                                <Calendar
                                                    id="birthdate"
                                                    v-model="
                                                        accountDetails.birthdate
                                                    "
                                                    dateFormat="yy-mm-dd"
                                                    showIcon
                                                    placeholder="Select date"
                                                />
                                            </div>
                                            <div class="form-group full-width">
                                                <label for="address">
                                                    <i
                                                        class="pi pi-map-marker"
                                                    ></i>
                                                    Address
                                                </label>
                                                <Textarea
                                                    id="address"
                                                    v-model="
                                                        accountDetails.address
                                                    "
                                                    rows="3"
                                                    placeholder="Enter your full address"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Emergency Contact Section -->
                                    <div class="info-section emergency-section">
                                        <div
                                            class="section-header emergency-header"
                                        >
                                            <i
                                                class="pi pi-exclamation-circle"
                                            ></i>
                                            <h5>Emergency Contact</h5>
                                        </div>
                                        <div class="form-grid">
                                            <div class="form-group">
                                                <label for="ice_name">
                                                    <i class="pi pi-user"></i>
                                                    Contact Person
                                                </label>
                                                <InputText
                                                    id="ice_name"
                                                    v-model="
                                                        accountDetails.ice_name
                                                    "
                                                    placeholder="Emergency contact name"
                                                />
                                            </div>
                                            <div class="form-group">
                                                <label for="ice_relationship">
                                                    <i class="pi pi-heart"></i>
                                                    Relationship
                                                </label>
                                                <InputText
                                                    id="ice_relationship"
                                                    v-model="
                                                        accountDetails.ice_relationship
                                                    "
                                                    placeholder="e.g., Spouse, Parent, Sibling"
                                                />
                                            </div>
                                            <div class="form-group">
                                                <label for="ice_phone">
                                                    <i class="pi pi-phone"></i>
                                                    Contact Number
                                                </label>
                                                <InputText
                                                    id="ice_phone"
                                                    v-model="
                                                        accountDetails.ice_phone
                                                    "
                                                    placeholder="+1 234 567 8900"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="form-actions-modern">
                                        <Button
                                            type="submit"
                                            label="Save Changes"
                                            icon="pi pi-check"
                                            :loading="savingDetails"
                                            class="save-button"
                                        />
                                        <Button
                                            type="button"
                                            label="Reset"
                                            icon="pi pi-refresh"
                                            @click="loadAccountDetails"
                                            severity="secondary"
                                            outlined
                                        />
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div
                            v-show="activeAccountTab === 'password'"
                            class="account-tab-content"
                        >
                            <Card>
                                <template #content>
                                    <form @submit.prevent="changePassword">
                                        <div class="p-fluid">
                                            <h5 class="section-title mb-3">
                                                Change Your Password
                                            </h5>

                                            <div class="field mb-4">
                                                <label for="newpassword"
                                                    >New Password</label
                                                >
                                                <Password
                                                    id="newpassword"
                                                    v-model="
                                                        passwordForm.password
                                                    "
                                                    toggleMask
                                                    :feedback="false"
                                                    placeholder="Enter new password"
                                                />
                                            </div>

                                            <div class="field">
                                                <label for="confirmpassword"
                                                    >Confirm Password</label
                                                >
                                                <Password
                                                    id="confirmpassword"
                                                    v-model="
                                                        passwordForm.password_confirmation
                                                    "
                                                    toggleMask
                                                    :feedback="false"
                                                    placeholder="Confirm password"
                                                />
                                            </div>
                                        </div>

                                        <div class="form-actions mt-4">
                                            <Button
                                                type="submit"
                                                label="Change Password"
                                                icon="pi pi-lock"
                                                :loading="changingPassword"
                                                severity="warning"
                                            />
                                        </div>
                                    </form>
                                </template>
                            </Card>
                        </div>

                        <!-- Timezone Settings -->
                        <div
                            v-show="activeAccountTab === 'timezone'"
                            class="account-tab-content"
                        >
                            <Card>
                                <template #content>
                                    <form @submit.prevent="updateTimezone">
                                        <div class="p-fluid">
                                            <h5 class="section-title mb-3">
                                                Timezone Preferences
                                            </h5>

                                            <div class="field mb-4">
                                                <label for="usertimezone"
                                                    >Preferred Timezone</label
                                                >
                                                <Dropdown
                                                    id="usertimezone"
                                                    v-model="
                                                        timezoneForm.usertimezone
                                                    "
                                                    :options="timezones"
                                                    optionLabel="label"
                                                    optionValue="tz"
                                                    placeholder="Select Timezone"
                                                    :disabled="
                                                        timezoneForm.auto_sync
                                                    "
                                                    filter
                                                    showClear
                                                />
                                            </div>

                                            <div class="field-checkbox">
                                                <Checkbox
                                                    id="auto_sync"
                                                    v-model="
                                                        timezoneForm.auto_sync
                                                    "
                                                    :binary="true"
                                                />
                                                <label
                                                    for="auto_sync"
                                                    class="ml-2"
                                                >
                                                    Automatically Sync Timezone
                                                </label>
                                            </div>

                                            <Message
                                                v-if="timezoneForm.auto_sync"
                                                severity="info"
                                                :closable="false"
                                                class="mt-3"
                                            >
                                                Timezone will automatically sync
                                                with your system settings
                                            </Message>
                                        </div>

                                        <div class="form-actions mt-4">
                                            <Button
                                                type="submit"
                                                label="Update Timezone"
                                                icon="pi pi-globe"
                                                :loading="updatingTimezone"
                                            />
                                        </div>
                                    </form>
                                </template>
                            </Card>
                        </div>
                    </div>
                </div>
            </TabPanel>

            <!-- Time Record Tab -->
            <TabPanel>
                <template #header>
                    <i class="pi pi-clock mr-2"></i>
                    <span>Record</span>
                </template>

                <div class="scrollable-content">
                    <div class="time-record-content">
                        <Card class="filter-card">
                            <template #content>
                                <div class="filter-section">
                                    <h5 class="section-title mb-3">
                                        <i class="pi pi-filter"></i>
                                        Filter Attendance Records
                                    </h5>

                                    <div class="filter-grid">
                                        <div class="filter-field">
                                            <label for="start_date"
                                                >Start Date</label
                                            >
                                            <Calendar
                                                id="start_date"
                                                v-model="recordFilter.startDate"
                                                dateFormat="yy-mm-dd"
                                                showIcon
                                                placeholder="Select start date"
                                            />
                                        </div>

                                        <div class="filter-field">
                                            <label for="end_date"
                                                >End Date</label
                                            >
                                            <Calendar
                                                id="end_date"
                                                v-model="recordFilter.endDate"
                                                dateFormat="yy-mm-dd"
                                                showIcon
                                                placeholder="Select end date"
                                            />
                                        </div>

                                        <div class="filter-actions">
                                            <Button
                                                label="Filter"
                                                icon="pi pi-search"
                                                @click="filterAttendanceRecords"
                                                :loading="loadingRecords"
                                            />
                                            <Button
                                                label="Clear"
                                                icon="pi pi-times"
                                                @click="clearFilter"
                                                severity="secondary"
                                                outlined
                                            />
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Total Hours Summary -->
                        <Card class="total-hours-card">
                            <template #content>
                                <div class="total-hours-display">
                                    <i class="pi pi-clock"></i>
                                    <div>
                                        <span class="total-label"
                                            >Total Hours</span
                                        >
                                        <span class="total-value">{{
                                            totalHours
                                        }}</span>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Attendance Records DataTable -->
                        <Card>
                            <template #content>
                                <DataTable
                                    :value="filteredRecords"
                                    :paginator="true"
                                    :rows="10"
                                    :rowsPerPageOptions="[10, 20, 50]"
                                    responsiveLayout="stack"
                                    breakpoint="768px"
                                    stripedRows
                                    :loading="loadingRecords"
                                    class="time-record-table"
                                >
                                    <template #empty>
                                        <div class="text-center p-4">
                                            <i
                                                class="pi pi-inbox"
                                                style="
                                                    font-size: 3rem;
                                                    color: #6c757d;
                                                "
                                            ></i>
                                            <p class="mt-3">
                                                No records found.
                                            </p>
                                        </div>
                                    </template>

                                    <Column field="date" header="Date">
                                        <template #body="slotProps">
                                            <div class="record-date">
                                                <i class="pi pi-calendar"></i>
                                                <strong>{{
                                                    formatRecordDate(
                                                        slotProps.data.time_in
                                                    )
                                                }}</strong>
                                            </div>
                                        </template>
                                    </Column>

                                    <Column field="time_in" header="Time In">
                                        <template #body="slotProps">
                                            <div class="record-time">
                                                <i class="pi pi-sign-in"></i>
                                                {{
                                                    formatTime(
                                                        slotProps.data.time_in
                                                    )
                                                }}
                                            </div>
                                        </template>
                                    </Column>

                                    <Column field="time_out" header="Time Out">
                                        <template #body="slotProps">
                                            <div
                                                v-if="slotProps.data.time_out"
                                                class="record-time"
                                            >
                                                <i class="pi pi-sign-out"></i>
                                                {{
                                                    formatTime(
                                                        slotProps.data.time_out
                                                    )
                                                }}
                                            </div>
                                            <Tag
                                                v-else
                                                severity="danger"
                                                value="Not yet timed out"
                                            />
                                        </template>
                                    </Column>

                                    <Column
                                        field="hours"
                                        header="Computed Hours"
                                    >
                                        <template #body="slotProps">
                                            <div class="computed-hours-badge">
                                                <i class="pi pi-clock"></i>
                                                <strong>{{
                                                    calculateRecordHours(
                                                        slotProps.data.time_in,
                                                        slotProps.data.time_out
                                                    )
                                                }}</strong>
                                            </div>
                                        </template>
                                    </Column>
                                </DataTable>
                            </template>
                        </Card>
                    </div>
                </div>
            </TabPanel>

            <!-- My Privileges Tab -->
            <TabPanel>
                <template #header>
                    <i class="pi pi-shield mr-2"></i>
                    <span>My Privileges</span>
                </template>

                <div class="scrollable-content">
                    <div class="privileges-content">
                        <!-- Header Card -->
                        <Card class="privileges-header-card">
                            <template #content>
                                <div class="privileges-header">
                                    <div class="header-icon">
                                        <i class="pi pi-shield"></i>
                                    </div>
                                    <div>
                                        <h5 class="header-title">
                                            Account Privileges
                                        </h5>
                                        <p class="header-subtitle">
                                            View your assigned system privileges
                                            and access permissions
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Privileges Grid -->
                        <Card class="privileges-card">
                            <template #content>
                                <div
                                    v-if="loadingPrivileges"
                                    class="loading-state"
                                >
                                    <ProgressSpinner
                                        style="width: 50px; height: 50px"
                                    />
                                    <p>Loading privileges...</p>
                                </div>

                                <div
                                    v-else-if="privilegesList.length === 0"
                                    class="empty-state"
                                >
                                    <i class="pi pi-info-circle"></i>
                                    <p>No privileges assigned</p>
                                </div>

                                <div v-else class="privileges-grid">
                                    <div
                                        v-for="privilege in privilegesList"
                                        :key="privilege.key"
                                        class="privilege-item"
                                        :class="{
                                            'privilege-enabled':
                                                privilege.enabled,
                                        }"
                                    >
                                        <Checkbox
                                            v-model="privilege.enabled"
                                            :inputId="privilege.key"
                                            :binary="true"
                                            disabled
                                            class="privilege-checkbox"
                                        />
                                        <label
                                            :for="privilege.key"
                                            class="privilege-label"
                                        >
                                            <i
                                                :class="privilege.icon"
                                                class="privilege-icon"
                                            ></i>
                                            <span>{{ privilege.label }}</span>
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Legend -->
                        <Card class="legend-card">
                            <template #content>
                                <div class="legend-content">
                                    <div class="legend-item">
                                        <i
                                            class="pi pi-check-circle"
                                            style="color: #28a745"
                                        ></i>
                                        <span
                                            >Enabled - You have access to this
                                            feature</span
                                        >
                                    </div>
                                    <div class="legend-item">
                                        <i
                                            class="pi pi-times-circle"
                                            style="color: #dc3545"
                                        ></i>
                                        <span
                                            >Disabled - Access not granted</span
                                        >
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                </div>
            </TabPanel>

            <!-- My Schedule Tab -->
            <TabPanel>
                <template #header>
                    <i class="pi pi-calendar mr-2"></i>
                    <span>My Schedule</span>
                </template>

                <div class="scrollable-content">
                    <div class="schedule-content">
                        <!-- Header Controls -->
                        <Card class="schedule-header-card">
                            <template #content>
                                <div class="schedule-header">
                                    <div class="schedule-nav">
                                        <Button
                                            icon="pi pi-chevron-left"
                                            severity="secondary"
                                            outlined
                                            @click="previousMonth"
                                            :disabled="loadingSchedule"
                                        />

                                        <div class="month-display">
                                            <i class="pi pi-calendar"></i>
                                            <span>{{ currentMonthLabel }}</span>
                                        </div>

                                        <Button
                                            icon="pi pi-chevron-right"
                                            severity="secondary"
                                            outlined
                                            @click="nextMonth"
                                            :disabled="loadingSchedule"
                                        />
                                    </div>

                                    <div class="schedule-legend">
                                        <div class="legend-item">
                                            <span
                                                class="status-dot present"
                                            ></span>
                                            <span>Present</span>
                                        </div>
                                        <div class="legend-item">
                                            <span
                                                class="status-dot late"
                                            ></span>
                                            <span>Late</span>
                                        </div>
                                        <div class="legend-item">
                                            <span
                                                class="status-dot absent"
                                            ></span>
                                            <span>Absent</span>
                                        </div>
                                        <div class="legend-item">
                                            <span
                                                class="swatch swatch-today"
                                            ></span>
                                            <span>Today</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Loading State -->
                        <Card v-if="loadingSchedule" class="loading-card">
                            <template #content>
                                <div class="loading-state">
                                    <ProgressSpinner
                                        style="width: 50px; height: 50px"
                                    />
                                    <p>Loading schedule...</p>
                                </div>
                            </template>
                        </Card>

                        <!-- Calendar Grid -->
                        <Card v-else class="calendar-card">
                            <template #content>
                                <div class="calendar-wrapper">
                                    <div class="calendar-grid">
                                        <!-- Day Headers -->
                                        <div class="calendar-dow">M</div>
                                        <div class="calendar-dow">T</div>
                                        <div class="calendar-dow">W</div>
                                        <div class="calendar-dow">T</div>
                                        <div class="calendar-dow">F</div>
                                        <div class="calendar-dow">S</div>
                                        <div class="calendar-dow">S</div>

                                        <!-- Render all cells -->
                                        <template
                                            v-for="(
                                                cell, index
                                            ) in calendarCells"
                                            :key="index"
                                        >
                                            <div
                                                v-if="cell.isBlank"
                                                class="calendar-cell blank"
                                            ></div>
                                            <div
                                                v-else
                                                class="calendar-cell"
                                                :class="{
                                                    'is-today': cell.isToday,
                                                    'is-selected':
                                                        cell.isSelected,
                                                    'has-schedule':
                                                        cell.hasSchedule,
                                                }"
                                                @click="selectDate(cell.day)"
                                            >
                                                <div class="cell-date">
                                                    <span>{{ cell.day }}</span>
                                                    <span
                                                        v-if="cell.status"
                                                        class="status-dot"
                                                        :class="cell.status"
                                                        :title="cell.status"
                                                    ></span>
                                                </div>
                                                <div class="cell-meta">
                                                    {{ cell.metaText }}
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <!-- Day Details -->
                        <Card v-if="selectedDayInfo" class="day-details-card">
                            <template #header>
                                <div class="day-details-header">
                                    <h6>{{ selectedDayFormatted }}</h6>
                                    <Button
                                        icon="pi pi-times"
                                        text
                                        rounded
                                        severity="secondary"
                                        @click="clearSelectedDay"
                                    />
                                </div>
                            </template>
                            <template #content>
                                <!-- Holidays -->
                                <div
                                    v-if="selectedDayInfo.holidays?.length"
                                    class="mb-3"
                                >
                                    <div class="detail-section-title">
                                        <i class="pi pi-sun"></i>
                                        <span>Holiday</span>
                                    </div>
                                    <ul class="holiday-list">
                                        <li
                                            v-for="(
                                                holiday, idx
                                            ) in selectedDayInfo.holidays"
                                            :key="idx"
                                        >
                                            {{ holiday.title }} —
                                            {{ holiday.date }}
                                            <span v-if="holiday.status"
                                                >({{ holiday.status }})</span
                                            >
                                        </li>
                                    </ul>
                                </div>

                                <!-- Schedule Entries -->
                                <div class="detail-section-title">
                                    <i class="pi pi-clock"></i>
                                    <span>Schedule</span>
                                </div>
                                <div
                                    v-if="!selectedDayInfo.entries?.length"
                                    class="empty-schedule"
                                >
                                    <i class="pi pi-info-circle"></i>
                                    <p>No schedule for this day</p>
                                </div>
                                <div v-else class="schedule-list">
                                    <div
                                        v-for="(
                                            entry, idx
                                        ) in selectedDayInfo.entries"
                                        :key="idx"
                                        class="schedule-entry"
                                    >
                                        <div class="entry-info">
                                            <div class="entry-name">
                                                {{ entry.name || "Shift" }}
                                            </div>
                                            <div
                                                v-if="entry.notes"
                                                class="entry-notes"
                                            >
                                                {{ entry.notes }}
                                            </div>
                                        </div>
                                        <div class="entry-time">
                                            {{ entry.start }} – {{ entry.end }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                </div>
            </TabPanel>
        </TabView>
    </Dialog>

    <!-- Notes Edit Dialog -->
    <Dialog
        v-model:visible="notesDialogVisible"
        header="Edit Notes"
        :style="{ width: '35rem' }"
        :breakpoints="{ '768px': '90vw' }"
        modal
        :draggable="false"
    >
        <div class="notes-dialog-content">
            <div class="field">
                <label for="notes" class="notes-label">Notes</label>
                <Textarea
                    id="notes"
                    v-model="currentNotes"
                    rows="6"
                    autoResize
                    placeholder="Add notes about this time entry..."
                    class="w-full"
                />
                <small class="notes-hint"
                    >Add any comments or details about this attendance
                    record</small
                >
            </div>
        </div>

        <template #footer>
            <div class="dialog-footer">
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="notesDialogVisible = false"
                    severity="secondary"
                    text
                />
                <Button
                    label="Save"
                    icon="pi pi-check"
                    @click="saveNotes"
                    :loading="savingNotes"
                    severity="success"
                />
            </div>
        </template>
    </Dialog>
</template>

<script>
import Dialog from "primevue/dialog";
import TabView from "primevue/tabview";
import TabPanel from "primevue/tabpanel";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Button from "primevue/button";
import Tag from "primevue/tag";
import Textarea from "primevue/textarea";
import Card from "primevue/card";
import Divider from "primevue/divider";

import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Calendar from "primevue/calendar";
import Dropdown from "primevue/dropdown";
import Checkbox from "primevue/checkbox";
import Message from "primevue/message";

import Swal from "sweetalert2";

export default {
    name: "ProfileModal",
    components: {
        Dialog,
        TabView,
        TabPanel,
        DataTable,
        Column,
        Button,
        Tag,
        Textarea,
        Card,
        Divider,
        InputText,
        Password,
        Calendar,
        Dropdown,
        Checkbox,
        Message,
    },
    props: {
        visible: {
            type: Boolean,
            default: false,
        },
    },
    emits: ["update:visible"],
    data() {
        return {
            attendanceRecords: [],
            lastRecordTimeIn: "",
            todayHours: "0 hrs 00 mins",
            weekHours: "0 hrs 00 mins",
            canClockIn: true,
            canClockOut: false,
            notesDialogVisible: false,
            currentNotes: "",
            selectedRecordId: null,
            loading: false,
            savingNotes: false,
            currentTime: "",
            currentDay: "",
            timeInterval: null,

            activeAccountTab: "details",
            accountDetails: {
                username: "",
                usertype: "",
                accounttype: "",
                full_name: "",
                work_email: "",
                contact_phone: "",
                birthdate: null,
                address: "",
                ice_name: "",
                ice_relationship: "",
                ice_phone: "",
            },
            passwordForm: {
                password: "",
                password_confirmation: "",
            },
            timezoneForm: {
                usertimezone: "",
                auto_sync: false,
            },
            timezones: [],
            savingDetails: false,
            changingPassword: false,
            updatingTimezone: false,
            accountDetailsLoaded: false,
            timezonesLoaded: false,

            recordFilter: {
                startDate: null,
                endDate: null,
            },
            filteredRecords: [],
            totalHours: "0 hrs 0 mins",
            loadingRecords: false,

            privilegesLoaded: false,
            loadingPrivileges: false,
            privilegesList: [
                {
                    key: "humanresource",
                    label: "Human Resource",
                    icon: "pi pi-users",
                    enabled: false,
                },
                {
                    key: "order",
                    label: "Order",
                    icon: "pi pi-shopping-cart",
                    enabled: false,
                },
                {
                    key: "unreceived",
                    label: "Unreceived",
                    icon: "pi pi-inbox",
                    enabled: false,
                },
                {
                    key: "receiving",
                    label: "Receiving",
                    icon: "pi pi-download",
                    enabled: false,
                },
                {
                    key: "labeling",
                    label: "Labeling",
                    icon: "pi pi-tags",
                    enabled: false,
                },
                {
                    key: "testing",
                    label: "Testing",
                    icon: "pi pi-check-square",
                    enabled: false,
                },
                {
                    key: "cleaning",
                    label: "Cleaning",
                    icon: "pi pi-sparkles",
                    enabled: false,
                },
                {
                    key: "packing",
                    label: "Packing",
                    icon: "pi pi-box",
                    enabled: false,
                },
                {
                    key: "stockroom",
                    label: "Stockroom",
                    icon: "pi pi-warehouse",
                    enabled: false,
                },
                {
                    key: "validation",
                    label: "Validation",
                    icon: "pi pi-verified",
                    enabled: false,
                },
                {
                    key: "fnsku",
                    label: "FNSKU",
                    icon: "pi pi-qrcode",
                    enabled: false,
                },
                {
                    key: "asinlist",
                    label: "ASIN List",
                    icon: "pi pi-list",
                    enabled: false,
                },
                {
                    key: "productionarea",
                    label: "Production Area",
                    icon: "pi pi-building",
                    enabled: false,
                },
                {
                    key: "rts",
                    label: "RTS",
                    icon: "pi pi-refresh",
                    enabled: false,
                },
                {
                    key: "returnscanner",
                    label: "Return Scanner",
                    icon: "pi pi-replay",
                    enabled: false,
                },
                {
                    key: "fbmorder",
                    label: "FBM Order",
                    icon: "pi pi-send",
                    enabled: false,
                },
                {
                    key: "notfound",
                    label: "Not Found",
                    icon: "pi pi-question-circle",
                    enabled: false,
                },
                {
                    key: "asinoption",
                    label: "ASIN Option",
                    icon: "pi pi-sliders-h",
                    enabled: false,
                },
                {
                    key: "houseage",
                    label: "Houseage",
                    icon: "pi pi-home",
                    enabled: false,
                },
                {
                    key: "printer",
                    label: "Printer",
                    icon: "pi pi-print",
                    enabled: false,
                },
                {
                    key: "announcement",
                    label: "Announcement",
                    icon: "pi pi-megaphone",
                    enabled: false,
                },
            ],

            // My Schedule
            loadingSchedule: false,
            scheduleData: null,
            viewYear: new Date().getFullYear(),
            viewMonth: new Date().getMonth(), // 0-11
            selectedDate: new Date(),

            dayHeaders: ["M", "T", "W", "T", "F", "S", "S"],
        };
    },
    watch: {
        visible(newVal) {
            if (newVal) {
                this.loadAttendanceData();
                this.startClock();

                // Preload account details
                if (!this.accountDetailsLoaded) {
                    this.loadAccountDetails();
                }

                // Preload timezones
                if (!this.timezonesLoaded) {
                    this.loadTimezones();
                }

                // Load time records
                this.filterAttendanceRecords();

                // Load privileges
                this.loadUserPrivileges();

                // Preload schedule
                this.loadScheduleData();
            } else {
                this.stopClock();
            }
        },
    },
    mounted() {
        if (this.visible) {
            this.loadAttendanceData();
            this.startClock();
        }
    },
    beforeUnmount() {
        this.stopClock();
    },
    computed: {
        currentMonthLabel() {
            return new Date(this.viewYear, this.viewMonth, 1).toLocaleString(
                "en-US",
                { month: "long", year: "numeric" }
            );
        },

        calendarCells() {
            const cells = [];
            const firstDay = new Date(this.viewYear, this.viewMonth, 1);
            const lastDay = new Date(this.viewYear, this.viewMonth + 1, 0);
            const daysInMonth = lastDay.getDate();

            // Calculate leading blanks (Monday = 0 blanks)
            let dow = firstDay.getDay(); // 0 = Sunday, 1 = Monday, etc.
            const leadingBlanks = dow === 0 ? 6 : dow - 1;

            // Add blank cells
            for (let i = 0; i < leadingBlanks; i++) {
                cells.push({ isBlank: true });
            }

            // Add day cells
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(this.viewYear, this.viewMonth, day);
                const iso = this.formatISO(date);
                const info = this.scheduleData?.byDate?.[iso];

                // Check if today
                const dateOnly = new Date(this.viewYear, this.viewMonth, day);
                dateOnly.setHours(0, 0, 0, 0);
                const isToday = dateOnly.getTime() === today.getTime();

                // Check if selected
                const isSelected =
                    this.selectedDate &&
                    this.formatISO(this.selectedDate) === iso;

                // Check if has schedule
                const hasSchedule =
                    info &&
                    (info.entries?.length > 0 || info.holidays?.length > 0);

                // Get status
                const status = info?.status || null;

                // Get meta text
                let metaText = "—";
                if (info) {
                    if (info.entries?.length) {
                        if (info.entries.length === 1) {
                            metaText = `${info.entries[0].start}–${info.entries[0].end}`;
                        } else {
                            metaText = `${info.entries.length} shifts`;
                        }
                    }

                    if (info.holidays?.length) {
                        const holidate = date.toLocaleDateString("en-US", {
                            month: "short",
                            day: "numeric",
                        });
                        metaText =
                            metaText === "—"
                                ? `Holiday: ${holidate}`
                                : `${metaText} • Holiday`;
                    }
                }

                cells.push({
                    isBlank: false,
                    day,
                    isToday,
                    isSelected,
                    hasSchedule,
                    status,
                    metaText,
                });
            }

            return cells;
        },

        selectedDayInfo() {
            if (!this.selectedDate || !this.scheduleData) return null;
            const iso = this.formatISO(this.selectedDate);
            return this.scheduleData.byDate?.[iso] || null;
        },

        selectedDayFormatted() {
            if (!this.selectedDate) return "";
            return this.selectedDate.toLocaleDateString("en-US", {
                weekday: "long",
                month: "long",
                day: "numeric",
                year: "numeric",
            });
        },
    },
    methods: {
        async loadAttendanceData() {
            this.loading = true;
            try {
                const response = await axios.get("/api/attendance/profile");
                const data = response.data;

                this.attendanceRecords = data.records || [];
                this.todayHours = data.todayHours || "0 hrs 00 mins";
                this.weekHours = data.weekHours || "0 hrs 00 mins";
                this.lastRecordTimeIn = data.lastRecordTimeIn || "";
                this.canClockIn = data.canClockIn || false;
                this.canClockOut = data.canClockOut || false;
            } catch (error) {
                console.error("Error loading attendance data:", error);
                Swal.fire({
                    title: "Error!",
                    text: "Failed to load attendance data",
                    icon: "error",
                    confirmButtonText: "OK",
                });
            } finally {
                this.loading = false;
            }
        },

        startClock() {
            this.updateCurrentTime();
            this.timeInterval = setInterval(this.updateCurrentTime, 1000);
        },

        stopClock() {
            if (this.timeInterval) {
                clearInterval(this.timeInterval);
                this.timeInterval = null;
            }
        },

        updateCurrentTime() {
            const now = new Date();
            this.currentTime = now.toLocaleTimeString("en-US", {
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
                hour12: true,
            });
            this.currentDay = now.toLocaleDateString("en-US", {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
            });
        },

        async confirmClockIn() {
            const result = await Swal.fire({
                title: "Clock In",
                text: "Are you sure you want to clock in?",
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#28a745",
                cancelButtonColor: "#6c757d",
                confirmButtonText: '<i class="pi pi-check"></i> Yes, clock in!',
                cancelButtonText: '<i class="pi pi-times"></i> Cancel',
            });

            if (result.isConfirmed) {
                try {
                    await axios.post("/attendance/clockin");

                    await Swal.fire({
                        title: "Success!",
                        text: "You have successfully clocked in",
                        icon: "success",
                        confirmButtonText: "OK",
                    });

                    this.loadAttendanceData();
                } catch (error) {
                    const errorData = error.response?.data;
                    const status = error.response?.status;

                    if (status === 409) {
                        await Swal.fire({
                            title: "Already Clocked In",
                            text:
                                errorData?.message ||
                                "You already have an open clock-in. Please clock out first.",
                            icon: "warning",
                            confirmButtonText: "OK",
                        });
                    } else if (status === 403) {
                        await Swal.fire({
                            title: "Cannot Clock In",
                            text:
                                errorData?.message ||
                                "You cannot clock in at this time.",
                            icon: "error",
                            confirmButtonText: "OK",
                        });
                    } else {
                        await Swal.fire({
                            title: "Error!",
                            text:
                                errorData?.message ||
                                "Failed to clock in. Please try again.",
                            icon: "error",
                            confirmButtonText: "OK",
                        });
                    }
                }
            }
        },

        async confirmClockOut() {
            const result = await Swal.fire({
                title: "Clock Out",
                text: "Are you sure you want to clock out?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#dc3545",
                cancelButtonColor: "#6c757d",
                confirmButtonText:
                    '<i class="pi pi-check"></i> Yes, clock out!',
                cancelButtonText: '<i class="pi pi-times"></i> Cancel',
            });

            if (result.isConfirmed) {
                try {
                    await axios.post("/attendance/clockout");

                    await Swal.fire({
                        title: "Success!",
                        text: "You have successfully clocked out",
                        icon: "success",
                        confirmButtonText: "OK",
                    });

                    this.loadAttendanceData();
                } catch (error) {
                    const errorData = error.response?.data;
                    const status = error.response?.status;

                    if (status === 400) {
                        await Swal.fire({
                            title: "Cannot Clock Out",
                            text:
                                errorData?.message ||
                                "No open clock-in record found. Please clock in first.",
                            icon: "warning",
                            confirmButtonText: "OK",
                        });
                    } else {
                        await Swal.fire({
                            title: "Error!",
                            text:
                                errorData?.message ||
                                "Failed to clock out. Please try again.",
                            icon: "error",
                            confirmButtonText: "OK",
                        });
                    }
                }
            }
        },

        formatTime(datetime) {
            if (!datetime) return "";
            const date = new Date(datetime);
            return date.toLocaleTimeString("en-US", {
                hour: "2-digit",
                minute: "2-digit",
                hour12: true,
            });
        },

        formatDate(datetime) {
            if (!datetime) return "";
            const date = new Date(datetime);
            return date.toLocaleDateString("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric",
            });
        },

        calculateHours(timeIn, timeOut) {
            if (!timeIn || !timeOut) return "Not calculated";

            const start = new Date(timeIn);
            const end = new Date(timeOut);
            const diff = end - start;

            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

            return `${hours}h ${minutes}m`;
        },

        openNotesModal(record) {
            this.selectedRecordId = record.id;
            this.currentNotes = record.notes || "";
            this.notesDialogVisible = true;
        },

        async saveNotes() {
            this.savingNotes = true;
            try {
                await axios.post(`/update-notes/${this.selectedRecordId}`, {
                    notes: this.currentNotes,
                });

                await Swal.fire({
                    title: "Success!",
                    text: "Notes updated successfully",
                    icon: "success",
                    confirmButtonText: "OK",
                });

                this.notesDialogVisible = false;
                this.loadAttendanceData();
            } catch (error) {
                await Swal.fire({
                    title: "Error!",
                    text: "Failed to update notes",
                    icon: "error",
                    confirmButtonText: "OK",
                });
            } finally {
                this.savingNotes = false;
            }
        },

        switchAccountTab(tab) {
            this.activeAccountTab = tab;

            if (tab === "details" && !this.accountDetailsLoaded) {
                this.loadAccountDetails();
            }

            if (tab === "timezone" && !this.timezonesLoaded) {
                this.loadTimezones();
            }
        },

        async loadAccountDetails() {
            try {
                const response = await axios.get("/account/details");
                const data = response.data;

                this.accountDetails = {
                    username: data.user.username || "",
                    usertype: data.user.office_role || "",
                    accounttype: data.user.accounttype || "",
                    full_name: data.profile.full_name || "",
                    work_email: data.profile.work_email || "",
                    contact_phone: data.profile.contact_phone || "",
                    birthdate: data.profile.birthdate
                        ? new Date(data.profile.birthdate)
                        : null,
                    address: data.profile.address || "",
                    ice_name: data.profile.ice_name || "",
                    ice_relationship: data.profile.ice_relationship || "",
                    ice_phone: data.profile.ice_phone || "",
                };

                this.accountDetailsLoaded = true;
            } catch (error) {
                console.error("Error loading account details:", error);
                Swal.fire({
                    title: "Error!",
                    text: "Failed to load account details",
                    icon: "error",
                    confirmButtonText: "OK",
                });
            }
        },

        async loadTimezones() {
            try {
                // You'll need to create an endpoint that returns timezones
                // For now, using a hardcoded list
                this.timezones = [
                    {
                        tz: "America/Los_Angeles",
                        label: "(UTC -08:00) America/Los_Angeles",
                    },
                    {
                        tz: "America/New_York",
                        label: "(UTC -05:00) America/New_York",
                    },
                    { tz: "UTC", label: "(UTC +00:00) UTC" },
                    // Add more timezones as needed
                ];

                // Load current timezone setting
                const response = await axios.get("/api/timezone/current");
                this.timezoneForm.usertimezone = response.data.usertimezone;
                this.timezoneForm.auto_sync = response.data.auto_sync;

                this.timezonesLoaded = true;
            } catch (error) {
                console.error("Error loading timezones:", error);
            }
        },

        async saveAccountDetails() {
            this.savingDetails = true;

            try {
                const formData = {
                    full_name: this.accountDetails.full_name,
                    work_email: this.accountDetails.work_email,
                    contact_phone: this.accountDetails.contact_phone,
                    birthdate: this.accountDetails.birthdate
                        ? this.accountDetails.birthdate instanceof Date
                            ? this.accountDetails.birthdate
                                  .toISOString()
                                  .split("T")[0]
                            : this.accountDetails.birthdate
                        : null,
                    address: this.accountDetails.address,
                    ice_name: this.accountDetails.ice_name,
                    ice_relationship: this.accountDetails.ice_relationship,
                    ice_phone: this.accountDetails.ice_phone,
                };

                const response = await axios.post(
                    "/account/update-details",
                    formData
                );

                if (response.data.ok) {
                    await Swal.fire({
                        title: "Success!",
                        text:
                            response.data.message ||
                            "Account details updated successfully",
                        icon: "success",
                        confirmButtonText: "OK",
                    });
                }
            } catch (error) {
                console.error("Error saving account details:", error);

                if (error.response?.status === 422) {
                    const errors = error.response.data.errors;
                    const errorMessages = Object.values(errors)
                        .flat()
                        .join("\n");

                    await Swal.fire({
                        title: "Validation Error",
                        html: errorMessages.replace(/\n/g, "<br>"),
                        icon: "error",
                        confirmButtonText: "OK",
                    });
                } else {
                    await Swal.fire({
                        title: "Error!",
                        text:
                            error.response?.data?.message ||
                            "Failed to save account details",
                        icon: "error",
                        confirmButtonText: "OK",
                    });
                }
            } finally {
                this.savingDetails = false;
            }
        },

        async changePassword() {
            if (
                this.passwordForm.password !==
                this.passwordForm.password_confirmation
            ) {
                Swal.fire({
                    title: "Password Mismatch",
                    text: "Passwords do not match",
                    icon: "warning",
                    confirmButtonText: "OK",
                });
                return;
            }

            this.changingPassword = true;
            try {
                const response = await axios.post(
                    "/update-password",
                    this.passwordForm
                );

                await Swal.fire({
                    title: "Success!",
                    text: "Password changed successfully",
                    icon: "success",
                    confirmButtonText: "OK",
                });

                // Reset form
                this.passwordForm = {
                    password: "",
                    password_confirmation: "",
                };
            } catch (error) {
                console.error("Error changing password:", error);
                Swal.fire({
                    title: "Error!",
                    text:
                        error.response?.data?.message ||
                        "Failed to change password",
                    icon: "error",
                    confirmButtonText: "OK",
                });
            } finally {
                this.changingPassword = false;
            }
        },

        async updateTimezone() {
            this.updatingTimezone = true;
            try {
                const response = await axios.post(
                    "/update-timezone",
                    this.timezoneForm
                );

                if (response.data.success) {
                    await Swal.fire({
                        title: "Success!",
                        text: response.data.message,
                        icon: "success",
                        confirmButtonText: "OK",
                    });
                }
            } catch (error) {
                console.error("Error updating timezone:", error);
                Swal.fire({
                    title: "Error!",
                    text: "Failed to update timezone",
                    icon: "error",
                    confirmButtonText: "OK",
                });
            } finally {
                this.updatingTimezone = false;
            }
        },

        async filterAttendanceRecords() {
            this.loadingRecords = true;

            try {
                const formData = {
                    start_date: this.recordFilter.startDate
                        ? this.recordFilter.startDate instanceof Date
                            ? this.recordFilter.startDate
                                  .toISOString()
                                  .split("T")[0]
                            : this.recordFilter.startDate
                        : null,
                    end_date: this.recordFilter.endDate
                        ? this.recordFilter.endDate instanceof Date
                            ? this.recordFilter.endDate
                                  .toISOString()
                                  .split("T")[0]
                            : this.recordFilter.endDate
                        : null,
                };

                const response = await axios.post(
                    "/attendance/filter",
                    formData
                );

                this.filteredRecords = response.data.employeeClocks || [];
                this.calculateTotalHours();
            } catch (error) {
                console.error("Error filtering records:", error);
                Swal.fire({
                    title: "Error!",
                    text: "Failed to filter attendance records",
                    icon: "error",
                    confirmButtonText: "OK",
                });
            } finally {
                this.loadingRecords = false;
            }
        },

        clearFilter() {
            this.recordFilter.startDate = null;
            this.recordFilter.endDate = null;
            this.filterAttendanceRecords();
        },

        calculateTotalHours() {
            let totalMinutes = 0;

            this.filteredRecords.forEach((record) => {
                if (record.time_in) {
                    const timeIn = new Date(record.time_in);
                    const timeOut = record.time_out
                        ? new Date(record.time_out)
                        : new Date();

                    const diffInMinutes = Math.round(
                        (timeOut - timeIn) / 60000
                    );
                    totalMinutes += diffInMinutes;
                }
            });

            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;

            this.totalHours = `${hours} hrs ${minutes} mins`;
        },

        formatRecordDate(datetime) {
            if (!datetime) return "";
            const date = new Date(datetime);
            return date.toLocaleDateString("en-US", {
                year: "numeric",
                month: "short",
                day: "numeric",
            });
        },

        calculateRecordHours(timeIn, timeOut) {
            if (!timeIn) return "N/A";

            const start = new Date(timeIn);
            const end = timeOut ? new Date(timeOut) : new Date();
            const diff = end - start;

            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

            return `${hours}h ${minutes}m`;
        },

        async loadUserPrivileges() {
            this.loadingPrivileges = true;

            try {
                const response = await axios.get("/myprivileges");

                if (response.data.status === "success" && response.data.data) {
                    const userPrivileges = response.data.data;

                    // Update privilegesList with user's actual privileges
                    this.privilegesList.forEach((privilege) => {
                        if (userPrivileges.hasOwnProperty(privilege.key)) {
                            // Convert to boolean (1 or 0 from database)
                            privilege.enabled =
                                userPrivileges[privilege.key] === 1 ||
                                userPrivileges[privilege.key] === "1";
                        }
                    });

                    // Mark as loaded
                    this.privilegesLoaded = true;
                }
            } catch (error) {
                console.error("Error loading privileges:", error);
                this.$toast.add({
                    severity: "error",
                    summary: "Error",
                    detail: "Failed to load privileges",
                    life: 3000,
                });
            } finally {
                this.loadingPrivileges = false;
            }
        },

        // === Date Helpers ===
        formatISO(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, "0");
            const d = String(date.getDate()).padStart(2, "0");
            return `${y}-${m}-${d}`;
        },

        isToday(day) {
            const date = new Date(this.viewYear, this.viewMonth, day);
            const today = new Date();
            return this.formatISO(date) === this.formatISO(today);
        },

        isSelected(day) {
            if (!this.selectedDate) return false;
            const date = new Date(this.viewYear, this.viewMonth, day);
            return this.formatISO(date) === this.formatISO(this.selectedDate);
        },

        hasSchedule(day) {
            const date = new Date(this.viewYear, this.viewMonth, day);
            const iso = this.formatISO(date);
            const info = this.scheduleData?.byDate?.[iso];
            return (
                info && (info.entries?.length > 0 || info.holidays?.length > 0)
            );
        },

        getStatus(day) {
            const date = new Date(this.viewYear, this.viewMonth, day);
            const iso = this.formatISO(date);
            return this.scheduleData?.byDate?.[iso]?.status || null;
        },

        getMetaText(day) {
            const date = new Date(this.viewYear, this.viewMonth, day);
            const iso = this.formatISO(date);
            const info = this.scheduleData?.byDate?.[iso];

            if (!info) return "—";

            let text = "";

            if (info.entries?.length) {
                if (info.entries.length === 1) {
                    text = `${info.entries[0].start}–${info.entries[0].end}`;
                } else {
                    text = `${info.entries.length} shifts`;
                }
            }

            if (info.holidays?.length) {
                const holidate = date.toLocaleDateString("en-US", {
                    month: "short",
                    day: "numeric",
                });
                text = text ? `${text} • Holiday` : `Holiday: ${holidate}`;
            }

            return text || "—";
        },

        // === Navigation ===
        async previousMonth() {
            this.viewMonth--;
            if (this.viewMonth < 0) {
                this.viewMonth = 11;
                this.viewYear--;
            }
            await this.loadScheduleData();
        },

        async nextMonth() {
            this.viewMonth++;
            if (this.viewMonth > 11) {
                this.viewMonth = 0;
                this.viewYear++;
            }
            await this.loadScheduleData();
        },

        selectDate(day) {
            this.selectedDate = new Date(this.viewYear, this.viewMonth, day);
        },

        clearSelectedDay() {
            this.selectedDate = null;
        },

        // === API ===
        async loadScheduleData() {
            this.loadingSchedule = true;

            try {
                const y = this.viewYear;
                const m = String(this.viewMonth + 1).padStart(2, "0");
                const ym = `${y}-${m}`;

                const response = await axios.get(`/schedule/month?ym=${ym}`);
                this.scheduleData = response.data || { byDate: {} };
            } catch (error) {
                console.error("Error loading schedule:", error);
                this.scheduleData = { byDate: {} };
                this.$toast.add({
                    severity: "error",
                    summary: "Error",
                    detail: "Failed to load schedule",
                    life: 3000,
                });
            } finally {
                this.loadingSchedule = false;
            }
        },
    },
};
</script>

<style>
/* Global styles for mobile fullscreen - NOT scoped */
@media (max-width: 768px) {
    .p-dialog.p-component.profile-modal {
        width: 100vw !important;
        height: 100vh !important;
        top: 0px !important;
        left: 0px !important;
        max-height: 100% !important;
        border-radius: 0 !important;
        margin: 0 !important;
        transform: none !important;
    }

    .profile-modal .p-dialog-header {
        border-radius: 0 !important;
    }

    .profile-modal .p-dialog-content {
        border-radius: 0 !important;
    }
}
</style>

<style scoped>
.mr-2 {
    margin-right: 0.5rem;
}

/* Remove all text decoration from nav links */
.profile-tabs :deep(a),
.profile-tabs :deep(a:hover),
.profile-tabs :deep(a:focus),
.profile-tabs :deep(a:active) {
    text-decoration: none !important;
}

/* ==================== PROFILE MODAL & TABS ==================== */

/* Profile Modal with Fixed Headers */
.profile-modal :deep(.p-dialog) {
    display: flex;
    flex-direction: column;
    max-height: 80vh;
}

.profile-modal :deep(.p-dialog-content) {
    padding: 0 !important;
    overflow: hidden !important;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.profile-tabs {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

/* Keep tabs fixed at top */
.profile-tabs :deep(.p-tabview-nav-container) {
    flex-shrink: 0;
    position: sticky;
    top: 0;
    z-index: 10;
    background: white;
}

.profile-tabs :deep(.p-tabview-nav) {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 0 1rem;
    margin: 0;
}

.profile-tabs :deep(.p-tabview-tab-header) {
    white-space: nowrap;
    font-size: 0.875rem;
}

/* Fix for tab headers - remove bottom border and center content */
.profile-tabs :deep(.p-tabview-nav-link) {
    border-bottom: none !important;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem 1.5rem;
}

.profile-tabs :deep(.p-tabview-nav-link:hover) {
    border-bottom: none !important;
}

.profile-tabs :deep(.p-tabview-header) {
    border-bottom: none !important;
}

.profile-tabs :deep(.p-tabview-header .p-tabview-nav-link) {
    border-bottom: none !important;
}

.profile-tabs :deep(.p-tabview-header .p-tabview-nav-link:hover) {
    border-bottom: none !important;
}

/* Active tab indicator */
.profile-tabs :deep(.p-tabview-header.p-highlight .p-tabview-nav-link) {
    border-bottom: 3px solid #007bff !important;
    color: #007bff;
}

.profile-tabs :deep(.p-tabview-header.p-highlight .p-tabview-nav-link:hover) {
    border-bottom: 3px solid #007bff !important;
}

.profile-tabs :deep(.p-tabview-panels) {
    flex: 1;
    overflow: hidden;
    padding: 0 !important;
}

.profile-tabs :deep(.p-tabview-panel) {
    height: 100%;
    overflow: hidden;
}

/* Make only content scrollable */
.scrollable-content {
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 1.5rem;
}

/* Custom scrollbar styling */
.scrollable-content::-webkit-scrollbar {
    width: 8px;
}

.scrollable-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.scrollable-content::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

.scrollable-content::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* ==================== ATTENDANCE TAB ==================== */

.attendance-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
}

/* Combined Info Card */
.combined-info-card {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
    margin: 0 auto 1.5rem auto;
}

.combined-info-card :deep(.p-card-content) {
    max-width: 700px;
    margin: 0 auto;
}

/* Time Display Section */
.time-display {
    text-align: center;
    color: white;
    margin-bottom: 1rem;
}

.current-time {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.current-day {
    font-size: 1rem;
    opacity: 0.95;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

/* Divider in combined card */
.combined-info-card :deep(.p-divider) {
    margin: 1rem 0;
}

.combined-info-card :deep(.p-divider.p-divider-horizontal:before) {
    border-top: 1px solid rgba(255, 255, 255, 0.3);
}

.combined-info-card :deep(.p-divider.p-divider-vertical:before) {
    border-left: 1px solid rgba(255, 255, 255, 0.3);
}

/* Hours Summary Section */
.summary-grid {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    max-width: 280px;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.summary-icon {
    font-size: 2rem;
    color: #007bff;
    flex-shrink: 0;
}

.summary-label {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.2rem;
    font-weight: 500;
}

.summary-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #2c3e50;
}

/* Clock Buttons */
.button-group {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    max-width: 500px;
    margin: 0 auto;
}

.clock-button {
    min-width: 180px;
}

/* Attendance Table */
.attendance-table {
    margin-top: 1rem;
}

.time-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.time-value {
    font-weight: 600;
    color: #2c3e50;
}

.date-value {
    font-size: 0.85rem;
    color: #6c757d;
}

.computed-hours {
    font-weight: 600;
    color: #28a745;
    font-size: 1.1rem;
}

/* ==================== NOTES DIALOG ==================== */

.notes-dialog-content {
    padding: 0.5rem 0;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.notes-label {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--text-color);
    margin-bottom: 0.25rem;
}

.notes-hint {
    color: var(--text-color-secondary);
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding-top: 0.5rem;
}

.w-full {
    width: 100%;
}

/* Improve textarea appearance */
:deep(.p-inputtextarea) {
    font-family: inherit;
    font-size: 0.95rem;
    line-height: 1.5;
}

:deep(.p-inputtextarea:focus) {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(var(--primary-color-rgb), 0.2);
}

.notes-text {
    color: #6c757d;
    font-style: italic;
}

/* ==================== ACCOUNT TAB ==================== */

.account-content {
    width: 100%;
}

.account-tabs-nav {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e9ecef;
    flex-wrap: wrap;
}

.account-tab-content {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Account Details Wrapper */
.account-details-wrapper {
    max-width: 1000px;
    margin: 0 auto;
}

/* Info Sections */
.info-section {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.info-section:hover {
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
    border-color: #007bff;
}

/* Section Headers */
.section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e9ecef;
}

.section-header i {
    font-size: 1.5rem;
    color: #007bff;
}

.section-header h5 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
}

.emergency-header i {
    color: #dc3545;
}

/* Read-Only Section */
.readonly-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.info-item label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: #6c757d;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: white;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.info-value i {
    color: #007bff;
    font-size: 1.1rem;
}

.info-value span {
    font-size: 1rem;
    font-weight: 500;
    color: #2c3e50;
}

/* Form Grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    font-size: 0.95rem;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.form-group label i {
    color: #007bff;
    font-size: 1rem;
}

/* Input Styling */
:deep(.p-inputtext),
:deep(.p-calendar),
:deep(.p-inputtextarea) {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

:deep(.p-inputtext:focus),
:deep(.p-calendar:focus-within),
:deep(.p-inputtextarea:focus) {
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

:deep(.p-inputtext::placeholder),
:deep(.p-inputtextarea::placeholder) {
    color: #adb5bd;
}

/* Modern Action Buttons */
.form-actions-modern {
    display: flex;
    gap: 1rem;
    justify-content: center;
    padding-top: 2rem;
    margin-top: 2rem;
    border-top: 2px solid #e9ecef;
}

.save-button {
    min-width: 180px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    font-size: 1rem;
    border-radius: 8px;
}

/* Primary Color Override */
:deep(.p-button:not(.p-button-outlined):not(.p-button-text)) {
    background: #007bff;
    border-color: #007bff;
}

:deep(.p-button:not(.p-button-outlined):not(.p-button-text):hover) {
    background: #0056b3;
    border-color: #0056b3;
}

:deep(.p-button:not(.p-button-outlined):not(.p-button-text):focus) {
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.5);
}

/* Calendar Icon Color */
:deep(.p-datepicker-trigger) {
    color: #007bff;
}

/* Emergency Section Styling */
.emergency-section {
    border-left: 4px solid #dc3545;
}

.emergency-section .section-header {
    border-bottom-color: #dc3545;
}

/* ==================== OTHER ACCOUNT TABS (Password & Timezone) ==================== */

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 1rem;
}

.p-fluid {
    width: 100%;
}

.field label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 0.95rem;
    color: #2c3e50;
}

.field-checkbox {
    display: flex;
    align-items: center;
    margin-top: 1rem;
}

.field-checkbox label {
    margin-bottom: 0;
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

.ml-2 {
    margin-left: 0.5rem;
}

.mt-2 {
    margin-top: 0.5rem;
}

.mt-3 {
    margin-top: 1rem;
}

.mt-4 {
    margin-top: 1.5rem;
}

.mb-3 {
    margin-bottom: 1rem;
}

.mb-4 {
    margin-bottom: 1.5rem;
}

.pl-2 {
    padding-left: 0.5rem;
}

:deep(.account-tab-content .p-card) {
    box-shadow: none;
    border: 1px solid #e9ecef;
    border-radius: 12px;
}

:deep(.account-tab-content .p-card .p-card-body) {
    padding: 1.5rem;
}

:deep(.account-tab-content .p-card .p-card-content) {
    padding: 0;
}

:deep(.p-password input) {
    width: 100%;
}

:deep(.p-password-panel) {
    padding: 1rem;
    margin-top: 0.5rem;
}

:deep(.p-inputtext:disabled) {
    background-color: #f8f9fa;
    opacity: 0.8;
}

/* ==================== TIME RECORD TAB ==================== */

.time-record-content {
    max-width: 1200px;
    margin: 0 auto;
}

.filter-card {
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.filter-section {
    padding: 0.5rem;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 1rem;
}

.section-title i {
    color: #007bff;
}

.filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 1rem;
    align-items: end;
}

.filter-field {
    display: flex;
    flex-direction: column;
}

.filter-field label {
    font-weight: 600;
    font-size: 0.95rem;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
}

.total-hours-card {
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
}

.total-hours-card :deep(.p-card-content) {
    padding: 1.5rem;
}

.total-hours-display {
    display: flex;
    align-items: center;
    gap: 1rem;
    color: white;
}

.total-hours-display i {
    font-size: 2.5rem;
}

.total-hours-display > div {
    display: flex;
    flex-direction: column;
}

.total-label {
    font-size: 0.9rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.total-value {
    font-size: 2rem;
    font-weight: 700;
}

.time-record-table {
    margin-top: 1rem;
}

.record-date,
.record-time {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.record-date i,
.record-time i {
    color: #007bff;
}

.computed-hours-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #007bff;
}

.computed-hours-badge i {
    font-size: 1.1rem;
}

/* ==================== MY PRIVILEGES TAB ==================== */

.privileges-content {
    max-width: 1200px;
    margin: 0 auto;
}

.privileges-header-card {
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
}

.privileges-header-card :deep(.p-card-content) {
    padding: 1.5rem;
}

.privileges-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    color: white;
}

.header-icon {
    font-size: 3rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.header-title {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.header-subtitle {
    margin: 0.5rem 0 0 0;
    font-size: 0.95rem;
    opacity: 0.9;
}

.privileges-card {
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.loading-state,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    text-align: center;
    color: #6c757d;
}

.loading-state p,
.empty-state p {
    margin-top: 1rem;
    font-size: 1rem;
}

.empty-state i {
    font-size: 3rem;
    color: #94a3b8;
}

.privileges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
    padding: 0.5rem;
}

.privilege-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.privilege-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.privilege-enabled {
    border-color: #28a745;
    background: #e8f5e9;
}

.privilege-enabled:hover {
    border-color: #1e7e34;
}

.privilege-checkbox {
    flex-shrink: 0;
}

.privilege-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    font-size: 0.95rem;
    color: #2c3e50;
    cursor: default;
    user-select: none;
}

.privilege-icon {
    color: #007bff;
    font-size: 1.1rem;
}

.privilege-enabled .privilege-icon {
    color: #28a745;
}

.legend-card {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.legend-content {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #6c757d;
}

.legend-item i {
    font-size: 1.2rem;
}

/* Disabled checkbox styling */
:deep(.privilege-checkbox.p-disabled) {
    opacity: 1;
}

:deep(.privilege-enabled .p-checkbox .p-checkbox-box) {
    border-color: #28a745;
    background: #28a745;
}

:deep(.privilege-item:not(.privilege-enabled) .p-checkbox .p-checkbox-box) {
    border-color: #dc3545;
    background: #fff;
}

/* ==================== MY SCHEDULE TAB ==================== */

.schedule-content {
    max-width: 100%;
    margin: 0 auto;
}

.schedule-header-card {
    margin-bottom: 1rem;
}

.schedule-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.schedule-nav {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.month-display {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    font-weight: 600;
    color: #2c3e50;
    min-width: 200px;
    justify-content: center;
}

.month-display i {
    color: #007bff;
}

.schedule-legend {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #6c757d;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

.status-dot.present {
    background-color: #28a745;
}

.status-dot.late {
    background-color: #ffc107;
}

.status-dot.absent {
    background-color: #dc3545;
}

.swatch {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    display: inline-block;
    flex-shrink: 0;
}

.swatch-today {
    background-color: #007bff;
    opacity: 0.3;
}

/* Calendar */
.calendar-card {
    margin-bottom: 1rem;
}

.calendar-wrapper {
    width: 100%;
    overflow-x: auto;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    min-width: 100%;
}

.calendar-dow {
    text-align: center;
    font-weight: 600;
    font-size: 0.8rem;
    color: #6c757d;
    padding: 0.5rem 0.25rem;
}

.calendar-cell {
    min-height: 70px;
    padding: 0.5rem;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.calendar-cell:not(.blank):hover {
    border-color: #007bff;
    box-shadow: 0 2px 4px rgba(0, 123, 255, 0.15);
}

.calendar-cell.blank {
    background: transparent;
    cursor: default;
    border: none;
}

.calendar-cell.is-today {
    background: rgba(0, 123, 255, 0.08);
    border-color: #007bff;
}

.calendar-cell.is-selected {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.calendar-cell.is-selected .cell-meta {
    color: rgba(255, 255, 255, 0.95);
}

.calendar-cell.has-schedule {
    border-color: #28a745;
}

.cell-date {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.25rem;
    font-weight: 600;
    font-size: 0.95rem;
}

.cell-meta {
    font-size: 0.7rem;
    color: #6c757d;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.2;
}

/* Day Details */
.day-details-card {
    margin-top: 1rem;
}

.day-details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.day-details-header h6 {
    margin: 0;
    color: #2c3e50;
    font-weight: 600;
}

.detail-section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.75rem;
}

.detail-section-title i {
    color: #007bff;
}

.holiday-list {
    list-style: none;
    padding: 0 0 0 1.5rem;
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.holiday-list li {
    margin-bottom: 0.5rem;
}

.empty-schedule {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2rem;
    color: #6c757d;
}

.empty-schedule i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.schedule-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.schedule-entry {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #007bff;
}

.entry-info {
    flex: 1;
}

.entry-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.entry-notes {
    font-size: 0.85rem;
    color: #6c757d;
}

.entry-time {
    font-weight: 600;
    color: #007bff;
    white-space: nowrap;
    margin-left: 1rem;
}

.loading-card {
    margin-bottom: 1rem;
}

.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 3rem;
    color: #6c757d;
}

.loading-state p {
    margin-top: 1rem;
}

/* ==================== RESPONSIVE DESIGN ==================== */

/* Tablet and Mobile */
@media (max-width: 768px) {
    .profile-modal :deep(.p-dialog) {
        max-height: 90vh;
    }

    .scrollable-content {
        padding: 1rem;
    }

    .attendance-container {
        padding: 0.5rem;
    }

    .combined-info-card {
        max-width: 100%;
    }

    .combined-info-card :deep(.p-card-content) {
        padding: 0;
    }

    .current-time {
        font-size: 1.5rem;
    }

    .current-day {
        font-size: 1.1rem;
    }

    .summary-grid {
        flex-direction: column;
        gap: 0.5rem;
    }

    .summary-grid :deep(.p-divider.p-divider-vertical) {
        display: none;
    }

    .summary-item {
        width: 100%;
        max-width: 100%;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.95);
    }

    .summary-icon {
        font-size: 2rem;
    }

    .summary-value {
        font-size: 1.25rem;
    }

    .button-group {
        flex-direction: column;
        align-items: stretch;
        max-width: 100%;
    }

    .clock-button {
        width: 100%;
        min-width: auto;
    }

    /* Stacked Table Styling - LEFT ALIGNED */
    .attendance-table {
        font-size: 0.9rem;
    }

    .attendance-table :deep(.p-datatable-wrapper) {
        overflow-x: visible;
    }

    .attendance-table :deep(.p-datatable-tbody > tr > td) {
        text-align: left;
        display: block;
        border: none;
        padding: 0.75rem 1rem;
    }

    .attendance-table :deep(.p-datatable-tbody > tr > td:before) {
        content: attr(data-label);
        font-weight: 600;
        color: #6c757d;
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .attendance-table :deep(.p-datatable-tbody > tr) {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 1rem;
        display: block;
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .attendance-table :deep(.p-datatable-tbody > tr > td:first-child) {
        background: #f8f9fa;
        border-radius: 6px 6px 0 0;
        font-weight: 600;
    }

    .attendance-table :deep(.p-datatable-tbody > tr > td:nth-child(3)) {
        background: #e8f5e9;
    }

    .attendance-table :deep(.p-datatable-tbody > tr > td:last-child) {
        border-radius: 0 0 6px 6px;
        text-align: center;
    }

    .attendance-table :deep(.p-datatable-thead) {
        display: none;
    }

    /* Keep time cells left aligned */
    .time-cell {
        align-items: flex-start;
    }

    /* Account Tab */
    .info-grid,
    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full-width {
        grid-column: 1;
    }

    .info-section {
        padding: 1rem;
    }

    .section-header {
        font-size: 1rem;
    }

    .form-actions-modern {
        flex-direction: column;
    }

    .save-button {
        width: 100%;
    }

    .account-tabs-nav {
        gap: 0.5rem;
    }

    .account-tabs-nav .p-button {
        flex: 1;
        min-width: auto;
    }

    :deep(.account-tab-content .p-card .p-card-body) {
        padding: 1rem;
    }

    /* Main Tabs */
    .profile-tabs :deep(.p-tabview-nav) {
        padding: 0 0.5rem;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
    }

    .profile-tabs :deep(.p-tabview-nav-link) {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }

    /* Hide scrollbar but keep functionality */
    .profile-tabs :deep(.p-tabview-nav)::-webkit-scrollbar {
        height: 2px;
    }

    .profile-tabs :deep(.p-tabview-nav)::-webkit-scrollbar-thumb {
        background: #007bff;
        border-radius: 2px;
    }

    .filter-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .filter-actions {
        width: 100%;
    }

    .filter-actions button {
        flex: 1;
    }

    .total-value {
        font-size: 1.5rem;
    }

    .attendance-container {
        padding: 0.5rem;
    }

    .current-time {
        font-size: 1.5rem;
    }

    .current-day {
        font-size: 1rem;
    }

    .button-group {
        flex-direction: column;
        align-items: stretch;
    }

    .clock-button {
        width: 100%;
        min-width: auto;
    }

    .summary-grid {
        flex-direction: column;
        gap: 0.5rem;
    }

    .summary-grid :deep(.p-divider) {
        display: none;
    }

    .summary-item {
        width: 100%;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .summary-icon {
        font-size: 2rem;
    }

    .summary-value {
        font-size: 1.25rem;
    }

    /* Stacked Table Styling - LEFT ALIGNED */
    .attendance-table {
        font-size: 0.9rem;
    }

    .attendance-table :deep(.p-datatable-wrapper) {
        overflow-x: visible;
    }

    .attendance-table :deep(.p-datatable-tbody > tr > td) {
        text-align: left;
        display: block;
        border: none;
        padding: 0.75rem 1rem;
    }

    .attendance-table :deep(.p-datatable-tbody > tr > td:before) {
        content: attr(data-label);
        font-weight: 600;
        color: #6c757d;
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .attendance-table :deep(.p-datatable-tbody > tr) {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 1rem;
        display: block;
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .attendance-table :deep(.p-datatable-tbody > tr > td:first-child) {
        background: #f8f9fa;
        border-radius: 6px 6px 0 0;
        font-weight: 600;
    }

    .attendance-table :deep(.p-datatable-tbody > tr > td:nth-child(3)) {
        background: #e8f5e9;
    }

    .attendance-table :deep(.p-datatable-tbody > tr > td:last-child) {
        border-radius: 0 0 6px 6px;
        text-align: center;
    }

    .attendance-table :deep(.p-datatable-thead) {
        display: none;
    }

    /* Keep time cells left aligned */
    .time-cell {
        align-items: flex-start;
    }

    .filter-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .filter-actions {
        width: 100%;
    }

    .filter-actions button {
        flex: 1;
    }

    .total-value {
        font-size: 1.5rem;
    }

    .time-record-content :deep(.p-card-content) {
        padding: 0;
    }

    /* Stacked Table Styling - LEFT ALIGNED */
    .time-record-table :deep(.p-datatable-wrapper) {
        overflow-x: visible;
    }

    .time-record-table :deep(.p-datatable-tbody > tr > td) {
        text-align: left;
        display: block;
        border: none;
        padding: 0.75rem 1rem;
    }

    .time-record-table :deep(.p-datatable-tbody > tr > td:before) {
        content: attr(data-label);
        font-weight: 600;
        color: #6c757d;
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .time-record-table :deep(.p-datatable-tbody > tr) {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 1rem;
        display: block;
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .time-record-table :deep(.p-datatable-tbody > tr > td:first-child) {
        background: #f8f9fa;
        border-radius: 6px 6px 0 0;
        font-weight: 600;
    }

    .time-record-table :deep(.p-datatable-tbody > tr > td:last-child) {
        border-radius: 0 0 6px 6px;
        background: #f0f8ff;
    }

    .time-record-table :deep(.p-datatable-thead) {
        display: none;
    }

    /* Left align all content in stacked mode */
    .record-date,
    .record-time,
    .computed-hours-badge {
        justify-content: flex-start;
    }

    .privileges-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
        padding: 0.25rem;
    }

    .privilege-item {
        padding: 0.75rem 0.5rem;
        gap: 0.5rem;
    }

    .privilege-label {
        font-size: 0.85rem;
        gap: 0.4rem;
    }

    .privilege-icon {
        font-size: 1rem;
    }

    .privileges-header {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .header-icon {
        font-size: 2.5rem;
    }

    .header-title {
        font-size: 1.25rem;
    }

    .header-subtitle {
        font-size: 0.85rem;
    }

    .legend-content {
        flex-direction: column;
        gap: 1rem;
    }

    .schedule-header {
        flex-direction: column;
        align-items: stretch;
    }

    .schedule-nav {
        justify-content: center;
    }

    .calendar-cell {
        min-height: 60px;
        padding: 0.35rem;
    }

    .cell-date {
        font-size: 0.85rem;
    }

    .cell-meta {
        font-size: 0.65rem;
    }
}

/* Extra Small Mobile */
@media (max-width: 576px) {
    .profile-tabs :deep(.p-tabview-nav-link) {
        padding: 0.75rem 0.75rem;
        font-size: 0.85rem;
    }

    /* Only show icon on very small screens */
    .profile-tabs :deep(.p-tabview-nav-link span:not(.pi)) {
        display: none;
    }

    .profile-tabs :deep(.p-tabview-nav-link .pi) {
        font-size: 1.2rem;
        margin: 0;
    }

    .combined-info-card :deep(.p-card-content) {
        padding: 0;
    }

    .current-time {
        font-size: 1.5rem;
    }

    .current-day {
        font-size: 1rem;
    }

    .summary-item {
        padding: 0.85rem;
        gap: 0.75rem;
    }

    .summary-icon {
        font-size: 1.75rem;
    }

    .summary-label {
        font-size: 0.85rem;
    }

    .summary-value {
        font-size: 1.15rem;
    }

    /* More compact stacked table */
    .attendance-table :deep(.p-datatable-tbody > tr > td) {
        padding: 0.6rem 0.75rem;
        font-size: 0.85rem;
    }

    .attendance-table :deep(.p-datatable-tbody > tr > td:before) {
        font-size: 0.8rem;
        margin-bottom: 0.2rem;
    }

    .attendance-table :deep(.p-datatable-tbody > tr) {
        margin-bottom: 0.75rem;
    }

    .time-value {
        font-size: 0.9rem;
    }

    .date-value {
        font-size: 0.8rem;
    }

    .computed-hours {
        font-size: 1rem;
    }

    .total-hours-display {
        flex-direction: column;
        text-align: center;
    }

    .total-hours-display i {
        font-size: 2rem;
    }

    .total-value {
        font-size: 1.25rem;
    }

    .section-title {
        font-size: 0.95rem;
    }

    .filter-field label {
        font-size: 0.9rem;
    }

    /* More compact stacked table */
    .time-record-table :deep(.p-datatable-tbody > tr > td) {
        padding: 0.6rem 0.75rem;
        font-size: 0.9rem;
    }

    .time-record-table :deep(.p-datatable-tbody > tr > td:before) {
        font-size: 0.8rem;
        margin-bottom: 0.2rem;
    }

    .time-record-table :deep(.p-datatable-tbody > tr) {
        margin-bottom: 0.75rem;
    }

    .privileges-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .privilege-item {
        padding: 0.6rem 0.5rem;
        gap: 0.5rem;
        border-radius: 6px;
    }

    .privilege-label {
        font-size: 0.8rem;
    }

    .privilege-icon {
        font-size: 0.9rem;
    }

    .privilege-checkbox :deep(.p-checkbox-box) {
        width: 18px;
        height: 18px;
    }

    .privileges-header-card :deep(.p-card) {
        margin-bottom: 1rem;
    }

    .privileges-header-card :deep(.p-card-content) {
        padding: 0;
    }

    .header-icon {
        font-size: 2rem;
    }

    .header-title {
        font-size: 1.1rem;
    }

    .header-subtitle {
        font-size: 0.8rem;
    }

    .legend-item {
        font-size: 0.85rem;
    }

    .legend-item i {
        font-size: 1rem;
    }

    .calendar-grid {
        gap: 3px;
    }

    .calendar-dow {
        font-size: 0.7rem;
        padding: 0.25rem;
    }

    .calendar-cell {
        min-height: 50px;
        padding: 0.25rem;
    }

    .cell-date {
        font-size: 0.8rem;
    }

    .cell-meta {
        font-size: 0.6rem;
        -webkit-line-clamp: 1;
    }

    .schedule-entry {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .entry-time {
        margin-left: 0;
    }
}
</style>
