<!-- Enhanced Marry Printers Modal -->
<div class="modal modal-printer fade" id="marryPrintersModal" tabindex="-1" aria-labelledby="marryPrintersModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content marry-modal-content">
            <div class="modal-header marry-modal-header">
                <h5 class="modal-title" id="marryPrintersModalLabel">
                    <i class="bi bi-heart-fill me-2 text-white"></i>
                    Marry Printers
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="marryPrintersForm">
                <div class="modal-body marry-modal-body-compact">
                    @csrf

                    <!-- Compact Header Info -->
                    <div class="marriage-info-card-compact">
                        <div class="d-flex align-items-center">
                            <div class="marriage-icon-wrapper-compact">
                                <i class="bi bi-heart-fill"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0">Create Printer Marriage</h6>
                                <small class="text-muted">Join two printers for synchronized operations</small>
                            </div>
                        </div>
                    </div>

                    <!-- Compact Printer Selection -->
                    <div class="marriage-selection-compact">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="printer-card-compact small-label-compact">
                                    <div class="card-header-compact">
                                        <i class="bi bi-tag-fill me-2"></i>Small Label Printer
                                    </div>
                                    <select class="form-select compact-printer-select" id="smallLabelPrinter"
                                        name="small_label_printer_id" required>
                                        <option value="">Choose label printer...</option>
                                    </select>
                                    <div class="invalid-feedback compact-feedback">Please select a printer</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="printer-card-compact instruction-card-compact">
                                    <div class="card-header-compact">
                                        <i class="bi bi-card-text-fill me-2"></i>Instruction Card Printer
                                    </div>
                                    <select class="form-select compact-printer-select" id="instructionCardPrinter"
                                        name="instruction_card_printer_id" required>
                                        <option value="">Choose card printer...</option>
                                    </select>
                                    <div class="invalid-feedback compact-feedback">Please select a printer</div>
                                </div>
                            </div>
                        </div>

                        <!-- Compact Connection Heart -->
                        <div class="connection-heart-compact">
                            <i class="bi bi-heart-fill"></i>
                        </div>
                    </div>

                    <!-- Compact Marriage Details -->
                    <div class="compact-form-grid">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="marriageName" class="compact-label">
                                    <i class="bi bi-tag me-1"></i>Marriage Name <span class="text-danger">*</span>
                                </label>
                                <div class="compact-input-wrapper">
                                    <input type="text" class="form-control compact-input" id="marriageName"
                                        name="marriage_name" placeholder="e.g., Production Line 1" required>
                                    <i class="bi bi-tag compact-input-icon"></i>
                                </div>
                                <div class="invalid-feedback compact-feedback">Please provide a marriage name</div>
                            </div>
                            <div class="col-md-4">
                                <!-- Spacer for better layout -->
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="marriageDescription" class="compact-label">
                                    <i class="bi bi-card-text me-1"></i>Description <span
                                        class="text-muted">(Optional)</span>
                                </label>
                                <textarea class="form-control compact-input compact-textarea" id="marriageDescription"
                                    name="description" rows="2"
                                    placeholder="Optional description for this printer marriage..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer marry-modal-footer-compact">
                    <button type="button" class="btn btn-light compact-cancel-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success compact-submit-btn">
                        <i class="bi bi-heart-fill me-1"></i>Create Marriage
                    </button>
                </div>
            </form>
        </div>
    </div>
</div> <!-- Enhanced Edit Printer Modal -->

