// ==========================
// COMPLETE PRINTER MANAGEMENT WITH ENHANCED MODAL FIX
// ==========================
const axios = window.axios;
if (!axios) {
    console.error(
        'window.axios is not available. Ensure @vite("resources/js/app.js") loads BEFORE this script.'
    );
}

// === SINGLE BACKDROP MANAGER (Bootstrap 5) ===
(function () {
    const BASE_Z = 1050; // bootstrap default backdrop z-index
    const ACTIVE_Z = 1055; // your modals use 1055+; backdrop will sit 5 below
    const BODY = document.body;

    function getShownModals() {
        return Array.from(document.querySelectorAll(".modal.show"));
    }

    function getTopModal() {
        // pick the modal with the highest (computed) z-index (fallback to last shown)
        const shown = getShownModals();
        if (!shown.length) return null;
        let top = shown[shown.length - 1];
        let topZ = Number(getComputedStyle(top).zIndex || ACTIVE_Z);
        for (const m of shown) {
            const z = Number(getComputedStyle(m).zIndex || ACTIVE_Z);
            if (z >= topZ) {
                top = m;
                topZ = z;
            }
        }
        return top;
    }

    function ensureSingleBackdrop(activeModal = null) {
        // Keep exactly one backdrop when any modal is open; else remove all
        const all = Array.from(document.querySelectorAll(".modal-backdrop"));
        // Remove extras (keep the last one, if any)
        all.slice(0, -1).forEach((n) => n.remove());

        const haveModals =
            getShownModals().length > 0 ||
            (activeModal && activeModal.classList.contains("show"));
        let backdrop = document.querySelector(".modal-backdrop");

        if (haveModals) {
            if (!backdrop) {
                backdrop = document.createElement("div");
                backdrop.className = "modal-backdrop fade show";
                BODY.appendChild(backdrop);
            }
            // Put the backdrop just below the top modal
            const top = activeModal || getTopModal();
            const topZ = Number(getComputedStyle(top).zIndex || ACTIVE_Z);
            backdrop.style.pointerEvents = ""; // clicks go to the top modal as normal
            BODY.classList.add("modal-open");
        } else {
            if (backdrop) backdrop.remove();
            BODY.classList.remove("modal-open");
            BODY.style.paddingRight = "";
            BODY.style.overflow = "";
        }
    }

    // Global listeners catch *all* modals (no need to wire each one)
    document.addEventListener(
        "show.bs.modal",
        function (ev) {
            const modal = ev.target;
            // make sure top modal is above the backdrop
            modal.style.zIndex = modal.style.zIndex || String(ACTIVE_Z);
            // If you're stacking, bump z-index slightly above previous top
            const top = getTopModal();
            if (top && top !== modal) {
                const currZ = Number(getComputedStyle(top).zIndex || ACTIVE_Z);
                modal.style.zIndex = String(currZ + 2);
            }
            ensureSingleBackdrop(modal);
        },
        true
    );

    document.addEventListener(
        "shown.bs.modal",
        function () {
            ensureSingleBackdrop(getTopModal());
        },
        true
    );

    document.addEventListener(
        "hide.bs.modal",
        function () {
            // Don’t remove immediately—another modal might still be visible
            // We will clean in 'hidden' event.
        },
        true
    );

    document.addEventListener(
        "hidden.bs.modal",
        function () {
            // After a modal fully hides, either adjust for remaining modals or remove all
            ensureSingleBackdrop(getTopModal());
        },
        true
    );

    // Expose to your code if needed
    window.__ensureSingleBackdrop = ensureSingleBackdrop;
})();

// Global state management for modals
window.printerModalState = {
    settingsModalInstance: null,
    activeModal: null,
    originalBackdrop: null,
};

// Global printer modal management functions
function cleanupModalBackdrops() {
    const backdrops = document.querySelectorAll(".modal-backdrop");
    backdrops.forEach((backdrop) => {
        if (backdrop) {
            backdrop.remove();
        }
    });

    const activeModals = document.querySelectorAll(".modal.show");
    if (activeModals.length === 0) {
        document.body.classList.remove("modal-open");
        document.body.style.paddingRight = "";
        document.body.style.overflow = "";
    }
}

function forceModalInteractive(modalElement) {
    if (!modalElement) return;

    modalElement.style.pointerEvents = "auto";
    modalElement.style.zIndex = "1055";

    const modalDialog = modalElement.querySelector(".modal-dialog");
    if (modalDialog) {
        modalDialog.style.pointerEvents = "auto";
        modalDialog.style.zIndex = "1056";
    }

    const modalContent = modalElement.querySelector(".modal-content");
    if (modalContent) {
        modalContent.style.pointerEvents = "auto";
        modalContent.style.zIndex = "1057";
        modalContent.style.opacity = "1";
        modalContent.style.filter = "none";
    }

    const formElements = modalElement.querySelectorAll(
        "input, select, textarea, button"
    );
    formElements.forEach((element) => {
        element.style.pointerEvents = "auto";
    });
}

function preventSettingsModalInterference() {
    const settingsModal = document.getElementById("settingsModal");
    if (settingsModal) {
        settingsModal.style.display = "block";
        settingsModal.classList.add("show");
    }
}

