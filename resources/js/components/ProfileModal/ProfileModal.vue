<template>
    <Dialog
        :visible="visible"
        @update:visible="$emit('update:visible', $event)"
        modal
        header="Profile"
        :style="{ width: '90%', height: '80vh' }"
        class="profile-modal"
    >
        <TabView class="profile-tabs" @tab-change="onTabChange">
            <!-- Attendance Tab -->
            <TabPanel>
                <template #header>
                    <i class="pi pi-calendar mr-2"></i>
                    <span>Attendance</span>
                </template>

                <div class="scrollable-content">
                    <!-- Your attendance content here -->
                    <div class="attendance-container">
                        <!-- Time Display Section -->
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
                                            {{
                                                $timeFormatter.getCurrentTime()
                                            }}
                                        </div>
                                        <div
                                            id="current-day"
                                            class="current-day"
                                        >
                                            {{
                                                $timeFormatter.getCurrentDate()
                                            }}
                                        </div>
                                        <div
                                            class="current-timezone"
                                            v-if="
                                                $timeFormatter.getTimezoneDisplay()
                                            "
                                        >
                                            <i class="pi pi-globe"></i>
                                            {{
                                                $timeFormatter.getTimezoneDisplay()
                                            }}
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
                                                $formatTime(
                                                    slotProps.data.timeIn
                                                )
                                            }}
                                        </div>
                                        <div class="date-value">
                                            {{
                                                $formatDate(
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
                                                $formatTime(
                                                    slotProps.data.timeOut
                                                )
                                            }}
                                        </div>
                                        <div class="date-value">
                                            {{
                                                $formatDate(
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
                                            $calculateHours(
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
                                                    v-model="
                                                        timezoneForm.auto_sync
                                                    "
                                                    :binary="true"
                                                />
                                                <label>
                                                    Automatically detect
                                                    timezone from browser
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
                                                icon="pi pi-check"
                                                :loading="updatingTimezone"
                                            />
                                            <!-- <Button
                                                type="button"
                                                label="Detect Now"
                                                icon="pi pi-refresh"
                                                @click="detectCurrentTimezone"
                                                severity="secondary"
                                                outlined
                                                class="ml-2"
                                            /> -->
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
                                                <!-- ✅ Using universal formatter -->
                                                <strong>{{
                                                    $formatDate(
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
                                                <!-- ✅ Using universal formatter -->
                                                {{
                                                    $formatTime(
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
                                                <!-- ✅ Using universal formatter -->
                                                {{
                                                    $formatTime(
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
                                                <!-- ✅ Using universal formatter -->
                                                <strong>{{
                                                    $calculateHours(
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

import ProgressSpinner from "primevue/progressspinner";
import Tooltip from "primevue/tooltip";
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
        ProgressSpinner,
    },
    directives: {
        tooltip: Tooltip,
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
            timeInterval: null,

            timeUpdateKey: 0,
            currentTabIndex: 0,

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
            detectedTimezone: "Detecting...",

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
                this.$timeFormatter.init().then(() => {
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
                });
            } else {
                this.stopClock();
            }
        },

        "timezoneForm.auto_sync"(newVal, oldVal) {
            // Only trigger if this is a user action (not initial load)
            if (this.timezonesLoaded && oldVal !== undefined) {
                if (newVal) {
                    console.log("🔄 Auto-sync enabled, detecting timezone...");
                    const detected = this.detectCurrentTimezone();
                    this.timezoneForm.usertimezone = detected;

                    // Show confirmation before auto-saving
                    Swal.fire({
                        title: "Auto-Sync Enabled",
                        html: `Your timezone will be automatically detected as:<br><strong>${this.detectedTimezone}</strong><br><br>Save this setting now?`,
                        icon: "info",
                        showCancelButton: true,
                        confirmButtonText: "Yes, Save",
                        cancelButtonText: "Not Now",
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.updateTimezone();
                        }
                    });
                } else {
                    console.log("🔄 Auto-sync disabled");
                }
            }
        },
    },
    mounted() {
        this.$timeFormatter.init().then(() => {
            if (this.visible) {
                this.loadAttendanceData();
                this.startClock();
            }
        });
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
        onTabChange(event) {
            this.currentTabIndex = event.index;

            // Tab index 0 = Attendance Tab
            if (event.index === 0) {
                console.log("👁️ Attendance tab visited, refreshing data...");
                this.refreshAttendanceTab();
            }

            // Tab index 1 = Account Tab
            if (event.index === 1 && !this.accountDetailsLoaded) {
                this.loadAccountDetails();
            }

            // Tab index 2 = Record Tab
            if (event.index === 2) {
                this.filterAttendanceRecords();
            }

            // Tab index 3 = My Privileges Tab
            if (event.index === 3 && !this.privilegesLoaded) {
                this.loadUserPrivileges();
            }

            // Tab index 4 = My Schedule Tab
            if (event.index === 4) {
                this.loadScheduleData();
            }
        },

        formatTime(datetime) {
            return this.$formatTime(datetime);
        },

        formatDate(datetime) {
            return this.$formatDate(datetime);
        },

        calculateHours(timeIn, timeOut) {
            return this.$calculateHours(timeIn, timeOut);
        },

        formatRecordDate(datetime) {
            return this.$formatDate(datetime);
        },

        calculateRecordHours(timeIn, timeOut) {
            return this.$calculateHours(timeIn, timeOut);
        },

        async refreshAttendanceTab() {
            try {
                // Refresh attendance data
                await this.loadAttendanceData();

                // Restart clock if not running
                if (!this.timeInterval) {
                    this.startClock();
                }

                console.log("✅ Attendance tab refreshed");
            } catch (error) {
                console.error("❌ Failed to refresh attendance tab:", error);
            }
        },

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

                if (data.hasPreviousDayOpenRecord) {
                    await this.checkAndAutoClockOut();
                }
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
            // Force update every second to refresh the time display
            this.timeInterval = setInterval(() => {
                this.timeUpdateKey++; // Trigger reactivity
                this.$forceUpdate(); // Force Vue to re-render
            }, 1000);
        },

        stopClock() {
            if (this.timeInterval) {
                clearInterval(this.timeInterval);
                this.timeInterval = null;
            }
        },

        detectCurrentTimezone() {
            try {
                let detected = "UTC";

                if (this.$timeFormatter?.detectBrowserTimezone) {
                    detected = this.$timeFormatter.detectBrowserTimezone();
                } else {
                    detected =
                        Intl.DateTimeFormat().resolvedOptions().timeZone ||
                        "UTC";
                }

                console.log("🌍 Detected timezone:", detected);

                // Find friendly name for display
                const timezone = this.timezones?.find(
                    (tz) => tz.tz === detected
                );
                this.detectedTimezone = timezone?.label || detected;

                return detected;
            } catch (error) {
                console.error("❌ Timezone detection error:", error);
                this.detectedTimezone = "Unable to detect";
                return "UTC";
            }
        },

        async loadTimezones() {
            try {
                // Common timezones list
                this.timezones = [
                    // Americas
                    {
                        tz: "America/Los_Angeles",
                        label: "(UTC -08:00) Pacific Time - Los Angeles",
                    },
                    {
                        tz: "America/Denver",
                        label: "(UTC -07:00) Mountain Time - Denver",
                    },
                    {
                        tz: "America/Chicago",
                        label: "(UTC -06:00) Central Time - Chicago",
                    },
                    {
                        tz: "America/New_York",
                        label: "(UTC -05:00) Eastern Time - New York",
                    },
                    {
                        tz: "America/Anchorage",
                        label: "(UTC -09:00) Alaska Time - Anchorage",
                    },
                    {
                        tz: "Pacific/Honolulu",
                        label: "(UTC -10:00) Hawaii Time - Honolulu",
                    },

                    // Asia
                    {
                        tz: "Asia/Manila",
                        label: "(UTC +08:00) Philippine Time - Manila",
                    },
                    {
                        tz: "Asia/Tokyo",
                        label: "(UTC +09:00) Japan Time - Tokyo",
                    },
                    {
                        tz: "Asia/Shanghai",
                        label: "(UTC +08:00) China Time - Shanghai",
                    },
                    {
                        tz: "Asia/Hong_Kong",
                        label: "(UTC +08:00) Hong Kong Time",
                    },
                    {
                        tz: "Asia/Singapore",
                        label: "(UTC +08:00) Singapore Time",
                    },
                    {
                        tz: "Asia/Seoul",
                        label: "(UTC +09:00) Korea Time - Seoul",
                    },
                    {
                        tz: "Asia/Bangkok",
                        label: "(UTC +07:00) Thailand Time - Bangkok",
                    },
                    {
                        tz: "Asia/Dubai",
                        label: "(UTC +04:00) UAE Time - Dubai",
                    },
                    {
                        tz: "Asia/Kolkata",
                        label: "(UTC +05:30) India Time - Kolkata",
                    },

                    // Europe
                    {
                        tz: "Europe/London",
                        label: "(UTC +00:00) UK Time - London",
                    },
                    {
                        tz: "Europe/Paris",
                        label: "(UTC +01:00) Central European Time - Paris",
                    },
                    {
                        tz: "Europe/Berlin",
                        label: "(UTC +01:00) Central European Time - Berlin",
                    },
                    { tz: "Europe/Moscow", label: "(UTC +03:00) Moscow Time" },

                    // Australia
                    {
                        tz: "Australia/Sydney",
                        label: "(UTC +10:00) Australian Eastern Time - Sydney",
                    },
                    {
                        tz: "Australia/Melbourne",
                        label: "(UTC +10:00) Australian Eastern Time - Melbourne",
                    },
                    {
                        tz: "Australia/Perth",
                        label: "(UTC +08:00) Australian Western Time - Perth",
                    },

                    // Others
                    {
                        tz: "UTC",
                        label: "(UTC +00:00) Coordinated Universal Time",
                    },
                ];

                // Load current timezone setting from backend
                const response = await axios.get("/api/timezone/current");
                this.timezoneForm.usertimezone =
                    response.data.usertimezone || "UTC";
                this.timezoneForm.auto_sync = response.data.auto_sync ?? false;

                // ✅ Detect current timezone AFTER timezones list is loaded
                // Use $nextTick to ensure everything is ready
                this.$nextTick(() => {
                    this.detectCurrentTimezone();
                });

                this.timezonesLoaded = true;
            } catch (error) {
                console.error("Error loading timezones:", error);
                // Set fallback even on error
                this.detectedTimezone = "UTC";
            }
        },

        async updateTimezone() {
            this.updatingTimezone = true;
            try {
                // If auto-sync is enabled, detect and use browser timezone
                let timezoneToSave = this.timezoneForm.usertimezone;
                if (this.timezoneForm.auto_sync) {
                    const detected = this.detectCurrentTimezone();
                    timezoneToSave = detected;
                    this.timezoneForm.usertimezone = detected; // Update form to show detected timezone
                }

                // Prepare the data to send (matching your backend structure)
                const timezoneData = {
                    usertimezone: timezoneToSave,
                    auto_sync: this.timezoneForm.auto_sync ? 1 : 0, // Send as 1/0 for backend
                };

                console.log("💾 Saving timezone:", timezoneData);

                const response = await axios.post(
                    "/update-timezone",
                    timezoneData
                );

                if (response.data.success) {
                    // Update the timeFormatter's timezone
                    if (
                        this.$timeFormatter &&
                        typeof this.$timeFormatter.setTimezone === "function"
                    ) {
                        this.$timeFormatter.setTimezone(timezoneToSave);
                        console.log(
                            "✅ TimeFormatter updated to:",
                            timezoneToSave
                        );
                    }

                    // Show success message
                    await Swal.fire({
                        title: "Success!",
                        text:
                            response.data.message ||
                            "Timezone updated successfully",
                        icon: "success",
                        confirmButtonText: "OK",
                        timer: 2000,
                    });

                    // Refresh the attendance data to reflect new timezone
                    if (this.currentTabIndex === 0) {
                        await this.loadAttendanceData();
                    }

                    // Refresh filtered records if on Record tab
                    if (this.currentTabIndex === 2) {
                        await this.filterAttendanceRecords();
                    }
                }
            } catch (error) {
                console.error("❌ Error updating timezone:", error);

                const errorMessage =
                    error.response?.data?.message ||
                    "Failed to update timezone";

                await Swal.fire({
                    title: "Error!",
                    text: errorMessage,
                    icon: "error",
                    confirmButtonText: "OK",
                });
            } finally {
                this.updatingTimezone = false;
            }
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

        async checkAndAutoClockOut() {
            try {
                if (!this.lastRecordTimeIn) return;

                const lastClockIn = new Date(this.lastRecordTimeIn);
                const today = new Date();

                lastClockIn.setHours(0, 0, 0, 0);
                today.setHours(0, 0, 0, 0);

                if (lastClockIn < today) {
                    console.log(
                        "Detected open clock-in from previous day, triggering auto clock-out..."
                    );

                    const clockInDate = new Date(
                        this.lastRecordTimeIn
                    ).toLocaleDateString("en-US", {
                        weekday: "long",
                        year: "numeric",
                        month: "long",
                        day: "numeric",
                    });

                    const clockInTime = this.formatTime(this.lastRecordTimeIn);

                    const result = await Swal.fire({
                        title: "Open Clock-In Detected",
                        html: `
                    <div style="text-align: left;">
                        <p>You have an open clock-in from:</p>
                        <p><strong>Date:</strong> ${clockInDate}</p>
                        <p><strong>Time:</strong> ${clockInTime}</p>
                        <br>
                        <p style="color: #dc3545;">⚠️ You forgot to clock out!</p>
                        <br>
                        <p>The system will automatically set your clock-out time to <strong>match your clock-in time</strong> for that day.</p>
                        <p style="font-size: 0.9em; color: #6c757d;">This means 0 hours will be recorded for that shift.</p>
                    </div>
                `,
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#3085d6",
                        cancelButtonColor: "#6c757d",
                        confirmButtonText:
                            '<i class="pi pi-check"></i> Yes, auto clock-out',
                        cancelButtonText: '<i class="pi pi-times"></i> Cancel',
                        allowOutsideClick: false,
                        width: "600px",
                    });

                    if (result.isConfirmed) {
                        await this.performAutoClockOut();
                    }
                }
            } catch (error) {
                console.error("Error in checkAndAutoClockOut:", error);
            }
        },

        async performAutoClockOut() {
            try {
                const response = await axios.post("/attendance/auto-clockout", {
                    last_clock_in: this.lastRecordTimeIn,
                });

                if (response.data.success) {
                    await Swal.fire({
                        title: "Auto Clock-Out Successful!",
                        html: `
                    <div style="text-align: left;">
                        <p>${response.data.message}</p>
                        <hr>
                        <p><strong>Date:</strong> ${response.data.date}</p>
                        <p><strong>Clock-in:</strong> ${this.formatTime(
                            response.data.time_in
                        )}</p>
                        <p><strong>Auto Clock-out:</strong> ${this.formatTime(
                            response.data.time_out
                        )}</p>
                        <hr>
                        <p style="color: #dc3545;"><strong>Hours Worked:</strong> 0h 0m</p>
                        <p style="font-size: 0.9em; color: #6c757d;">Note: TimeOut was set to match TimeIn as per system policy.</p>
                    </div>
                `,
                        icon: "success",
                        confirmButtonText: "OK",
                        width: "600px",
                    });

                    await this.loadAttendanceData();
                }
            } catch (error) {
                console.error("Error performing auto clock-out:", error);

                const errorData = error.response?.data;
                const status = error.response?.status;

                let errorMessage =
                    "Failed to perform auto clock-out. Please try again.";

                if (status === 404) {
                    errorMessage = "No open clock-in record found to process.";
                } else if (status === 400) {
                    errorMessage =
                        errorData?.message ||
                        "Cannot process auto clock-out for today's records.";
                } else if (errorData?.message) {
                    errorMessage = errorData.message;
                }

                await Swal.fire({
                    title: "Error!",
                    text: errorMessage,
                    icon: "error",
                    confirmButtonText: "OK",
                });

                await this.loadAttendanceData();
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

<style src="./ProfileModalGlobal.css"></style>
<style scoped src="./ProfileModal.css"></style>
