// session-heartbeat.js
// Add this file to your project's public/js directory

class SessionManager {
    constructor(options = {}) {
        // Default configuration
        this.config = {
            heartbeatUrl: '/keep-alive',           // URL to ping for keeping session alive
            heartbeatInterval: 5 * 60 * 1000,      // 5 minutes in milliseconds
            warningTime: 2 * 60 * 1000,            // 2 minutes before expiry
            sessionLifetime: 120 * 60 * 1000,      // 2 hours (should match Laravel config)
            onWarning: null,                       // Warning callback
            onExpired: null,                       // Expiration callback
            debug: false                           // Enable debug logging
            // Merge with provided options
        };
        
        Object.assign(this.config, options);
        
        // Internal state
        this.heartbeatTimer = null;
        this.warningTimer = null;
        this.lastActivity = Date.now();
        this.isActive = true;
        
        // Bind methods to this instance
        this.startHeartbeat = this.startHeartbeat.bind(this);
        this.stopHeartbeat = this.stopHeartbeat.bind(this);
        this.resetTimers = this.resetTimers.bind(this);
        this.sendHeartbeat = this.sendHeartbeat.bind(this);
        this.showWarning = this.showWarning.bind(this);
        this.handleExpiry = this.handleExpiry.bind(this);
        this.activityDetected = this.activityDetected.bind(this);
        
        this.init();
    }
    
    log(...args) {
        if (this.config.debug) {
            console.log('[SessionManager]', ...args);
        }
    }
    
    init() {
        this.log('Initializing session manager');
        
        // Set up activity listeners for common user actions
        const activityEvents = [
            'mousedown', 'mousemove', 'keypress', 
            'scroll', 'touchstart', 'click', 'keydown'
        ];
        
        activityEvents.forEach(event => {
            document.addEventListener(event, this.activityDetected, { passive: true });
        });
        
        // Monitor visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.log('Tab is visible again');
                this.isActive = true;
                this.activityDetected();
                this.sendHeartbeat(); // Immediate heartbeat when tab becomes visible
            } else {
                this.log('Tab is hidden');
                this.isActive = false;
            }
        });
        
        // Start the heartbeat
        this.startHeartbeat();
        
        // Set up warning timer
        this.setupWarningTimer();
    }
    
    activityDetected() {
        this.lastActivity = Date.now();
        // If user was inactive and is now active again
        if (!this.isActive) {
            this.isActive = true;
            this.log('User activity detected after inactivity');
            this.sendHeartbeat(); // Immediate heartbeat when user becomes active
        }
        
        // Reset the warning timer when there's activity
        this.setupWarningTimer();
    }
    
    setupWarningTimer() {
        // Clear existing warning timer
        if (this.warningTimer) {
            clearTimeout(this.warningTimer);
        }
        
        // Set new warning timer
        const timeToWarning = this.config.sessionLifetime - this.config.warningTime;
        this.warningTimer = setTimeout(this.showWarning, timeToWarning);
    }
    
    startHeartbeat() {
        this.log('Starting heartbeat');
        // Clear any existing timer
        this.stopHeartbeat();
        
        // Start a new timer
        this.heartbeatTimer = setInterval(() => {
            const timeSinceLastActivity = Date.now() - this.lastActivity;
            
            // Only send heartbeat if user is active or was active recently
            if (this.isActive || timeSinceLastActivity < this.config.heartbeatInterval * 2) {
                this.sendHeartbeat();
            } else {
                this.log('Skipping heartbeat due to inactivity');
            }
        }, this.config.heartbeatInterval);
        
        // Send an immediate heartbeat
        this.sendHeartbeat();
    }
    
    stopHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
    }
    
    resetTimers() {
        this.stopHeartbeat();
        if (this.warningTimer) {
            clearTimeout(this.warningTimer);
        }
        this.startHeartbeat();
        this.setupWarningTimer();
    }
    
    async sendHeartbeat() {
        try {
            this.log('Sending heartbeat');
            const response = await fetch(this.config.heartbeatUrl, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`Heartbeat failed: ${response.status}`);
            }
            
            const data = await response.json();
            this.log('Heartbeat successful', data);
            
            // Reset the warning timer
            this.setupWarningTimer();
            
        } catch (error) {
            this.log('Heartbeat error:', error);
            // If we get an unauthorized response, the session may have expired
            if (error.message.includes('401') || error.message.includes('419')) {
                this.handleExpiry();
            }
        }
    }
    
    showWarning() {
        this.log('Session expiry warning');
        
        if (typeof this.config.onWarning === 'function') {
            this.config.onWarning();
        } else {
            // Default warning behavior
            const warningTime = Math.floor(this.config.warningTime / 60000);
            const result = confirm(`Your session will expire in approximately ${warningTime} minutes due to inactivity. Click OK to stay logged in.`);
            
            if (result) {
                this.sendHeartbeat();
                this.resetTimers();
            } else {
                // User clicked cancel, let the session expire
                setTimeout(this.handleExpiry, this.config.warningTime);
            }
        }
    }
    
    handleExpiry() {
        this.log('Session expired');
        
        if (typeof this.config.onExpired === 'function') {
            this.config.onExpired();
        } else {
            // Default expiry behavior - redirect to login
            alert('Your session has expired. You will be redirected to the login page.');
            window.location.href = '/login';
        }
    }
    
    // Public method to manually extend the session
    extendSession() {
        this.log('Manually extending session');
        this.activityDetected();
        this.sendHeartbeat();
    }
    
    // Clean up when no longer needed
    destroy() {
        this.log('Destroying session manager');
        this.stopHeartbeat();
        
        if (this.warningTimer) {
            clearTimeout(this.warningTimer);
        }
        
        // Remove event listeners
        const activityEvents = [
            'mousedown', 'mousemove', 'keypress', 
            'scroll', 'touchstart', 'click', 'keydown'
        ];
        
        activityEvents.forEach(event => {
            document.removeEventListener(event, this.activityDetected);
        });
    }
}

