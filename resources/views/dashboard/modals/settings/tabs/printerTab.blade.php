{{-- resources/views/dashboard/modals/settings/tabs/printerTab.blade.php --}}
<div class="tab-pane fade" id="printer" role="tabpanel" aria-labelledby="printer-tab">
    <div class="container-fluid p-4">
        <div class="row">
            <div class="col-12">
                <h4 class="mb-4">
                    <i class="bi bi-printer me-2"></i>
                    Printer Management
                </h4>
                
                <!-- Fixed Printer Sub-tabs with proper visibility -->
                <div class="card">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs card-header-tabs" id="printerSubTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="printer-list-tab" data-bs-toggle="tab" 
                                        data-bs-target="#printer-list" type="button" role="tab" 
                                        aria-controls="printer-list" aria-selected="true">
                                    <i class="bi bi-list-ul me-1"></i>
                                    All Printers
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="small-label-tab" data-bs-toggle="tab" 
                                        data-bs-target="#small-label" type="button" role="tab" 
                                        aria-controls="small-label" aria-selected="false">
                                    <i class="bi bi-tag me-1"></i>
                                    Small Label
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="instruction-card-tab" data-bs-toggle="tab" 
                                        data-bs-target="#instruction-card" type="button" role="tab" 
                                        aria-controls="instruction-card" aria-selected="false">
                                    <i class="bi bi-card-text me-1"></i>
                                    Instruction Card
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="married-printer-tab" data-bs-toggle="tab" 
                                        data-bs-target="#married-printer" type="button" role="tab" 
                                        aria-controls="married-printer" aria-selected="false">
                                    <i class="bi bi-arrow-through-heart me-1"></i>
                                    Married Printers
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card-body p-0">
                        <!-- Printer Sub-Tab Content -->
                        <div class="tab-content" id="printerSubTabContent">
                            
                            <!-- All Printers Tab -->
                            <div class="tab-pane fade show active" id="printer-list" role="tabpanel" aria-labelledby="printer-list-tab">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">
                                            <i class="bi bi-printer me-2"></i>
                                            All Printers
                                        </h5>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPrinterModal">
                                            <i class="bi bi-plus-circle me-1"></i>
                                            Add Printer
                                        </button>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Printer Name</th>
                                                    <th>Type</th>
                                                    <th>IP Address</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="allPrintersTableBody">
                                                <tr>
                                                    <td colspan="5" class="text-center">Loading printers...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Small Label Printers Tab -->
                            <div class="tab-pane fade" id="small-label" role="tabpanel" aria-labelledby="small-label-tab">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">
                                            <i class="bi bi-tag me-2"></i>
                                            Small Label Printers
                                        </h5>
                                    </div>
                                    
                                    <div class="row" id="smallLabelPrintersGrid">
                                        <div class="col-12">
                                            <div class="alert alert-info text-center">Click to load small label printers...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Instruction Card Printers Tab -->
                            <div class="tab-pane fade" id="instruction-card" role="tabpanel" aria-labelledby="instruction-card-tab">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">
                                            <i class="bi bi-card-text me-2"></i>
                                            Instruction Card Printers
                                        </h5>
                                    </div>
                                    
                                    <div class="row" id="instructionCardPrintersGrid">
                                        <div class="col-12">
                                            <div class="alert alert-info text-center">Click to load instruction card printers...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Married Printers Tab -->
                            <div class="tab-pane fade" id="married-printer" role="tabpanel" aria-labelledby="married-printer-tab">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">
                                            <i class="bi bi-arrow-through-heart me-2"></i>
                                            Married Printers
                                        </h5>
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#marryPrintersModal">
                                            <i class="bi bi-plus-circle me-1"></i>
                                            Marry Printers
                                        </button>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Married printers allow you to pair a small label printer with an instruction card printer for synchronized printing.
                                    </div>
                                    
                                    <div id="marriedPrintersContainer">
                                        <div class="alert alert-info text-center">Click to load married printers...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced styles for better visibility */
.printer-card {
    transition: transform 0.2s ease-in-out;
    border: 1px solid #dee2e6;
}

.printer-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.status-badge {
    font-size: 0.75rem;
}

.married-printer-pair {
    border: 2px solid #28a745;
    border-radius: 10px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    margin-bottom: 1rem;
}

.printer-connection-line {
    border-top: 2px dashed #28a745;
    position: relative;
    margin: 1rem 0;
}

.printer-connection-line::before {
    content: "♥";
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    color: #28a745;
    font-size: 16px;
    padding: 0 5px;
}

/* Ensure sub-tabs are visible */
#printerSubTabs .nav-link {
    color: #495057;
    border: 1px solid transparent;
    border-top-left-radius: 0.375rem;
    border-top-right-radius: 0.375rem;
    padding: 0.75rem 1rem;
    font-weight: 500;
}

#printerSubTabs .nav-link:hover {
    color: #0056b3;
    background-color: #e9ecef;
    border-color: #dee2e6 #dee2e6 #fff;
}

#printerSubTabs .nav-link.active {
    color: #495057;
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
}

/* Fix tab content display */
.tab-content > .tab-pane {
    display: none;
}

.tab-content > .active {
    display: block;
}

/* Ensure proper spacing and visibility */
.card-header-tabs {
    margin-bottom: -1px;
}

.card-header-tabs .nav-link {
    margin-bottom: -1px;
    border: 1px solid transparent;
}

.card-header-tabs .nav-link.active {
    color: var(--bs-gray-900);
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color) var(--bs-border-color) var(--bs-body-bg);
}
</style>