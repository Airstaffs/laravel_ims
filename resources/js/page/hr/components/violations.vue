<template>
    <div class="violation-section">
        <h4>Employee Violations</h4>

        <div class="d-md-none">
            <!-- <div class="vio-card shadow-sm rounded-3 mb-2 p-3" v-for="(violation, index) in $parent.violations"
                :key="violation.id">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge text-bg-light">{{ index + 1 }}</span>
                            <div class="vio-emp fw-semibold text-truncate">
                                {{ violation.employee || "—" }}
                            </div>
                        </div>
                        <div class="text-secondary small">
                            {{ violation.date || "—" }}
                        </div>
                    </div>

                </div>

                <div class="mt-2">
                    <div class="text-secondary small mb-1">Description</div>
                    <div class="vio-desc clamp-3">
                        {{ violation.description || "—" }}
                    </div>
                </div>
            </div> -->
            <Card v-for="(violation, index) in $parent.violations" :key="violation.id">
                <template #title>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge text-bg-light">{{ index + 1 }}</span>
                        <span class="vio-emp">{{ violation.employee || "—" }}</span>
                    </div>
                </template>

                <template #content>
                    <div class="detail-container-vio">
                        <div class="detail-item-vio">
                            <span>Date: </span>
                            <span>{{ violation.date || "—" }}</span>
                        </div>
                        <div class="detail-item-vio">
                            <span>Violation: </span>
                            <span>{{ violation.violation || "—" }}</span>
                        </div>
                        <div class="detail-item-vio">
                            <span>Description: </span>
                            <span>{{ violation.description || "—" }}</span>
                        </div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Desktop / Medium+ screens: Enhanced table -->
        <!-- <div class="table-responsive d-none d-md-block">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 72px">#</th>
                        <th>Employee</th>
                        <th>Description</th>
                        <th style="width: 200px">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(violation, index) in $parent.violations" :key="violation.id">
                        <td class="text-secondary">{{ index + 1 }}</td>
                        <td class="fw-semibold text-truncate" style="max-width: 260px">
                            {{ violation.employee || "—" }}
                        </td>
                        <td class="text-truncate" style="max-width: 520px">
                            {{ violation.description || "—" }}
                        </td>
                        <td class="text-secondary">
                            {{ violation.date || "—" }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div> -->
        <XDataTable :value="$parent.violations" :columns="columns" :paginator="false" :showIndex="true"
            tableClass="d-none d-md-block">
            <template #description="{ data }">
                <p>{{ data.description || '--' }}</p>
            </template>
        </XDataTable>
    </div>
</template>

<script>
import { Card } from 'primevue';
import XDataTable from '../../../components/DataTable/XDataTable.vue';
const TABLE_COLUMNS = [
    {
        field: "employee",
        header: "Employee",
        bodyStyle: "font-size: 14px"
    },
    {
        field: "description",
        header: "Description",
        slot: "description",
        bodyStyle: "font-size: 14px"
    },
    {
        field: "violation",
        header: "Violation",
        bodyStyle: "font-size: 14px"
    },
    {
        field: "date",
        header: "Date",
        bodyStyle: "font-size: 14px"
    },

]
export default {
    components: {
        XDataTable,
        Card
    },
    data() {
        return {
            columns: TABLE_COLUMNS
        }
    }
}
</script>

<style>
.detail-item-vio {
    display: flex;
    align-items: start;
    gap: 6px;
    font-size: 14px;
}

.detail-item-vio span:nth-child(1) {
    font-weight: bold;
}

.detail-container-vio {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
</style>