{{-- resources/views/dashboard/modals/asinoption.blade.php --}}

<!-- ASIN Option Modal -->
<div class="modal fade" id="asinOptionModal" tabindex="-1" aria-labelledby="asinOptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="asinOptionModalLabel">
                    <i class="bi bi-list-check me-2"></i>
                    Choose ASIN Option
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 justify-content-center">
                    <!-- ASIN List Option -->
                    <div class="col-lg-4 col-md-6 col-sm-12">
                        <a href="#" class="asin-option-card asin-list-card text-decoration-none" 
                           onclick="selectAsinOption('asin-list')">
                            <div class="card-icon">
                                <i class="bi bi-file-text"></i>
                            </div>
                            <div class="card-title">ASIN List</div>
                            <div class="card-description">
                                View and manage your ASIN listings
                            </div>
                        </a>
                    </div>

                    <!-- FNSKU List Option -->
                    <div class="col-lg-4 col-md-6 col-sm-12">
                        <a href="#" class="asin-option-card fnsku-list-card text-decoration-none"
                           onclick="selectAsinOption('fnsku-list')">
                            <div class="card-icon">
                                <i class="bi bi-upc-scan"></i>
                            </div>
                            <div class="card-title">FNSKU List</div>
                            <div class="card-description">
                                View and manage your FNSKU codes
                            </div>
                        </a>
                    </div>

                    <!-- FNSKU Creation Option -->
                    <div class="col-lg-4 col-md-6 col-sm-12">
                        <a href="#" class="asin-option-card fnsku-creation-card text-decoration-none"
                           onclick="selectAsinOption('fnsku-creation')">
                            <div class="card-icon">
                                <i class="bi bi-plus-circle"></i>
                            </div>
                            <div class="card-title">FNSKU Creation</div>
                            <div class="card-description">
                                Create new FNSKU codes
                            </div>
                        </a>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12 text-center">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Select an option to proceed
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ASIN Option Modal Styles -->
<style>
    /* Custom styles for ASIN Option Modal */
    .asin-option-card {
        transition: all 0.3s ease;
        cursor: pointer;
        border: 2px solid #e9ecef;
        border-radius: 15px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        height: 180px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-decoration: none;
        padding: 1.5rem;
        text-align: center;
        width: 100%;
    }

    .asin-option-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        border-color: {{ session('theme_color', '#007bff') }};
        color: white;
        text-decoration: none;
    }

    .asin-option-card .card-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.9;
    }

    .asin-option-card .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .asin-option-card .card-description {
        font-size: 0.85rem;
        opacity: 0.85;
        line-height: 1.3;
        margin: 0;
    }

    .asin-list-card {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    }

    .fnsku-list-card {
        background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
    }

    .fnsku-creation-card {
        background: linear-gradient(135deg, #a8e6cf 0%, #56cc9d 100%);
    }

    #asinOptionModal .modal-dialog {
        max-width: 800px;
        width: 95%;
        margin: 1.75rem auto;
    }

    #asinOptionModal .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    }

    #asinOptionModal .modal-header {
        border-bottom: 1px solid #e9ecef;
        border-radius: 15px 15px 0 0;
        background: linear-gradient(135deg, {{ session('theme_color', '#007bff') }} 0%, {{ session('theme_color', '#007bff') }}88 100%);
        color: white;
        padding: 1.25rem 1.5rem;
    }

    #asinOptionModal .modal-header .btn-close {
        filter: invert(1);
    }

    #asinOptionModal .modal-body {
        padding: 2rem;
    }

    #asinOptionModal .modal-title {
        font-size: 1.2rem;
        font-weight: 600;
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
        /* 2 cards per row on medium screens */
        .asin-option-card {
            height: 160px;
        }
        
        .asin-option-card .card-icon {
            font-size: 2.5rem;
            margin-bottom: 0.8rem;
        }
        
        .asin-option-card .card-title {
            font-size: 1rem;
        }
        
        .asin-option-card .card-description {
            font-size: 0.8rem;
        }
    }

    @media (max-width: 768px) {
        #asinOptionModal .modal-dialog {
            max-width: 95%;
            margin: 1rem auto;
        }
        
        #asinOptionModal .modal-body {
            padding: 1.5rem;
        }
        
        .asin-option-card {
            height: 140px;
            padding: 1rem;
        }
        
        .asin-option-card .card-icon {
            font-size: 2rem;
            margin-bottom: 0.6rem;
        }
        
        .asin-option-card .card-title {
            font-size: 0.95rem;
            margin-bottom: 0.3rem;
        }
        
        .asin-option-card .card-description {
            font-size: 0.75rem;
        }
    }

    @media (max-width: 576px) {
        /* 1 card per row on small screens */
        .asin-option-card {
            height: 120px;
        }
        
        .asin-option-card .card-icon {
            font-size: 1.8rem;
        }
        
        #asinOptionModal .modal-body {
            padding: 1.25rem;
        }
    }
</style>

<!-- ASIN Option Modal JavaScript -->
<script>
    // Make sure these functions are available globally
      (function() {
        // Function to show ASIN Option Modal
        window.showAsinOptionModal = function() {
            console.log('Showing ASIN Option Modal...');
            const asinModal = new bootstrap.Modal(document.getElementById('asinOptionModal'));
            asinModal.show();
        };

        // Function to handle ASIN option selection
        window.selectAsinOption = function(optionType) {
            console.log('ASIN Option selected:', optionType);
            
            // Hide the modal first
            const asinModal = bootstrap.Modal.getInstance(document.getElementById('asinOptionModal'));
            if (asinModal) {
                asinModal.hide();
            }

            // Handle the selected option
            switch(optionType) {
                case 'asin-list':
                    console.log('Loading ASIN List component...');
                    // Load the ASIN List component (when implemented)
                    if (typeof window.loadContent === 'function') {
                        window.loadContent('asinlist');
                    } else {
                        showAsinAlert('ASIN List', 'ASIN List functionality will be implemented here.');
                    }
                    break;
                    
                case 'fnsku-list':
                    console.log('Loading FNSKU List component...');
                    // Load the FNSKU component (fnsku.vue)
                    if (typeof window.loadContent === 'function') {
                        window.loadContent('fnsku');
                    } else {
                        showAsinAlert('FNSKU List', 'Unable to load FNSKU List component.');
                    }
                    break;

                case 'fnsku-creation':
                    console.log('Loading FNSKU Creation component...');
                    // Load the MSKU Creation component (async loaded)
                    if (typeof window.loadContent === 'function') {
                        window.loadContent('mskucreation');
                    } else {
                        console.error('loadContent function not available');
                        showAsinAlert('FNSKU Creation', 'Unable to load FNSKU Creation component.');
                    }
                    break;
                    
                default:
                    console.log('Unknown option selected:', optionType);
                    break;
            }
        };

        // Helper function to show styled alerts
        function showAsinAlert(title, message) {
            const alertHtml = `
                <div class="alert alert-info alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                    <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>${title}</h6>
                    <p class="mb-0">${message}</p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert[style*="position: fixed"]');
                alerts.forEach(alert => alert.remove());
            }, 5000);
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('ASIN Option Modal initialized');
            
            // Test if modal exists
            const modalElement = document.getElementById('asinOptionModal');
            if (modalElement) {
                console.log('ASIN Option Modal element found');
                
                // Test the showAsinOptionModal function
                if (typeof window.showAsinOptionModal === 'function') {
                    console.log('showAsinOptionModal function is available');
                } else {
                    console.error('showAsinOptionModal function is not available!');
                }
            } else {
                console.error('ASIN Option Modal element not found!');
            }
        });
    })();
</script>