function ensurePrinterTabActive() {
    setTimeout(() => {
        const printerTab = document.getElementById("printer-tab");
        const printerPane = document.getElementById("printer");

        if (printerTab && printerPane) {
            document
                .querySelectorAll("#settingsTab .nav-item")
                .forEach((tab) => {
                    tab.classList.remove("active");
                    tab.setAttribute("aria-selected", "false");
                });

            document
                .querySelectorAll("#settingsTabContent .tab-pane")
                .forEach((pane) => {
                    pane.classList.remove("show", "active");
                });

            printerTab.classList.add("active");
            printerTab.setAttribute("aria-selected", "true");
            printerPane.classList.add("show", "active");

            const currentSubTab =
                window.printerTabState?.subTab || "printer-list-tab";
            const subTab = document.getElementById(currentSubTab);
            const subTabContent = document.getElementById(
                currentSubTab.replace("-tab", "")
            );

            if (subTab && subTabContent) {
                document
                    .querySelectorAll("#printerSubTabs .nav-link")
                    .forEach((tab) => {
                        tab.classList.remove("active");
                        tab.setAttribute("aria-selected", "false");
                    });
                document
                    .querySelectorAll("#printerSubTabContent .tab-pane")
                    .forEach((pane) => {
                        pane.classList.remove("show", "active");
                    });

                subTab.classList.add("active");
                subTab.setAttribute("aria-selected", "true");
                subTabContent.classList.add("show", "active");
            }
        }
    }, 100);
}

function handlePrinterModalOpen(modalId, modalElement) {
    console.log(`Opening printer modal: ${modalId}`);

    window.printerModalState.activeModal = modalId;

    preventSettingsModalInterference();

    modalElement.style.zIndex = "1055";
    modalElement.style.display = "block";

    setTimeout(() => {
        forceModalInteractive(modalElement);

        const backdrop = document.querySelector(".modal-backdrop:last-of-type");
        if (backdrop) {
            backdrop.style.pointerEvents = "none";
        }
    }, 50);
}

function handlePrinterModalClose(modalId, modalElement) {
    console.log(`Closing printer modal: ${modalId}`);

    modalElement.style.zIndex = "";
    modalElement.style.pointerEvents = "";

    const modalDialog = modalElement.querySelector(".modal-dialog");
    const modalContent = modalElement.querySelector(".modal-content");

    if (modalDialog) modalDialog.style.pointerEvents = "";
    if (modalContent) {
        modalContent.style.pointerEvents = "";
        modalContent.style.opacity = "";
        modalContent.style.filter = "";
    }

    window.printerModalState.activeModal = null;

    setTimeout(() => {
        const settingsModal = document.getElementById("settingsModal");
        if (settingsModal) {
            settingsModal.style.zIndex = "1050";
            settingsModal.style.display = "block";
            settingsModal.classList.add("show");

            document.body.classList.add("modal-open");

            cleanupModalBackdrops();

            const backdrop = document.createElement("div");
            backdrop.className = "modal-backdrop fade show";
            document.body.appendChild(backdrop);

            ensurePrinterTabActive();
        }
    }, 100);
}

// Global printer functions
window.editPrinter = function (printerId) {
    console.log("Edit printer clicked for ID:", printerId);

    cleanupModalBackdrops();

    fetch(`/api/printer-management/get-printer/${printerId}`)
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                const printer = data.printer;
                console.log("Printer data received:", printer);

                const modal = document.getElementById("editPrinterModal");
                if (!modal) {
                    console.error("Edit printer modal not found in DOM!");
                    alert(
                        "Edit modal not found. Please ensure the modal HTML is loaded."
                    );
                    return;
                }

                document.getElementById("editPrinterId").value =
                    printer.printerid;
                document.getElementById("editPrinterName").value =
                    printer.printername || "";
                document.getElementById("editPrinterType").value =
                    printer.printer_type || "";
                document.getElementById("editPrinterIP").value =
                    printer.printerip || "";
                document.getElementById("editPrinterPort").value =
                    printer.port || "9100";
                document.getElementById("editPrinterDescription").value =
                    printer.description || "";
                document.getElementById("editPrinterStatus").value =
                    printer.status || "active";

                const form = modal.querySelector("#editPrinterForm");
                if (form) {
                    form.classList.remove("was-validated");

                    const feedbacks =
                        form.querySelectorAll(".invalid-feedback");
                    feedbacks.forEach(
                        (feedback) => (feedback.style.display = "none")
                    );

                    const inputs = form.querySelectorAll(
                        ".form-control, .form-select"
                    );
                    inputs.forEach((input) => {
                        input.classList.remove("is-valid", "is-invalid");
                    });
                }

                console.log("Form fields populated, showing modal...");

                handlePrinterModalOpen("editPrinterModal", modal);

                try {
                    const modalInstance = new bootstrap.Modal(modal, {
                        backdrop: "static",
                        keyboard: true,
                        focus: true,
                    });

                    modalInstance.show();

                    setTimeout(() => {
                        forceModalInteractive(modal);

                        const firstInput = modal.querySelector(
                            'input:not([type="hidden"]):not([readonly])'
                        );
                        if (firstInput) {
                            firstInput.focus();
                        }
                    }, 100);

                    console.log("Edit modal show() called successfully");
                } catch (error) {
                    console.error("Error showing edit modal:", error);

                    modal.style.display = "block";
                    modal.classList.add("show");
                    document.body.classList.add("modal-open");

                    if (!document.querySelector(".modal-backdrop")) {
                        const backdrop = document.createElement("div");
                        backdrop.className = "modal-backdrop fade show";
                        backdrop.style.pointerEvents = "none";
                        document.body.appendChild(backdrop);
                    }

                    forceModalInteractive(modal);
                }
            } else {
                console.error("Error fetching printer details:", data.message);
                alert(
                    "Error fetching printer details: " +
                        (data.message || "Unknown error")
                );
            }
        })
        .catch((error) => {
            console.error("Error fetching printer details:", error);
            alert("Error fetching printer details. Please try again.");
        });
};

