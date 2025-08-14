<!-- Settings Modal Structure -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="settingsModalLabel">Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs" id="settingsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="design-tab" data-bs-toggle="tab" data-bs-target="#design" 
                                type="button" role="tab" aria-controls="design" aria-selected="true">
                            <i class="bi bi-palette"></i>
                            <span> Title & Design</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="user-tab" data-bs-toggle="tab" data-bs-target="#user" 
                                type="button" role="tab" aria-controls="user" aria-selected="false">
                            <i class="bi bi-person-plus"></i>
                            <span> Add User</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="store-tab" data-bs-toggle="tab" data-bs-target="#store" 
                                type="button" role="tab" aria-controls="store" aria-selected="false">
                            <i class="bi bi-shop"></i>
                            <span> Store List</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="privilege-tab" data-bs-toggle="tab" data-bs-target="#privilege" 
                                type="button" role="tab" aria-controls="privilege" aria-selected="false">
                            <i class="bi bi-shield-lock"></i>
                            <span> Privileges</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="usertimerecord-tab" data-bs-toggle="tab" data-bs-target="#usertimerecord" 
                                type="button" role="tab" aria-controls="usertimerecord" aria-selected="false">
                            <i class="bi bi-clock"></i>
                            <span> Time Record</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="userlogs-tab" data-bs-toggle="tab" data-bs-target="#userlogs" 
                                type="button" role="tab" aria-controls="userlogs" aria-selected="false">
                            <i class="bi bi-person-lines-fill"></i>
                            <span> User Logs</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="printer-tab" data-bs-toggle="tab" data-bs-target="#printer" 
                                type="button" role="tab" aria-controls="printer" aria-selected="false">
                            <i class="bi bi-printer"></i>
                            <span> Printers</span>
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="settingsTabContent">
                    <!-- Design Tab -->
                    <div class="tab-pane fade show active" id="design" role="tabpanel" aria-labelledby="design-tab">
                        <!-- Your existing design tab content -->
                    </div>

                    <!-- User Tab -->
                    <div class="tab-pane fade" id="user" role="tabpanel" aria-labelledby="user-tab">
                        <!-- Your existing user tab content -->
                    </div>

                    <!-- Store Tab -->
                    <div class="tab-pane fade" id="store" role="tabpanel" aria-labelledby="store-tab">
                        <!-- Your existing store tab content -->
                    </div>

                    <!-- Privilege Tab -->
                    <div class="tab-pane fade" id="privilege" role="tabpanel" aria-labelledby="privilege-tab">
                        <!-- Your existing privilege tab content -->
                    </div>

                    <!-- Time Record Tab -->
                    <div class="tab-pane fade" id="usertimerecord" role="tabpanel" aria-labelledby="usertimerecord-tab">
                        <!-- Your existing time record tab content -->
                    </div>

                    <!-- User Logs Tab -->
                    <div class="tab-pane fade" id="userlogs" role="tabpanel" aria-labelledby="userlogs-tab">
                        <!-- Your existing user logs tab content -->
                    </div>

                    <!-- PRINTER TAB CONTENT - ADD THIS -->
                    <div class="tab-pane fade" id="printer" role="tabpanel" aria-labelledby="printer-tab">
                        <div class="container-fluid p-4">
                            <div class="row">
                                <div class="col-12">
                                    <h4 class="mb-4">
                                        <i class="bi bi-printer me-2"></i>
                                        Printer Management
                                    </h4>
                                    
                                    <!-- Printer Sub-tabs -->
                                    <ul class="nav nav-pills mb-4" id="printerSubTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="printer-list-tab" data-bs-toggle="pill" 
                                                    data-bs-target="#printer-list" type="button" role="tab" 
                                                    aria-controls="printer-list" aria-selected="true">
                                                <i class="bi bi-list-ul me-1"></i>
                                                All Printers
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="small-label-tab" data-bs-toggle="pill" 
                                                    data-bs-target="#small-label" type="button" role="tab" 
                                                    aria-controls="small-label" aria-selected="false">
                                                <i class="bi bi-tag me-1"></i>
                                                Small Label Printers
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="instruction-card-tab" data-bs-toggle="pill" 
                                                    data-bs-target="#instruction-card" type="button" role="tab" 
                                                    aria-controls="instruction-card" aria-selected="false">
                                                <i class="bi bi-card-text me-1"></i>
                                                Instruction Card Printers
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="married-printer-tab" data-bs-toggle="pill" 
                                                    data-bs-target="#married-printer" type="button" role="tab" 
                                                    aria-controls="married-printer" aria-selected="false">
                                                <i class="bi bi-arrow-through-heart me-1"></i>
                                                Married Printers
                                            </button>
                                        </li>
                                    </ul>

                                    <!-- Printer Sub-Tab Content -->
                                    <div class="tab-content" id="printerSubTabContent">
                                        
                                        <!-- All Printers Tab -->
                                        <div class="tab-pane fade show active" id="printer-list" role="tabpanel" aria-labelledby="printer-list-tab">
                                            <div class="card">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <h5 class="mb-0">
                                                        <i class="bi bi-printer me-2"></i>
                                                        All Printers
                                                    </h5>
                                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPrinterModal">
                                                        <i class="bi bi-plus-circle me-1"></i>
                                                        Add Printer
                                                    </button>
                                                </div>
                                                <div class="card-body">
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
                                                                <!-- Printer data will be loaded here -->
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Small Label Printers Tab -->
                                        <div class="tab-pane fade" id="small-label" role="tabpanel" aria-labelledby="small-label-tab">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="mb-0">
                                                        <i class="bi bi-tag me-2"></i>
                                                        Small Label Printers
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row" id="smallLabelPrintersGrid">
                                                        <!-- Small label printers will be loaded here as cards -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Instruction Card Printers Tab -->
                                        <div class="tab-pane fade" id="instruction-card" role="tabpanel" aria-labelledby="instruction-card-tab">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="mb-0">
                                                        <i class="bi bi-card-text me-2"></i>
                                                        Instruction Card Printers
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row" id="instructionCardPrintersGrid">
                                                        <!-- Instruction card printers will be loaded here as cards -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Married Printers Tab -->
                                        <div class="tab-pane fade" id="married-printer" role="tabpanel" aria-labelledby="married-printer-tab">
                                            <div class="card">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <h5 class="mb-0">
                                                        <i class="bi bi-arrow-through-heart me-2"></i>
                                                        Married Printers
                                                    </h5>
                                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#marryPrintersModal">
                                                        <i class="bi bi-plus-circle me-1"></i>
                                                        Marry Printers
                                                    </button>
                                                </div>
                                                <div class="card-body">
                                                    <div class="alert alert-info">
                                                        <i class="bi bi-info-circle me-2"></i>
                                                        Married printers allow you to pair a small label printer with an instruction card printer for synchronized printing.
                                                    </div>
                                                    <div id="marriedPrintersContainer">
                                                        <!-- Married printer pairs will be displayed here -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- END PRINTER TAB CONTENT -->

                </div>
            </div>
        </div>
    </div>
