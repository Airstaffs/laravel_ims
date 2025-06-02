<template>
    <div v-if="show" class="modal workHistory">
        <div class="modal-overlay" @click="$emit('close')"></div>

        <div class="modal-content">
            <div class="modal-header">
                <h2>Work History</h2>
                <button class="btn btn-modal-close" @click="$emit('close')">&times;</button>
            </div>

            <div class="modal-body">
                <div v-if="loading">Loading work history...</div>
                <div v-else-if="error">{{ error }}</div>
                <div v-else>
                    <pre>{{ workHistory }}</pre> <!-- You can format this prettier -->
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import WorkHistory from "./workhistory.js";
    export default WorkHistory;
</script>