window.showAddPrinterModal = function () {
    console.log("Show add printer modal called");

    cleanupModalBackdrops();

    const modal = document.getElementById("addPrinterModal");
    if (!modal) {
        console.error("Add printer modal not found in DOM!");
        alert(
            "Add printer modal not found. Please ensure the modal HTML is loaded."
        );
        return;
    }

    const form = modal.querySelector("#addPrinterForm");
    if (form) {
        form.reset();
        form.classList.remove("was-validated");

        const feedbacks = form.querySelectorAll(".invalid-feedback");
        feedbacks.forEach((feedback) => (feedback.style.display = "none"));

        const inputs = form.querySelectorAll(".form-control, .form-select");
        inputs.forEach((input) => {
            input.classList.remove("is-valid", "is-invalid");
        });
    }

    handlePrinterModalOpen("addPrinterModal", modal);

    try {
        const modalInstance = new bootstrap.Modal(modal, {
            backdrop: "static",
            keyboard: true,
            focus: true,
        });

        modalInstance.show();

        setTimeout(() => {
            forceModalInteractive(modal);

            const firstInput = modal.querySelector(
                'input:not([type="hidden"])'
            );
            if (firstInput) {
                firstInput.focus();
            }
        }, 100);

        console.log("Add modal show() called successfully");
    } catch (error) {
        console.error("Error showing add printer modal:", error);

        modal.style.display = "block";
        modal.classList.add("show");
        document.body.classList.add("modal-open");

        if (!document.querySelector(".modal-backdrop")) {
            const backdrop = document.createElement("div");
            backdrop.className = "modal-backdrop fade show";
            backdrop.style.pointerEvents = "none";
            document.body.appendChild(backdrop);
        }

        forceModalInteractive(modal);
    }
};

window.testPrinter = function (printerId) {
    const originalEvent = event;
    const testBtn = originalEvent.target.closest("button");
    const originalText = testBtn.innerHTML;

    testBtn.disabled = true;
    testBtn.innerHTML =
        '<i class="spinner-border spinner-border-sm me-1"></i>Testing...';

    fetch(`/api/printer-management/test-printer/${printerId}`, {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        },
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                if (typeof showToast === "function") {
                    showToast("✅ Printer test successful!", "success");
                } else {
                    alert("Printer test successful!");
                }
            } else {
                if (typeof showToast === "function") {
                    showToast(
                        "❌ Printer test failed: " +
                            (data.message || "Unknown error"),
                        "error"
                    );
                } else {
                    alert(
                        "Printer test failed: " +
                            (data.message || "Unknown error")
                    );
                }
            }
        })
        .catch((error) => {
            console.error("Error testing printer:", error);
            if (typeof showToast === "function") {
                showToast("❌ Error testing printer connection", "error");
            } else {
                alert("Error testing printer connection");
            }
        })
        .finally(() => {
            testBtn.disabled = false;
            testBtn.innerHTML = originalText;
        });
};

function showDeletePrinterConfirmation(printerId) {
    window.currentDeletePrinterId = printerId;

    Swal.fire({
        title: "Are you sure?",
        text: "This printer will be permanently deleted.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, delete it!",
        cancelButtonText: "Cancel",
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            deletePrinter();
        }
    });
}

window.divorcePrinters = function (marriageId) {
    if (
        confirm(
            "Are you sure you want to divorce these printers? This will break their marriage and they will be available for new marriages."
        )
    ) {
        const confirmBtn = event.target;
        const originalText = confirmBtn.innerHTML;

        confirmBtn.disabled = true;
        confirmBtn.innerHTML =
            '<i class="spinner-border spinner-border-sm me-1"></i>Divorcing...';

        fetch(`/api/printer-management/divorce-printers/${marriageId}`, {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]'
                ).content,
            },
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    if (typeof showToast === "function") {
                        showToast(
                            "💔 Printers divorced successfully!",
                            "success"
                        );
                    } else {
                        alert("Printers divorced successfully!");
                    }

                    if (typeof window.refreshPrinterData === "function") {
                        window.refreshPrinterData();
                    }

                    setTimeout(() => {
                        if (typeof fetchMarriedPrinters === "function") {
                            fetchMarriedPrinters();
                        }
                        if (typeof fetchAllPrinters === "function") {
                            fetchAllPrinters();
                        }
                        if (typeof loadAvailablePrinters === "function") {
                            loadAvailablePrinters();
                        }
                    }, 100);
                } else {
                    if (typeof showToast === "function") {
                        showToast(
                            "❌ " +
                                (data.message || "Error divorcing printers"),
                            "error"
                        );
                    } else {
                        alert(data.message || "Error divorcing printers");
                    }
                }
            })
            .catch((error) => {
                console.error("Error divorcing printers:", error);
                if (typeof showToast === "function") {
                    showToast("❌ Error divorcing printers", "error");
                } else {
                    alert("Error divorcing printers");
                }
            })
            .finally(() => {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            });
    }
};

