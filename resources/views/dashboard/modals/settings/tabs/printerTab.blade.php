{{-- resources/views/dashboard/modals/settings/tabs/printerTab.blade.php --}}
{{-- NOTE: Ensure your main layout includes @stack('modals') before </body> --}}

<div class="tab-pane fade" id="printer" role="tabpanel" aria-labelledby="printer-tab">
    <div class="container-fluid p-4">
        <div class="row">
            <div class="col-12">
                <h4 class="mb-4">
                    <i class="bi bi-printer me-2"></i>
                    Printer Management
                </h4>

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
                        <div class="tab-content" id="printerSubTabContent">

                            <!-- All Printers -->
                            <div class="tab-pane fade show active" id="printer-list" role="tabpanel"
                                aria-labelledby="printer-list-tab">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">
                                            <i class="bi bi-printer me-2"></i>
                                            All Printers
                                        </h5>
                                        <button type="button" class="btn btn-primary" onclick="showAddPrinterModal()">
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

                            <!-- Small Label -->
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

                            <!-- Instruction Card -->
                            <div class="tab-pane fade" id="instruction-card" role="tabpanel"
                                aria-labelledby="instruction-card-tab">
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

                            <!-- Married Printers -->
                            <div class="tab-pane fade" id="married-printer" role="tabpanel"
                                aria-labelledby="married-printer-tab">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">
                                            <i class="bi bi-arrow-through-heart me-2"></i>
                                            Married Printers
                                        </h5>
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal"
                                            data-bs-target="#marryPrintersModal">
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

                        </div><!-- /tab-content -->
                    </div>
                </div><!-- /card -->
            </div>
        </div>
    </div>
</div>

