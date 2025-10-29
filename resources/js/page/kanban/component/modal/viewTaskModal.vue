<template>
    <div class="modal fade show" tabindex="-1" style="display: block;">
        <div class="modal-dialog modal-dialog-centered modal-responsive">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">View Task</h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>

                <div class="modal-body">
                    <!-- Responsive Tabs -->
                    <div class="tab-container">
                        <ul class="nav nav-tabs flex-nowrap" id="myTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="details-tab" data-bs-toggle="tab"
                                    data-bs-target="#details-pane" type="button" role="tab">
                                    Details
                                </button>
                            </li>

                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="comment-tab" data-bs-toggle="tab"
                                    data-bs-target="#comment-tab-pane" type="button" role="tab">
                                    Comments
                                </button>
                            </li>

                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="activity-log-tab" data-bs-toggle="tab"
                                    data-bs-target="#activity-log-tab-pane" type="button" role="tab">
                                    Activity Logs
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="tab-content mt-3" id="myTabContent">
                        <div class="tab-pane fade show active" id="details-pane" role="tabpanel" tabindex="0">
                            <TaskDetails :task="task" />
                        </div>

                        <div class="tab-pane fade" id="comment-tab-pane" role="tabpanel" tabindex="0">
                            <TaskComments v-if="showComments" :task-id="task.id" :mentions="task.mentions" />
                        </div>

                        <div class="tab-pane fade" id="activity-log-tab-pane" role="tabpanel" tabindex="0">
                            <TaskActivityLogs />
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary w-100 w-sm-auto" @click="$emit('close')">Close</button>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show" />
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import TaskComments from '../taskComments.vue'
import TaskDetails from '../taskDetails.vue'
import TaskActivityLogs from '../taskActivityLogs.vue'

const props = defineProps({
    task: { type: Object, required: true }
})

const showComments = ref(false)

function handleTabShown(e) {
    const targetId = e.target.getAttribute('data-bs-target')
    if (targetId === '#comment-tab-pane') {
        showComments.value = true
    }
}

onMounted(() => {
    document.addEventListener('shown.bs.tab', handleTabShown)
})

onBeforeUnmount(() => {
    document.removeEventListener('shown.bs.tab', handleTabShown)
})
</script>

<style>
.modal-responsive {
    max-width: 600px;
    width: 90%;
}

.modal-dialog {
    display: flex;
    align-items: center;
    min-height: calc(100vh - 1rem);
}

.modal-content {
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-body {
    overflow-y: auto;
    max-height: calc(80vh - 100px);
    padding-right: 8px;
}

/* Scrollable tabs container */
.tab-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.tab-container::-webkit-scrollbar {
    display: none;
}

.nav-tabs {
    flex-wrap: nowrap;
    border-bottom: 1px solid #dee2e6;
}

.nav-tabs .nav-item {
    flex: 0 0 auto;
    white-space: nowrap;
}

.nav-tabs .nav-link {
    padding: 0.5rem 1rem;
}

/* --- Responsive --- */
@media (max-width: 768px) {
    .modal-responsive {
        width: 95%;
        max-width: none;
        margin: 0 auto;
    }

    .modal-body {
        max-height: calc(80vh - 80px);
        padding: 0.75rem;
    }

    .modal-footer {
        flex-direction: column;
        gap: 0.5rem;
    }

    .btn {
        width: 100%;
    }

    .tab-container {
        margin-bottom: 0.5rem;
    }
}
</style>
