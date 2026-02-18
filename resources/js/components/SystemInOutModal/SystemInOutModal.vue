<template>
    <Dialog :visible="visible" @update:visible="$emit('update:visible', $event)" modal header="Time Logs"
        :style="{ width: '90%', height: '80vh' }" class="profile-modal" @show="onDialogShow">
        <div>
            <!-- Filters -->
            <div class="filter-container">
                <div>
                    <label>Status: </label>
                    <Select v-model="selectedStatus" :options="clockStatusOption" optionLabel="label"
                        placeholder="Select status" class="select-status" size="small" @change="applyFilters">
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="d-flex align-items-center">
                                <i :class="getStatusIcon(slotProps.value.value)"
                                    :style="{ color: getStatusColor(slotProps.value.value), marginRight: '8px' }">
                                </i>
                                <div>{{ slotProps.value.label }}</div>
                            </div>
                            <span v-else>{{ slotProps.placeholder }}</span>
                        </template>

                        <template #option="slotProps">
                            <div class="d-flex align-items-center">
                                <i :class="getStatusIcon(slotProps.option.value)"
                                    :style="{ color: getStatusColor(slotProps.option.value), marginRight: '8px' }">
                                </i>
                                <div>{{ slotProps.option.label }}</div>
                            </div>
                        </template>
                    </Select>
                </div>

                <div>
                    <label>Account: </label>
                    <Select v-model="selectedAccountType" :options="accountTypeOption" optionLabel="label"
                        placeholder="Select account type" class="select-status" size="small" @change="applyFilters">
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="d-flex align-items-center">
                                <img v-if="slotProps.value.value"
                                    :src="`https://flagcdn.com/w40/${slotProps.value.value.toLowerCase()}.png`"
                                    style="width: 18px; margin-right: 8px;" />
                                <i v-else class="pi pi-globe" style="margin-right: 8px; font-size: 14px;"></i>

                                <div>{{ slotProps.value.label }}</div>
                            </div>
                            <span v-else>{{ slotProps.placeholder }}</span>
                        </template>

                        <template #option="slotProps">
                            <div class="d-flex align-items-center">
                                <img v-if="slotProps.option.value"
                                    :src="`https://flagcdn.com/w40/${slotProps.option.value.toLowerCase()}.png`"
                                    style="width: 18px; margin-right: 8px;" />
                                <i v-else class="pi pi-globe" style="margin-right: 8px; font-size: 14px;"></i>

                                <div>{{ slotProps.option.label }}</div>
                            </div>
                        </template>
                    </Select>
                </div>

                <Button label="Reset" @click="onReset" />
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading-container">
                <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
                <p>Loading attendance data...</p>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="error-container">
                <i class="pi pi-exclamation-triangle" style="font-size: 2rem; color: #ef4444;"></i>
                <p>{{ error }}</p>
                <Button label="Retry" @click="fetchAttendance" size="small" />
            </div>

            <!-- Data Display -->
            <div v-else class="attendance-content">
                <!-- Records Table -->
                <div class="records-section desktop-view">
                    <!-- Stats Cards -->
                    <div class="stats-grid">
                        <div class="stat-card stat-card-in">
                            <div class="stat-icon-container">
                                <i class="pi pi-sign-in"></i>
                            </div>
                            <div class="stat-details">
                                <p class="stat-label">Currently Clocked In</p>
                                <h2 class="stat-number">{{ currentlyClockedIn }}</h2>
                                <p class="stat-subtitle">{{ currentlyClockedIn === 1 ? 'user' : 'users' }} active</p>
                            </div>
                        </div>

                        <div class="stat-card stat-card-out">
                            <div class="stat-icon-container">
                                <i class="pi pi-sign-out"></i>
                            </div>
                            <div class="stat-details">
                                <p class="stat-label">Currently Clocked Out</p>
                                <h2 class="stat-number">{{ currentlyClockedOut }}</h2>
                                <p class="stat-subtitle">{{ currentlyClockedOut === 1 ? 'user' : 'users' }} inactive</p>
                            </div>
                        </div>
                    </div>

                    <XDataTable 
    v-if="attendanceData.length > 0" 
    :value="attendanceData" 
    :paginator="false" 
    :columns="columns"
    :scrollable="true"
    scrollHeight="300px"
