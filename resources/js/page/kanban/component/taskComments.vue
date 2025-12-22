<template>
    <div>
        <h4 class="mb-3">Comments</h4>

        <!-- No comments -->
        <p v-if="!comments.length" class="text-secondary">No comments yet</p>

        <!-- Comments list -->
        <div v-else class="mb-3">
            <Card v-for="comment in comments" :key="comment.id" class="mb-2">
                <template #content>
                    <div class="d-flex align-items-center mb-2">
                        <!-- Avatar / Fallback -->
                        <div class="mr-2">
                            <Avatar v-if="comment.profile_picture" :image="comment.profile_picture" shape="circle"
                                size="normal" @error="comment.profile_picture = ''" />
                            <Avatar v-else :label="comment.username ? comment.username.charAt(0).toUpperCase() : '?'"
                                shape="circle" size="normal"
                                style="background-color: var(--primary-color); color: var(--primary-color-text)" />
                        </div>

                        <!-- User Info -->
                        <div>
                            <strong>{{ comment.userId === userId ? "(Me)" : comment.username }}</strong>
                            <small class="text-secondary ms-2">{{ formatDate(comment.created_at) }}</small>
                        </div>
                    </div>

                    <p class="m-0 text-color" style="word-break: break-word;">{{ comment.content }}</p>
                </template>
            </Card>
        </div>

        <!-- Comment form -->
        <form v-if="ifHasPermission()" @submit.prevent="addComment" class="flex flex-column gap-2">
            <Textarea v-model="newComment" placeholder="Write a comment..." :autoResize="true" rows="2"
                :disabled="isSubmitting" fluid />

            <div class="flex justify-content-end">
                <Button type="submit" label="Send" icon="pi pi-send" :disabled="isSubmitting || !newComment.trim()"
                    :loading="isSubmitting" size="small" />
            </div>
        </form>

        <small v-else class="text-secondary">You don't have permission to comment</small>
    </div>
</template>

<script setup>
import axios from 'axios'
import { ref, onMounted } from 'vue'
import Card from 'primevue/card'
import Avatar from 'primevue/avatar'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'

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
.text-secondary {
    color: var(--text-color-secondary);
}

.text-color {
    color: var(--text-color);
}
</style>