// Printer Management Initialization
function initPrinterManagement() {
    console.log("Initializing printer management...");

    const allPrintersTable = document.getElementById("allPrintersTableBody");
    if (!allPrintersTable) {
        console.error(
            "Printer table element not found. Make sure the printer tab HTML is loaded."
        );
        return;
    }

    window.printerTabState = {
        mainTab: "printer",
        subTab: "printer-list-tab",
    };

    fetchAllPrinters();
    loadAvailablePrinters();

    setupSubTabListeners();
    setupFormListeners();

    window.refreshPrinterData = function () {
        fetchAllPrinters();
        fetchMarriedPrinters();
        loadAvailablePrinters();
    };
}

function setupSubTabListeners() {
    const smallLabelTab = document.getElementById("small-label-tab");
    if (smallLabelTab) {
        smallLabelTab.addEventListener("shown.bs.tab", function (e) {
            console.log("Small label tab shown");
            window.printerTabState.subTab = "small-label-tab";
            fetchPrintersByType("small_label");
        });
    }

    const instructionCardTab = document.getElementById("instruction-card-tab");
    if (instructionCardTab) {
        instructionCardTab.addEventListener("shown.bs.tab", function (e) {
            console.log("Instruction card tab shown");
            window.printerTabState.subTab = "instruction-card-tab";
            fetchPrintersByType("instruction_card");
        });
    }

    const marriedPrinterTab = document.getElementById("married-printer-tab");
    if (marriedPrinterTab) {
        marriedPrinterTab.addEventListener("shown.bs.tab", function (e) {
            console.log("Married printer tab shown");
            window.printerTabState.subTab = "married-printer-tab";
            fetchMarriedPrinters();
        });
    }

    const printerListTab = document.getElementById("printer-list-tab");
    if (printerListTab) {
        printerListTab.addEventListener("shown.bs.tab", function (e) {
            console.log("All printers tab shown");
            window.printerTabState.subTab = "printer-list-tab";
            fetchAllPrinters();
        });
    }

    smallLabelTab?.addEventListener("click", function () {
        window.printerTabState.subTab = "small-label-tab";
        setTimeout(() => fetchPrintersByType("small_label"), 100);
    });

    instructionCardTab?.addEventListener("click", function () {
        window.printerTabState.subTab = "instruction-card-tab";
        setTimeout(() => fetchPrintersByType("instruction_card"), 100);
    });

    marriedPrinterTab?.addEventListener("click", function () {
        window.printerTabState.subTab = "married-printer-tab";
        setTimeout(() => fetchMarriedPrinters(), 100);
    });

    printerListTab?.addEventListener("click", function () {
        window.printerTabState.subTab = "printer-list-tab";
        setTimeout(() => fetchAllPrinters(), 100);
    });
}

function setupFormListeners() {
    const addPrinterForm = document.getElementById("addPrinterForm");
    if (addPrinterForm) {
        addPrinterForm.addEventListener("submit", function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (!this.checkValidity()) {
                this.classList.add("was-validated");
                return;
            }

            this.classList.add("was-validated");
            addNewPrinter(this);
        });
    }

    const editPrinterForm = document.getElementById("editPrinterForm");
    if (editPrinterForm) {
        editPrinterForm.addEventListener("submit", function (e) {
            e.preventDefault();
            e.stopPropagation();

            console.log("Form element:", this); // Debug log
            console.log("Form validity:", this.checkValidity()); // Debug log

            if (!this.checkValidity()) {
                this.classList.add("was-validated");
                return;
            }

            this.classList.add("was-validated");

            // Make sure updatePrinter exists
            if (typeof window.updatePrinter === "function") {
                console.log("Calling updatePrinter with form:", this); // Debug log
                window.updatePrinter(this);
            } else {
                console.error("updatePrinter function not found!");
            }
        });
    }

    const marryPrintersForm = document.getElementById("marryPrintersForm");
    if (marryPrintersForm) {
        marryPrintersForm.addEventListener("submit", function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (!this.checkValidity()) {
                this.classList.add("was-validated");
                return;
            }

            this.classList.add("was-validated");
            marryPrinters(this);
        });
    }

    const confirmDeleteBtn = document.getElementById("confirmDeletePrinter");
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", function () {
            deletePrinter();
        });
    }

    const ipInputs = document.querySelectorAll('input[name="ip_address"]');
    ipInputs.forEach((input) => {
        input.addEventListener("input", function () {
            const ipRegex =
                /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
            if (this.value && !ipRegex.test(this.value)) {
                this.setCustomValidity("Please enter a valid IP address");
            } else {
                this.setCustomValidity("");
            }
        });
    });
}

// Fetch all printers and display in main table
function fetchAllPrinters() {
    const tbody = document.getElementById("allPrintersTableBody");
    if (!tbody) {
        console.error("allPrintersTableBody element not found");
        return;
    }

    tbody.innerHTML =
        '<tr><td colspan="5" class="text-center">Loading printers...</td></tr>';

    fetch("/api/printer-management/get-printers")
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                renderAllPrintersTable(data.printers || []);
            } else {
                console.error("Failed to fetch printers:", data.message);
                tbody.innerHTML =
                    '<tr><td colspan="5" class="text-center text-danger">Error loading printers</td></tr>';
            }
        })
        .catch((error) => {
            console.error("Error fetching printers:", error);
            tbody.innerHTML =
                '<tr><td colspan="5" class="text-center text-danger">Error loading printers</td></tr>';
        });
}

