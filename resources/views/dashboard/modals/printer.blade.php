<!-- resources/views/dashboard/modals/printer.blade.php -->
<div id="printer-app-container"></div>

<script>
window.printerApp = null;

// Function to show printer modal
function showPrinterModal() {
    console.log('Opening printer modal...');
    
    // Load the printer Vue component if not already loaded
    if (!window.printerApp) {
        loadPrinterComponent();
    }
}

// Function to wait for dependencies and load printer component
function loadPrinterComponent() {
    console.log('Loading printer component...');
    
    // Function to check if everything is ready
    function checkReady() {
        if (!window.appInstance) {
            console.log('Main app not ready yet...');
            return false;
        }
        
        if (!window.createApp) {
            console.log('createApp not available yet...');
            return false;
        }
        
        if (!window.appInstance.$options.components.printer) {
            console.log('Printer component not registered yet...');
            return false;
        }
        
        return true;
    }
    
    // If not ready, wait and retry
    if (!checkReady()) {
        setTimeout(loadPrinterComponent, 200);
        return;
    }
    
    // Everything is ready, create the app
    const printerComponent = window.appInstance.$options.components.printer;
    createPrinterApp(printerComponent);
}

// Create the printer app
function createPrinterApp(PrinterComponent) {
    console.log('Creating printer app with component');
    
    const createApp = window.createApp;
    
    if (!createApp) {
        console.error('createApp function not available');
        return;
    }
    
    try {
        window.printerApp = createApp(PrinterComponent);
        
        // Copy global properties from main app if available
        if (window.appInstance && window.appInstance.config && window.appInstance.config.globalProperties) {
            const globalProps = window.appInstance.config.globalProperties;
            Object.keys(globalProps).forEach(key => {
                if (key !== '$el' && key !== '$root') {
                    window.printerApp.config.globalProperties[key] = globalProps[key];
                }
            });
        }
        
        window.printerApp.mount('#printer-app-container');
        console.log('Printer app mounted successfully');
    } catch (error) {
        console.error('Failed to mount printer app:', error);
    }
}

// Clean up function
function cleanupPrinterApp() {
    console.log('Cleaning up printer app...');
    if (window.printerApp) {
        try {
            window.printerApp.unmount();
        } catch (error) {
            console.error('Error unmounting printer app:', error);
        }
        window.printerApp = null;
        document.getElementById('printer-app-container').innerHTML = '';
    }
}

// Expose cleanup function globally
window.cleanupPrinterApp = cleanupPrinterApp;
</script>