const API_BASE_URL = import.meta.env.VITE_API_URL;

export default {
  data() {
    return {
      showAddEmployeeModal: false,
      employees: [],
      newEmployee: {
        name: '',
        position: ''
      }
    }
  },
  async mounted() {
    try {
      const res = await fetch(`${API_BASE_URL}/hr/employees`)
      this.employees = await res.json()
    } catch (err) {
      console.error('Failed to load employees', err)
    }
  },
  methods: {
    addEmployee() {
      if (!this.newEmployee.name || !this.newEmployee.position) {
        alert('Please fill in all fields.')
        return
      }

      this.employees.push({
        id: Date.now(),
        name: this.newEmployee.name,
        position: this.newEmployee.position
      })

      this.newEmployee = {
        name: '',
        position: ''
      }
      this.showAddEmployeeModal = false
    }
  }
}
