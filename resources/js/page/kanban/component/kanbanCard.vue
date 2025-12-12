<template>
	<Card class="kanban-card">
		<!-- New Task Badge -->
		<template #title>
			<Tag v-if="isNewTask" severity="success" value="New Task" class="mb-2" />
		</template>

		<template #content>
			<div class="d-flex justify-content-between align-items-center mb-3">
				<!-- Priority Badge -->
				<PriorityBadge :priority="task.priority || 'medium'" />

				<!-- Dropdown -->
				<div class="d-flex align-items-center position-relative" ref="dropdownRef">
					<span class="text-secondary" style="font-size: 12px;">{{ formatDate(task.created_at) }}</span>

					<div class="ms-2 position-relative">
						<Button icon="pi pi-ellipsis-v" text rounded @click="toggleDropdown" size="small" />

						<Menu ref="menu" :model="menuItems" :popup="true" @hide="isDropdownOpen = false" />
					</div>
				</div>
			</div>

			<!-- Task Title & Mentions -->
			<div class="card-title mb-2 text-break fw-semibold">{{ task.title }}</div>
			<MentionedProfile v-if="task.mentions?.length" :mentions="task.mentions" />

			<div class="d-flex align-items-center justify-content-between mt-3">
				<div class="text-secondary" style="font-size: 12px;">Created by: {{ task.createdBy }}</div>
				<div class="d-flex gap-2 text-secondary" style="font-size: 14px;">
					<div class="d-flex gap-1 align-items-center">
						<span>{{ task.fileCount }}</span>
						<i class="pi pi-paperclip"></i>
					</div>
					<div class="d-flex gap-1 align-items-center">
						<span>{{ task.commentCount }}</span>
						<i class="pi pi-comments"></i>
					</div>
				</div>
			</div>
		</template>
	</Card>

	<!-- Modals -->
	<ViewTaskModal v-if="activeModals.view && selectedTask" :task="selectedTask" @close="closeModal('view')" />
	<DeleteTaskModal v-if="activeModals.delete && selectedTask" :task="selectedTask" :loading="loading"
		@close="closeModal('delete')" @confirm="deleteTask" />
	<PermissionModal v-if="activeModals.permission && selectedTask" :task="selectedTask"
		@close="closeModal('permission')" />
	<EditTaskModal v-if="activeModals.edit && selectedTask" ref="editTaskModalRef" :taskData="selectedTask"
		:allUsers="allUsers" @task-updated="handleTaskUpdate" @close="closeModal('edit')" />
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

import Card from 'primevue/card'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Tag from 'primevue/tag'

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
const menu = ref(null)

const hasPermission = (type) => {
	const userId = window.user.id
	const mention = props.task.mentions?.find(u => u.id === userId)

	if (type === 'edit') return mention?.can_edit ?? props.task.userId === userId
	if (type === 'delete') return mention?.can_delete ?? props.task.userId === userId
	if (type === 'permission') return props.task.userId === userId
	return false
}

// Menu items for dropdown
const menuItems = computed(() => {
	const items = [
		{
			label: 'View',
			icon: 'pi pi-eye',
			command: () => openModal('view', props.task)
		}
	]

	if (hasPermission('edit')) {
		items.push({
			label: 'Edit',
			icon: 'pi pi-pencil',
			command: () => openModal('edit', props.task)
		})
	}

	if (hasPermission('permission')) {
		items.push({
			label: 'Permission',
			icon: 'pi pi-shield',
			command: () => openModal('permission', props.task)
		})
	}

	if (hasPermission('delete')) {
		items.push({
			separator: true
		})
		items.push({
			label: 'Delete',
			icon: 'pi pi-trash',
			class: 'text-danger',
			command: () => openModal('delete', props.task)
		})
	}

	return items
})

// Dropdown
const toggleDropdown = (event) => {
	menu.value.toggle(event)
	isDropdownOpen.value = !isDropdownOpen.value
}

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

		if (type === 'view' && isNewTask.value) {
			markAsRead()
			isNewTask.value = false

			const notifIds = ['kanbanNotifDesktop', 'kanbanNotifMobile']

			notifIds.forEach(id => {
				const elem = document.getElementById(id)
				if (elem) {
					let count = parseInt(elem.textContent || '0', 10)
					count = Math.max(0, count - 1)
					elem.textContent = count
					elem.style.display = count > 0 ? 'inline' : 'none'
				}
			})
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
	const task = props.task
	const userId = window.user.id

	// Normalize readBy
	let readBy = []
	if (Array.isArray(task.readBy)) {
		readBy = task.readBy
	} else if (typeof task.readBy === 'string' && task.readBy.length) {
		try {
			readBy = JSON.parse(task.readBy)
		} catch {
			readBy = []
		}
	}

	// Task creator has no "new" badge
	if (userId === task.userId) {
		isNewTask.value = false
		return
	}

	// If user already read, it's not new
	isNewTask.value = !readBy.includes(userId)
}

const markAsRead = () => {
	if (isNewTask.value) {
		const payload = {
			userId: window.user.id,
			taskId: props.task.id
		}
		window.kanbanMentionedCount = Math.max(0, window.kanbanMentionedCount - 1)
		axios.post('/user/kanban/readNotif', payload)
	}
}

// Date formatting
function formatDate(dateString) {
	const date = new Date(dateString)

	const months = [
		"January", "February", "March", "April", "May", "June",
		"July", "August", "September", "October", "November", "December"
	]

	const month = months[date.getUTCMonth()]
	const day = date.getUTCDate()
	const year = date.getUTCFullYear()

	let hours = date.getUTCHours()
	const minutes = date.getUTCMinutes().toString().padStart(2, '0')
	const ampm = hours >= 12 ? 'pm' : 'am'
	hours = hours % 12 || 12

	return `${month} ${day}, ${year} ${hours}:${minutes}${ampm}`
}

onMounted(() => {
	document.addEventListener('click', handleClickOutside)
	updateTaskReadStatus()
})

onBeforeUnmount(() => {
	document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.kanban-card {
	margin-bottom: 0.5rem;
}

.kanban-card :deep(.p-card-body) {
	padding: 1rem;
}

.kanban-card :deep(.p-card-content) {
	padding: 0;
}

.card-title {
	font-size: 1rem;
	line-height: 1.4;
}
</style>