@push('modals')
    <!-- Add Printer Modal -->
    <div class="modal fade" id="addPrinterModal" tabindex="-1" aria-labelledby="addPrinterModalLabel"
         aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content printer-modal-content-fix">
                <div class="modal-header printer-modal-header-fix">
                    <h5 class="modal-title" id="addPrinterModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>
                        Add New Printer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <form id="addPrinterForm">
                    <div class="modal-body printer-modal-body-fix">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label for="addPrinterName" class="form-label printer-label-fix">
                                        <i class="bi bi-printer me-1 text-primary"></i>
                                        Printer Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control printer-input-fix" id="addPrinterName"
                                           name="printer_name" placeholder="Enter printer name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label for="addPrinterType" class="form-label printer-label-fix">
                                        <i class="bi bi-tag me-1 text-primary"></i>
                                        Printer Type <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select printer-input-fix" id="addPrinterType" name="printer_type" required>
                                        <option value="">Select Printer Type</option>
                                        <option value="small_label">🏷️ Small Label</option>
                                        <option value="instruction_card">📋 Instruction Card</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-8">
                                <div class="mb-2">
                                    <label for="addPrinterIP" class="form-label printer-label-fix">
                                        <i class="bi bi-globe me-1 text-primary"></i>
                                        IP Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control printer-input-fix" id="addPrinterIP" name="ip_address"
                                           placeholder="192.168.1.100" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <label for="addPrinterPort" class="form-label printer-label-fix">
                                        <i class="bi bi-plug me-1 text-primary"></i>
                                        Port
                                    </label>
                                    <input type="number" class="form-control printer-input-fix" id="addPrinterPort" name="port"
                                           value="9100" min="1" max="65535">
                                </div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="addPrinterDescription" class="form-label printer-label-fix">
                                <i class="bi bi-card-text me-1 text-primary"></i>
                                Description
                            </label>
                            <textarea class="form-control printer-input-fix" id="addPrinterDescription" name="description" rows="2"
                                      placeholder="Optional description for this printer"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer printer-modal-footer-fix">
                        <button type="button" class="btn btn-secondary printer-btn-fix" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary printer-btn-fix">
                            <i class="bi bi-plus-circle me-1"></i>Add Printer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Printer Modal -->
    <div class="modal fade" id="editPrinterModal" tabindex="-1" aria-labelledby="editPrinterModalLabel"
         aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content printer-modal-content-fix">
                <div class="modal-header printer-modal-header-fix">
                    <h5 class="modal-title" id="editPrinterModalLabel">
                        <i class="bi bi-pencil me-2"></i>
                        Edit Printer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editPrinterForm">
                    <div class="modal-body printer-modal-body-fix">
                        @csrf
                        <input type="hidden" id="editPrinterId" name="printer_id">

                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label for="editPrinterName" class="form-label printer-label-fix">
                                        <i class="bi bi-printer me-1 text-primary"></i>
                                        Printer Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control printer-input-fix" id="editPrinterName"
                                           name="printer_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label for="editPrinterType" class="form-label printer-label-fix">
                                        <i class="bi bi-tag me-1 text-primary"></i>
                                        Printer Type <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select printer-input-fix" id="editPrinterType" name="printer_type" required>
                                        <option value="">Select Printer Type</option>
                                        <option value="small_label">Small Label</option>
                                        <option value="instruction_card">Instruction Card</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-8">
                                <div class="mb-2">
                                    <label for="editPrinterIP" class="form-label printer-label-fix">
                                        <i class="bi bi-globe me-1 text-primary"></i>
                                        IP Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control printer-input-fix" id="editPrinterIP" name="ip_address"
                                           placeholder="192.168.1.100" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <label for="editPrinterPort" class="form-label printer-label-fix">
                                        <i class="bi bi-plug me-1 text-primary"></i>
                                        Port
                                    </label>
                                    <input type="number" class="form-control printer-input-fix" id="editPrinterPort" name="port"
                                           value="9100" min="1" max="65535">
                                </div>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label for="editPrinterStatus" class="form-label printer-label-fix">
                                        <i class="bi bi-circle-fill me-1 text-primary"></i>
                                        Status
                                    </label>
                                    <select class="form-select printer-input-fix" id="editPrinterStatus" name="status">
                                        <option value="active">🟢 Active</option>
                                        <option value="inactive">🔴 Inactive</option>
                                        <option value="maintenance">🟡 Maintenance</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6"></div>
                        </div>

                        <div class="mb-2">
                            <label for="editPrinterDescription" class="form-label printer-label-fix">
                                <i class="bi bi-card-text me-1 text-primary"></i>
                                Description
                            </label>
                            <textarea class="form-control printer-input-fix" id="editPrinterDescription" name="description" rows="2"
                                      placeholder="Optional description for this printer"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer printer-modal-footer-fix">
                        <button type="button" class="btn btn-secondary printer-btn-fix" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary printer-btn-fix">
                            <i class="bi bi-check-circle me-1"></i>Update Printer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Printer Confirmation Modal -->
    <div class="modal fade" id="deletePrinterModal" tabindex="-1" aria-labelledby="deletePrinterModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePrinterModalLabel">
                        <i class="bi bi-trash me-2"></i>
                        Delete Printer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this printer?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This action cannot be undone. If this printer is married, the marriage will also be dissolved.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeletePrinter">Delete Printer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Marry Printers Modal -->
    <div class="modal fade" id="marryPrintersModal" tabindex="-1" aria-labelledby="marryPrintersModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
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
                        @csrf
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Marriage allows two printers to work together: one for small labels and one for instruction cards.
                        </div>

                        <div class="mb-3">
                            <label for="smallLabelPrinter" class="form-label">Small Label Printer <span class="text-danger">*</span></label>
                            <select class="form-select" id="smallLabelPrinter" name="small_label_printer_id" required>
                                <option value="">Select Small Label Printer</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="instructionCardPrinter" class="form-label">Instruction Card Printer <span class="text-danger">*</span></label>
                            <select class="form-select" id="instructionCardPrinter" name="instruction_card_printer_id" required>
                                <option value="">Select Instruction Card Printer</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="marriageName" class="form-label">Marriage Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="marriageName" name="marriage_name"
                                   placeholder="e.g., Production Line 1" required>
                        </div>

                        <div class="mb-3">
                            <label for="marriageDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="marriageDescription" name="description" rows="3"
                                      placeholder="Optional description for this printer marriage"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Marry Printers</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endpush