function renderAllPrintersTable(printers) {
    const tbody = document.getElementById("allPrintersTableBody");
    const mobileContainer = document.getElementById("printersMobile");
    const emptyRow = document.getElementById("printersEmptyRow");
    const emptyCard = document.getElementById("printersEmptyCard");

    if (!tbody) {
        console.error(
            "allPrintersTableBody element not found in renderAllPrintersTable"
        );
        return;
    }

    // Clear existing content
    if (emptyRow) emptyRow.remove();
    if (emptyCard) emptyCard.remove();
    tbody.innerHTML = "";
    if (mobileContainer) {
        mobileContainer.innerHTML = "";
    }

    if (printers.length === 0) {
        // Show empty state for both desktop and mobile
        tbody.innerHTML =
            '<tr><td colspan="5" class="text-center">No printers found</td></tr>';
        if (mobileContainer) {
            mobileContainer.innerHTML = `
                <div class="alert alert-info text-center" role="alert">
                    No printers found
                </div>
            `;
        }
        return;
    }

    // Render each printer record
    printers.forEach((printer, index) => {
        const statusBadge = getStatusBadge(printer.status);
        const typeBadge = getTypeBadge(printer.printer_type);
        const marriageStatus = printer.married_to_printer_id
            ? '<i class="bi bi-heart-fill text-success" title="Married"></i>'
            : '<i class="bi bi-heart text-muted" title="Single"></i>';

        const printerName = printer.printername || "Unknown";
        const ipAddress = `${printer.printerip || "N/A"}:${
            printer.port || "9100"
        }`;
        const cardBg = index % 2 === 0 ? "bg-light" : "bg-white";

        // Desktop table row
        if (tbody) {
            tbody.insertAdjacentHTML(
                "beforeend",
                `
                <tr>
                    <td>${printerName} ${marriageStatus}</td>
                    <td>${typeBadge}</td>
                    <td>${ipAddress}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary edit-printer-btn"
                                data-id="${printer.printerid}"
                                title="Edit Printer">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success"
                                onclick="testPrinter(${printer.printerid})"
                                title="Test Printer">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                onclick="confirmAndDeletePrinter(${printer.printerid})"
                                title="Delete Printer">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                `
            );
        }

        // Mobile card view
        if (mobileContainer) {
            mobileContainer.insertAdjacentHTML(
                "beforeend",
                `
                <div class="card mb-3 shadow-sm ${cardBg}">
                    <div class="card-body">
                        <h6 class="mb-2">
                            <strong>${printerName}</strong> ${marriageStatus}
                        </h6>
                        <p class="mb-1"><strong>Type:</strong> ${typeBadge}</p>
                        <p class="mb-1"><strong>IP Address:</strong> ${ipAddress}</p>
                        <p class="mb-2"><strong>Status:</strong> ${statusBadge}</p>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary edit-printer-btn flex-fill"
                                data-id="${printer.printerid}"
                                title="Edit Printer">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success"
                                onclick="testPrinter(${printer.printerid})"
                                title="Test Printer">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                onclick="confirmAndDeletePrinter(${printer.printerid})"
                                title="Delete Printer">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                `
            );
        }
    });

    // Add this event listener once (outside the insertion loop)
    tbody.addEventListener("click", function (e) {
        if (e.target.closest(".edit-printer-btn")) {
            const printerId = e.target.closest(".edit-printer-btn").dataset.id;
            console.log("Edit printer ID:", printerId);

            // Fetch the printer data from the server
            fetch(`/api/printer-management/get-printer/${printerId}`)
                .then((response) => {
                    console.log("Response status:", response.status);
                    return response.json();
                })
                .then((data) => {
                    console.log("Full response object:", data);

                    // Extract the printer object from the response
                    const printer = data.printer;
                    console.log("Printer object:", printer);

                    // SET THE PRINTER ID in the hidden input
                    document.getElementById("editPrinterId").value =
                        printer.printerid;

                    // Populate the form fields with correct field names
                    document.getElementById("editPrinterName").value =
                        printer.printername || "";
                    document.getElementById("editPrinterType").value =
                        printer.printer_type || "";
                    document.getElementById("editPrinterIP").value =
                        printer.printerip || "";
                    document.getElementById("editPrinterPort").value =
                        printer.port || 9100;
                    document.getElementById("editPrinterStatus").value =
                        printer.status || "active";
                    document.getElementById("editPrinterDescription").value =
                        printer.description || "";

                    console.log("Form populated with values");
                    console.log("Printer ID set to:", printer.printerid);

                    // Show edit modal WITHOUT backdrop
                    const editModal = new bootstrap.Modal(
                        document.getElementById("editPrinterModal"),
                        {
                            backdrop: false,
                        }
                    );
                    editModal.show();

                    // Extra cleanup - remove any backdrop that might appear
                    setTimeout(() => {
                        const backdrops =
                            document.querySelectorAll(".modal-backdrop");
                        backdrops.forEach((backdrop) => {
                            if (
                                document
                                    .getElementById("editPrinterModal")
                                    .classList.contains("show")
                            ) {
                                backdrop.remove();
                            }
                        });
                    }, 50);
                })
                .catch((error) => {
                    console.error("Error fetching printer data:", error);
                    console.error("Error details:", error.message);
                    alert("Failed to load printer data. Please try again.");
                });
        }
    });
}