<div class="modal modal-printer fade" id="editPrinterModal" tabindex="-1" aria-labelledby="editPrinterModalLabel"
    aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content edit-printer-modal-content">
            <div class="modal-header edit-printer-modal-header">
                <h5 class="modal-title" id="editPrinterModalLabel">
                    <i class="bi bi-pencil-square me-2 text-white"></i>
                    Edit Printer
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="editPrinterForm">
                <div class="modal-body edit-printer-modal-body">
                    @csrf
                    <input type="hidden" id="editPrinterId" name="printer_id">

                    <!-- Compact Header Info -->
                    <div class="printer-info-header-compact">
                        <div class="d-flex align-items-center">
                            <div class="printer-icon-wrapper-compact edit-printer-icon">
                                <i class="bi bi-gear-fill"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0">Update Printer Settings</h6>
                                <small class="text-muted">Modify configuration settings</small>
                            </div>
                        </div>
                    </div>

                    <!-- Compact Form Grid -->
                    <div class="compact-form-grid">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="editPrinterName" class="compact-label">
                                    <i class="bi bi-printer me-1"></i>Printer Name <span class="text-danger">*</span>
                                </label>
                                <div class="compact-input-wrapper">
                                    <input type="text" class="form-control compact-input" id="editPrinterName"
                                        name="printer_name" required>
                                    <i class="bi bi-printer compact-input-icon"></i>
                                </div>
                                <div class="invalid-feedback compact-feedback">Required field</div>
                            </div>
                            <div class="col-md-6">
                                <label for="editPrinterType" class="compact-label">
                                    <i class="bi bi-tag me-1"></i>Type <span class="text-danger">*</span>
                                </label>
                                <div class="compact-input-wrapper">
                                    <select class="form-select compact-input compact-select" id="editPrinterType"
                                        name="printer_type" required>
                                        <option value="">Select Type</option>
                                        <option value="small_label">🏷️ Small Label</option>
                                        <option value="instruction_card">📋 Instruction Card</option>
                                    </select>
                                    <i class="bi bi-tag compact-input-icon"></i>
                                </div>
                                <div class="invalid-feedback compact-feedback">Please select type</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="editPrinterIP" class="compact-label">
                                    <i class="bi bi-globe me-1"></i>IP Address <span class="text-danger">*</span>
                                </label>
                                <div class="compact-input-wrapper">
                                    <input type="text" class="form-control compact-input" id="editPrinterIP"
                                        name="ip_address" placeholder="192.168.1.100" required>
                                    <i class="bi bi-globe compact-input-icon"></i>
                                </div>
                                <div class="invalid-feedback compact-feedback">Valid IP required</div>
                            </div>
                            <div class="col-md-3">
                                <label for="editPrinterPort" class="compact-label">
                                    <i class="bi bi-plug me-1"></i>Port
                                </label>
                                <div class="compact-input-wrapper">
                                    <input type="number" class="form-control compact-input" id="editPrinterPort"
                                        name="port" value="9100" min="1" max="65535">
                                    <i class="bi bi-plug compact-input-icon"></i>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="editPrinterStatus" class="compact-label">
                                    <i class="bi bi-circle-fill me-1"></i>Status
                                </label>
                                <div class="compact-input-wrapper">
                                    <select class="form-select compact-input compact-select" id="editPrinterStatus"
                                        name="status">
                                        <option value="active">🟢 Active</option>
                                        <option value="inactive">🔴 Inactive</option>
                                        <option value="maintenance">🟡 Maintenance</option>
                                    </select>
                                    <i class="bi bi-circle-fill compact-input-icon"></i>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="editPrinterDescription" class="compact-label">
                                    <i class="bi bi-card-text me-1"></i>Description <span
                                        class="text-muted">(Optional)</span>
                                </label>
                                <textarea class="form-control compact-input compact-textarea"
                                    id="editPrinterDescription" name="description" rows="2"
                                    placeholder="Update printer description or notes..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer edit-printer-modal-footer">
                    <button type="button" class="btn btn-light compact-cancel-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary compact-submit-btn">
                        <i class="bi bi-check-circle me-1"></i>Update Printer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>{{-- resources/views/dashboard/modals/settings/tabs/printerTab.blade.php --}}
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
                                    data-bs-target="#printer-list" type="button" role="tab" aria-controls="printer-list"
                                    aria-selected="true">
                                    <i class="bi bi-list-ul me-1"></i>
                                    All Printers
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="small-label-tab" data-bs-toggle="tab"
                                    data-bs-target="#small-label" type="button" role="tab" aria-controls="small-label"
                                    aria-selected="false">
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
                            <div class="tab-pane fade" id="small-label" role="tabpanel"
                                aria-labelledby="small-label-tab">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">
                                            <i class="bi bi-tag me-2"></i>
                                            Small Label Printers
                                        </h5>
                                    </div>

                                    <div class="row" id="smallLabelPrintersGrid">
                                        <div class="col-12">
                                            <div class="alert alert-info text-center">Click to load small label
                                                printers...</div>
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
                                            <div class="alert alert-info text-center">Click to load instruction card
                                                printers...</div>
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
                                        Married printers allow you to pair a small label printer with an instruction
                                        card printer for synchronized printing.
                                    </div>

                                    <div id="marriedPrintersContainer">
                                        <div class="alert alert-info text-center">Click to load married printers...
                                        </div>
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
    <!-- Enhanced Add Printer Modal -->
    <div class="modal modal-printer fade" id="addPrinterModal" tabindex="-1" aria-labelledby="addPrinterModalLabel"
        aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content add-printer-modal-content">
                <div class="modal-header add-printer-modal-header">
                    <h5 class="modal-title" id="addPrinterModalLabel">
                        <i class="bi bi-plus-circle me-2 text-white"></i>
                        Add New Printer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form id="addPrinterForm">
                    <div class="modal-body add-printer-modal-body">
                        @csrf

                        <!-- Compact Header Info -->
                        <div class="printer-info-header-compact">
                            <div class="d-flex align-items-center">
                                <div class="printer-icon-wrapper-compact add-printer-icon">
                                    <i class="bi bi-printer-fill"></i>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-0">Configure New Printer</h6>
                                    <small class="text-muted">Set up printer for your operations</small>
                                </div>
                            </div>
                        </div>

                        <!-- Compact Form Grid -->
                        <div class="compact-form-grid">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="addPrinterName" class="compact-label">
                                        <i class="bi bi-printer me-1"></i>Printer Name <span class="text-danger">*</span>
                                    </label>
                                    <div class="compact-input-wrapper">
                                        <input type="text" class="form-control compact-input" id="addPrinterName"
                                            name="printer_name" placeholder="Enter printer name" required>
                                        <i class="bi bi-printer compact-input-icon"></i>
                                    </div>
                                    <div class="invalid-feedback compact-feedback">Required field</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="addPrinterType" class="compact-label">
                                        <i class="bi bi-tag me-1"></i>Type <span class="text-danger">*</span>
                                    </label>
                                    <div class="compact-input-wrapper">
                                        <select class="form-select compact-input compact-select" id="addPrinterType"
                                            name="printer_type" required>
                                            <option value="">Select Type</option>
                                            <option value="small_label">🏷️ Small Label</option>
                                            <option value="instruction_card">📋 Instruction Card</option>
                                        </select>
                                        <i class="bi bi-tag compact-input-icon"></i>
                                    </div>
                                    <div class="invalid-feedback compact-feedback">Please select type</div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="addPrinterIP" class="compact-label">
                                        <i class="bi bi-globe me-1"></i>IP Address <span class="text-danger">*</span>
                                    </label>
                                    <div class="compact-input-wrapper">
                                        <input type="text" class="form-control compact-input" id="addPrinterIP"
                                            name="ip_address" placeholder="192.168.1.100" required>
                                        <i class="bi bi-globe compact-input-icon"></i>
                                    </div>
                                    <div class="invalid-feedback compact-feedback">Valid IP required</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="addPrinterPort" class="compact-label">
                                        <i class="bi bi-plug me-1"></i>Port
                                    </label>
                                    <div class="compact-input-wrapper">
                                        <input type="number" class="form-control compact-input" id="addPrinterPort"
                                            name="port" value="9100" min="1" max="65535">
                                        <i class="bi bi-plug compact-input-icon"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="addPrinterDescription" class="compact-label">
                                        <i class="bi bi-card-text me-1"></i>Description <span
                                            class="text-muted">(Optional)</span>
                                    </label>
                                    <textarea class="form-control compact-input compact-textarea" id="addPrinterDescription"
                                        name="description" rows="2"
                                        placeholder="Optional notes about this printer..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer add-printer-modal-footer">
                        <button type="button" class="btn btn-light compact-cancel-btn" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-success compact-submit-btn">
                            <i class="bi bi-plus-circle me-1"></i>Add Printer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Printer Modal -->
    <div class="modal modal-printer fade" id="editPrinterModal" tabindex="-1" aria-labelledby="editPrinterModalLabel"
        aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content printer-modal-content-fix">
                <div class="modal-header printer-modal-header-fix">
                    <h5 class="modal-title" id="editPrinterModalLabel">
                        <i class="bi bi-pencil me-2"></i>
                        Edit Printer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
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
                                    <div class="invalid-feedback">
                                        Please provide a valid printer name.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label for="editPrinterType" class="form-label printer-label-fix">
                                        <i class="bi bi-tag me-1 text-primary"></i>
                                        Printer Type <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select printer-input-fix" id="editPrinterType" name="printer_type"
                                        required>
                                        <option value="">Select Printer Type</option>
                                        <option value="small_label">🏷️ Small Label</option>
                                        <option value="instruction_card">📋 Instruction Card</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Please select a printer type.
                                    </div>
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
                                    <input type="text" class="form-control printer-input-fix" id="editPrinterIP"
                                        name="ip_address" placeholder="192.168.1.100" required>
                                    <div class="invalid-feedback">
                                        Please provide a valid IP address.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <label for="editPrinterPort" class="form-label printer-label-fix">
                                        <i class="bi bi-plug me-1 text-primary"></i>
                                        Port
                                    </label>
                                    <input type="number" class="form-control printer-input-fix" id="editPrinterPort"
                                        name="port" value="9100" min="1" max="65535">
                                    <div class="invalid-feedback">
                                        Please provide a valid port number (1-65535).
                                    </div>
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
                            <textarea class="form-control printer-input-fix" id="editPrinterDescription" name="description"
                                rows="2" placeholder="Optional description for this printer"></textarea>
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
    <div class="modal modal-printer fade" id="deletePrinterModal" tabindex="-1" aria-labelledby="deletePrinterModalLabel"
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

    <!-- Enhanced Marry Printers Modal -->
    <div class="modal modal-printer fade" id="marryPrintersModal" tabindex="-1" aria-labelledby="marryPrintersModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content marry-modal-content">
                <div class="modal-header marry-modal-header">
                    <h5 class="modal-title" id="marryPrintersModalLabel">
                        <i class="bi bi-heart-fill me-2 text-white"></i>
                        Unite Printers in Marriage
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form id="marryPrintersForm">
                    <div class="modal-body marry-modal-body">
                        @csrf

                        <!-- Header Info Card -->
                        <div class="marriage-info-card">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="marriage-icon-wrapper">
                                        <i class="bi bi-heart-fill"></i>
                                    </div>
                                </div>
                                <div class="col">
                                    <h6 class="mb-1">Create Printer Marriage</h6>
                                    <p class="mb-0 text-muted">Join two printers to work together seamlessly for
                                        synchronized printing operations.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Printer Selection Section -->
                        <div class="marriage-selection-section">
                            <div class="row g-4">
                                <!-- Small Label Printer -->
                                <div class="col-md-6">
                                    <div class="printer-selection-card small-label-card">
                                        <div class="card-icon">
                                            <i class="bi bi-tag-fill"></i>
                                        </div>
                                        <div class="card-content">
                                            <h6 class="card-title">Small Label Printer</h6>
                                            <p class="card-subtitle">For product labels and tags</p>
                                            <select class="form-select printer-select" id="smallLabelPrinter"
                                                name="small_label_printer_id" required>
                                                <option value="">Choose your label printer...</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Please select a small label printer.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Connection Heart -->
                                <div class="col-md-12 d-md-none">
                                    <div class="connection-heart-mobile">
                                        <i class="bi bi-heart-fill"></i>
                                    </div>
                                </div>

                                <!-- Instruction Card Printer -->
                                <div class="col-md-6">
                                    <div class="printer-selection-card instruction-card-card">
                                        <div class="card-icon">
                                            <i class="bi bi-card-text-fill"></i>
                                        </div>
                                        <div class="card-content">
                                            <h6 class="card-title">Instruction Card Printer</h6>
                                            <p class="card-subtitle">For detailed instructions and cards</p>
                                            <select class="form-select printer-select" id="instructionCardPrinter"
                                                name="instruction_card_printer_id" required>
                                                <option value="">Choose your card printer...</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Please select an instruction card printer.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Desktop Connection Heart -->
                            <div class="connection-heart-desktop d-none d-md-block">
                                <div class="heart-connector">
                                    <div class="connector-line"></div>
                                    <div class="heart-icon">
                                        <i class="bi bi-heart-fill"></i>
                                    </div>
                                    <div class="connector-line"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Marriage Details Section -->
                        <div class="marriage-details-section">
                            <div class="section-header">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Marriage Details
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="marriageName" class="form-label marriage-label">
                                        <i class="bi bi-tag me-1"></i>
                                        Marriage Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control marriage-input" id="marriageName"
                                        name="marriage_name" placeholder="e.g., Production Line Alpha, Warehouse Station 1"
                                        required>
                                    <div class="invalid-feedback">
                                        Please provide a meaningful name for this marriage.
                                    </div>
                                    <div class="form-text">
                                        <i class="bi bi-lightbulb me-1"></i>
                                        Choose a name that identifies where these printers will be used together.
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="marriageDescription" class="form-label marriage-label">
                                        <i class="bi bi-card-text me-1"></i>
                                        Description <span class="text-muted">(Optional)</span>
                                    </label>
                                    <textarea class="form-control marriage-input" id="marriageDescription"
                                        name="description" rows="3"
                                        placeholder="Describe the purpose of this printer marriage, location, or any special notes..."></textarea>
                                    <div class="form-text">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Add details about where these printers are located or how they'll be used together.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer marry-modal-footer">
                        <button type="button" class="btn btn-light marry-cancel-btn" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-success marry-submit-btn">
                            <i class="bi bi-heart-fill me-1"></i>
                            Create Marriage
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endpush

