<template>
	<div class="p-2" v-if="!isLoading">
		<div class="grid_container">
			<!-- Loop through each status -->
			<div v-for="(tasks, status) in tasksByStatus" :key="status" class="status_container" :class="{
				'status-todo': status === 'To Do',
				'status-inprogress': status === 'In Progress',
				'status-review': status === 'Under Review',
				'status-done': status === 'Done'
			}">
				<div class="header_status">
					<h3>{{ status }} ({{ tasks.length }})</h3>
					<div class="d-flex align-items-center gap-2">
						<AddTaskModal :allUsers="userData" @task-added="fetchTasks" v-show="status === 'To Do'" />
						<button @click="toggleHidden(status)" class="btn btn-sm">
							<i class="bi bi-chevron-down" />
						</button>
					</div>
				</div>

				<div v-show="!isHidden[status]">
					<div v-for="task in tasks.slice(0, visibleCounts[status])" :key="task.id"
						style="margin-bottom: 10px;">
						<KanbanCard :task="task" @fetch-tasks="fetchTasks" :allUsers="userData" />
					</div>

					<button v-if="visibleCounts[status] < tasks.length" @click="loadMore(status)"
						style="width: 100%; margin-top: 10px;" class="btn btn-primary btn-sm">
						Load More
					</button>
				</div>
			</div>
		</div>
	</div>
	<div v-else>
		<div class="w-100 text-center my-5">
			<span class="d-flex align-items-center justify-content-center gap-2 fs-4">
				<div class="spinner-border text-primary" role="status">
					<span class="visually-hidden">Loading...</span>
				</div>
				Loading Kanban
			</span>
		</div>


	</div>
</template>


<script>
import kanban from './kanban';
export default kanban
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
	padding: 15px;
	border-radius: 10px;
	background-color: #f1f5f9;
	display: flex;
	flex-direction: column;
	gap: 15px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.status_container h3 {
	margin: 0 0 10px 0;
	font-size: 1.1rem;
	font-weight: 600;
}

.status-todo {
	background-color: #e0f2fe;
	border-left: 5px solid #60a5fa;
}

.status-inprogress {
	background-color: #fef3c7;
	border-left: 5px solid #fbbf24;
}

.status-review {
	background-color: #ede9fe;
	border-left: 5px solid #a78bfa;
}

.status-done {
	background-color: #d1fae5;
	border-left: 5px solid #34d399;
}

.header_status {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.header_status button {
	width: auto;
	padding: 0.25rem 0.5rem;
	flex-shrink: 0;
}

@media (max-width: 600px) {
	.header_status button {
		padding: 0.2rem 0.4rem;

		font-size: 0.85rem;

	}
}

/* Responsive */
@media (max-width: 600px) {
	.grid_container {
		grid-template-columns: 1fr;
	}
}
</style>