</div>

<!-- PRINTER MODALS - ADD THESE OUTSIDE THE SETTINGS MODAL -->

<!-- Add Printer Modal -->
<div class="modal fade" id="addPrinterModal" tabindex="-1" aria-labelledby="addPrinterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPrinterModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>
                    Add New Printer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addPrinterForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="printerName" class="form-label">Printer Name *</label>
                        <input type="text" class="form-control" id="printerName" name="printer_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="printerType" class="form-label">Printer Type *</label>
                        <select class="form-select" id="printerType" name="printer_type" required>
                            <option value="">Select Printer Type</option>
                            <option value="small_label">Small Label Printer</option>
                            <option value="instruction_card">Instruction Card Printer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="printerIP" class="form-label">IP Address *</label>
                        <input type="text" class="form-control" id="printerIP" name="ip_address" 
                               placeholder="192.168.1.100" required>
                    </div>
                    <div class="mb-3">
                        <label for="printerPort" class="form-label">Port</label>
                        <input type="number" class="form-control" id="printerPort" name="port" 
                               placeholder="9100" value="9100">
                    </div>
                    <div class="mb-3">
                        <label for="printerDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="printerDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>
                        Add Printer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Printer Modal -->
<div class="modal fade" id="editPrinterModal" tabindex="-1" aria-labelledby="editPrinterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPrinterModalLabel">
                    <i class="bi bi-pencil me-2"></i>
                    Edit Printer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPrinterForm">
                <div class="modal-body">
                    <input type="hidden" id="editPrinterId" name="printer_id">
                    <div class="mb-3">
                        <label for="editPrinterName" class="form-label">Printer Name *</label>
                        <input type="text" class="form-control" id="editPrinterName" name="printer_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPrinterType" class="form-label">Printer Type *</label>
                        <select class="form-select" id="editPrinterType" name="printer_type" required>
                            <option value="small_label">Small Label Printer</option>
                            <option value="instruction_card">Instruction Card Printer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editPrinterIP" class="form-label">IP Address *</label>
                        <input type="text" class="form-control" id="editPrinterIP" name="ip_address" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPrinterPort" class="form-label">Port</label>
                        <input type="number" class="form-control" id="editPrinterPort" name="port">
                    </div>
                    <div class="mb-3">
                        <label for="editPrinterDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editPrinterDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editPrinterStatus" class="form-label">Status</label>
                        <select class="form-select" id="editPrinterStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>
                        Update Printer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Marry Printers Modal -->
<div class="modal fade" id="marryPrintersModal" tabindex="-1" aria-labelledby="marryPrintersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="marryPrintersModalLabel">
                    <i class="bi bi-arrow-through-heart me-2"></i>
                    Marry Printers
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="marryPrintersForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Select a small label printer and an instruction card printer to create a married pair for synchronized printing.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="smallLabelPrinter" class="form-label">Small Label Printer *</label>
                                <select class="form-select" id="smallLabelPrinter" name="small_label_printer_id" required>
                                    <option value="">Select Small Label Printer</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="instructionCardPrinter" class="form-label">Instruction Card Printer *</label>
                                <select class="form-select" id="instructionCardPrinter" name="instruction_card_printer_id" required>
                                    <option value="">Select Instruction Card Printer</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="marriageName" class="form-label">Marriage Name *</label>
                        <input type="text" class="form-control" id="marriageName" name="marriage_name" 
                               placeholder="e.g., Warehouse A Label Station" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="marriageDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="marriageDescription" name="description" 
                                  rows="3" placeholder="Optional description for this printer pair"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-arrow-through-heart me-1"></i>
                        Marry Printers
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deletePrinterModal" tabindex="-1" aria-labelledby="deletePrinterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deletePrinterModalLabel">
                    <i class="bi bi-trash me-2"></i>
                    Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this printer?</p>
                <p class="text-danger"><strong>This action cannot be undone.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeletePrinter">
                    <i class="bi bi-trash me-1"></i>
                    Delete Printer
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.printer-card {
    transition: transform 0.2s ease-in-out;
}

.printer-card:hover {
    transform: translateY(-2px);
}

.status-badge {
    font-size: 0.75rem;
}

.married-printer-pair {
    border: 2px solid #28a745;
    border-radius: 10px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.printer-connection-line {
    border-top: 2px dashed #28a745;
    position: relative;
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
</style>