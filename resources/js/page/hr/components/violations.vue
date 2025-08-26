<template>
    <div class="violation-section">
        <h4>Employee Violations</h4>

        <div class="d-md-none">
            <div
                class="vio-card shadow-sm rounded-3 mb-2 p-3"
                v-for="(violation, index) in $parent.violations"
                :key="violation.id"
            >
                <div class="d-flex justify-content-between align-items-start">
                    <div class="me-2">
                        <div class="vio-emp fw-semibold text-truncate">
                            {{ violation.employee || "—" }}
                        </div>
                        <div class="text-secondary small">
                            {{ violation.date || "—" }}
                        </div>
                    </div>
                    <span class="badge text-bg-light">{{ index + 1 }}</span>
                </div>

                <div class="mt-2">
                    <div class="text-secondary small mb-1">Description</div>
                    <div class="vio-desc clamp-3">
                        {{ violation.description || "—" }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Desktop / Medium+ screens: Enhanced table -->
        <div class="table-responsive d-none d-md-block">
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
                    <tr
                        v-for="(violation, index) in $parent.violations"
                        :key="violation.id"
                    >
                        <td class="text-secondary">{{ index + 1 }}</td>
                        <td
                            class="fw-semibold text-truncate"
                            style="max-width: 260px"
                        >
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
        </div>
    </div>
</template>