// Create a global instance
window.sessionManager = new SessionManager({
    debug: true,  // Set to false in production
    heartbeatInterval: 5 * 60 * 1000,  // 5 minutes
    // Custom warning handler with modal dialog
    onWarning: function() {
        // Check if Bootstrap is available
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
            // Create modal if it doesn't exist
            let warningModal = document.getElementById('session-warning-modal');
            
            if (!warningModal) {
                const modalHtml = `
                <div class="modal fade" id="session-warning-modal" tabindex="-1" aria-labelledby="sessionWarningModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title" id="sessionWarningModalLabel">Session Expiring Soon</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Your session will expire soon due to inactivity.</p>
                                <p>Do you want to continue working?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Log Out</button>
                                <button type="button" class="btn btn-primary" id="extend-session-btn">Continue Working</button>
                            </div>
                        </div>
                    </div>
                </div>`;
                
                const modalContainer = document.createElement('div');
                modalContainer.innerHTML = modalHtml;
                document.body.appendChild(modalContainer.firstChild);
                
                warningModal = document.getElementById('session-warning-modal');
                
                // Add event listener to the continue button
                document.getElementById('extend-session-btn').addEventListener('click', () => {
                    window.sessionManager.extendSession();
                    bootstrap.Modal.getInstance(warningModal).hide();
                });
            }
            
            // Show the modal
            const modal = new bootstrap.Modal(warningModal);
            modal.show();
            
            // Auto-extend session when modal is closed via the X button
            warningModal.addEventListener('hide.bs.modal', (event) => {
                // If close wasn't triggered by "Continue Working" button, let session expire
                if (event.target.querySelector('#extend-session-btn:focus') === null) {
                    console.log('Modal closed without extending session');
                    // User chose not to extend - do nothing and let session expire
                }
            });
        } else {
            // Fallback if Bootstrap is not available
            const result = confirm('Your session will expire soon due to inactivity. Click OK to stay logged in.');
            if (result) {
                window.sessionManager.extendSession();
            }
        }
    },
    // Custom expiry handler
    onExpired: function() {
        // Play logout sound if available
        const logoutSound = document.getElementById('logout-sound');
        if (logoutSound) {
            logoutSound.play();
        }
        
        alert('Your session has expired due to inactivity. You will be redirected to the login page.');
        
        // Use the logout form if available, otherwise redirect to login
        const logoutForm = document.getElementById('logout-expired-form');
        if (logoutForm) {
            logoutForm.submit();
        } else {
            window.location.href = '/logout';
        }
    }
});