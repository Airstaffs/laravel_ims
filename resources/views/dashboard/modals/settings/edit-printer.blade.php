<div class="modal fade" id="editPrinterTestModal" tabindex="-1" aria-labelledby="editStoreTestModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPrinterTestModalLabel">
                    <i class="bi bi-pencil-square"></i>
                    <span>Edit Printer</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form id="editPrinterForm">
                    @csrf

                    <!-- Compact Header Info -->
                    <div class="printer-info-header-compact">
                        <div class="d-flex align-items-center">
                            <div class="printer-icon-wrapper-compact add-printer-icon">
                                <i class="bi bi-printer-fill"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0">Update Printer Settings</h6>
                                <small class="text-muted">Modify configuration settings</small>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="printerId" name="printer_id">

                    <fieldset>
                        <label>
                            <i class="bi bi-printer me-1"></i>
                            <span>Printer Name</span>
                            <span class="text-danger">*</span>
                        </label>
                        <div class="compact-input-wrapper">
                            <input type="text" class="form-control compact-input" id="printerName" name="printer_name"
                                required>
                            <i class="bi bi-printer compact-input-icon"></i>
                        </div>
                    </fieldset>

                    <fieldset>
                        <label>
                            <i class="bi bi-tag me-1"></i>
                            <span>Type</span>
                            <span class="text-danger">*</span>
                        </label>
                        <div class="compact-input-wrapper">
                            <select class="form-select compact-input compact-select" id="printerType"
                                name="printer_type" required>
                                <option value="">Select Type</option>
                                <option value="small_label">🏷️ Small Label</option>
                                <option value="instruction_card">📋 Instruction Card</option>
                            </select>
                            <i class="bi bi-tag compact-input-icon"></i>
                        </div>
                    </fieldset>

                    <fieldset>
                        <label>
                            <i class="bi bi-globe me-1"></i>
                            <span>IP Address</span>
                            <span class="text-danger">*</span>
                        </label>
                        <div class="compact-input-wrapper">
                            <input type="text" class="form-control compact-input" id="printerIp" name="ip_address"
                                placeholder="192.168.1.100" required>
                            <i class="bi bi-globe compact-input-icon"></i>
                        </div>
                    </fieldset>

                    <fieldset>
                        <label>
                            <i class="bi bi-plug me-1"></i>Port
                        </label>
                        <div class="compact-input-wrapper">
                            <input type="number" class="form-control compact-input" id="printerPort" name="port"
                                value="9100" min="1" max="65535">
                            <i class="bi bi-plug compact-input-icon"></i>
                        </div>
                    </fieldset>

                    <fieldset>
                        <label>
                            <i class="bi bi-circle-fill me-1"></i>
                            <span>Status</span>
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
                    </fieldset>

                    <fieldset style="width: 100%;">
                        <label>
                            <i class="bi bi-card-text me-1"></i>
                            <span>Description</span>
                            <span class="text-muted">(Optional)</span>
                        </label>
                        <textarea class="form-control compact-input compact-textarea" id="description"
                            name="description" rows="2" placeholder="Update printer description or notes..."></textarea>
                    </fieldset>

                    <div class="form-footer">
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
    </div>
</div>