// Add new printer
function addNewPrinter(form) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML =
        '<i class="spinner-border spinner-border-sm me-1"></i>Adding...';

    fetch("/api/printer-management/add-printer", {
        method: "POST",
        body: formData,
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        },
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                Swal.fire({
                    icon: "success",
                    title: "Printer Added",
                    text: "✅ Printer added successfully!",
                    confirmButtonText: "OK",
                });

                const modal = document.getElementById("addPrinterModal");
                if (modal) {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }

                form.reset();

                toggleAddPrinter();
                fetchAllPrinters();
                loadAvailablePrinters();

                setTimeout(() => {
                    const currentSubTab =
                        window.printerTabState?.subTab || "printer-list-tab";
                    if (currentSubTab === "small-label-tab") {
                        fetchPrintersByType("small_label");
                    } else if (currentSubTab === "instruction-card-tab") {
                        fetchPrintersByType("instruction_card");
                    } else if (currentSubTab === "married-printer-tab") {
                        fetchMarriedPrinters();
                    }
                }, 100);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: data.message || "❌ Error adding printer",
                });
            }
        })
        .catch((error) => {
            console.error("Error adding printer:", error);
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "❌ Error adding printer. Please try again.",
            });
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
}

// Update printer
window.updatePrinter = function (form) {
    console.log("updatePrinter called with:", form); // Debug log

    if (!form) {
        console.error("Form is null or undefined");
        Swal.fire({
            icon: "error",
            title: "Form error",
            text: "Form element not found.",
        });
        return;
    }

    const printerIdInput = form.querySelector('[name="printer_id"]');
    console.log("Printer ID input element:", printerIdInput); // Debug log

    const printerId = printerIdInput?.value;
    console.log("Printer ID value:", printerId); // Debug log

    if (!printerId) {
        Swal.fire({
            icon: "warning",
            title: "Printer ID not found",
            text: "Please reopen the editor and try again.",
        });
        return;
    }

    const formData = new FormData(form);

    // Debug: log all form data
    console.log("Form data entries:");
    for (let pair of formData.entries()) {
        console.log(pair[0] + ": " + pair[1]);
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn?.innerHTML || "Save changes";

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML =
            '<i class="spinner-border spinner-border-sm me-1"></i>Updating...';
    }

    fetch(`/api/printer-management/update-printer/${printerId}`, {
        method: "POST",
        body: formData,
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        },
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                // Close modal first so the alert is front and center
                const modal = document.getElementById("editPrinterModal");
                if (modal) {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                }

                Swal.fire({
                    icon: "success",
                    title: "Printer updated",
                    text: "Your changes have been saved.",
                    confirmButtonText: "OK",
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Refresh the printer list
                        if (typeof fetchAllPrinters === "function") {
                            fetchAllPrinters();
                        }
                    }
                });

                // Remove backdrops
                document
                    .querySelectorAll(".modal-backdrop")
                    .forEach((backdrop) => backdrop.remove());
            } else {
                // Laravel validation errors? data.errors = { field: ["msg", ...], ... }
                const validationHtml = data.errors
                    ? Object.values(data.errors)
                          .flat()
                          .map((msg) => `<li>${msg}</li>`)
                          .join("")
                    : "";

                Swal.fire({
                    icon: "error",
                    title: "Update failed",
                    html: validationHtml
                        ? `<ul style="text-align:left;margin:0 1rem">${validationHtml}</ul>`
                        : data.message || "Error updating printer.",
                });
            }
        })
        .catch((error) => {
            console.error("Error updating printer:", error);
            Swal.fire({
                icon: "error",
                title: "Network error",
                text: "Error updating printer. Please try again.",
            });
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        });
};

// Updated deletePrinter
(function () {
    window.confirmAndDeletePrinter = function (printerId) {
        const csrfToken =
            document.querySelector('meta[name="csrf-token"]')?.content || "";

        Swal.fire({
            title: "Delete this printer?",
            text: "This action cannot be undone.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, delete it!",
            cancelButtonText: "Cancel",
            reverseButtons: true,
            showLoaderOnConfirm: true,
            preConfirm: () => {
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 15000);

                return fetch(
                    `/api/printer-management/delete-printer/${encodeURIComponent(
                        printerId
                    )}`,
                    {
                        method: "DELETE",
                        headers: {
                            ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                        },
                        credentials: "same-origin",
                        signal: controller.signal,
                    }
                )
                    .then(async (res) => {
                        clearTimeout(timeout);
                        let data = null;
                        try {
                            data = await res.json();
                        } catch {}

                        if (!res.ok) {
                            const msg =
                                data?.message ||
                                `Error deleting printer (HTTP ${res.status})`;
                            throw new Error(msg);
                        }
                        if (!data?.success)
                            throw new Error(data?.message || "Delete failed");

                        return data; // becomes result.value
                    })
                    .catch((err) => {
                        Swal.showValidationMessage(
                            err.name === "AbortError"
                                ? "Request timed out. Please try again."
                                : err.message || "Error deleting printer"
                        );
                    });
            },
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    icon: "success",
                    title: "Deleted!",
                    text: "The printer has been deleted successfully.",
                    timer: 2000,
                    showConfirmButton: false,
                });

                // Refresh lists
                fetchAllPrinters?.();
                loadAvailablePrinters?.();

                setTimeout(() => {
                    const tab =
                        window.printerTabState?.subTab || "printer-list-tab";
                    if (tab === "small-label-tab")
                        fetchPrintersByType?.("small_label");
                    else if (tab === "instruction-card-tab")
                        fetchPrintersByType?.("instruction_card");
                    else if (tab === "married-printer-tab")
                        fetchMarriedPrinters?.();
                }, 100);
            } else if (result.dismiss) {
                Swal.fire({
                    icon: "info",
                    title: "Cancelled",
                    text: "Deletion was cancelled.",
                    timer: 1500,
                    showConfirmButton: false,
                });
            }
        });
    };
})();

