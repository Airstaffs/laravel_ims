<template>
	<div class="card mb-2">
		<div class="card-body">
			<!-- New Task Badge -->
			<span v-if="isNewTask" class="badge text-bg-success mb-4">New Task</span>

			<div class="d-flex justify-content-between align-items-center">
				<!-- Priority Badge -->
				<PriorityBadge :priority="task.priority || 'medium'" />

				<!-- Dropdown -->
				<div class="d-flex align-items-center position-relative" ref="dropdownRef">
					<span class="text-secondary" style="font-size: 12px;">{{ formatDate(task.created_at) }}</span>

					<div class="ms-2 position-relative">
						<button class="btn btn-light btn-sm" @click="toggleDropdown">
							<i class="bi bi-three-dots-vertical"></i>
						</button>

						<div v-if="isDropdownOpen" class="dropdown-menu dropdown-menu-end show custom-dropdown"
							@click.stop>
							<button @click="openModal('view', task)" class="dropdown-item">
								<i class="bi bi-eye me-2"></i>View
							</button>

							<button v-if="hasPermission('edit')" @click.prevent="openModal('edit', task)"
								class="dropdown-item">
								<i class="bi bi-pencil me-2"></i>Edit
							</button>

							<button v-if="hasPermission('permission')" @click.prevent="openModal('permission', task)"
								class="dropdown-item">
								<i class="bi bi-shield-lock me-2"></i>Permission
							</button>

							<button v-if="hasPermission('delete')" @click.prevent="openModal('delete', task)"
								class="dropdown-item text-danger">
								<i class="bi bi-trash me-2"></i>Delete
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Task Title & Mentions -->
			<div class="card-title mt-1 text-break">{{ task.title }}</div>
			<MentionedProfile v-if="task.mentions?.length" :mentions="task.mentions" />
			<div class="d-flex align-items-center justify-content-between mt-2">
				<div class="text-secondary" style="font-size: 12px;">Created by: {{ task.createdBy }}</div>
				<div class="d-flex gap-2 text-secondary" style="font-size: 14px;">
					<div class="d-flex gap-1 align-items-center">
						<span>{{ task.fileCount }}</span>
						<i class="bi bi-paperclip"></i>
					</div>
					<div class="d-flex gap-1 align-items-center ">
						<span>{{ task.commentCount }}</span>
						<i class="bi bi-chat"></i>
					</div>
				</div>
			</div>

		</div>

		<!-- Modals -->
		<ViewTaskModal v-if="activeModals.view && selectedTask" :task="selectedTask" @close="closeModal('view')" />
		<DeleteTaskModal v-if="activeModals.delete && selectedTask" :task="selectedTask" :loading="loading"
			@close="closeModal('delete')" @confirm="deleteTask" />
		<PermissionModal v-if="activeModals.permission && selectedTask" :task="selectedTask"
			@close="closeModal('permission')" />
		<EditTaskModal v-if="activeModals.edit && selectedTask" ref="editTaskModalRef" :taskData="selectedTask"
			:allUsers="allUsers" @task-updated="handleTaskUpdate" @close="closeModal('edit')" />
	</div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

import PriorityBadge from './priorityBadge.vue'
import MentionedProfile from './mentionedProfile.vue'
import ViewTaskModal from './modal/viewTaskModal.vue'
import DeleteTaskModal from './modal/deleteTaskModal.vue'
import PermissionModal from './modal/permissionModal.vue'
import EditTaskModal from './modal/editTaskModal.vue'

const props = defineProps({ task: Object, allUsers: Array })
const emit = defineEmits(['fetch-tasks'])

const isDropdownOpen = ref(false)
const selectedTask = ref(null)
const loading = ref(false)
const activeModals = ref({
	view: false,
	delete: false,
	permission: false,
	edit: false,
})
const dropdownRef = ref(null)
const editTaskModalRef = ref(null)
const isNewTask = ref(false)


const hasPermission = (type) => {
	const userId = window.user.id
	const mention = props.task.mentions?.find(u => u.id === userId)

	if (type === 'edit') return mention?.can_edit ?? props.task.userId === userId
	if (type === 'delete') return mention?.can_delete ?? props.task.userId === userId
	if (type === 'permission') return props.task.userId === userId
	return false
}

// Dropdown
const toggleDropdown = () => (isDropdownOpen.value = !isDropdownOpen.value)
const handleClickOutside = (e) => {
	if (!dropdownRef.value?.contains(e.target)) isDropdownOpen.value = false
}


const openModal = (type, task) => {
	if (type === 'view' || hasPermission(type)) {
		selectedTask.value = task
		activeModals.value[type] = true
		isDropdownOpen.value = false

		if (type === 'edit' && editTaskModalRef.value) {
			editTaskModalRef.value.openModal()
		}

		//after opening the view modal, mark as read the notif if the task is new
		if (type === 'view' && isNewTask.value) {
			markAsRead()
			isNewTask.value = false
			const notifElem = document.getElementById('kanbanNotifAccount');
			if (notifElem) {
				let count = parseInt(notifElem.textContent || '0', 10);
				count = Math.max(0, count - 1); // subtract 1 but not below 0
				notifElem.textContent = count;
			}
		}
	} else {
		Swal.fire('Access Denied', 'You do not have permission to use this button', 'error')
	}
}

const closeModal = (type) => {
	activeModals.value[type] = false
	selectedTask.value = null
}


const deleteTask = async () => {
	if (!selectedTask.value) return
	loading.value = true
	try {
		await axios.post('/user/kanban/deleteTask', { taskId: selectedTask.value.id })
		emit('fetch-tasks')
		Swal.fire('Deleted!', 'Task has been deleted.', 'success')
	} catch (err) {
		console.error(err)
		Swal.fire('Error', 'Failed to delete task.', 'error')
	} finally {
		loading.value = false
		closeModal('delete')
	}
}

const handleTaskUpdate = () => {
	emit('fetch-tasks')
	closeModal('edit')
}


const updateTaskReadStatus = () => {
	const task = props.task;
	const userId = window.user.id;

	// Normalize readBy
	let readBy = [];
	if (Array.isArray(task.readBy)) {
		readBy = task.readBy;
	} else if (typeof task.readBy === 'string' && task.readBy.length) {
		try {
			readBy = JSON.parse(task.readBy);
		} catch {
			readBy = [];
		}
	}

	// Task creator has no "new" badge
	if (userId === task.userId) {
		isNewTask.value = false;
		return;
	}

	// If user already read, it's not new
	isNewTask.value = !readBy.includes(userId);
};


const markAsRead = () => {
	if (isNewTask.value) {
		const payload = {
			userId: window.user.id,
			taskId: props.task.id
		}
		axios.post('/user/kanban/readNotif', payload)
	}
}

// Date formatting
function formatDate(dateString) {
	const date = new Date(dateString);

	const months = [
		"January", "February", "March", "April", "May", "June",
		"July", "August", "September", "October", "November", "December"
	];

	const month = months[date.getUTCMonth()];
	const day = date.getUTCDate();
	const year = date.getUTCFullYear();

	let hours = date.getUTCHours();
	const minutes = date.getUTCMinutes().toString().padStart(2, '0');
	const ampm = hours >= 12 ? 'pm' : 'am';
	hours = hours % 12 || 12;

	return `${month} ${day}, ${year} ${hours}:${minutes}${ampm}`;
}


onMounted(() => {
	document.addEventListener('click', handleClickOutside)
	updateTaskReadStatus()
})

onBeforeUnmount(() => {
	document.removeEventListener('click', handleClickOutside)
})
</script>
