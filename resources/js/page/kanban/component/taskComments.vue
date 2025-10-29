<template>
    <div>
        <h4 class="mb-3">Comments</h4>

        <!-- ✅ No comments -->
        <p v-if="!comments.length" class="text-secondary">No comments yet</p>

        <!-- ✅ Comments list -->
        <div v-else class="mb-3">
            <div v-for="comment in comments" :key="comment.id" class="border rounded p-2 mb-2 bg-light">
                <div class="d-flex align-items-center mb-1">
                    <!-- Avatar / Fallback -->
                    <div class="me-2">
                        <template v-if="comment.profile_picture">
                            <img :src="comment.profile_picture" alt="avatar" class="rounded-circle border" width="32"
                                height="32" @error="comment.profile_picture = ''" />
                        </template>

                        <template v-else>
                            <div class="rounded-circle border bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
                                style="width: 32px; height: 32px; font-size: 0.9rem;">
                                {{ comment.username ? comment.username.charAt(0).toUpperCase() : '?' }}
                            </div>
                        </template>
                    </div>

                    <!-- User Info -->
                    <div>
                        <strong>{{ ` ${comment.userId === userId ? "(Me)" : comment.username}` }}</strong>
                        <small class="text-muted ms-1">{{ formatDate(comment.created_at) }}</small>
                    </div>
                </div>

                <p class="mb-0 text-dark text-break">{{ comment.content }}</p>
            </div>
        </div>

        <form v-if="ifHasPermission()" @submit.prevent="addComment" class="d-flex flex-column gap-2">
            <textarea v-model="newComment" class="form-control" placeholder="Write a comment..." rows="2"
                :disabled="isSubmitting"></textarea>

            <div class="d-flex justify-content-end">
                <button class="btn btn-primary btn-sm d-flex align-items-center gap-2 px-4"
                    :disabled="isSubmitting || !newComment.trim()">
                    <i class="bi bi-send"></i>
                    <span>{{ isSubmitting ? 'Sending...' : 'Send' }}</span>
                </button>
            </div>
        </form>

        <small v-else class="text-secondary">You dont have a permission to comment</small>
    </div>
</template>

<script setup>
import axios from 'axios'
import { ref, onMounted } from 'vue'

const props = defineProps({
    taskId: {
        type: Number,
        required: true
    },
    mentions: {
        type: Array,
        required: true
    }
})

const comments = ref([])
const newComment = ref('')
const isSubmitting = ref(false)
const userId = window.user.id

function ifHasPermission() {
    const userId = window.user.id
    const permission = props.mentions.find(user => user.id === userId)

    if (permission && !!permission['can_comment'] === false) {
        return false
    } else {
        return true
    }
}

async function getTaskComments() {
    try {
        const { data } = await axios.post('/user/kanban/getTaskComments', { taskId: props.taskId })
        if (data.success) {
            comments.value = data.comments
        }
    } catch (error) {
        console.error('Error fetching comments:', error)
    }
}

async function addComment() {
    if (!newComment.value.trim()) return
    isSubmitting.value = true
    try {
        const { data } = await axios.post('/user/kanban/addTaskComment', {
            taskId: props.taskId,
            content: newComment.value,
            userId: window.user.id
        })

        if (data.success && data.comment) {
            newComment.value = ''
            getTaskComments()
        }
    } catch (error) {
        console.error('Error adding comment:', error)
    } finally {
        isSubmitting.value = false
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

onMounted(getTaskComments)
</script>

<style scoped>
textarea {
    resize: none;
}

.border {
    border-color: #dee2e6 !important;
}

.bg-light {
    background-color: #f9fafb !important;
}
</style>