<style scoped>
    /* ===== ENHANCED MODAL MANAGEMENT CSS ===== */

    /* Base modal z-index hierarchy */

    /* Printer modals - higher layer */
    .modal-printer {
        z-index: 1055 !important;
    }

    .modal-printer .modal-backdrop {
        z-index: 1050 !important;
    }

    /* Force modal content to be fully opaque and interactive */
    .modal-printer .modal-content {
        background-color: #fff !important;
        opacity: 1 !important;
        filter: none !important;
        pointer-events: auto !important;
        position: relative !important;
        z-index: 1 !important;
    }

    .modal-printer .modal-dialog {
        pointer-events: auto !important;
        opacity: 1 !important;
    }

    /* Ensure modal backdrops don't interfere */
    .modal-printer .modal-backdrop {
        pointer-events: none !important;
        background-color: rgba(0, 0, 0, 0.5) !important;
    }

    .modal-printer .modal-backdrop.show {
        opacity: 0.5 !important;
    }

    /* Prevent multiple backdrop stacking */
    .modal-printer .modal-backdrop+.modal-backdrop {
        display: none !important;
    }

    /* Force visibility and interaction for all modal elements */

    .modal-printer .modal.show {
        display: block !important;
        opacity: 1 !important;
    }

    .modal-printer .modal.show .modal-dialog {
        transform: none !important;
        opacity: 1 !important;
    }

    .modal-printer .modal.show .modal-content {
        opacity: 1 !important;
        transform: none !important;
        pointer-events: auto !important;
        background: #fff !important;
    }

    .modal.modal-printer .show input,
    .modal.modal-printer .show select,
    .modal.modal-printer .show textarea,
    .modal.modal-printer .show button {
        pointer-events: auto !important;
        opacity: 1 !important;
    }

    /* Body modal-open state management */
    body.modal-open {
        overflow: hidden !important;
        padding-right: 0px !important;
    }

    /* Toast container - highest z-index */
    .modal-printer .toast-container {
        z-index: 9999 !important;
        position: fixed !important;
        top: 1rem !important;
        right: 1rem !important;
    }

    /* Critical fixes for backdrop issues */
    .modal-printer body:not(.modal-open) .modal-backdrop {
        display: none !important;
    }

    /* Ensure only one backdrop exists at a time */
    .modal-printer .modal-backdrop:not(:last-of-type) {
        display: none !important;
    }

    /* Force printer modals to be on top */
    body.modal-open .modal[id*="Printer"] {
        z-index: 1055 !important;
    }

    body.modal-open .modal[id*="Printer"]+.modal-backdrop {
        z-index: 1050 !important;
    }

    /* Prevent gray overlay stacking */
    .modal-printer .modal-backdrop.fade {
        opacity: 0;
        transition: opacity 0.15s linear;
    }

    .modal-printer .modal-backdrop.show {
        opacity: 0.5;
    }

    /* ===== PRINTER TAB SPECIFIC STYLES ===== */
    #printer .printer-card {
        transition: transform .2s ease-in-out;
        border: 1px solid #dee2e6;
    }

    #printer .printer-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, .1);
    }

    #printer .status-badge {
        font-size: .75rem;
    }

    #printer .married-printer-pair {
        border: 2px solid #28a745;
        border-radius: 10px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
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
        background: rgba(0, 0, 0, .075);
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
        box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075);
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

    /* ===== COMPACT ENHANCED MODAL STYLES ===== */

    /* Add Printer Modal - Compact */
    .add-printer-modal-content {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    }

    .add-printer-modal-header {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 1rem 1.5rem;
        border: none;
        position: relative;
    }

    .add-printer-modal-header::before {
        content: '';
        position: absolute;
        top: -30%;
        right: -5%;
        width: 60px;
        height: 60px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        animation: float 5s ease-in-out infinite;
    }

    .add-printer-modal-body {
        padding: 1.5rem;
        background: #ffffff;
    }

    .add-printer-modal-footer {
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        padding: 1rem 1.5rem;
        gap: 0.75rem;
    }

    /* Edit Printer Modal - Compact */
    .edit-printer-modal-content {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    }

    .edit-printer-modal-header {
        background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
        color: white;
        padding: 1rem 1.5rem;
        border: none;
        position: relative;
    }

    .edit-printer-modal-header::before {
        content: '';
        position: absolute;
        top: -30%;
        left: -5%;
        width: 60px;
        height: 60px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        animation: float 4s ease-in-out infinite reverse;
    }

    .edit-printer-modal-body {
        padding: 1.5rem;
        background: #ffffff;
    }

    .edit-printer-modal-footer {
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        padding: 1rem 1.5rem;
        gap: 0.75rem;
    }

    /* Marry Modal - Compact */
    .marry-modal-body-compact {
        padding: 1.5rem;
        background: #ffffff;
    }

    .marry-modal-footer-compact {
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        padding: 1rem 1.5rem;
        gap: 0.75rem;
    }

    /* Compact Header Info */
    .printer-info-header-compact {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        border: 1px solid #2196f3;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }

    .printer-icon-wrapper-compact {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .marriage-info-card-compact {
        background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
        border: 1px solid #ffcc02;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }

    .marriage-icon-wrapper-compact {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, #e91e63, #ad1457);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        box-shadow: 0 4px 12px rgba(233, 30, 99, 0.2);
    }

    /* Compact Form Grid */
    .compact-form-grid {
        gap: 1rem;
    }

    .compact-form-grid .row {
        margin-bottom: 1rem;
    }

    .compact-form-grid .row:last-child {
        margin-bottom: 0;
    }

    .compact-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        display: block;
    }

    .compact-input-wrapper {
        position: relative;
    }

    .compact-input {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 0.6rem 0.75rem;
        padding-right: 2.5rem;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        background: white;
    }

    .compact-input:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.15rem rgba(0, 123, 255, 0.1);
        outline: none;
    }

    .compact-textarea {
        resize: vertical;
        min-height: 70px;
    }

    .compact-input-icon {
        position: absolute;
        right: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        font-size: 0.9rem;
        pointer-events: none;
    }

    .compact-select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 6.5 6L14 6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 2.5rem center;
        background-size: 12px 10px;
    }

    .compact-feedback {
        display: none;
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.8rem;
        color: #dc3545;
        font-weight: 500;
    }

    /* Compact Marriage Selection */
    .marriage-selection-compact {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .printer-card-compact {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 1rem;
        transition: all 0.2s ease;
    }

    .printer-card-compact:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .small-label-compact {
        border-left: 4px solid #007bff;
        background: linear-gradient(135deg, #ffffff 0%, #f0f8ff 100%);
    }

    .instruction-card-compact {
        border-left: 4px solid #17a2b8;
        background: linear-gradient(135deg, #ffffff 0%, #f0fdff 100%);
    }

    .card-header-compact {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.75rem;
        font-size: 0.9rem;
    }

    .compact-printer-select {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 0.6rem;
        font-size: 0.9rem;
        background: white;
        transition: all 0.2s ease;
    }

    .compact-printer-select:focus {
        border-color: #e91e63;
        box-shadow: 0 0 0 0.15rem rgba(233, 30, 99, 0.1);
        outline: none;
    }

    .connection-heart-compact {
        text-align: center;
        margin: 1rem 0;
    }

    .connection-heart-compact i {
        font-size: 1.5rem;
        color: #e91e63;
        animation: heartbeat 2s ease-in-out infinite;
    }

    /* Compact Buttons */
    .compact-cancel-btn {
        border: 1px solid #dee2e6;
        color: #495057;
        font-weight: 500;
        padding: 0.6rem 1.25rem;
        border-radius: 6px;
        transition: all 0.2s ease;
        background: white;
    }

    .compact-cancel-btn:hover {
        background: #f8f9fa;
        border-color: #adb5bd;
    }

    .compact-submit-btn {
        font-weight: 600;
        padding: 0.6rem 1.5rem;
        border-radius: 6px;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }

    .add-printer-modal-footer .compact-submit-btn {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .add-printer-modal-footer .compact-submit-btn:hover {
        background: linear-gradient(135deg, #218838, #1e7e34);
        transform: translateY(-1px);
    }

    .edit-printer-modal-footer .compact-submit-btn {
        background: linear-gradient(135deg, #007bff, #6610f2);
        color: white;
    }

    .edit-printer-modal-footer .compact-submit-btn:hover {
        background: linear-gradient(135deg, #0056b3, #520dc2);
        transform: translateY(-1px);
    }

    .marry-modal-footer-compact .compact-submit-btn {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .marry-modal-footer-compact .compact-submit-btn:hover {
        background: linear-gradient(135deg, #218838, #1e7e34);
        transform: translateY(-1px);
    }

    .compact-submit-btn:disabled {
        background: #6c757d !important;
        transform: none !important;
        opacity: 0.6;
    }

    /* Enhanced Validation for Compact */
    .was-validated .compact-input:valid {
        border-color: #28a745;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.98-.93-.99-.94'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 2.25rem center;
        background-size: 0.8rem;
    }

    .was-validated .compact-input:invalid {
        border-color: #dc3545;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6 .4 5 .4-5'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 2.25rem center;
        background-size: 0.8rem;
    }

    .was-validated .compact-printer-select:valid {
        border-color: #28a745;
    }

    .was-validated .compact-printer-select:invalid {
        border-color: #dc3545;
    }

    .was-validated .compact-input:invalid~.compact-feedback,
    .was-validated .compact-printer-select:invalid~.compact-feedback {
        display: block;
    }

    /* Success states for printer cards */
    .printer-card-compact.selected {
        border-left-width: 4px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .small-label-compact.selected {
        border-left-color: #28a745;
        background: linear-gradient(135deg, #ffffff 0%, #f0fff4 100%);
    }

    .instruction-card-compact.selected {
        border-left-color: #28a745;
        background: linear-gradient(135deg, #ffffff 0%, #f0fff4 100%);
    }

    /* Responsive Compact Design */
    @media (max-width: 768px) {

        .add-printer-modal-body,
        .edit-printer-modal-body,
        .marry-modal-body-compact {
            padding: 1rem;
        }

        .add-printer-modal-footer,
        .edit-printer-modal-footer,
        .marry-modal-footer-compact {
            padding: 0.75rem 1rem;
            flex-direction: column;
        }

        .compact-cancel-btn,
        .compact-submit-btn {
            width: 100%;
            margin-bottom: 0.5rem;
            padding: 0.75rem;
        }

        .compact-submit-btn {
            margin-bottom: 0;
        }

        .printer-info-header-compact,
        .marriage-info-card-compact {
            padding: 0.75rem;
            margin-bottom: 1rem;
        }

        .printer-icon-wrapper-compact,
        .marriage-icon-wrapper-compact {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }

        .printer-card-compact {
            padding: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .compact-input {
            padding: 0.5rem 0.75rem;
            padding-right: 2rem;
            font-size: 0.85rem;
        }

        .compact-input-icon {
            right: 0.5rem;
            font-size: 0.8rem;
        }

        .compact-form-grid .row {
            margin-bottom: 0.75rem;
        }
    }

    8px 20px rgba(0, 123, 255, 0.4);
    }

    .enhanced-submit-btn:disabled {
        background: #6c757d !important;
        border-color: #6c757d !important;
        transform: none !important;
        box-shadow: none !important;
        opacity: 0.6;
    }

    /* Enhanced Form Validation */
    .was-validated .enhanced-input:valid {
        border-color: #28a745;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.98-.93-.99-.94'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 3.5rem center;
        background-size: 1rem;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15);
    }

    .was-validated .enhanced-input:invalid {
        border-color: #dc3545;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6 .4 5 .4-5'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 3.5rem center;
        background-size: 1rem;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
    }

    .was-validated .enhanced-input:invalid~.enhanced-feedback {
        display: block;
        animation: slideIn 0.3s ease;
    }

    /* Loading states */
    .enhanced-submit-btn .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: 0.15em;
    }

    /* Input focus effects */
    .enhanced-input:focus {
        transform: translateY(-1px);
    }

    .enhanced-select:focus {
        transform: translateY(-1px);
    }

    /* Success state styling */
    .input-group-enhanced.success .enhanced-input {
        border-color: #28a745;
        background: linear-gradient(135deg, #ffffff 0%, #f0fff4 100%);
    }

    .input-group-enhanced.success .input-icon {
        color: #28a745;
    }

    /* Error state styling */
    .input-group-enhanced.error .enhanced-input {
        border-color: #dc3545;
        background: linear-gradient(135deg, #ffffff 0%, #fff5f5 100%);
    }

    .input-group-enhanced.error .input-icon {
        color: #dc3545;
    }

    /* Enhanced responsive design */
    @media (max-width: 768px) {

        .add-printer-modal-body,
        .edit-printer-modal-body {
            padding: 1.5rem;
        }

        .add-printer-modal-footer,
        .edit-printer-modal-footer {
            padding: 1rem 1.5rem;
            flex-direction: column;
        }

        .enhanced-cancel-btn,
        .enhanced-submit-btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }

        .enhanced-submit-btn {
            margin-bottom: 0;
        }

        .printer-info-header {
            padding: 1rem;
        }

        .printer-icon-wrapper {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
        }

        .printer-config-section {
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .enhanced-input {
            padding: 0.75rem 1rem;
            padding-right: 2.5rem;
        }

        .input-icon {
            right: 0.75rem;
            font-size: 1rem;
        }
    }

    /* ===== ENHANCED MARRY PRINTERS MODAL STYLES ===== */

    .marry-modal-content {
        border: none;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    }

    .marry-modal-header {
        background: linear-gradient(135deg, #e91e63 0%, #ad1457 100%);
        color: white;
        padding: 1.5rem;
        border: none;
        position: relative;
        overflow: hidden;
    }

    .marry-modal-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        animation: float 6s ease-in-out infinite;
    }

    .marry-modal-header::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -5%;
        width: 60px;
        height: 60px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 50%;
        animation: float 4s ease-in-out infinite reverse;
    }

    @keyframes float {

        0%,
        100% {
            transform: translateY(0px) rotate(0deg);
        }

        50% {
            transform: translateY(-10px) rotate(180deg);
        }
    }

    .marry-modal-body {
        padding: 2rem;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    }

    .marriage-info-card {
        background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
        border: 2px solid #ffcc02;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .marriage-info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #ffcc02, #ff9800, #ffcc02);
        animation: shimmer 2s linear infinite;
    }

    @keyframes shimmer {
        0% {
            transform: translateX(-100%);
        }

        100% {
            transform: translateX(100%);
        }
    }

    .marriage-icon-wrapper {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #e91e63, #ad1457);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
    }

    .marriage-selection-section {
        position: relative;
        margin-bottom: 2rem;
    }

    .printer-selection-card {
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 16px;
        padding: 1.5rem;
        height: 100%;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .printer-selection-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
    }

    .small-label-card {
        border-color: #007bff;
        background: linear-gradient(135deg, #ffffff 0%, #f0f8ff 100%);
    }

    .small-label-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #007bff, #0056b3);
    }

    .instruction-card-card {
        border-color: #17a2b8;
        background: linear-gradient(135deg, #ffffff 0%, #f0fdff 100%);
    }

    .instruction-card-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #17a2b8, #138496);
    }

    .card-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .small-label-card .card-icon {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
    }

    .instruction-card-card .card-icon {
        background: linear-gradient(135deg, #17a2b8, #138496);
        color: white;
    }

    .card-title {
        font-weight: 600;
        color: #343a40;
        margin-bottom: 0.5rem;
    }

    .card-subtitle {
        color: #6c757d;
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }

    .printer-select {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 0.75rem;
        font-size: 0.95rem;
        background: white;
        transition: all 0.3s ease;
    }

    .printer-select:focus {
        border-color: #e91e63;
        box-shadow: 0 0 0 0.2rem rgba(233, 30, 99, 0.15);
        outline: none;
    }

    .connection-heart-desktop {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 10;
    }

    .heart-connector {
        display: flex;
        align-items: center;
        width: 120px;
    }

    .connector-line {
        flex: 1;
        height: 3px;
        background: linear-gradient(90deg, #e91e63, #ad1457, #e91e63);
        border-radius: 2px;
        position: relative;
        overflow: hidden;
    }

    .connector-line::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        animation: flow 2s linear infinite;
    }

    @keyframes flow {
        0% {
            left: -100%;
        }

        100% {
            left: 100%;
        }
    }

    .heart-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #e91e63, #ad1457);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        margin: 0 8px;
        box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
        animation: heartbeat 2s ease-in-out infinite;
    }

    @keyframes heartbeat {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }
    }

    .connection-heart-mobile {
        text-align: center;
        margin: 1rem 0;
    }

    .connection-heart-mobile i {
        font-size: 2rem;
        color: #e91e63;
        animation: heartbeat 2s ease-in-out infinite;
    }

    .marriage-details-section {
        background: white;
        border: 2px solid #f1f3f4;
        border-radius: 16px;
        padding: 1.5rem;
        position: relative;
    }

    .section-header {
        font-weight: 600;
        color: #343a40;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #f1f3f4;
        font-size: 1.1rem;
    }

    .marriage-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    .marriage-input {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 0.75rem;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: white;
    }

    .marriage-input:focus {
        border-color: #e91e63;
        box-shadow: 0 0 0 0.2rem rgba(233, 30, 99, 0.15);
        outline: none;
        background: #fefefe;
    }

    .form-text {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 0.5rem;
    }

    .marry-modal-footer {
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border-top: 2px solid #f1f3f4;
        padding: 1.5rem 2rem;
        gap: 1rem;
    }

    .marry-cancel-btn {
        border: 2px solid #e9ecef;
        color: #495057;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .marry-cancel-btn:hover {
        background: #f8f9fa;
        border-color: #dee2e6;
        transform: translateY(-1px);
    }

    .marry-submit-btn {
        background: linear-gradient(135deg, #28a745, #20c997);
        border: 2px solid #28a745;
        color: white;
        font-weight: 600;
        padding: 0.75rem 2rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }

    .marry-submit-btn:hover {
        background: linear-gradient(135deg, #218838, #1e7e34);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
    }

    .marry-submit-btn:disabled {
        background: #6c757d;
        border-color: #6c757d;
        transform: none;
        box-shadow: none;
    }

    /* Form Validation Enhancements */
    .was-validated .printer-select:valid {
        border-color: #28a745;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.98-.93-.99-.94'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
    }

    .was-validated .printer-select:invalid {
        border-color: #dc3545;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6 .4 5 .4-5'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
    }

    .was-validated .marriage-input:valid {
        border-color: #28a745;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.98-.93-.99-.94'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
    }

    .was-validated .marriage-input:invalid {
        border-color: #dc3545;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6 .4 5 .4-5'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
    }

    /* Spinner styles for loading states */
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
    }

    .btn:disabled {
        opacity: 0.6;
    }

    /* Prevent text selection on buttons */
    .btn {
        user-select: none;
    }

    /* Modal fade transitions */
    .modal.fade .modal-dialog {
        transition: transform 0.3s ease-out;
        transform: translate(0, -50px);
    }

    .modal.show .modal-dialog {
        transform: none;
    }

    /* Additional safety for form interactions */
    .modal-body input:focus,
    .modal-body select:focus,
    .modal-body textarea:focus {
        outline: none !important;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
        border-color: #007bff !important;
        z-index: 1 !important;
        position: relative !important;
    }

    /* Loading State */
    .marry-submit-btn .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: 0.15em;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        #printer .table-responsive {
            font-size: .875rem;
        }

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

        .toast-container {
            left: 1rem !important;
            right: 1rem !important;
            top: 1rem !important;
        }

        .toast {
            min-width: auto !important;
            width: 100% !important;
        }

        .marry-modal-body {
            padding: 1.5rem;
        }

        .marry-modal-footer {
            padding: 1rem 1.5rem;
            flex-direction: column;
        }

        .marry-cancel-btn,
        .marry-submit-btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }

        .marry-submit-btn {
            margin-bottom: 0;
        }

        .marriage-info-card {
            padding: 1rem;
        }

        .marriage-icon-wrapper {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
        }

        .printer-selection-card {
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .marriage-details-section {
            padding: 1rem;
        }
    }

    /* Focus and Accessibility */
    .printer-select:focus,
    .marriage-input:focus {
        outline: 2px solid transparent;
        outline-offset: 2px;
    }

    /* Enhanced Invalid Feedback */
    .invalid-feedback {
        display: none;
        width: 100%;
        margin-top: 0.5rem;
        font-size: 0.875rem;
        color: #dc3545;
        font-weight: 500;
    }

    .was-validated .printer-select:invalid~.invalid-feedback,
    .was-validated .marriage-input:invalid~.invalid-feedback,
    .was-validated .form-control:invalid~.invalid-feedback,
    .was-validated .form-select:invalid~.invalid-feedback {
        display: block;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Success state for completed selections */
    .printer-selection-card.selected {
        border-color: #28a745;
        background: linear-gradient(135deg, #ffffff 0%, #f0fff4 100%);
    }

    .printer-selection-card.selected .card-icon {
        background: linear-gradient(135deg, #28a745, #20c997);
    }

    /* Hover effects */
    .printer-selection-card:hover {
        border-color: #e91e63;
    }

    .marriage-details-section:hover {
        border-color: #e91e63;
        box-shadow: 0 4px 12px rgba(233, 30, 99, 0.1);
    }

    /* Form validation styles for all modals */
    .was-validated .form-control:valid,
    .was-validated .form-select:valid {
        border-color: #198754;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='m2.3 6.73.98-.93-.99-.94'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }

    .was-validated .form-control:invalid,
    .was-validated .form-select:invalid {
        border-color: #dc3545;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6 .4 5 .4-5'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
</style>

<script>
    /**
     * Helper: open Add Printer modal (called by the button)
     */
    function showAddPrinterModal() {
        console.log('showAddPrinterModal function called from HTML');

        // Call the global function
        if (typeof window.showAddPrinterModal === 'function') {
            window.showAddPrinterModal();
        } else {
            console.error('Global showAddPrinterModal function not found');

            // Fallback approach
            const modal = document.getElementById('addPrinterModal');
            if (modal) {
                const modalInstance = new bootstrap.Modal(modal, { backdrop: 'static' });
                modalInstance.show();
            }
        }
    }

    /**
     * Initialize printer tab when DOM is ready
     */
    document.addEventListener('DOMContentLoaded', function () {
        // Ensure printer management functions are available globally
        console.log('Printer tab DOM ready');

        // Auto-load printer data when printer tab is clicked
        const printerTab = document.getElementById('printer-tab');
        if (printerTab) {
            printerTab.addEventListener('click', function () {
                console.log('Printer tab clicked');

                // Small delay to ensure tab content is loaded
                setTimeout(() => {
                    if (typeof window.refreshPrinterData === 'function') {
                        window.refreshPrinterData();
                    } else if (typeof fetchAllPrinters === 'function') {
                        fetchAllPrinters();
                    }
                }, 100);
            });
        }

        // Enhanced marry printers modal functionality
        const smallLabelSelect = document.getElementById('smallLabelPrinter');
        const instructionCardSelect = document.getElementById('instructionCardPrinter');

        if (smallLabelSelect) {
            smallLabelSelect.addEventListener('change', function () {
                const card = this.closest('.printer-selection-card');
                if (this.value) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
                updateMarriagePreview();
            });
        }

        if (instructionCardSelect) {
            instructionCardSelect.addEventListener('change', function () {
                const card = this.closest('.printer-selection-card');
                if (this.value) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
                updateMarriagePreview();
            });
        }

        // Auto-generate marriage name based on selected printers
        function updateMarriagePreview() {
            const smallLabel = smallLabelSelect?.options[smallLabelSelect.selectedIndex]?.text;
            const instructionCard = instructionCardSelect?.options[instructionCardSelect.selectedIndex]?.text;
            const marriageNameInput = document.getElementById('marriageName');

            if (smallLabel && instructionCard && marriageNameInput && !marriageNameInput.value) {
                const smallLabelName = smallLabel.split(' (')[0];
                const instructionCardName = instructionCard.split(' (')[0];
                marriageNameInput.placeholder = `${smallLabelName} + ${instructionCardName} Marriage`;
            }
        }

        // Reset modal when it's hidden
        const marryModal = document.getElementById('marryPrintersModal');
        if (marryModal) {
            marryModal.addEventListener('hidden.bs.modal', function () {
                // Reset form
                const form = this.querySelector('#marryPrintersForm');
                if (form) {
                    form.reset();
                    form.classList.remove('was-validated');
                }

                // Remove selected classes
                this.querySelectorAll('.printer-selection-card').forEach(card => {
                    card.classList.remove('selected');
                });

                // Reset marriage name placeholder
                const marriageNameInput = this.querySelector('#marriageName');
                if (marriageNameInput) {
                    marriageNameInput.placeholder = 'e.g., Production Line Alpha, Warehouse Station 1';
                }
            });
        }
    });
</script>