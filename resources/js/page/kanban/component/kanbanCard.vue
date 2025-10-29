<template>
	<div class="card mb-2">
		<div class="card-body">
			<div class="d-flex justify-content-between align-items-center">
				<PriorityBadge :priority="task.priority || 'medium'" />
				<div class="d-flex align-items-center position-relative" ref="dropdownRef">
					<span class="date text-secondary">{{ formatDate(task.created_at) }}</span>

					<!-- Dropdown -->
					<div class="ms-2 position-relative">
						<button class="btn btn-light btn-sm" @click="toggleDropdown">
							<i class="bi bi-three-dots-vertical"></i>
						</button>

						<div v-if="dropdownOpen" class="dropdown-menu dropdown-menu-end show custom-dropdown"
							@click.stop>
							<button @click="openModal('view', task)" class="dropdown-item">
								<i class="bi bi-eye me-2"></i>View
							</button>
							<button v-if="checkPermission('edit')" @click.prevent="openModal('edit', task)"
								class="dropdown-item">
								<i class="bi bi-pencil me-2"></i>Edit
							</button>
							<button v-if="checkPermission('permission')" @click.prevent="openModal('permission', task)"
								class="dropdown-item">
								<i class="bi bi-shield-lock me-2"></i>Permission
							</button>
							<button v-if="checkPermission('delete')" @click.prevent="openModal('delete', task)"
								class="dropdown-item text-danger">
								<i class="bi bi-trash me-2"></i>Delete
							</button>
						</div>
					</div>
				</div>
			</div>

			<div class="card-title mt-1 text-break">{{ task.title }}</div>
			<MentionedProfile :mentions="task.mentions" v-if="task.mentions && task.mentions.length" />

		</div>

		<!-- Modals -->
		<ViewTaskModal v-if="modalState.view && currentTask" :task="currentTask" @close="closeModal('view')" />
		<DeleteTaskModal v-if="modalState.delete && currentTask" :task="currentTask" :loading="loading"
			@close="closeModal('delete')" @confirm="handleDeleteTask" />
		<PermissionModal v-if="modalState.permission && currentTask" :task="currentTask"
			@close="closeModal('permission')" />
	</div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import PriorityBadge from './priorityBadge.vue'
import MentionedProfile from './mentionedProfile.vue'
import ViewTaskModal from './modal/viewTaskModal.vue'
import DeleteTaskModal from './modal/deleteTaskModal.vue'
import PermissionModal from './modal/permissionModal.vue'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({ task: Object })

const dropdownOpen = ref(false)
const currentTask = ref(null)
const loading = ref(false)
const modalState = ref({
	view: false,
	delete: false,
	permission: false
})
const dropdownRef = ref(null)
const emit = defineEmits(['fetch-tasks'])

// Permission check
const checkPermission = type => {
	const userId = window.user.id
	const mention = props.task.mentions?.find(u => u.id === userId)
	return type === 'edit'
		? mention?.can_edit ?? props.task.userId === userId
		: type === 'delete'
			? mention?.can_delete ?? props.task.userId === userId
			: type === 'permission'
				? props.task.userId === userId
				: false
}

// Dropdown
const toggleDropdown = () => dropdownOpen.value = !dropdownOpen.value
const handleClickOutside = e => {
	if (!dropdownRef.value?.contains(e.target)) dropdownOpen.value = false
}

// Modal handling
const openModal = (type, task) => {
	if (type === 'view' || checkPermission(type)) {
		currentTask.value = task
		modalState.value[type] = true
		dropdownOpen.value = false
	} else {
		Swal.fire('Access Denied', 'You do not have permission to use this button', 'error')
	}
}
const closeModal = type => {
	modalState.value[type] = false
	currentTask.value = null
}

// Delete task
const handleDeleteTask = async () => {
	if (!currentTask.value) return
	loading.value = true
	try {
		await axios.post('/user/kanban/deleteTask', { taskId: currentTask.value.id })
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

// Date formatting
const formatDate = dateStr =>
	dateStr ? new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : ''

onMounted(() => document.addEventListener('click', handleClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside))
</script>
