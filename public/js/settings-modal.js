// Main DOMContentLoaded event
document.addEventListener("DOMContentLoaded", function () {
    // Initialize modal management
    initializeModalManagement();

    // Track printer modal state to prevent tab switching
    let printerModalOpen = false;

    function handlePrinterModalOpenState() {
        printerModalOpen = true;
        const settingsModal = document.getElementById("settingsModal");
        if (settingsModal) {
            settingsModal.classList.add("printer-modal-open");
        }
    }

    function handlePrinterModalCloseState() {
        printerModalOpen = false;
        const settingsModal = document.getElementById("settingsModal");
        if (settingsModal) {
            settingsModal.classList.remove("printer-modal-open");

            setTimeout(() => {
                ensurePrinterTabActive();
                cleanupModalBackdrops();
                settingsModal.style.zIndex = "1050";
            }, 100);
        }
    }

    const printerModals = [
        "addPrinterModal",
        "editPrinterModal",
        "deletePrinterModal",
        "marryPrintersModal",
    ];

    printerModals.forEach((modalId) => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.addEventListener("show.bs.modal", function () {
                handlePrinterModalOpenState();
                handlePrinterModalOpen(modalId, this);
            });

            modal.addEventListener("hidden.bs.modal", function () {
                handlePrinterModalCloseState();
                handlePrinterModalClose(modalId, this);
            });
        }
    });

    const settingsModalEl = document.getElementById("settingsModal");
    const userListModal = document.getElementById("userListModal");
    const editUserModal = document.getElementById("editUserModal");
    const deleteUserModal = document.getElementById("deleteUserModal");
    const addUserForm = document.getElementById("addUserForm");
    const togglePassword = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("edit_password");

    if (!settingsModalEl) return;

    const settingsModal = new bootstrap.Modal(settingsModalEl);

    // Password toggle
    togglePassword?.addEventListener("click", function () {
        const isPassword = passwordInput.type === "password";
        passwordInput.type = isPassword ? "text" : "password";
        this.classList.toggle("bi-eye");
        this.classList.toggle("bi-eye-slash");
    });

    // Settings modal event handlers
    settingsModalEl.addEventListener("shown.bs.modal", function () {
        if (printerModalOpen) {
            return;
        }

        const defaultTab = document.querySelector("#design-tab");
        if (defaultTab) {
            const tabInstance = bootstrap.Tab.getOrCreateInstance(defaultTab);
            tabInstance.show();
        }

        document.querySelectorAll("#settingsTab .nav-link").forEach((tab) => {
            tab.classList.remove("active");
            tab.setAttribute("aria-selected", "false");
        });

        document.querySelector("#design-tab")?.classList.add("active");
        document
            .querySelector("#design-tab")
            ?.setAttribute("aria-selected", "true");
    });

    settingsModalEl.addEventListener("hidden.bs.modal", function () {
        if (printerModalOpen) {
            return;
        }

        document.querySelectorAll("#settingsTab .nav-link").forEach((tab) => {
            tab.classList.remove("active");
            tab.setAttribute("aria-selected", "false");
        });

        document
            .querySelectorAll("#settingsTabContent .tab-pane")
            .forEach((tabPane) => {
                tabPane.classList.remove("show", "active");
            });

        const defaultTab = document.querySelector("#design-tab");
        if (defaultTab) {
            const tabInstance = bootstrap.Tab.getOrCreateInstance(defaultTab);
            tabInstance.show();
        }
    });

    document.addEventListener("DOMContentLoaded", function () {
        const privilegeTab = document.getElementById("privilege-tab");
        if (privilegeTab) {
            privilegeTab.addEventListener("click", function () {
                const userId = document.getElementById("selectUser").value;
                if (userId) {
                    refreshStoreList();
                }
            });
        }

        const selectUser = document.getElementById("selectUser");
        if (selectUser) {
            selectUser.addEventListener("change", function () {
                if (this.value) {
                    refreshStoreList();
                }
            });
        }
    });

    // Settings tabs initialization
    let scriptInitialized = false;
    let userLogsScriptInitialized = false;
    let printerScriptInitialized = false;

    const settingsTab = document.getElementById("settingsTab");
    if (settingsTab) {
        settingsTab.addEventListener("shown.bs.tab", function (event) {
            const targetTab = event.target.getAttribute("data-bs-target");

            if (targetTab === "#usertimerecord" && !scriptInitialized) {
                initTimeRecordScript();
                scriptInitialized = true;
            }

            if (targetTab === "#userlogs" && !userLogsScriptInitialized) {
                initUserLogsScript();
                userLogsScriptInitialized = true;
            }

            if (targetTab === "#printer" && !printerScriptInitialized) {
                initPrinterManagement();
                printerScriptInitialized = true;
            }
        });
    }
});

// Modal management initialization
function initializeModalManagement() {
    const settingsModal = document.getElementById("settingsModal");
    if (!settingsModal) return;

    window.printerModalState.settingsModalInstance =
        bootstrap.Modal.getOrCreateInstance(settingsModal, {
            backdrop: "static",
            keyboard: false,
        });

    settingsModal.addEventListener("hide.bs.modal", function (e) {
        if (window.printerModalState.activeModal) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    });

    setupPrinterModalListeners();
}

function setupPrinterModalListeners() {
    const printerModalIds = [
        "addPrinterModal",
        "editPrinterModal",
        "deletePrinterModal",
        "marryPrintersModal",
    ];

    printerModalIds.forEach((modalId) => {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.addEventListener("show.bs.modal", function (e) {
            console.log(`${modalId} is opening`);
            handlePrinterModalOpen(modalId, this);
        });

        modal.addEventListener("shown.bs.modal", function (e) {
            console.log(`${modalId} is now visible`);
            forceModalInteractive(this);
        });

        modal.addEventListener("hide.bs.modal", function (e) {
            console.log(`${modalId} is closing`);
            handlePrinterModalClose(modalId, this);
        });

        modal.addEventListener("hidden.bs.modal", function (e) {
            console.log(`${modalId} is now hidden`);
            handlePrinterModalClose(modalId, this);
        });
    });
}

// Utility function for toast notifications
function showToast(message, type = "info") {
    let toastContainer = document.getElementById("toastContainer");
    if (!toastContainer) {
        toastContainer = document.createElement("div");
        toastContainer.id = "toastContainer";
        toastContainer.className =
            "toast-container position-fixed top-0 end-0 p-3";
        toastContainer.style.zIndex = "9999";
        document.body.appendChild(toastContainer);
    }

    const toastId = "toast-" + Date.now();
    const bgClass =
        type === "success"
            ? "bg-success"
            : type === "error"
            ? "bg-danger"
            : "bg-info";

    const toastHTML = `
        <div id="${toastId}" class="toast ${bgClass} text-white" role="alert">
            <div class="toast-body">${message}</div>
        </div>
    `;

    toastContainer.insertAdjacentHTML("beforeend", toastHTML);

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();

    toastElement.addEventListener("hidden.bs.toast", function () {
        this.remove();
    });
}
