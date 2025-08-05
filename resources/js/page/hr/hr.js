export const tabs = [
  { name: 'schedule', label: 'Schedule' },
  { name: 'employee', label: 'Employee' },
  { name: 'holiday', label: 'Holiday' },
  { name: 'leave', label: 'Employee Leave' },
  { name: 'announcement', label: 'Announcement' }
]

export const blankEmployee = () => ({
  name: '',
  position: ''
})

export async function fetchEmployees() {
  const res = await fetch('/api/hr/employees')
  const data = await res.json()
  return data
}

export function addEmployeeLogic(context) {
  const { newEmployee, employees } = context

  if (!newEmployee.name || !newEmployee.position) {
    alert('Please fill in all fields.')
    return
  }

  employees.push({
    id: Date.now(), // Still using dummy ID for frontend-only
    name: newEmployee.name,
    position: newEmployee.position
  })

  context.newEmployee = blankEmployee()
  context.showAddEmployeeModal = false
}


import { tabs, blankEmployee, fetchEmployees, addEmployeeLogic } from './hr.js'

export default {
  data() {
    return {
      tabs,
      currentTab: 'employee',
      showAddEmployeeModal: false,
      employees: [],
      newEmployee: blankEmployee()
    }
  },
  async mounted() {
    this.employees = await fetchEmployees()
  },
  methods: {
    addEmployee() {
      addEmployeeLogic(this)
    }
  }
}