<template>
	<h4>Activity Logs</h4>

	<div>
		<div v-for="activity in activiyLogs" :key="activity.id" class="border rounded p-2 mb-2 bg-light">
			<div class="d-flex align-items-center mb-1">
				<div class="me-2">
					<template v-if="activity.profile_picture">
						<img :src="activity.profile_picture" alt="avatar" class="rounded-circle border" width="32" height="32"
							@error="activity.profile_picture = ''" />
					</template>

					<template v-else>
						<div
							class="rounded-circle border bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
							style="width: 32px; height: 32px; font-size: 0.9rem;">
							{{ activity.username ? activity.username.charAt(0).toUpperCase() : '?' }}
						</div>
					</template>
				</div>

				<div>
					<strong>{{ activity.username }}</strong>
					<small class="text-muted ms-1">{{ formatDate(activity.created_at) }}</small>
				</div>
			</div>

			<p class="mb-0 text-secondary text-break">{{ activity.description }}</p>
		</div>
	</div>
</template>

<script setup>
import axios from 'axios';
import { onMounted, ref } from 'vue';

const props = defineProps({
	taskId: {
		type: Number,
		required: true
	}
})

const activiyLogs = ref([])

async function getActivityLogs() {
	try {
		const response = await axios.post('/user/kanban/getActivityLogs', { taskId: props.taskId })
		if (response.data.logs) {
			activiyLogs.value = response.data.logs
		}
	} catch (error) {
		console.log(error)
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

<style scoped></style>