>
    <template #status="{ data }">
        <Tag :icon="getStatusIcon(data.status)" :severity="getStatusColor(data.status)"
            :value="data.status === 'clocked_in' ? 'Clocked In' : 'Clocked Out'" />
    </template>

    <template #username="{ data }">
        <div class="d-flex align-items-center gap-2">
            <Avatar v-if="data.profile_picture" :image="data.profile_picture" shape="circle" />
            <Avatar v-else :label="data.username.charAt(0).toUpperCase()" shape="circle" />
            <p>{{ data.username }}</p>
        </div>
    </template>

    <template #timeIn="{ data }">
        <p>{{ $formatTime(data.time_in) }}</p>
    </template>

    <template #timeOut="{ data }">
        <p>{{ $formatTime(data.time_out) || '--' }}</p>
    </template>

    <template #duration="{ data }">
        <p class="fw-bold" :style="{ color: '#0079FA' }">{{ data.duration || '--' }}</p>
    </template>

    <template #accountType="{ data }">
        <div class="d-flex align-items-center">
            <img :src="`https://flagcdn.com/w40/${data.accounttype.toLowerCase()}.png`"
                style="width: 18px; margin-right: 8px;" />
            <p>{{ data.accounttype === 'US' ? 'United States' : 'Philippines' }}</p>
        </div>
    </template>
</XDataTable>

                    <div v-else class="no-records">
                        <i class="pi pi-inbox" style="font-size: 3rem; color: #d1d5db;"></i>
                        <p>No records found</p>
                    </div>
                </div>

                <div class="mobile-view">
                    <div class="mobile-card-container" v-for="data in attendanceData">
                        <div class="mobile-card-header">
                            <div>
                                <Avatar v-if="data.profile_picture" :image="data.profile_picture" shape="circle"
                                    size="large" />
                                <Avatar v-else :label="data.username.charAt(0).toUpperCase()" shape="circle"
                                    size="large" />
                            </div>
                            <div>
                                <div class="d-flex flex-column align-items-center gap-2 mb-2">
                                    <p class="fw-bold">{{ data.username }}</p>
                                    <div class="d-flex align-items-center">
                                        <img :src="`https://flagcdn.com/w40/${data.accounttype.toLowerCase()}.png`"
                                            style="width: 18px; margin-right: 8px;" />
                                        <p>{{ data.accounttype }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mobile-card-body">
                            <Tag :icon="getStatusIcon(data.status)" :severity="getStatusColor(data.status)"
                                :value="data.status === 'clocked_in' ? 'Clocked In' : 'Clocked Out'" />

                            <div class="d-flex align-items-center gap-2 flex-column mt-2">
                                <div class="mobile-card-field">
                                    <div class="mobile-card-field-label">Time In:</div>
                                    <div class="mobile-card-field-value">{{ $formatTime(data.time_in) }}</div>
                                </div>

                                <div class="mobile-card-field">
                                    <div class="mobile-card-field-label">Time Out:</div>
                                    <div class="mobile-card-field-value">{{ $formatTime(data.time_out) || '--' }}</div>
                                </div>

                                <div class="mobile-card-field">
                                    <div class="mobile-card-field-label">Time Spent:</div>
                                    <div class="mobile-card-field-value " :style="{ color: '#0079FA' }">{{ data.duration ||
                                        '--' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Dialog>
</template>

<script>
import axios from "axios";
import { Avatar, Button, Card, Select, Tag } from "primevue";
import Dialog from "primevue/dialog";
import XDataTable from "../../components/DataTable/XDataTable.vue";

const TABLE_COLUMNS = [
    {
        field: "status",
        slot: "status",
        header: "Status",
        style: { width: "4rem", minWidth: "4rem" },
    },
    {
        field: "username",
        slot: "username",
        header: "Username",
        style: { width: "4rem", minWidth: "4rem" },
    },
    {
        field: "time_in",
        slot: "timeIn",
        header: "Time In",
        style: { width: "4rem", minWidth: "4rem", fontWeight: 'bold' },
    },
    {
        field: "time_out",
        slot: "timeOut",
        header: "Time Out",
        style: { width: "4rem", minWidth: "4rem", fontWeight: 'bold' },
    },
    {
        field: "duration",
        slot: "duration",
        header: "Time Spent",
        style: { width: "4rem", minWidth: "4rem" },
    },
    {
        field: "accounttype",
        slot: "accountType",
        header: "Account",
        style: { width: "4rem", minWidth: "4rem" },
    }
]

export default {
    name: "SystemInOutModal",
    components: {
        Dialog,
        Button,
        Select,
        XDataTable,
        Tag,
        Avatar,
        Card
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
            columns: TABLE_COLUMNS,
            selectedStatus: { label: "All", value: '' },
            selectedAccountType: { label: "All", value: '' },
            clockStatusOption: [
                { label: "All", value: '' },
                { label: "Clock In", value: 'clocked_in' },
                { label: "Clock Out", value: 'clocked_out' }
            ],
            accountTypeOption: [
                { label: "All", value: '' },
                { label: "Philippines", value: 'PH' },
                { label: "United States", value: 'US' }
            ],
            attendanceData: [],
            loading: false,
            error: null,
            currentlyClockedIn: 0,
            currentlyClockedOut: 0
        }
    },
    methods: {
        getStatusIcon(value) {
            switch (value) {
                case 'clocked_in': return 'pi pi-sign-in';
                case 'clocked_out': return 'pi pi-sign-out';
                default: return 'pi pi-filter';
            }
        },

        getStatusColor(value) {
            switch (value) {
                case 'clocked_in': return 'success';
                case 'clocked_out': return 'danger';
                default: return '#64748B';
            }
        },

        formatStatus(status) {
            return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        },

        applyFilters() {
            this.fetchAttendance();
        },

        onReset() {
            this.selectedAccountType = { label: "All", value: '' }
            this.selectedStatus = { label: "All", value: '' }
            this.fetchAttendance()
        },

        async fetchAttendance() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.post("/attendance/systeminout", {
                    status: this.selectedStatus?.value || '',
                    account: this.selectedAccountType?.value || '',
                }, {
                    withCredentials: true
                });

                if (response.data.success) {
                    const data = response.data.data.records || []
                    const summary = response.data.data.summary
                    this.attendanceData = data;

                    this.currentlyClockedIn = summary.clocked_in
                    this.currentlyClockedOut = summary.clocked_out
                } else {
                    throw new Error(response.data.message || 'Failed to fetch attendance');
                }
            } catch (error) {
                this.error = error.response?.data?.message || error.message || 'Failed to load attendance data';
                this.attendanceData = [];
            } finally {
                this.loading = false;
            }
        },

        onDialogShow() {
            this.selectedStatus = { label: "All", value: '' };
            this.selectedAccountType = { label: "All", value: '' };
            this.fetchAttendance();
        }
    }
}
</script>