<style>
/* ===== PRINTER TAB ONLY (safe to keep) ===== */
#printer .printer-card { transition: transform .2s ease-in-out; border: 1px solid #dee2e6; }
#printer .printer-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,.1); }
#printer .status-badge { font-size: .75rem; }
#printer .married-printer-pair { border:2px solid #28a745; border-radius:10px; background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%); margin-bottom:1rem; }
#printer .printer-connection-line { border-top:2px dashed #28a745; position:relative; margin:1rem 0; }
#printer .printer-connection-line::before { content:"♥"; position:absolute; top:-8px; left:50%; transform:translateX(-50%); background:#fff; color:#28a745; font-size:16px; padding:0 5px; }

#printer #printerSubTabs { background:#f8f9fa; }
#printer #printerSubTabs .nav-link { color:#495057; border:1px solid transparent; border-top-left-radius:.375rem; border-top-right-radius:.375rem; padding:.75rem 1rem; font-weight:500; }
#printer #printerSubTabs .nav-link:hover { color:#0056b3; background:#e9ecef; border-color:#dee2e6 #dee2e6 transparent; }
#printer #printerSubTabs .nav-link.active { color:#495057; background:#fff; border-color:#dee2e6 #dee2e6 #fff; z-index:1; }

#printer #printerSubTabContent { background:#fff; }
#printer #printerSubTabContent .tab-pane { display:none; min-height:300px; }
#printer #printerSubTabContent .tab-pane.show.active { display:block; }

#printer .card-header-tabs { margin-bottom:-1px; border-bottom:1px solid #dee2e6; }
#printer .table th { background:#343a40; color:#fff; font-weight:600; border-color:#454d55; }
#printer .table-hover tbody tr:hover { background:rgba(0,0,0,.075); }
#printer .alert { border-radius:.5rem; }
#printer .alert-info { background:#d1ecf1; border-color:#bee5eb; color:#0c5460; }
#printer .card { box-shadow:0 .125rem .25rem rgba(0,0,0,.075); border-radius:.5rem; }
#printer .card-header { background:#f8f9fa; border-bottom:1px solid #dee2e6; font-weight:600; }
#printer .badge { font-size:.75em; font-weight:500; }

@media (max-width: 768px) {
    #printer .table-responsive { font-size:.875rem; }
}

