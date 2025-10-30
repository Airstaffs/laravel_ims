import axios from 'axios'
import AddTaskModal from './component/modal/addTaskModal.vue'
import PriorityBadge from './component/priorityBadge.vue'
import MentionedProfile from './component/mentionedProfile.vue'
import KanbanCard from './component/kanbanCard.vue'

export default {
  components: {
    AddTaskModal,
    PriorityBadge,
    MentionedProfile,
    KanbanCard
  },

  data() {
    return {
      viewModal: false,
      isLoading: false,
      user: window.user || null,
      userData: [],
      tasks: [],
      batchSize: 3,
      visibleCounts: {
        'To Do': 3,
        'In Progress': 3,
        'Under Review': 3,
        'Done': 3
      },
      isHidden: {
        'To Do': false,
        'In Progress': false,
        'Under Review': false,
        'Done': false
      }
    }
  },

  methods: {

  async fetchUserData() {
    try {
      const response = await axios.get('/user/getAllUsers')
      if (response.data.data) {
        
        // dont include current user
        const users = response.data.data.filter(user => user.id !== this.user.id)
        this.userData = users
      }
    } catch (error) {
      console.log('User data fetch error:', error)
    }
  },

  async fetchTasks() {
    try {
      const payload = { userId: this.user.id }
      const response = await axios.post('/user/kanban/getTasks', payload)
      if (response.data.tasks) {
        this.tasks = response.data.tasks
      }
    } catch (error) {
      console.log('Tasks fetch error:', error)
    }
  },

    async fetchAllData() {
    this.isLoading = true
    try {
      await Promise.all([this.fetchUserData(), this.fetchTasks()])
    } catch (error) {
      console.error('Error fetching data:', error)
    } finally {
      this.isLoading = false
    }
  },

    toggleHidden(status) {
      this.isHidden[status] = !this.isHidden[status]
    },

    loadMore(status) {
      this.visibleCounts[status] += this.batchSize
    },

    mapStatusLabel(status) {
      switch (status) {
        case 'todo': return 'To Do'
        case 'inprogress': return 'In Progress'
        case 'review': return 'Under Review'
        case 'done': return 'Done'
        default: return status
      }
    }
  },

  computed: {
    tasksByStatus() {
      const grouped = {
        'To Do': [],
        'In Progress': [],
        'Under Review': [],
        'Done': []
      }

      this.tasks.forEach(task => {
        const label = this.mapStatusLabel(task.status)
        if (grouped[label]) grouped[label].push(task)
      })

      return grouped
    }
  },

  mounted() {
    this.fetchAllData()
  }
}
