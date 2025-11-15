<template>
    <Dialog
        :visible="visible"
        @update:visible="$emit('update:visible', $event)"
        modal
        header="Profile"
        :style="{ width: '90%', height: '80vh' }"
        class="profile-modal"
        :maximizable="true"
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

                            <!-- Time Display -->
                            <Card class="time-card mb-3">
                                <template #content>
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

                            <!-- Hours Summary -->
                            <div class="hours-summary">
                                <Card>
                                    <template #content>
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
                            </div>
                        </div>

                        <!-- Attendance DataTable -->
                        <DataTable
                            :value="attendanceRecords"
                            :paginator="true"
                            :rows="5"
                            :rowsPerPageOptions="[5, 10, 20]"
                            responsiveLayout="scroll"
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
                    <div id="timerecord">
                        <!-- Time record content will go here -->
                        <p>Time record content placeholder</p>
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
                    <div id="myprivileges">
                        <!-- Privileges content will go here -->
                        <p>Privileges content placeholder</p>
                    </div>
                </div>
            </TabPanel>

            <!-- My Schedule Tab -->
            <TabPanel>
                <template #header>
                    <i class="pi pi-calendar-plus mr-2"></i>
                    <span>My Schedule</span>
                </template>

                <div class="scrollable-content">
                    <div id="myschedule">
                        <!-- Schedule content will go here -->
                        <p>Schedule content placeholder</p>
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
        };
    },
    watch: {
        visible(newVal) {
            if (newVal) {
                this.loadAttendanceData();
                this.startClock();

                if (
                    this.activeAccountTab === "details" &&
                    !this.accountDetailsLoaded
                ) {
                    this.loadAccountDetails();
                }
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
    },
};
</script>

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
    padding: 0;
}

.time-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.time-card :deep(.p-card-content) {
    padding: 1.5rem;
}

.time-display {
    color: white;
    text-align: center;
}

.current-time {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.current-day {
    font-size: 1.1rem;
    opacity: 0.9;
}

.button-group {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.clock-button {
    min-width: 150px;
}

.hours-summary {
    margin-top: 1.5rem;
}

.summary-grid {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 1rem;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    justify-content: center;
}

.summary-icon {
    font-size: 2rem;
    color: var(--primary-color);
}

.summary-label {
    font-size: 0.875rem;
    color: var(--text-color-secondary);
    margin-bottom: 0.25rem;
}

.summary-value {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-color);
}

.attendance-table {
    margin-top: 2rem;
}

.time-cell {
    text-align: center;
}

.time-value {
    font-weight: 600;
    font-size: 1rem;
}

.date-value {
    font-size: 0.875rem;
    color: var(--text-color-secondary);
    margin-top: 0.25rem;
}

.computed-hours {
    font-weight: 600;
    color: var(--primary-color);
    text-align: center;
}

.text-muted {
    color: var(--text-color-secondary);
    font-style: italic;
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

/* ==================== RESPONSIVE DESIGN ==================== */

/* Tablet and Mobile */
@media (max-width: 768px) {
    .profile-modal :deep(.p-dialog) {
        max-height: 90vh;
    }

    .scrollable-content {
        padding: 1rem;
    }

    /* Attendance Tab */
    .current-time {
        font-size: 2rem;
    }

    .current-day {
        font-size: 1rem;
    }

    .summary-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .summary-grid :deep(.p-divider-vertical) {
        display: none;
    }

    .button-group {
        flex-direction: column;
        align-items: stretch;
    }

    .clock-button {
        width: 100%;
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
}
</style>
