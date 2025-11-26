<template>
    <div class="holiday-container">
        <div class="holiday-header">
            <h4>Holidays</h4>
            <Button size="small" icon="pi pi-plus" severity="info" @click="$parent.openHolidayModal()"
                label="Add Holiday" />
        </div>
        <!-- 
        <div class="controller-header">
            <div class="controller-year">
                <label>Year (view)</label>
                <input type="number" class="form-control" v-model.number="$parent.holidayYear" min="2000" max="2100"
                    @change="$parent.fetchHolidays()" />
            </div>
        </div> -->
        <!--Filter--->
        <div>
            <fieldset class="d-flex align-items-center gap-3 ">
                <label for="">Year</label>
                <InputText type="number" size="small" v-model.number="$parent.holidayYear" min="2000" max="2100"
                    @change="$parent.fetchHolidays()" />
            </fieldset>
        </div>

        <div class="d-md-none">
            <div class="hol-card shadow-sm rounded-3 mb-2 p-3" v-for="(h, i) in $parent.holidays" :key="h.holidayID">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-3 flex-grow-1">
                        <div class="fw-semibold text-break">
                            {{ h.title || "—" }}
                        </div>
                        <div class="text-secondary small">
                            <span class="me-2">#{{ i + 1 }}</span>
                            <span :title="'Stored: ' + (h.holidate || '')">
                                {{ h.display_date || "—" }}
                            </span>
                        </div>
                    </div>
                    <span class="badge text-bg-light">{{
                        h.is_recurring ? "Recurring" : "One-time"
                    }}</span>
                </div>

                <!-- Status -->
                <div class="mt-2">
                    <span class="status-pill">
                        {{ h.status || "—" }}
                    </span>
                </div>

                <!-- Actions -->
                <div class="mt-3">
                    <div class="d-flex flex-row gap-2">
                        <Button class="w-100" @click="$parent.editHoliday(h)" label="Edit" size="small" severity="info"
                            icon="pi pi-pencil" />
                        <Button class="w-100" @click="$parent.deleteHoliday(h.holidayID)" label="Delete"
                            severity="danger" icon="pi pi-trash" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Desktop / Medium+ screens: Enhanced table -->
        <XDataTable :value="$parent.holidays" :columns="columns" tableClass="d-none d-md-block" :paginator="false"
            :showIndex="true">

            <template #title="{ data }">
                <div
                    style="word-break: break-word; white-space: normal; overflow-wrap: break-word; flex: 1;font-size: .8rem;">
                    <p class="fw-bold">{{ data.title }}</p>
                </div>
            </template>
            <template #recurring="{ data }">
                <Tag severity="secondary" :value="data.is_recurring ? 'Yes' : 'No'" />
            </template>

            <template #actions="{ data }">
                <div class="d-flex align-items-start">
                    <Button label="Edit" size="small" severity="contrast" variant="text" class="text-primary"
                        icon="pi pi-pencil" @click="$parent.editHoliday(data)" />
                    <Button label="Delete" size="small" severity="contrast" variant="text" class="text-danger"
                        icon="pi pi-trash" @click="$parent.deleteHoliday(data)" />
                </div>
            </template>
        </XDataTable>
        <!-- <div class="table-responsive d-none d-md-block">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 72px">#</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th style="width: 220px">Date</th>
                        <th style="width: 150px">Recurring</th>
                        <th style="width: 220px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(h, i) in $parent.holidays" :key="h.holidayID">
                        <td class="text-secondary">{{ i + 1 }}</td>
                        <td class="fw-semibold text-truncate" style="max-width: 360px">
                            {{ h.title || "—" }}
                        </td>
                        <td>
                            <span class="status-pill">{{
                                h.status || "—"
                                }}</span>
                        </td>
                        <td class="text-secondary">
                            <span :title="'Stored: ' + (h.holidate || '')">
                                {{ h.display_date || "—" }}
                            </span>
                        </td>
                        <td>
                            <span class="badge text-bg-light">
                                {{ h.is_recurring ? "Yes" : "No" }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-outline-primary btn-sm" @click="$parent.editHoliday(h)">
                                    Edit
                                </button>
                                <button class="btn btn-outline-danger btn-sm"
                                    @click="$parent.deleteHoliday(h.holidayID)">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div> -->
    </div>

    <div v-if="$parent.showHolidayModal" class="modal modal-addHoliday">
        <div class="modal-overlay" @click="$parent.closeHolidayModal()"></div>

        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    {{ $parent.holidayForm.holidayID ? "Update" : "Add" }}
                    Holiday
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                    @click="$parent.closeHolidayModal()"></button>
            </div>

            <div class="modal-body">
                <form @submit.prevent="$parent.saveHoliday()">
                    <fieldset>
                        <label>Title</label>
                        <input class="form-control" v-model="$parent.holidayForm.title" required />
                    </fieldset>
                    <fieldset>
                        <label>Status</label>
                        <select class="form-select" v-model="$parent.holidayForm.status" required>
                            <option value="Regular Holiday">
                                Regular Holiday
                            </option>
                            <option value="Special Holiday">
                                Special Holiday
                            </option>
                        </select>
                    </fieldset>
                    <fieldset>
                        <label>Date</label>
                        <input type="date" class="form-control" v-model="$parent.holidayForm.holidate" required />
                    </fieldset>
                    <fieldset>
                        <div class="has-checkbox">
                            <input class="form-check-input holiday-checkbox m-0" type="checkbox" id="isRecurring"
                                v-model="$parent.holidayForm.is_recurring" />
                            <label>Recurring yearly</label>
                        </div>
                    </fieldset>

                    <div class="submit-container">
                        <button class="btn btn-primary" type="submit">
                            {{
                                $parent.holidayForm.holidayID ? "Update" : "Add"
                            }}
                        </button>
                        <button class="btn btn-secondary" type="button" @click="$parent.resetHolidayForm()">
                            Clear
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { Button, InputText, Tag } from 'primevue';
import XDataTable from '../../../components/DataTable/XDataTable.vue'

const TABLE_COLUMN = [
    {
        field: "title",
        header: "Title",
        slot: "title",
        bodyStyle: "font-size: 14px",
        style: { maxWidth: '14rem' }
    },
    {
        field: "status",
        header: "Status",
        bodyStyle: "font-size: 14px"
    },
    {
        field: "display_date",
        header: "Date",
        bodyStyle: "font-size: 14px"
    },
    {
        field: "is_recurring",
        header: "Recurring",
        slot: "recurring",
        bodyStyle: "font-size: 14px"
    },
]
export default {
    components: {
        XDataTable,
        InputText,
        Tag,
        Button
    },
    data() {
        return {
            columns: TABLE_COLUMN
        }
    }
}
</script>

<style scoped src="../hr.css"></style>