async function deletePrinter() {
    const printerId = window.currentDeletePrinterId;

    if (!printerId) {
        showToast("❌ Printer ID not found", "error");
        return;
    }

    const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.content || "";

    const result = await Swal.fire({
        title: "Delete this printer?",
        text: "This action cannot be undone.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, delete",
        cancelButtonText: "Cancel",
        reverseButtons: true,
        focusCancel: true,
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        allowEscapeKey: () => !Swal.isLoading(),
        // ⚠️ IMPORTANT: return the Promise directly
        preConfirm: () => {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000);

            return fetch(
                `/api/printer-management/delete-printer/${encodeURIComponent(
                    printerId
                )}`,
                {
                    method: "DELETE",
                    headers: {
                        ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest", // helps Laravel treat as AJAX
                    },
                    credentials: "same-origin",
                    signal: controller.signal,
                }
            )
                .then(async (response) => {
                    clearTimeout(timeout);
                    let data = null;
                    try {
                        data = await response.json();
                    } catch (_) {}

                    if (!response.ok) {
                        const msg =
                            data?.message ||
                            (response.status === 419
                                ? "CSRF token mismatch (419). Refresh and try again."
                                : response.status === 403
                                ? "Not authorized to delete this printer."
                                : response.status === 404
                                ? "Printer not found."
                                : `Error deleting printer (HTTP ${response.status}).`);
                        throw new Error(msg);
                    }

                    if (!data?.success) {
                        throw new Error(
                            data?.message || "Error deleting printer"
                        );
                    }

                    return data; // <- becomes result.value
                })
                .catch((err) => {
                    console.error("Delete printer failed:", err);
                    const msg =
                        err.name === "AbortError"
                            ? "Request timed out. Please check your connection and try again."
                            : err.message || "Error deleting printer";
                    Swal.showValidationMessage(msg);
                });
        },
    });

    // Success path: preConfirm resolved and user confirmed
    if (result.isConfirmed && result.value) {
        showToast("✅ Printer deleted successfully!", "success");

        // Refresh lists
        fetchAllPrinters?.();
        loadAvailablePrinters?.();

        // Respect your sub-tab refresh logic
        setTimeout(() => {
            const currentSubTab =
                window.printerTabState?.subTab || "printer-list-tab";
            if (currentSubTab === "small-label-tab") {
                fetchPrintersByType?.("small_label");
            } else if (currentSubTab === "instruction-card-tab") {
                fetchPrintersByType?.("instruction_card");
            } else if (currentSubTab === "married-printer-tab") {
                fetchMarriedPrinters?.();
            }
        }, 100);
    }

    // Always clear after flow
    window.currentDeletePrinterId = null;
}

// Load available printers for marriage dropdowns
function loadAvailablePrinters() {
    fetch("/api/printer-management/get-available-printers")
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                populateMarriageDropdowns(
                    data.small_label || [],
                    data.instruction_card || []
                );
            } else {
                console.error(
                    "Failed to load available printers:",
                    data.message
                );
            }
        })
        .catch((error) => {
            console.error("Error loading available printers:", error);
        });
}

function populateMarriageDropdowns(
    smallLabelPrinters,
    instructionCardPrinters
) {
    const smallLabelSelect = document.getElementById("smallLabelPrinter");
    const instructionCardSelect = document.getElementById(
        "instructionCardPrinter"
    );

    if (smallLabelSelect) {
        smallLabelSelect.innerHTML =
            '<option value="">Select Small Label Printer</option>';
        smallLabelPrinters.forEach((printer) => {
            smallLabelSelect.innerHTML += `<option value="${printer.printerid}">${printer.printername} (${printer.printerip})</option>`;
        });
    }

    if (instructionCardSelect) {
        instructionCardSelect.innerHTML =
            '<option value="">Select Instruction Card Printer</option>';
        instructionCardPrinters.forEach((printer) => {
            instructionCardSelect.innerHTML += `<option value="${printer.printerid}">${printer.printername} (${printer.printerip})</option>`;
        });
    }
}

// Marry printers
function marryPrinters(form) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML =
        '<i class="spinner-border spinner-border-sm me-1"></i>Marrying...';

    fetch("/api/printer-management/marry-printers", {
        method: "POST",
        body: formData,
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        },
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                Swal.fire({
                    icon: "success",
                    title: "💕 Printers married successfully!",
                    confirmButtonText: "OK",
                });

                const modal = document.getElementById("marryPrintersModal");
                if (modal) {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }

                form.reset();
                toggleMarryPrinter();
                fetchMarriedPrinters();
                fetchAllPrinters();
                loadAvailablePrinters();
                window.printerTabState.subTab = "married-printer-tab";

                setTimeout(() => {
                    const marriedTab = document.getElementById(
                        "married-printer-tab"
                    );
                    if (marriedTab) {
                        const tabInstance =
                            bootstrap.Tab.getOrCreateInstance(marriedTab);
                        tabInstance.show();
                    }
                }, 100);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "❌ Error",
                    text: data.message || "Error marrying printers",
                    confirmButtonText: "Close",
                });
            }
        })
        .catch((error) => {
            console.error("Error marrying printers:", error);
            Swal.fire({
                icon: "error",
                title: "❌ Error",
                text: "Error marrying printers. Please try again.",
                confirmButtonText: "Close",
            });
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
}

// Fetch printers by type for specific tabs
function fetchPrintersByType(type) {
    fetch(`/api/printer-management/get-printers?type=${type}`)
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                if (type === "small_label") {
                    renderPrinterCards(
                        data.printers || [],
                        "smallLabelPrintersGrid"
                    );
                } else if (type === "instruction_card") {
                    renderPrinterCards(
                        data.printers || [],
                        "instructionCardPrintersGrid"
                    );
                }
            } else {
                console.error(
                    `Failed to fetch ${type} printers:`,
                    data.message
                );
            }
        })
        .catch((error) => {
            console.error(`Error fetching ${type} printers:`, error);
        });
}