/* ===== MODAL VISUAL STYLES ONLY (no layout overrides) ===== */
.printer-modal-content-fix { border-radius:8px; border:none; box-shadow:0 10px 30px rgba(0,0,0,.3); overflow:hidden; }
.printer-modal-header-fix { background:linear-gradient(135deg,#007bff 0%,#0056b3 100%); color:#fff; padding:.75rem 1rem; border:none; }
.printer-modal-body-fix { padding:1rem; background:#f8f9fa; }
.printer-modal-footer-fix { padding:.75rem 1rem; background:#fff; border-top:1px solid #dee2e6; }
.printer-label-fix { font-size:.85rem; font-weight:600; margin-bottom:.25rem; color:#495057; }
.printer-input-fix { font-size:.9rem; padding:.45rem .65rem; border:1px solid #ced4da; border-radius:4px; background:#fff; }
.printer-input-fix:focus { border-color:#007bff; box-shadow:0 0 0 .15rem rgba(0,123,255,.1); outline:none; }
.printer-btn-fix { font-size:.9rem; padding:.45rem 1rem; border-radius:4px; font-weight:500; }
.printer-modal-body-fix .row { margin-bottom:.75rem; }
.printer-modal-body-fix .row:last-child { margin-bottom:0; }

@media (max-width: 768px) {
    .printer-modal-body-fix { padding:.85rem; }
    .printer-modal-header-fix { padding:.65rem .85rem; }
    .printer-modal-footer-fix { padding:.65rem .85rem; flex-direction:column; }
    .printer-btn-fix { width:100%; margin-bottom:.25rem; }
    .printer-btn-fix:last-child { margin-bottom:0; }
}

/* make sure printer modals are fully opaque */
#addPrinterModal .modal-content,
#editPrinterModal .modal-content {
  background: #fff;
  opacity: 1 !important;
  filter: none !important;
}

#settingsModal {
    z-index: 1050 !important;
}

#settingsModal.show {
    display: block !important;
}

/* Printer Modals - Top Layer */
#addPrinterModal,
#editPrinterModal,
#deletePrinterModal,
#marryPrintersModal {
    z-index: 1055 !important;
}

/* Modal Content Fixes - Prevent Gray Overlay Issues */
.modal-content {
    background-color: #fff !important;
    opacity: 1 !important;
    filter: none !important;
    pointer-events: auto !important;
}

.modal-dialog {
    pointer-events: auto !important;
    transform: none !important;
}

/* Backdrop Management - Prevent Interaction Issues */
.modal-backdrop {
    pointer-events: none !important;
}

.modal-backdrop.show {
    opacity: 0.5 !important;
}

/* Force visibility and interaction for printer modals */
.modal.show .modal-dialog .modal-content {
    opacity: 1 !important;
    transform: none !important;
    pointer-events: auto !important;
    background: #fff !important;
}

/* Ensure form elements are interactive */
.modal.show input,
.modal.show select,
.modal.show textarea,
.modal.show button {
    pointer-events: auto !important;
    opacity: 1 !important;
}

/* Fix for nested modal backdrop issues */
body.modal-open {
    overflow: hidden !important;
    padding-right: 0px !important;
}

/* Toast container - highest z-index */
.toast-container {
    z-index: 9999 !important;
}

/* Printer Tab Specific Styles (from your original CSS) */
#printer .printer-card { 
    transition: transform .2s ease-in-out; 
    border: 1px solid #dee2e6; 
}

#printer .printer-card:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 4px 8px rgba(0,0,0,.1); 
}

#printer .status-badge { 
    font-size: .75rem; 
}

#printer .married-printer-pair { 
    border: 2px solid #28a745; 
    border-radius: 10px; 
    background: linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%); 
    margin-bottom: 1rem; 
}

#printer .printer-connection-line { 
    border-top: 2px dashed #28a745; 
    position: relative; 
    margin: 1rem 0; 
}

#printer .printer-connection-line::before { 
    content: "♥"; 
    position: absolute; 
    top: -8px; 
    left: 50%; 
    transform: translateX(-50%); 
    background: #fff; 
    color: #28a745; 
    font-size: 16px; 
    padding: 0 5px; 
}

#printer #printerSubTabs { 
    background: #f8f9fa; 
}

#printer #printerSubTabs .nav-link { 
    color: #495057; 
    border: 1px solid transparent; 
    border-top-left-radius: .375rem; 
    border-top-right-radius: .375rem; 
    padding: .75rem 1rem; 
    font-weight: 500; 
}

#printer #printerSubTabs .nav-link:hover { 
    color: #0056b3; 
    background: #e9ecef; 
    border-color: #dee2e6 #dee2e6 transparent; 
}

#printer #printerSubTabs .nav-link.active { 
    color: #495057; 
    background: #fff; 
    border-color: #dee2e6 #dee2e6 #fff; 
    z-index: 1; 
}

#printer #printerSubTabContent { 
    background: #fff; 
}

#printer #printerSubTabContent .tab-pane { 
    display: none; 
    min-height: 300px; 
}

#printer #printerSubTabContent .tab-pane.show.active { 
    display: block; 
}

#printer .card-header-tabs { 
    margin-bottom: -1px; 
    border-bottom: 1px solid #dee2e6; 
}

#printer .table th { 
    background: #343a40; 
    color: #fff; 
    font-weight: 600; 
    border-color: #454d55; 
}

#printer .table-hover tbody tr:hover { 
    background: rgba(0,0,0,.075); 
}

#printer .alert { 
    border-radius: .5rem; 
}

#printer .alert-info { 
    background: #d1ecf1; 
    border-color: #bee5eb; 
    color: #0c5460; 
}

