import Schedule from './components/Schedule.vue'
import Employee from './components/Employee.vue'
import Holiday from './components/Holiday.vue'
import Leave from './components/Leave.vue'
import Announcement from './components/Announcement.vue'
import TimeRecordRate from './components/TimeRecordRate.vue'
import IncidentReport from './components/IncidentReport.vue'

export const tabs = [
  { name: 'Schedule', label: 'Schedule' },
  { name: 'Employee', label: 'Employee' },
  { name: 'Holiday', label: 'Holiday' },
  { name: 'Leave', label: 'Employee Leave' },
  { name: 'Announcement', label: 'Announcement' },
  { name: 'TimeRecordRate', label: 'Time Record / Rate' },
  { name: 'IncidentReport', label: 'Incident Report' }
]

export const componentsMap = {
  Schedule,
  Employee,
  Holiday,
  Leave,
  Announcement,
  TimeRecordRate,
  IncidentReport
}
