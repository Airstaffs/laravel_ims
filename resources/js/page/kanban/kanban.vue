<template>
	<div class="p-2" v-if="!isLoading">
		<div class="grid_container">
			<!-- Loop through each status -->
			<Panel v-for="(tasks, status) in tasksByStatus" :key="status" :toggleable="true"
				:collapsed="isHidden[status]" @update:collapsed="(value) => isHidden[status] = value" :class="{
					'status-todo': status === 'To Do',
					'status-inprogress': status === 'In Progress',
					'status-review': status === 'Under Review',
					'status-done': status === 'Done'
				}" class="status_container">
				<template #header>
					<div class="header_content">
						<h3>{{ status }} ({{ tasks.length }})</h3>
						<div class="header_actions">
							<AddTaskModal :allUsers="userData" @task-added="fetchTasks" v-show="status === 'To Do'" />
						</div>
					</div>
				</template>

				<template #default>
					<div class="panel-content">
						<div v-for="task in tasks.slice(0, visibleCounts[status])" :key="task.id" class="task-item">
							<KanbanCard :task="task" @fetch-tasks="fetchTasks" :allUsers="userData" />
						</div>

						<Button v-if="visibleCounts[status] < tasks.length" @click="loadMore(status)" label="Load More"
							class="w-full mt-3" size="small" severity="primary" />
					</div>
				</template>
			</Panel>
		</div>
	</div>
	<div v-else>
		<div class="w-100 text-center my-5	">
			<span class="d-flex flex-column align-items-center justify-content-center gap-2 fs-4">
				<ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" animationDuration="1s" />
				Loading Kanban
			</span>
		</div>
	</div>
</template>

<script>
import Panel from 'primevue/panel';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';
import kanban from './kanban';

export default {
	...kanban,
	components: {
		...kanban.components,
		Panel,
		Button,
		ProgressSpinner
	}
}
</script>

<style scoped>
.grid_container {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	padding: 20px;
	align-items: start;
	background: #eeefef;
	border-radius: 10px;
}

.status_container {
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

/* Override PrimeVue Panel styles for different statuses */
.status_container :deep(.p-panel) {
	border-radius: 10px;
	background-color: #f1f5f9;
}

.status-todo :deep(.p-panel) {
	background-color: #e0f2fe;
	border-left: 5px solid #60a5fa;
}

.status-inprogress :deep(.p-panel) {
	background-color: #fef3c7;
	border-left: 5px solid #fbbf24;
}

.status-review :deep(.p-panel) {
	background-color: #ede9fe;
	border-left: 5px solid #a78bfa;
}

.status-done :deep(.p-panel) {
	background-color: #d1fae5;
	border-left: 5px solid #34d399;
}

.status_container :deep(.p-panel-header) {
	background-color: transparent;
	border: none;
	padding: 1rem;
}

.status_container :deep(.p-panel-content) {
	padding: 0 1rem 1rem 1rem;
	background-color: transparent;
}

/* Header layout fix */
.header_content {
	display: flex;
	align-items: center;
	justify-content: space-between;
	width: 100%;
	gap: 1rem;
}

.header_content h3 {
	margin: 0;
	font-size: 1.1rem;
	font-weight: 600;
	flex-grow: 1;
}

.header_actions {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	flex-shrink: 0;
}

/* Ensure toggle button stays on the right */
.status_container :deep(.p-panel-header-icon) {
	margin-left: 0.5rem;
}

.panel-content {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.task-item {
	margin-bottom: 0;
}

/* Responsive */
@media (max-width: 600px) {
	.grid_container {
		grid-template-columns: 1fr;
		padding: 10px;
	}

	.header_content h3 {
		font-size: 1rem;
	}
}
</style>