#printer .card { 
    box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); 
    border-radius: .5rem; 
}

#printer .card-header { 
    background: #f8f9fa; 
    border-bottom: 1px solid #dee2e6; 
    font-weight: 600; 
}

#printer .badge { 
    font-size: .75em; 
    font-weight: 500; 
}

@media (max-width: 768px) {
    #printer .table-responsive { 
        font-size: .875rem; 
    }
}

/* Modal Visual Styles (from your original CSS) */
.printer-modal-content-fix { 
    border-radius: 8px; 
    border: none; 
    box-shadow: 0 10px 30px rgba(0,0,0,.3); 
    overflow: hidden; 
}

.printer-modal-header-fix { 
    background: linear-gradient(135deg,#007bff 0%,#0056b3 100%); 
    color: #fff; 
    padding: .75rem 1rem; 
    border: none; 
}

.printer-modal-body-fix { 
    padding: 1rem; 
    background: #f8f9fa; 
}

.printer-modal-footer-fix { 
    padding: .75rem 1rem; 
    background: #fff; 
    border-top: 1px solid #dee2e6; 
}

.printer-label-fix { 
    font-size: .85rem; 
    font-weight: 600; 
    margin-bottom: .25rem; 
    color: #495057; 
}

.printer-input-fix { 
    font-size: .9rem; 
    padding: .45rem .65rem; 
    border: 1px solid #ced4da; 
    border-radius: 4px; 
    background: #fff; 
}

.printer-input-fix:focus { 
    border-color: #007bff; 
    box-shadow: 0 0 0 .15rem rgba(0,123,255,.1); 
    outline: none; 
}

.printer-btn-fix { 
    font-size: .9rem; 
    padding: .45rem 1rem; 
    border-radius: 4px; 
    font-weight: 500; 
}

.printer-modal-body-fix .row { 
    margin-bottom: .75rem; 
}

.printer-modal-body-fix .row:last-child { 
    margin-bottom: 0; 
}

@media (max-width: 768px) {
    .printer-modal-body-fix { 
        padding: .85rem; 
    }
    
    .printer-modal-header-fix { 
        padding: .65rem .85rem; 
    }
    
    .printer-modal-footer-fix { 
        padding: .65rem .85rem; 
        flex-direction: column; 
    }
    
    .printer-btn-fix { 
        width: 100%; 
        margin-bottom: .25rem; 
    }
    
    .printer-btn-fix:last-child { 
        margin-bottom: 0; 
    }
}

/* Make sure printer modals are fully opaque and interactive */
#addPrinterModal .modal-content,
#editPrinterModal .modal-content,
#deletePrinterModal .modal-content,
#marryPrintersModal .modal-content {
    background: #fff !important;
    opacity: 1 !important;
    filter: none !important;
    pointer-events: auto !important;
}

/* Prevent modal from being hidden behind other elements */
.modal.fade .modal-dialog {
    transition: transform 0.3s ease-out;
    transform: translate(0, -50px);
}

.modal.show .modal-dialog {
    transform: none;
}

/* Ensure proper stacking order */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1055;
    width: 100%;
    height: 100%;
    overflow-x: hidden;
    overflow-y: auto;
    outline: 0;
}

/* Additional safety for form interactions */
.modal-body input:focus,
.modal-body select:focus,
.modal-body textarea:focus {
    outline: none !important;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
    border-color: #007bff !important;
}

/* Spinner styles for loading states */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

.btn:disabled {
    opacity: 0.6;
}

/* Toast notification positioning */
.toast-container {
    position: fixed !important;
    top: 1rem !important;
    right: 1rem !important;
    z-index: 9999 !important;
}

.toast {
    min-width: 300px;
}

/* Prevent text selection on buttons */
.btn {
    user-select: none;
}
</style>

<script>
/**
 * Helper: open Add Printer modal (called by the button)
 */
function showAddPrinterModal() {
    var modalEl = document.getElementById('addPrinterModal');
    if (!modalEl) return;
    var instance = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static' });
    instance.show();
}
</script>
