<template>
	<div class="activity-logs">
		<h4 class="mb-3">Activity Logs</h4>

		<div v-if="loading" class="text-center py-4">
			<ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" />
		</div>

		<div v-else-if="activiyLogs.length === 0" class="text-center py-4">
			<p class="text-secondary">No activity logs found.</p>
		</div>

		<div v-else class="activity-list">
			<Card v-for="activity in activiyLogs" :key="activity.id" class="activity-card mb-2">
				<template #content>
					<div class="d-flex align-items-center mb-2">
						<div class="me-2">
							<Avatar v-if="activity.profile_picture" :image="activity.profile_picture" size="large"
								shape="circle" @error="activity.profile_picture = ''" />
							<Avatar v-else :label="activity.username ? activity.username.charAt(0).toUpperCase() : '?'"
								size="large" shape="circle"
								style="background-color: var(--p-primary-color); color: white;" />
						</div>

						<div class="flex-grow-1">
							<div class="fw-semibold">{{ activity.username }}</div>
							<small class="text-secondary">{{ formatDate(activity.created_at) }}</small>
						</div>
					</div>

					<p class="mb-0 text-secondary text-break">{{ activity.description }}</p>
				</template>
			</Card>
		</div>
	</div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'

import Card from 'primevue/card'
import Avatar from 'primevue/avatar'
import ProgressSpinner from 'primevue/progressspinner'

const props = defineProps({
	taskId: {
		type: Number,
		required: true
	}
})

const activiyLogs = ref([])
const loading = ref(false)

async function getActivityLogs() {
	try {
		loading.value = true
		const response = await axios.post('/user/kanban/getActivityLogs', {
			taskId: props.taskId
		})

		if (response.data.logs) {
			activiyLogs.value = response.data.logs
		}
	} catch (error) {
		console.error('Failed to load activity logs:', error)
	} finally {
		loading.value = false
	}
}

function formatDate(datetime) {
	const date = new Date(datetime)
	return date.toLocaleString('en-US', {
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit'
	})
}

onMounted(getActivityLogs)
</script>

<style scoped>
.activity-logs {
	padding: 0.5rem 0;
}

.activity-card {
	background-color: var(--p-surface-50);
}

.activity-card :deep(.p-card-content) {
	padding: 1rem;
}

.activity-list {
	max-height: 60vh;
	overflow-y: auto;
	padding-right: 0.5rem;
}

/* Scrollbar styling */
.activity-list::-webkit-scrollbar {
	width: 6px;
}

.activity-list::-webkit-scrollbar-track {
	background: var(--p-surface-100);
	border-radius: 3px;
}

.activity-list::-webkit-scrollbar-thumb {
	background: var(--p-surface-300);
	border-radius: 3px;
}

.activity-list::-webkit-scrollbar-thumb:hover {
	background: var(--p-surface-400);
}

/* Mobile responsive */
@media (max-width: 768px) {
	.activity-card :deep(.p-card-content) {
		padding: 0.75rem;
	}
}
</style>