function renderPrinterCards(printers, containerId) {
    const container = document.getElementById(containerId);

    if (!container) {
        console.error(`Container ${containerId} not found`);
        return;
    }

    if (printers.length === 0) {
        container.innerHTML =
            '<div class="col-12"><div class="alert alert-info text-center">No printers found</div></div>';
        return;
    }

    container.innerHTML = printers
        .map((printer) => {
            const statusBadge = getStatusBadge(printer.status);
            const marriageStatus = printer.married_to_printer_id
                ? '<span class="badge bg-success ms-2">Married</span>'
                : '<span class="badge bg-secondary ms-2">Single</span>';

            return `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card printer-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">${
                                printer.printername || "Unknown"
                            }${marriageStatus}</h6>
                            ${statusBadge}
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <strong>IP:</strong> ${
                                    printer.printerip || "N/A"
                                }<br>
                                <strong>Port:</strong> ${
                                    printer.port || "9100"
                                }<br>
                                ${
                                    printer.description
                                        ? `<strong>Description:</strong> ${printer.description}<br>`
                                        : ""
                                }
                                ${
                                    printer.married_to_printer_id
                                        ? `<strong>Married to ID:</strong> ${printer.married_to_printer_id}`
                                        : ""
                                }
                            </p>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                    onclick="editPrinter(${printer.printerid})"
                                    title="Edit Printer">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm"
                                    onclick="testPrinter(${printer.printerid})"
                                    title="Test Printer">
                                    <i class="bi bi-check-circle"></i> Test
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        })
        .join("");
}

// Fetch and display married printers
function fetchMarriedPrinters() {
    const container = document.getElementById("marriedPrintersContainer");
    if (!container) {
        console.error("marriedPrintersContainer not found");
        return;
    }

    container.innerHTML =
        '<div class="alert alert-info text-center">Loading married printers...</div>';

    fetch("/api/printer-management/get-married-printers")
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                // ✅ Hide the initial blue info alert
                const infoAlert = document.querySelector(
                    "#marriedPrinters .alert.alert-info"
                );
                if (infoAlert) {
                    infoAlert.style.display = "none";
                }

                const marriages = data.marriages || [];

                if (marriages.length === 0) {
                    // ✅ Show message if no married printers
                    container.innerHTML = `
                        <div class="alert alert-secondary text-center">
                            <strong>No married printers found.</strong>
                        </div>
                    `;
                } else {
                    // ✅ Render the printers normally
                    renderMarriedPrinters(marriages);
                }
            } else {
                console.error(
                    "Failed to fetch married printers:",
                    data.message
                );
                container.innerHTML =
                    '<div class="alert alert-danger text-center">Error loading married printers</div>';
            }
        })
        .catch((error) => {
            console.error("Error fetching married printers:", error);
            container.innerHTML =
                '<div class="alert alert-danger text-center">Error loading married printers</div>';
        });
}

function renderMarriedPrinters(marriages) {
    const container = document.getElementById("marriedPrintersContainer");

    if (!container) {
        console.error("marriedPrintersContainer not found");
        return;
    }

    if (marriages.length === 0) {
        container.innerHTML =
            '<div class="alert alert-info text-center">No married printers found</div>';
        return;
    }

    container.innerHTML = marriages
        .map(
            (marriage) => `
            <div class="married-printer-pair p-3 mb-3">
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="bi bi-tag-fill text-primary fs-3"></i>
                                <h6 class="mt-2 mb-1">${
                                    marriage.small_label_printer.printer_name
                                }</h6>
                                <small class="text-muted">${
                                    marriage.small_label_printer.ip_address
                                }</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="printer-connection-line my-3"></div>
                    </div>
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="bi bi-card-text-fill text-success fs-3"></i>
                                <h6 class="mt-2 mb-1">${
                                    marriage.instruction_card_printer
                                        .printer_name
                                }</h6>
                                <small class="text-muted">${
                                    marriage.instruction_card_printer.ip_address
                                }</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <h5 class="text-success">${marriage.marriage_name}</h5>
                    ${
                        marriage.description
                            ? `<p class="text-muted mb-2">${marriage.description}</p>`
                            : ""
                    }
                    <button type="button" class="btn btn-outline-danger btn-sm"
                        onclick="divorcePrinters(${
                            marriage.small_label_printer.printer_id
                        })"
                        title="Divorce Printers">
                        <i class="bi bi-heart-break me-1"></i>
                        Divorce Printers
                    </button>
                </div>
            </div>
        `
        )
        .join("");
}

// Helper functions
function getStatusBadge(status) {
    const badges = {
        active: '<span class="badge bg-success status-badge">Active</span>',
        inactive:
            '<span class="badge bg-secondary status-badge">Inactive</span>',
        maintenance:
            '<span class="badge bg-warning status-badge">Maintenance</span>',
    };
    return badges[status] || badges["inactive"];
}

function getTypeBadge(type) {
    const badges = {
        small_label: '<span class="badge bg-primary">Small Label</span>',
        instruction_card: '<span class="badge bg-info">Instruction Card</span>',
    };
    return badges[type] || '<span class="badge bg-secondary">Unknown</span>';
}
