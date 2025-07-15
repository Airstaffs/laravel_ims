<template>
  <div class="printer-scanner-container">
    <!-- Desktop: Modal Scanner -->
    <div v-if="!isMobile" 
         class="modal fade" 
         :class="{ show: showScanner }" 
         :style="{ display: showScanner ? 'block' : 'none' }" 
         tabindex="-1" 
         role="dialog">
      <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content scanner-modal">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="fas fa-print me-2"></i>
              Label Printer Scanner
            </h5>
            <button type="button" class="btn-close btn-close-white" @click="closeScanner"></button>
          </div>
          <div class="modal-body p-4">
            <ScannerInterface />
          </div>
        </div>
      </div>
      <div v-if="showScanner" class="modal-backdrop fade show"></div>
    </div>

    <!-- Mobile: Full Screen Scanner -->
    <div v-if="isMobile && showScanner" class="mobile-scanner-fullscreen">
      <ScannerInterface />
    </div>

    <!-- Desktop: Main Content when scanner is closed -->
    <div v-if="!isMobile && !showScanner" class="printer-dashboard">
      <div class="container-fluid p-4">
        <div class="row justify-content-center">
          <div class="col-12 col-lg-10">
            <div class="card shadow border-0">
              <div class="card-header bg-gradient-primary text-white">
                <h4 class="mb-0 d-flex align-items-center">
                  <i class="fas fa-print me-3"></i>
                  Label Printer Dashboard
                </h4>
              </div>
              <div class="card-body text-center py-5">
                <div class="scanner-launch-area">
                  <div class="mb-4">
                    <div class="scanner-icon-container">
                      <i class="fas fa-qrcode scanner-icon"></i>
                      <div class="icon-pulse"></div>
                    </div>
                  </div>
                  <h2 class="mb-3 text-dark">Ready to Print Labels</h2>
                  <p class="text-muted mb-4 lead">Scan serial numbers to generate and print product labels</p>
                  <button @click="openScanner" class="btn btn-primary btn-lg px-5 py-3 rounded-pill">
                    <i class="fas fa-camera me-2"></i>
                    Start Scanner
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Scans Section -->
        <div class="row justify-content-center mt-4" v-if="recentScans.length > 0">
          <div class="col-12 col-lg-10">
            <div class="card shadow border-0">
              <div class="card-header bg-light">
                <h5 class="mb-0 d-flex align-items-center">
                  <i class="fas fa-history me-2 text-muted"></i>
                  Recent Scans
                </h5>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead class="table-light">
                      <tr>
                        <th><i class="fas fa-barcode me-1"></i> Serial Number</th>
                        <th><i class="fas fa-clock me-1"></i> Scan Time</th>
                        <th><i class="fas fa-info-circle me-1"></i> Status</th>
                        <th><i class="fas fa-cogs me-1"></i> Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="scan in recentScans" :key="scan.id">
                        <td><code class="bg-light p-1 rounded">{{ scan.serial }}</code></td>
                        <td class="text-muted">{{ formatTime(scan.timestamp) }}</td>
                        <td>
                          <span class="badge rounded-pill" :class="getStatusClass(scan.status)">
                            <i :class="getStatusIcon(scan.status)" class="me-1"></i>
                            {{ scan.status }}
                          </span>
                        </td>
                        <td>
                          <button @click="reprintLabel(scan)" class="btn btn-sm btn-outline-primary rounded-pill">
                            <i class="fas fa-redo me-1"></i>
                            Reprint
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PrinterScanner',
  data() {
    return {
      showScanner: false,
      isMobile: false,
      recentScans: [
        {
          id: 1,
          serial: 'SN123456789',
          timestamp: new Date(),
          status: 'Printed'
        },
        {
          id: 2,
          serial: 'SN987654321',
          timestamp: new Date(Date.now() - 300000),
          status: 'Failed'
        },
        {
          id: 3,
          serial: 'SN555444333',
          timestamp: new Date(Date.now() - 600000),
          status: 'Printed'
        }
      ]
    }
  },
  mounted() {
    this.checkMobile();
    window.addEventListener('resize', this.checkMobile);
    
    // Don't auto-open scanner, let user click the button
    // this.openScanner();
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.checkMobile);
    // Clean up modal classes
    document.body.classList.remove('modal-open');
  },
  methods: {
    checkMobile() {
      this.isMobile = window.innerWidth <= 768;
    },
    openScanner() {
      this.showScanner = true;
      if (!this.isMobile) {
        document.body.classList.add('modal-open');
      }
    },
    closeScanner() {
      this.showScanner = false;
      if (!this.isMobile) {
        document.body.classList.remove('modal-open');
      }
    },
    formatTime(timestamp) {
      return new Date(timestamp).toLocaleString();
    },
    getStatusClass(status) {
      return {
        'bg-success': status === 'Printed',
        'bg-danger': status === 'Failed',
        'bg-warning': status === 'Pending'
      };
    },
    getStatusIcon(status) {
      return {
        'fas fa-check': status === 'Printed',
        'fas fa-times': status === 'Failed',
        'fas fa-clock': status === 'Pending'
      };
    },
    reprintLabel(scan) {
      console.log('Reprinting label for:', scan.serial);
      // Add reprint logic here
      this.showToast('info', `Reprinting label for ${scan.serial}...`);
    },
    showToast(type, message) {
      const toast = document.createElement('div');
      toast.className = `toast align-items-center text-bg-${type} border-0 show`;
      toast.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">${message}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
      `;
      
      let container = document.querySelector('.toast-container');
      if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
      }
      
      container.appendChild(toast);
      setTimeout(() => {
        if (toast.parentElement) {
          toast.remove();
        }
      }, 3000);
    }
  },
  components: {
    ScannerInterface: {
      template: `
        <div class="scanner-interface">
          <!-- Mobile Header (only visible on mobile) -->
          <div v-if="$parent.isMobile" class="mobile-header">
            <button @click="$parent.closeScanner" class="btn btn-link text-white p-0">
              <i class="fas fa-arrow-left fs-4"></i>
            </button>
            <h4 class="text-white mb-0 flex-grow-1 text-center">Label Scanner</h4>
            <div style="width: 24px;"></div>
          </div>

          <!-- Scanner Area -->
          <div class="scanner-area">
            <div class="row">
              <div class="col-lg-6">
                <!-- Camera Container -->
                <div class="camera-container">
                  <div class="camera-placeholder">
                    <div class="scanning-frame" :class="{ active: isScanning }">
                      <div class="scan-line" v-if="isScanning"></div>
                      <div class="corner-frame top-left"></div>
                      <div class="corner-frame top-right"></div>
                      <div class="corner-frame bottom-left"></div>
                      <div class="corner-frame bottom-right"></div>
                    </div>
                    <div class="camera-icon" v-if="!isScanning">
                      <i class="fas fa-camera"></i>
                      <p class="mt-2 mb-0 text-muted">Position barcode here</p>
                    </div>
                    <div class="scanning-text" v-if="isScanning">
                      <i class="fas fa-spinner fa-spin"></i>
                      <p class="mt-2 mb-0">Scanning...</p>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="col-lg-6">
                <!-- Scanner Controls -->
                <div class="scanner-controls">
                  <div class="mb-4">
                    <h5 class="text-center mb-3">
                      <i class="fas fa-scan me-2"></i>
                      Scanner Controls
                    </h5>
                  </div>

                  <!-- Manual Input -->
                  <div class="mb-4">
                    <label class="form-label fw-bold">
                      <i class="fas fa-keyboard me-1"></i>
                      Manual Entry
                    </label>
                    <div class="input-group input-group-lg">
                      <span class="input-group-text">
                        <i class="fas fa-barcode"></i>
                      </span>
                      <input 
                        type="text" 
                        class="form-control"
                        placeholder="Enter serial number"
                        v-model="manualSerial"
                        @keyup.enter="processSerial"
                      >
                      <button @click="processSerial" class="btn btn-success" :disabled="!manualSerial">
                        <i class="fas fa-print me-1"></i>
                        Print
                      </button>
                    </div>
                  </div>

                  <!-- Action Buttons -->
                  <div class="row g-3">
                    <div class="col-6">
                      <button 
                        @click="toggleScanning" 
                        class="btn w-100 btn-lg"
                        :class="isScanning ? 'btn-danger' : 'btn-primary'"
                      >
                        <i :class="isScanning ? 'fas fa-stop' : 'fas fa-play'" class="me-2"></i>
                        {{ isScanning ? 'Stop' : 'Start' }}
                      </button>
                    </div>
                    <div class="col-6">
                      <button @click="$parent.closeScanner" class="btn btn-secondary w-100 btn-lg">
                        <i class="fas fa-times me-2"></i>
                        Close
                      </button>
                    </div>
                  </div>

                  <!-- Stats -->
                  <div class="mt-4">
                    <div class="row text-center">
                      <div class="col-4">
                        <div class="stat-card">
                          <div class="stat-number">{{ scanResults.length }}</div>
                          <div class="stat-label">Scanned</div>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="stat-card">
                          <div class="stat-number text-success">{{ getSuccessCount() }}</div>
                          <div class="stat-label">Success</div>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="stat-card">
                          <div class="stat-number text-danger">{{ getFailCount() }}</div>
                          <div class="stat-label">Failed</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Scan Results -->
          <div class="scan-results mt-4" v-if="scanResults.length > 0">
            <h6 class="mb-3">
              <i class="fas fa-list me-2"></i>
              Session Results ({{ scanResults.length }})
            </h6>
            <div class="row">
              <div class="col-12">
                <div class="results-container">
                  <div 
                    v-for="result in scanResults.slice(0, 5)" 
                    :key="result.id"
                    class="result-item"
                  >
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <strong class="text-primary">{{ result.serial }}</strong>
                        <small class="text-muted d-block">{{ formatTime(result.timestamp) }}</small>
                      </div>
                      <div class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill" :class="getStatusClass(result.status)">
                          {{ result.status }}
                        </span>
                        <button @click="reprintLabel(result)" class="btn btn-sm btn-outline-primary">
                          <i class="fas fa-redo"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      `,
      data() {
        return {
          isScanning: false,
          manualSerial: '',
          scanResults: []
        }
      },
      mounted() {
        this.simulateScanning();
      },
      methods: {
        toggleScanning() {
          this.isScanning = !this.isScanning;
          if (this.isScanning) {
            this.startScanning();
          }
        },
        startScanning() {
          setTimeout(() => {
            if (this.isScanning) {
              const mockSerial = 'SN' + Math.random().toString().substr(2, 9);
              this.processSerial(mockSerial);
              this.isScanning = false;
            }
          }, 3000);
        },
        simulateScanning() {
          setInterval(() => {
            if (!this.isScanning && Math.random() > 0.9) {
              const mockSerial = 'AUTO' + Math.random().toString().substr(2, 8);
              this.processSerial(mockSerial);
            }
          }, 8000);
        },
        processSerial(serial = null) {
          const serialToProcess = serial || this.manualSerial;
          if (!serialToProcess) return;

          const newScan = {
            id: Date.now(),
            serial: serialToProcess,
            timestamp: new Date(),
            status: Math.random() > 0.2 ? 'Printed' : 'Failed'
          };

          this.scanResults.unshift(newScan);
          this.$parent.recentScans.unshift(newScan);
          this.manualSerial = '';
          
          this.showToast(
            newScan.status === 'Printed' ? 'success' : 'error', 
            newScan.status === 'Printed' 
              ? `✓ Label printed for ${serialToProcess}` 
              : `✗ Print failed for ${serialToProcess}`
          );
        },
        getSuccessCount() {
          return this.scanResults.filter(r => r.status === 'Printed').length;
        },
        getFailCount() {
          return this.scanResults.filter(r => r.status === 'Failed').length;
        },
        showToast(type, message) {
          this.$parent.showToast(type, message);
        },
        formatTime(timestamp) {
          return new Date(timestamp).toLocaleTimeString();
        },
        getStatusClass(status) {
          return {
            'bg-success': status === 'Printed',
            'bg-danger': status === 'Failed',
            'bg-warning': status === 'Pending'
          };
        },
        reprintLabel(scan) {
          this.processSerial(scan.serial);
        }
      }
    }
  }
}
</script>

<style scoped>
/* Container Styles */
.printer-scanner-container {
  min-height: 100vh;
  background: #f8f9fa;
}

/* Gradient Background */
.bg-gradient-primary {
  background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
}

/* Desktop Modal Styles */
.scanner-modal {
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.scanner-modal .modal-header {
  background: linear-gradient(135deg, #007bff, #0056b3);
  color: white;
  border-bottom: none;
  padding: 1.5rem 2rem;
}

.scanner-modal .modal-body {
  background: white;
  min-height: 500px;
}

/* Mobile Full Screen */
.mobile-scanner-fullscreen {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, #007bff, #0056b3);
  z-index: 9999;
  overflow-y: auto;
}

.mobile-header {
  display: flex;
  align-items: center;
  padding: 1rem;
  background: rgba(0, 0, 0, 0.1);
}

/* Scanner Interface */
.scanner-interface {
  color: #333;
}

.mobile-scanner-fullscreen .scanner-interface {
  color: white;
  padding: 0 1rem 1rem;
}

.scanner-area {
  padding: 1rem 0;
}

/* Camera Container */
.camera-container {
  position: relative;
  max-width: 100%;
  margin-bottom: 2rem;
}

.camera-placeholder {
  aspect-ratio: 4/3;
  background: linear-gradient(145deg, #f1f3f4, #e8eaed);
  border-radius: 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  border: 2px solid #e9ecef;
  box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.1);
}

.mobile-scanner-fullscreen .camera-placeholder {
  background: rgba(255, 255, 255, 0.15);
  border-color: rgba(255, 255, 255, 0.3);
  backdrop-filter: blur(10px);
}

/* Scanning Frame */
.scanning-frame {
  position: absolute;
  top: 20px;
  left: 20px;
  right: 20px;
  bottom: 20px;
  border: 2px solid transparent;
  border-radius: 15px;
  transition: all 0.3s ease;
}

.scanning-frame.active {
  border-color: #28a745;
  box-shadow: 0 0 30px rgba(40, 167, 69, 0.6);
}

/* Corner Frames */
.corner-frame {
  position: absolute;
  width: 40px;
  height: 40px;
  border: 4px solid #007bff;
  transition: all 0.3s ease;
}

.corner-frame.top-left {
  top: -4px;
  left: -4px;
  border-right: none;
  border-bottom: none;
  border-top-left-radius: 15px;
}

.corner-frame.top-right {
  top: -4px;
  right: -4px;
  border-left: none;
  border-bottom: none;
  border-top-right-radius: 15px;
}

.corner-frame.bottom-left {
  bottom: -4px;
  left: -4px;
  border-right: none;
  border-top: none;
  border-bottom-left-radius: 15px;
}

.corner-frame.bottom-right {
  bottom: -4px;
  right: -4px;
  border-left: none;
  border-top: none;
  border-bottom-right-radius: 15px;
}

/* Scan Line Animation */
.scan-line {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, #28a745, transparent);
  animation: scanLine 2s linear infinite;
  box-shadow: 0 0 10px rgba(40, 167, 69, 0.8);
}

@keyframes scanLine {
  0% { top: 0; }
  100% { top: calc(100% - 3px); }
}

/* Camera Icon */
.camera-icon {
  font-size: 3rem;
  color: #6c757d;
  text-align: center;
}

.scanning-text {
  text-align: center;
  color: #28a745;
  font-weight: 600;
}

.mobile-scanner-fullscreen .camera-icon {
  color: rgba(255, 255, 255, 0.8);
}

/* Dashboard Styles */
.printer-dashboard {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.scanner-launch-area {
  padding: 4rem 2rem;
}

.scanner-icon-container {
  position: relative;
  display: inline-block;
}

.scanner-icon {
  font-size: 8rem;
  color: #007bff;
  opacity: 0.8;
}

.icon-pulse {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 120%;
  height: 120%;
  border: 3px solid #007bff;
  border-radius: 50%;
  opacity: 0.3;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { transform: translate(-50%, -50%) scale(0.8); opacity: 0.3; }
  50% { transform: translate(-50%, -50%) scale(1.1); opacity: 0.1; }
  100% { transform: translate(-50%, -50%) scale(1.3); opacity: 0; }
}

/* Form Controls */
.form-control:focus {
  border-color: #007bff;
  box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
}

.mobile-scanner-fullscreen .form-control {
  background: rgba(255, 255, 255, 0.9);
  border: 1px solid rgba(255, 255, 255, 0.3);
}

/* Buttons */
.btn {
  border-radius: 10px;
  font-weight: 500;
  transition: all 0.3s ease;
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.btn-lg {
  padding: 0.75rem 2rem;
  font-size: 1.1rem;
}

/* Scanner Controls */
.scanner-controls {
  background: #f8f9fa;
  border-radius: 15px;
  padding: 2rem;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.mobile-scanner-fullscreen .scanner-controls {
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
}

/* Stats Cards */
.stat-card {
  background: white;
  padding: 1rem;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
}

.stat-number {
  font-size: 2rem;
  font-weight: bold;
}

.stat-label {
  font-size: 0.8rem;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Scan Results */
.results-container {
  max-height: 250px;
  overflow-y: auto;
}

.result-item {
  background: white;
  padding: 1rem;
  border-radius: 10px;
  margin-bottom: 0.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.2s ease;
}

.result-item:hover {
  transform: translateX(5px);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
}

.mobile-scanner-fullscreen .result-item {
  background: rgba(255, 255, 255, 0.9);
}

/* Cards */
.card {
  border-radius: 15px;
  border: none;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
}

.card-header {
  border-radius: 15px 15px 0 0 !important;
  border-bottom: none;
  padding: 1.5rem 2rem;
}

/* Table Styles */
.table {
  border-radius: 10px;
  overflow: hidden;
}

.table th {
  background-color: #f8f9fa;
  border-bottom: 2px solid #dee2e6;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.85rem;
  letter-spacing: 0.5px;
  padding: 1rem;
}

.table td {
  padding: 1rem;
  vertical-align: middle;
}

/* Badge Styles */
.badge {
  font-size: 0.8rem;
  padding: 0.5rem 1rem;
  font-weight: 500;
}

/* Responsive Design */
@media (max-width: 768px) {
  .scanner-interface {
    padding: 1rem;
  }
  
  .camera-container {
    margin-bottom: 1.5rem;
  }
  
  .scanner-controls {
    padding: 1.5rem;
  }
  
  .scanner-launch-area {
    padding: 2rem 1rem;
  }
  
  .scanner-icon {
    font-size: 5rem;
  }
}

/* Modal Backdrop */
.modal-backdrop {
  background-color: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(5px);
}

/* Loading Animation */
@keyframes pulseCorners {
  0% { opacity: 1; }
  50% { opacity: 0.3; }
  100% { opacity: 1; }
}

.scanning-frame.active .corner-frame {
  animation: pulseCorners 1s ease-in-out infinite;
  border-color: #28a745;
}
</style>