<style scoped>

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.75rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    border: 2px solid transparent;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
}

.stat-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
}

/* Clocked In Card */
.stat-card-in::before {
    background: linear-gradient(90deg, #10b981 0%, #059669 100%);
}

.stat-card-in:hover {
    border-color: #10b981;
}

.stat-card-in .stat-icon-container {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #059669;
}

/* Clocked Out Card */
.stat-card-out::before {
    background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
}

.stat-card-out:hover {
    border-color: #ef4444;
}

.stat-card-out .stat-icon-container {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #dc2626;
}

.stat-icon-container {
    width: 72px;
    height: 72px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 2rem;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.stat-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.stat-label {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
    margin: 0.25rem 0;
    background: linear-gradient(135deg, #1e293b 0%, #475569 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-subtitle {
    font-size: 0.875rem;
    color: #94a3b8;
    font-weight: 500;
    margin: 0;
}

/* Filter Container */
.filter-container {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding: 1rem;
    border-radius: 8px;
    color: #ffffff;
    flex-wrap: wrap;
}

.filter-container>div {
    flex: 1;
    min-width: 200px;
}

.filter-container label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
}

.select-status {
    width: 100%;
}

.filter-container .p-button {
    align-self: flex-end;
}

/* Loading & Error */
.loading-container,
.error-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    gap: 1rem;
}

/* Records Section */
.records-section h4 {
    margin-bottom: 1rem;
    color: #111827;
}

/* No Records */
.no-records {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    gap: 1rem;
}

.no-records p {
    color: #9ca3af;
    font-size: 0.875rem;
}

/* ========================= */
/* MOBILE FIX */
/* ========================= */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .stat-card {
        padding: 1.25rem;
    }

    .stat-icon-container {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }

    .stat-number {
        font-size: 2.25rem;
    }

    .mobile-card-container {
        border: 1px solid rgb(134, 134, 134);
        width: 100%;
        border-radius: .4rem;
        padding: .5rem;
        margin-bottom: 1rem;
    }

    .mobile-card-header {
        display: flex;
        align-items: center;
        background: #f4f9ff;
        padding: .5rem;
    }

    .mobile-card-body {
        margin-top: 1rem;
    }

    .mobile-card-field {
        background: #f4f9ff;
        width: 100%;
        padding: .4rem;
        border-radius: .4rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .mobile-card-field-label,
    .mobile-card-field-value {
        font-weight: bold;
    }

    .filter-container {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }

    .filter-container>div {
        width: 100%;
        min-width: 100%;
    }

    .filter-container .p-button {
        width: 100%;
        align-self: stretch;
    }
}

@media (max-width: 480px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }

    .stat-icon-container {
        width: 56px;
        height: 56px;
    }

    .stat-number {
        font-size: 2rem;
    }
}
</style>