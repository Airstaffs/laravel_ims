// ==========================
// GLOBAL PRINTER FUNCTIONS - MUST BE AT THE TOP BEFORE DOMContentLoaded
// ==========================

// Make printer functions globally accessible for onclick handlers
window.editPrinter = function(printerId) {
    fetch(`/api/printer-management/get-printer/${printerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const printer = data.printer;
                document.getElementById('editPrinterId').value = printer.printerid;
                document.getElementById('editPrinterName').value = printer.printername;
                document.getElementById('editPrinterType').value = printer.printer_type;
                document.getElementById('editPrinterIP').value = printer.printerip;
                document.getElementById('editPrinterPort').value = printer.port || '';
                document.getElementById('editPrinterDescription').value = printer.description || '';
                document.getElementById('editPrinterStatus').value = printer.status || 'active';
                
                bootstrap.Modal.getOrCreateInstance(document.getElementById('editPrinterModal')).show();
            }
        })
        .catch(error => {
            console.error('Error fetching printer details:', error);
            alert('Error fetching printer details');
        });
};

window.testPrinter = function(printerId) {
    fetch(`/api/printer-management/test-printer/${printerId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Printer test successful!');
        } else {
            alert('Printer test failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error testing printer:', error);
        alert('Error testing printer connection');
    });
};

window.showDeletePrinterConfirmation = function(printerId) {
    // Set global variable that's accessible everywhere
    window.currentDeletePrinterId = printerId;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deletePrinterModal')).show();
};

window.divorcePrinters = function(marriageId) {
    if (confirm('Are you sure you want to divorce these printers? This will break their marriage and they will be available for new marriages.')) {
        fetch(`/api/printer-management/divorce-printers/${marriageId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Printers divorced successfully!');
                // Call the global functions if they exist
                if (typeof window.refreshPrinterData === 'function') {
                    window.refreshPrinterData();
                }
            } else {
                alert(data.message || 'Error divorcing printers');
            }
        })
        .catch(error => {
            console.error('Error divorcing printers:', error);
            alert('Error divorcing printers');
        });
    }
};

// ==========================
// START OF YOUR EXISTING DOMContentLoaded CODE
// ==========================

document.addEventListener("DOMContentLoaded", function () {
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

    settingsModalEl.addEventListener("shown.bs.modal", function () {
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

    // ==========================
    // User Management
    // ==========================

    let deleteUserId = null;
    let skipModalCycle = false;

    // Fetch & Render Users
    function fetchUsers() {
        fetch(window.routes.fetchUsers)
            .then((res) => res.json())
            .then((data) => {
                const tbody = document.getElementById("userTableBody");
                if (!data.status || !data.data) return;

                tbody.innerHTML =
                    data.data
                        .map((user) => {
                            const createdAt = new Date(
                                user.created_at
                            ).toLocaleString();
                            const badgeClass =
                                user.role === "SuperAdmin"
                                    ? "bg-danger"
                                    : user.role === "SubAdmin"
                                    ? "bg-warning"
                                    : "bg-info";
                            return `
                            <tr>
                                <td>${user.username}</td>
                                <td><span class="badge ${badgeClass}">${user.role}</span></td>
                                <td>${createdAt}</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="editUser(${user.id}, '${user.username}', '${user.role}')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="showDeleteConfirmation(${user.id}, '${user.username}')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>`;
                        })
                        .join("") ||
                    `<tr><td colspan="4" class="text-center">No users found</td></tr>`;
            })
            .catch((err) => {
                console.error("Error:", err);
                document.getElementById("userTableBody").innerHTML =
                    '<tr><td colspan="4" class="text-center text-danger">Error loading users</td></tr>';
            });
    }

    // Show User List Modal
    userListModal?.addEventListener("show.bs.modal", () => {
        document.activeElement?.blur();
        bootstrap.Modal.getInstance(settingsModalEl)?.hide();
        fetchUsers();
    });

    // Clean up backdrop after closing
    userListModal?.addEventListener("hidden.bs.modal", function () {
        if (
            !document
                .getElementById("editUserModal")
                ?.classList.contains("show")
        );
    });

    // Add User Form
    addUserForm?.addEventListener("submit", function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch(window.routes.addUser, {
            method: "POST",
            body: formData,
        })
            .then((res) =>
                res.ok
                    ? res.json()
                    : res.json().then((err) => Promise.reject(err))
            )
            .then((data) => {
                if (!data.success) throw new Error(data.message);
                document.activeElement?.blur();
                bootstrap.Modal.getInstance(settingsModalEl)?.hide();
                this.reset();
                alert("User added successfully!");
                bootstrap.Modal.getOrCreateInstance(userListModal).show();
                fetchUsers();
            })
            .catch((err) => {
                console.error("Error:", err);
                alert(err.message || "Error adding user");
            });
    });

    // Expose Edit Function
    window.editUser = function (userId, username, role) {
        document.activeElement?.blur();
        bootstrap.Modal.getInstance(userListModal)?.hide();
        bootstrap.Modal.getInstance(settingsModalEl)?.hide();

        // Prevent recursive modal cycle
        skipModalCycle = true;

        document.getElementById("edit_user_id").value = userId;
        document.getElementById("edit_username").value = username;
        document.getElementById("edit_role").value = role;
        document.getElementById("edit_password").value = "";

        setTimeout(() => {
            bootstrap.Modal.getOrCreateInstance(editUserModal).show();
        }, 150);
    };

    // Submit Edit Form
    document
        .getElementById("editUserForm")
        ?.addEventListener("submit", function (e) {
            e.preventDefault();
            const userId = document.getElementById("edit_user_id").value;

            fetch(`${window.routes.updateUser}/${userId}`, {
                method: "POST",
                body: new FormData(this),
                headers: {
                    "X-CSRF-TOKEN": document.querySelector(
                        "meta[name='csrf-token']"
                    ).content,
                },
            })
                .then((res) => res.json())
                .then((data) => {
                    if (!data.success) throw new Error(data.message);
                    alert("User updated successfully!");
                    bootstrap.Modal.getInstance(editUserModal)?.hide();
                    bootstrap.Modal.getOrCreateInstance(userListModal).show();
                    fetchUsers();
                })
                .catch((error) => {
                    console.error("Update Error:", error);
                    alert(error.message || "Error updating user");
                });
        });

    // Show User List after editing (prevent recursion)
    editUserModal?.addEventListener("hidden.bs.modal", () => {
        // Ensure focus is blurred to avoid aria-hidden conflicts
        if (editUserModal.contains(document.activeElement)) {
            document.activeElement.blur();
        }

        // Show the settings modal after edit closes
        const userListModalInstance =
            bootstrap.Modal.getOrCreateInstance(userListModal);
        userListModalInstance.show();
    });

    document.getElementById("confirmDelete")?.addEventListener("click", () => {
        if (!deleteUserId) return;

        fetch(`${window.routes.deleteUser}/${deleteUserId}`, {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": document.querySelector(
                    "meta[name='csrf-token']"
                ).content,
            },
        })
            .then((res) => res.json())
            .then((data) => {
                if (!data.success) throw new Error(data.message);
                document.activeElement?.blur();
                bootstrap.Modal.getInstance(deleteUserModal)?.hide();
                alert("User deleted successfully!");
                bootstrap.Modal.getOrCreateInstance(userListModal).show();
                fetchUsers();
            })
            .catch((error) => {
                console.error("Delete Error:", error);
                alert(error.message || "Error deleting user");
            });
    });

    deleteUserModal?.addEventListener("show.bs.modal", () => {
        document.activeElement?.blur();
        bootstrap.Modal.getInstance(userListModal)?.hide();
    });

    deleteUserModal?.addEventListener("hidden.bs.modal", () => {
        if (
            !userListModal?.classList.contains("show") &&
            !editUserModal?.classList.contains("show") &&
            !settingsModalEl?.classList.contains("show")
        ) {
            document.querySelector(".modal-backdrop")?.remove();
        }
    });

    axios.defaults.headers.common["X-CSRF-TOKEN"] = document
        .querySelector('meta[name="csrf-token"]')
        .getAttribute("content");

    // Show the add store modal and hide the settings modal
    document
        .getElementById("addStoreButton")
        ?.addEventListener("click", function () {
            // Show the add store modal
            $("#addStoreModal").modal("show");
            $("#settingsModal").modal("hide");
        });

    // Add Store Submission
    document
        .getElementById("addStoreForm")
        ?.addEventListener("submit", function (e) {
            e.preventDefault();

            const storeName = document
                .getElementById("newStoreName")
                .value.trim();

            const Strabbreviation = document
                .getElementById("Strabbreviation")
                .value.trim();
            if (!storeName) {
                alert("Store name cannot be empty.");
                return;
            }

            // Normalize and check for duplicate
            const existingStores = Array.from(
                document.querySelectorAll("#storeList li")
            );
            const storeExists = existingStores.some(
                (store) =>
                    store.textContent.trim().toLowerCase() ===
                    storeName.toLowerCase()
            );

            if (storeExists) {
                alert(
                    "Store name already exists. Please choose a different name."
                );
                return;
            }

            console.log(Strabbreviation);

            axios
                .post("/add-store", { storename: storeName, Strabbreviation: Strabbreviation })
                .then((response) => {
                    if (!response.data.success)
                        throw new Error("Failed to add store.");

                    // Add new store to list
                    const newItem = document.createElement("li");
                    newItem.className = "list-group-item";
                    newItem.innerHTML = `
                ${response.data.store.storename}
                <div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-secondary btn-sm edit-store-btn"
                        data-id="${response.data.store.store_id}"
                        data-name="${response.data.store.storename}">
                        Edit
                    </button>
                    <button class="btn btn-danger btn-sm delete-store-btn"
                        data-id="${response.data.store.store_id}">
                        Delete
                    </button>
                </div>
            `;
                    document.getElementById("storeList").appendChild(newItem);

                    alert(
                        `Store "${response.data.store.storename}" added successfully!`
                    );

                    // Hide addStoreModal and reset form
                    const addStoreModal = bootstrap.Modal.getInstance(
                        document.getElementById("addStoreModal")
                    );
                    addStoreModal?.hide();
                    e.target.reset();

                    // On modal hide, show settings modal with store tab active
                    const settingsModal = new bootstrap.Modal(
                        document.getElementById("settingsModal")
                    );
                    const addStoreModalEl =
                        document.getElementById("addStoreModal");

                    addStoreModalEl.addEventListener(
                        "hidden.bs.modal",
                        function handler() {
                            settingsModal.show();

                            // Activate store tab
                            const storeTab =
                                document.getElementById("store-tab");
                            const tabInstance =
                                bootstrap.Tab.getOrCreateInstance(storeTab);
                            tabInstance.show();

                            // Remove listener after first execution
                            addStoreModalEl.removeEventListener(
                                "hidden.bs.modal",
                                handler
                            );
                        }
                    );
                })
                .catch((error) => {
                    console.error("Store Add Error:", error);
                    alert(
                        error.message ||
                            "An error occurred while saving the store."
                    );
                });
        });

    // Fetch and display the list of stores on page load
    document.addEventListener("DOMContentLoaded", function () {
        fetchStoreList();
    });

    // Function to fetch and display store list from the server
    function fetchStoreList() {
        axios
            .get("/get-stores")
            .then((response) => {
                const storeList = document.getElementById("storeList");
                if (storeList) {
                    storeList.innerHTML = ""; // Clear the list before populating it

                    response.data.stores.forEach((store) => {
                        const listItem = document.createElement("li");
                        listItem.classList.add("list-group-item");
                        listItem.innerHTML = `
                        ${store.storename}
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-secondary btn-sm edit-store-btn"
                                    data-id="${store.store_id}"
                                    data-name="${store.storename}">
                                Edit
                            </button>
                            <button class="btn btn-danger btn-sm delete-store-btn"
                                    data-id="${store.store_id}">
                                Delete
                            </button>
                        </div>
                    `;
                        storeList.appendChild(listItem);
                    });
                }
            })
            .catch((error) => {
                console.error("Error fetching stores:", error);
            });
    }

    // Re-fetch store list when switching to the "Store List" tab
    $("#store-tab").on("click", function () {
        fetchStoreList(); // Re-fetch the store list when the tab is clicked
    });

    function refreshStoreList() {
        const userId = document.getElementById("selectUser").value;
        if (!userId) {
            console.warn("No user selected");
            return;
        }

        showLoadingIndicator();

        fetch(`/fetchNewlyAddedStoreCol?user_id=${userId}`)
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then((data) => {
                if (data && data.stores) {
                    updateStoreList(data.stores);
                }
            })
            .catch((error) => {
                console.error("Error fetching store list:", error);
                showErrorMessage("Failed to load stores. Please try again.");
            })
            .finally(() => {
                hideLoadingIndicator();
            });
    }

    function updateStoreList(stores) {
        const storeContainer = document.getElementById("storeContainer");

        // Save current checkbox states
        const currentStates = new Map();
        document
            .querySelectorAll('input[name="privileges_stores[]"]')
            .forEach((input) => {
                currentStates.set(input.value, input.checked);
            });

        let storeListHTML = '<h6>Stores</h6><div class="row mb-3">';

        stores.forEach((store) => {
            // Check if we have a saved state, otherwise use the server state
            const isChecked = currentStates.has(store.store_column)
                ? currentStates.get(store.store_column)
                : store.is_checked;

            storeListHTML += `
            <div class="col-4 form-check mb-2">
                <input class="form-check-input"
                       type="checkbox"
                       name="privileges_stores[]"
                       value="${store.store_column}"
                       ${isChecked ? "checked" : ""}>
                <label class="form-check-label">${store.store_name}</label>
            </div>`;
        });

        storeListHTML += "</div>";
        storeContainer.innerHTML = storeListHTML;
    }

    function showLoadingIndicator() {
        const container = document.getElementById("storeContainer");
        if (container) {
            container.innerHTML +=
                '<div class="loading-spinner">Loading stores...</div>';
        }
    }

    function hideLoadingIndicator() {
        const spinner = document.querySelector(".loading-spinner");
        if (spinner) {
            spinner.remove();
        }
    }

    function showErrorMessage(message) {
        const storeContainer = document.getElementById("storeContainer");
        if (storeContainer) {
            storeContainer.innerHTML = `<div class="alert alert-danger">${message}</div>`;
        }
    }

    // Event Listeners
    document.addEventListener("DOMContentLoaded", function () {
        // Initialize privilege tab listener
        const privilegeTab = document.getElementById("privilege-tab");
        if (privilegeTab) {
            privilegeTab.addEventListener("click", function () {
                const userId = document.getElementById("selectUser").value;
                if (userId) {
                    refreshStoreList();
                }
            });
        }

        // Initialize select user change listener
        const selectUser = document.getElementById("selectUser");
        if (selectUser) {
            selectUser.addEventListener("change", function () {
                if (this.value) {
                    refreshStoreList();
                }
            });
        }
    });
    // Delete Store functionality
    document.addEventListener("click", function (e) {
        if (e.target.classList.contains("delete-store-btn")) {
            const storeId = e.target.dataset.id;

            // Confirm before deleting
            if (confirm("Are you sure you want to delete this store?")) {
                // Send the delete request to the backend
                axios
                    .delete(`/delete-store/${storeId}`)
                    .then((response) => {
                        if (response.data.success) {
                            const storeItem = e.target.closest("li");
                            storeItem.remove();
                        }
                    })
                    .catch((error) => {
                        console.error("Error deleting store:", error);
                        alert(
                            "An error occurred while deleting the store. Please try again later."
                        );
                    });
            }
        }
    });

    $(document).on("click", ".edit-store-btn", function () {
        const storeId = $(this).data("id");
        $("#settingsModal").modal("hide");
        // Fetch the store details using the store ID
        axios
            .get(`/get-store/${storeId}`)
            .then((response) => {
                const store = response.data.store;

                // Populate the modal with the current store details
                $("#editStoreId").val(store.store_id);
                $("#editStoreName").val(store.storename);
                $("#editClientID").val(store.client_id);
                $("#editClientSecret").val(store.client_secret);
                $("#editRefreshToken").val(store.refresh_token);
                $("#editMerchantID").val(store.MerchantID);
                $("#editMarketplace").val(store.Marketplace);
                $("#editMarketplaceID").val(store.MarketplaceID);

                // Show the modal
                $("#editStoreModal").modal("show");
            })
            .catch((error) => {
                console.error("Error fetching store details:", error);
                alert("An error occurred while fetching store details.");
            });
    });

    document
        .getElementById("editStoreForm")
        ?.addEventListener("submit", function (e) {
            e.preventDefault(); // Prevent default form submission

            const storeId = document.getElementById("editStoreId").value.trim();
            if (!storeId) {
                alert("Store ID is missing. Please try again.");
                return;
            }

            // Gather the updated data from the form
            const updatedStoreData = {
                store_id: storeId, // Should match the store_id column in the database
                storename:
                    document.getElementById("editStoreName").value.trim() ||
                    null,
                client_id:
                    document.getElementById("editClientID").value.trim() ||
                    null,
                client_secret:
                    document.getElementById("editClientSecret").value.trim() ||
                    null,
                refresh_token:
                    document.getElementById("editRefreshToken").value.trim() ||
                    null,
                MerchantID:
                    document.getElementById("editMerchantID").value.trim() ||
                    null,
                Marketplace:
                    document.getElementById("editMarketplace").value.trim() ||
                    null,
                MarketplaceID:
                    document.getElementById("editMarketplaceID").value.trim() ||
                    null,
            };

            console.log(updatedStoreData);

            // Send request to update store
            axios
                .post("/update-store/" + storeId, updatedStoreData, {
                    headers: {
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute("content"),
                    },
                })
                .then((response) => {
                    console.log(response);
                    if (response.data.success) {
                        alert("Store updated successfully");
                        fetchStoreList();
                        $("#editStoreModal").modal("hide");
                        $("#settingsModal").modal("show");
                        $("#store-tab").tab("show");
                    } else {
                        // Display the error message returned by the server
                        alert(
                            response.data.message || "Failed to update store"
                        );
                    }
                })
                .catch((error) => {
                    console.error("Error updating store:", error);
                    alert("An error occurred while updating the store.");
                });
        });

    // Alternatively, if you're using the close button explicitly, you can handle it like this:
    document
        .querySelector("#editStoreModal .btn-close")
        ?.addEventListener("click", function () {
            // Show the settings modal and select the store tab after closing the edit modal
            $("#settingsModal").modal("show");
            $("#store-tab").tab("show"); // This activates the store tab
        });

    function fetchMarketplaces() {
        console.log("Modal is shown, fetching marketplaces...");
        axios
            .get("/fetch-marketplaces")
            .then((response) => {
                const marketplaceSelect =
                    document.getElementById("selectMarketplace");

                if (marketplaceSelect) {
                    // Clear previous options
                    marketplaceSelect.innerHTML = "";

                    // Optional: Add a placeholder-like option (disabled)
                    if (response.data.length === 0) {
                        const placeholder = document.createElement("option");
                        placeholder.textContent = "No marketplaces available";
                        placeholder.disabled = true;
                        marketplaceSelect.appendChild(placeholder);
                        return;
                    }

                    // Populate select with fetched marketplaces
                    response.data.forEach((marketplace) => {
                        const option = document.createElement("option");
                        option.value =
                            marketplace.value ?? marketplace.id ?? marketplace.name; // fallback chain
                        option.textContent =
                            marketplace.name ??
                            marketplace.label ??
                            marketplace.value;
                        marketplaceSelect.appendChild(option);
                    });
                }
            })
            .catch((error) => {
                console.error("Error fetching marketplaces:", error);
                alert("Failed to load marketplaces.");
            });
    }

    function updateMarketplaceFields() {
        const marketplaceSelect = document.getElementById("selectMarketplace");
        if (!marketplaceSelect) return;
        
        const selectedOptions = Array.from(marketplaceSelect.selectedOptions);

        // Retrieve existing values from the input fields
        const editMarketplace = document.getElementById("editMarketplace");
        const editMarketplaceID = document.getElementById("editMarketplaceID");
        
        if (!editMarketplace || !editMarketplaceID) return;
        
        const currentNames = editMarketplace.value.split(",").map((name) => name.trim());
        const currentIDs = editMarketplaceID.value.split(",").map((id) => id.trim());

        // Add new values, avoiding duplicates
        selectedOptions.forEach((option) => {
            if (!currentNames.includes(option.textContent)) {
                currentNames.push(option.textContent);
                currentIDs.push(option.value);
            }
        });

        // Update the fields with the updated values
        editMarketplace.value = currentNames.filter(Boolean).join(", ");
        editMarketplaceID.value = currentIDs.filter(Boolean).join(", ");
    }

    // Attach event listeners
    document
        .getElementById("editStoreModal")
        ?.addEventListener("show.bs.modal", fetchMarketplaces);
    document
        .getElementById("selectMarketplace")
        ?.addEventListener("change", updateMarketplaceFields);

    // Settings -  Time Record & Userlogs  -----
    let scriptInitialized = false;
    let userLogsScriptInitialized = false;
    let printerScriptInitialized = false; // Add printer script initialization flag

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

            // Add printer tab initialization
            if (targetTab === "#printer" && !printerScriptInitialized) {
                initPrinterManagement();
                printerScriptInitialized = true;
            }
        });
    }

    // ==========================
    // Printer Management - UPDATED VERSION WITH ELEMENT CHECKS
    // ==========================

    function initPrinterManagement() {
        console.log('Initializing printer management...');
        
        // Check if required elements exist before proceeding
        const allPrintersTable = document.getElementById('allPrintersTableBody');
        if (!allPrintersTable) {
            console.error('Printer table element not found. Make sure the printer tab HTML is loaded.');
            return;
        }
        
        // Load all printers on initialization
        fetchAllPrinters();
        loadAvailablePrinters();
        
        // Setup event listeners for sub-tabs
        setupSubTabListeners();
        
        // Setup form event listeners
        setupFormListeners();
        
        // Make internal functions globally accessible for refreshing data
        window.refreshPrinterData = function() {
            fetchAllPrinters();
            fetchMarriedPrinters();
            loadAvailablePrinters();
        };
    }

   function setupSubTabListeners() {
    // When switching to small label tab
    const smallLabelTab = document.getElementById('small-label-tab');
    if (smallLabelTab) {
        smallLabelTab.addEventListener('shown.bs.tab', function (e) {
            console.log('Small label tab shown');
            fetchPrintersByType('small_label');
        });
    }

    // When switching to instruction card tab
    const instructionCardTab = document.getElementById('instruction-card-tab');
    if (instructionCardTab) {
        instructionCardTab.addEventListener('shown.bs.tab', function (e) {
            console.log('Instruction card tab shown');
            fetchPrintersByType('instruction_card');
        });
    }

    // When switching to married printer tab
    const marriedPrinterTab = document.getElementById('married-printer-tab');
    if (marriedPrinterTab) {
        marriedPrinterTab.addEventListener('shown.bs.tab', function (e) {
            console.log('Married printer tab shown');
            fetchMarriedPrinters();
        });
    }

    // Also add click listeners as backup
    smallLabelTab?.addEventListener('click', function() {
        setTimeout(() => fetchPrintersByType('small_label'), 100);
    });

    instructionCardTab?.addEventListener('click', function() {
        setTimeout(() => fetchPrintersByType('instruction_card'), 100);
    });

    marriedPrinterTab?.addEventListener('click', function() {
        setTimeout(() => fetchMarriedPrinters(), 100);
    });
}

    function setupFormListeners() {
        // Add Printer Form
        document.getElementById('addPrinterForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            addNewPrinter(this);
        });

        // Edit Printer Form
        document.getElementById('editPrinterForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            updatePrinter(this);
        });

        // Marry Printers Form
        document.getElementById('marryPrintersForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            marryPrinters(this);
        });

        // Delete confirmation - Updated to use global variable
        document.getElementById('confirmDeletePrinter')?.addEventListener('click', function() {
            deletePrinter();
        });
    }

    // Fetch all printers and display in main table
    function fetchAllPrinters() {
        // Check if element exists before making request
        const tbody = document.getElementById('allPrintersTableBody');
        if (!tbody) {
            console.error('allPrintersTableBody element not found');
            return;
        }

        fetch('/api/printer-management/get-printers')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderAllPrintersTable(data.printers || []);
                } else {
                    console.error('Failed to fetch printers:', data.message);
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading printers</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error fetching printers:', error);
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading printers</td></tr>';
            });
    }

    function renderAllPrintersTable(printers) {
        const tbody = document.getElementById('allPrintersTableBody');
        
        // Double-check element exists
        if (!tbody) {
            console.error('allPrintersTableBody element not found in renderAllPrintersTable');
            return;
        }
        
        if (printers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">No printers found</td></tr>';
            return;
        }

        tbody.innerHTML = printers.map(printer => {
            const statusBadge = getStatusBadge(printer.status);
            const typeBadge = getTypeBadge(printer.printer_type);
            const marriageStatus = printer.married_to_printer_id ? 
                '<i class="bi bi-heart-fill text-success" title="Married"></i>' : 
                '<i class="bi bi-heart text-muted" title="Single"></i>';
            
            return `
                <tr>
                    <td>${printer.printername || 'Unknown'} ${marriageStatus}</td>
                    <td>${typeBadge}</td>
                    <td>${printer.printerip || 'N/A'}:${printer.port || '9100'}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                onclick="editPrinter(${printer.printerid})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success"
                                onclick="testPrinter(${printer.printerid})">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                onclick="showDeletePrinterConfirmation(${printer.printerid})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Fetch printers by type for specific tabs
    function fetchPrintersByType(type) {
        fetch(`/api/printer-management/get-printers?type=${type}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (type === 'small_label') {
                        renderPrinterCards(data.printers || [], 'smallLabelPrintersGrid');
                    } else if (type === 'instruction_card') {
                        renderPrinterCards(data.printers || [], 'instructionCardPrintersGrid');
                    }
                }
            })
            .catch(error => {
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
            container.innerHTML = '<div class="col-12"><div class="alert alert-info text-center">No printers found</div></div>';
            return;
        }

        container.innerHTML = printers.map(printer => {
            const statusBadge = getStatusBadge(printer.status);
            const marriageStatus = printer.married_to_printer_id ? 
                '<span class="badge bg-success ms-2">Married</span>' : 
                '<span class="badge bg-secondary ms-2">Single</span>';
            
            return `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card printer-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">${printer.printername || 'Unknown'}${marriageStatus}</h6>
                            ${statusBadge}
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <strong>IP:</strong> ${printer.printerip || 'N/A'}<br>
                                <strong>Port:</strong> ${printer.port || '9100'}<br>
                                ${printer.description ? `<strong>Description:</strong> ${printer.description}<br>` : ''}
                                ${printer.married_to_printer_id ? `<strong>Married to ID:</strong> ${printer.married_to_printer_id}` : ''}
                            </p>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                    onclick="editPrinter(${printer.printerid})">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm"
                                    onclick="testPrinter(${printer.printerid})">
                                    <i class="bi bi-check-circle"></i> Test
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Add new printer
    function addNewPrinter(form) {
        const formData = new FormData(form);
        
        fetch('/api/printer-management/add-printer', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Printer added successfully!');
                const modal = document.getElementById('addPrinterModal');
                if (modal) {
                    bootstrap.Modal.getInstance(modal)?.hide();
                }
                form.reset();
                fetchAllPrinters();
                loadAvailablePrinters(); // Refresh dropdowns
            } else {
                alert(data.message || 'Error adding printer');
            }
        })
        .catch(error => {
            console.error('Error adding printer:', error);
            alert('Error adding printer');
        });
    }

    // Update printer
    function updatePrinter(form) {
        const printerId = document.getElementById('editPrinterId')?.value;
        if (!printerId) {
            alert('Printer ID not found');
            return;
        }
        
        const formData = new FormData(form);
        
        fetch(`/api/printer-management/update-printer/${printerId}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Printer updated successfully!');
                const modal = document.getElementById('editPrinterModal');
                if (modal) {
                    bootstrap.Modal.getInstance(modal)?.hide();
                }
                fetchAllPrinters();
            } else {
                alert(data.message || 'Error updating printer');
            }
        })
        .catch(error => {
            console.error('Error updating printer:', error);
            alert('Error updating printer');
        });
    }

    // Delete printer - Updated to use global variable
    function deletePrinter() {
        if (!window.currentDeletePrinterId) return;
        
        fetch(`/api/printer-management/delete-printer/${window.currentDeletePrinterId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Printer deleted successfully!');
                const modal = document.getElementById('deletePrinterModal');
                if (modal) {
                    bootstrap.Modal.getInstance(modal)?.hide();
                }
                fetchAllPrinters();
                loadAvailablePrinters(); // Refresh dropdowns
            } else {
                alert(data.message || 'Error deleting printer');
            }
        })
        .catch(error => {
            console.error('Error deleting printer:', error);
            alert('Error deleting printer');
        })
        .finally(() => {
            window.currentDeletePrinterId = null; // Clear the global variable
        });
    }

    // Load available printers for marriage dropdowns
    function loadAvailablePrinters() {
        fetch('/api/printer-management/get-available-printers')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateMarriageDropdowns(data.small_label || [], data.instruction_card || []);
                }
            })
            .catch(error => {
                console.error('Error loading available printers:', error);
            });
    }

    function populateMarriageDropdowns(smallLabelPrinters, instructionCardPrinters) {
        const smallLabelSelect = document.getElementById('smallLabelPrinter');
        const instructionCardSelect = document.getElementById('instructionCardPrinter');
        
        // Populate small label printers
        if (smallLabelSelect) {
            smallLabelSelect.innerHTML = '<option value="">Select Small Label Printer</option>';
            smallLabelPrinters.forEach(printer => {
                smallLabelSelect.innerHTML += `<option value="${printer.printerid}">${printer.printername}</option>`;
            });
        }
        
        // Populate instruction card printers
        if (instructionCardSelect) {
            instructionCardSelect.innerHTML = '<option value="">Select Instruction Card Printer</option>';
            instructionCardPrinters.forEach(printer => {
                instructionCardSelect.innerHTML += `<option value="${printer.printerid}">${printer.printername}</option>`;
            });
        }
    }

    // Marry printers
    function marryPrinters(form) {
        const formData = new FormData(form);
        
        fetch('/api/printer-management/marry-printers', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Printers married successfully!');
                const modal = document.getElementById('marryPrintersModal');
                if (modal) {
                    bootstrap.Modal.getInstance(modal)?.hide();
                }
                form.reset();
                fetchMarriedPrinters();
                loadAvailablePrinters(); // Refresh available printers
            } else {
                alert(data.message || 'Error marrying printers');
            }
        })
        .catch(error => {
            console.error('Error marrying printers:', error);
            alert('Error marrying printers');
        });
    }

    // Fetch and display married printers
    function fetchMarriedPrinters() {
        fetch('/api/printer-management/get-married-printers')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderMarriedPrinters(data.marriages || []);
                } else {
                    console.error('Failed to fetch married printers:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching married printers:', error);
                const container = document.getElementById('marriedPrintersContainer');
                if (container) {
                    container.innerHTML = '<div class="alert alert-danger">Error loading married printers</div>';
                }
            });
    }

    function renderMarriedPrinters(marriages) {
        const container = document.getElementById('marriedPrintersContainer');
        
        if (!container) {
            console.error('marriedPrintersContainer not found');
            return;
        }
        
        if (marriages.length === 0) {
            container.innerHTML = '<div class="alert alert-info text-center">No married printers found</div>';
            return;
        }

        container.innerHTML = marriages.map(marriage => `
            <div class="married-printer-pair p-3 mb-3">
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="bi bi-tag-fill text-primary fs-3"></i>
                                <h6 class="mt-2 mb-1">${marriage.small_label_printer.printer_name}</h6>
                                <small class="text-muted">${marriage.small_label_printer.ip_address}</small>
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
                                <h6 class="mt-2 mb-1">${marriage.instruction_card_printer.printer_name}</h6>
                                <small class="text-muted">${marriage.instruction_card_printer.ip_address}</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <h5 class="text-success">${marriage.marriage_name}</h5>
                    ${marriage.description ? `<p class="text-muted mb-2">${marriage.description}</p>` : ''}
                    <button type="button" class="btn btn-outline-danger btn-sm"
                        onclick="divorcePrinters(${marriage.small_label_printer.printer_id})">
                        <i class="bi bi-heart-break me-1"></i>
                        Divorce Printers
                    </button>
                </div>
            </div>
        `).join('');
    }

    // Helper functions
    function getStatusBadge(status) {
        const badges = {
            'active': '<span class="badge bg-success status-badge">Active</span>',
            'inactive': '<span class="badge bg-secondary status-badge">Inactive</span>',
            'maintenance': '<span class="badge bg-warning status-badge">Maintenance</span>'
        };
        return badges[status] || badges['inactive'];
    }

    function getTypeBadge(type) {
        const badges = {
            'small_label': '<span class="badge bg-primary">Small Label</span>',
            'instruction_card': '<span class="badge bg-info">Instruction Card</span>'
        };
        return badges[type] || '<span class="badge bg-secondary">Unknown</span>';
    }

    // ==========================
    // End Printer Management
    // ==========================

    function initTimeRecordScript() {
        const selectUser = document.getElementById("selectUserDrop");
        const startDate = document.getElementById("start_date");
        const endDate = document.getElementById("end_date");
        const filterButton = document.getElementById("filterRecords");
        const tbody = document.getElementById("timeRecordsBody");
        const mobileContainer = document.getElementById("timeRecordsMobile");

        function formatDate(date) {
            return new Date(date).toLocaleDateString("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric",
            });
        }

        function formatTime(date) {
            return new Date(date).toLocaleTimeString("en-US", {
                hour: "2-digit",
                minute: "2-digit",
                hour12: true,
            });
        }

        function calculateHours(timeIn, timeOut) {
            const diff = timeOut - timeIn;
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            return `${hours}h ${minutes}m`;
        }

        function renderRecord(record, index) {
            const timeIn = new Date(record.TimeIn);
            const timeOut = record.TimeOut ? new Date(record.TimeOut) : null;
            const totalHours = timeOut
                ? calculateHours(timeIn, timeOut)
                : "Active";
            const notes = record.Notes || "-";
            const timeOutStr = timeOut
                ? formatTime(timeOut)
                : "Not clocked out";
            const formattedDate = formatDate(timeIn);
            const cardBg = index % 2 === 0 ? "bg-light" : "bg-white";

            if (tbody) {
                tbody.innerHTML += `
                        <tr>
                            <td>
                                <ul class="list-unstyled m-0">
                                    <li><strong>${formattedDate}</strong></li>
                                    <li><strong>IN:</strong> ${formatTime(
                                        timeIn
                                    )}</li>
                                    <li><strong>OUT:</strong> ${timeOutStr}</li>
                                </ul>
                            </td>
                            <td>${totalHours}</td>
                            <td>${notes}</td>
                        </tr>`;
            }

            if (mobileContainer) {
                mobileContainer.innerHTML += `
                        <div class="card mb-3 shadow-sm ${cardBg}">
                            <div class="card-body">
                                <h6 class="mb-1"><strong>${formattedDate}</strong></h6>
                                <p class="mb-1"><strong>Time In:</strong> ${formatTime(
                                    timeIn
                                )}</p>
                                <p class="mb-1"><strong>Time Out:</strong> ${timeOutStr}</p>
                                <p class="mb-1"><strong>Total Hours:</strong> ${totalHours}</p>
                                <p class="mb-0"><strong>Notes:</strong> ${
                                    notes !== "-"
                                        ? `<i class="bi bi-sticky me-1"></i>${notes}`
                                        : "-"
                                }</p>
                            </div>
                        </div>`;
            }
        }

        function fetchTimeRecords() {
            const userId = selectUser?.value || CURRENT_USER_ID;

            // Get current date in YYYY-MM-DD format
            const today = new Date().toISOString().split("T")[0];

            // Default to 2000-01-01 if empty
            const start = startDate?.value || "2025-01-01";
            const end = endDate?.value || today;

            // Populate date inputs visually if empty
            if (startDate && !startDate.value) startDate.value = start;
            if (endDate && !endDate.value) endDate.value = end;

            // Validate range
            if (new Date(start) > new Date(end)) {
                alert("Please select a valid date range.");
                return;
            }

            // Loading placeholders
            if (tbody) tbody.innerHTML = `<tr><td colspan="3" class="text-center">Loading records...</td></tr>`;
            if (mobileContainer) mobileContainer.innerHTML = `<div class="alert alert-info text-center">Loading records...</div>`;

            // Fetch records
            fetch(
                `/get-time-records/${userId}?start_date=${start}&end_date=${end}`
            )
                .then((response) => response.json())
                .then((data) => {
                    if (tbody) tbody.innerHTML = "";
                    if (mobileContainer) mobileContainer.innerHTML = "";

                    if (data.length === 0) {
                        if (tbody) tbody.innerHTML = `<tr><td colspan="3" class="text-center">No logs found</td></tr>`;
                        if (mobileContainer) mobileContainer.innerHTML = `<div class="alert alert-info text-center">No logs found</div>`;
                        return;
                    }

                    data.forEach((record, index) =>
                        renderRecord(record, index)
                    );
                })
                .catch((error) => {
                    console.error("Error fetching time records:", error);
                    if (tbody) tbody.innerHTML = `<tr><td colspan="3" class="text-danger text-center">Error loading records</td></tr>`;
                    if (mobileContainer) mobileContainer.innerHTML = `<div class="alert alert-danger text-center">Error loading records</div>`;
                });
        }

        // Event listeners
        selectUser?.addEventListener("change", fetchTimeRecords);
        filterButton?.addEventListener("click", fetchTimeRecords);

        // Initial auto-load
        fetchTimeRecords();
    }

    function initUserLogsScript() {
        const selectUser = document.getElementById("selectUserDrop_logs");
        const startDate = document.getElementById("start_date_logs");
        const endDate = document.getElementById("end_date_logs");
        const filterButton = document.getElementById("filter_logs");
        const tbody = document.getElementById("userlogsData");
        const cardContainer = document.getElementById("userlogsCardView");

        // Format full datetime
        function formatDateTime(dateTime) {
            return new Date(dateTime).toLocaleString("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
                hour12: true,
            });
        }

        // Format just date
        function formatDate(dateTime) {
            return new Date(dateTime).toLocaleDateString("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric",
            });
        }

        // Fetch and display logs
        function fetchUserLogs() {
            const userId = selectUser?.value || CURRENT_USER_ID;
            const today = new Date().toISOString().split("T")[0];
            const start = startDate?.value || "2025-01-01";
            const end = endDate?.value || today;

            // Fill inputs visually if empty
            if (startDate && !startDate.value) startDate.value = start;
            if (endDate && !endDate.value) endDate.value = end;

            const params = new URLSearchParams({
                user_id: userId,
                start_date_logs: start,
                end_date_logs: end,
            });

            // Show loading state
            if (tbody) tbody.innerHTML = `<tr><td colspan="3" class="text-center">Loading logs...</td></tr>`;
            if (cardContainer) cardContainer.innerHTML = `<div class="alert alert-info text-center">Loading logs...</div>`;

            fetch(`/get-user-logs?${params}`)
                .then((response) => response.json())
                .then((data) => {
                    if (tbody) tbody.innerHTML = "";
                    if (cardContainer) cardContainer.innerHTML = "";

                    if (data.length > 0) {
                        data.forEach((log, index) => {
                            const formattedDate = formatDate(log.datetimelogs);
                            const actions = log.actions || "-";
                            const cardBg =
                                index % 2 === 0 ? "bg-light" : "bg-white";

                            // Desktop table row
                            if (tbody) {
                                tbody.innerHTML += `
                                        <tr class="tr-notes">
                                            <td class="td-notes">${log.username}</td>
                                            <td class="td-notes notes-column">${actions}</td>
                                            <td class="td-notes">${formattedDate}</td>
                                        </tr>`;
                            }

                            // Mobile card
                            if (cardContainer) {
                                cardContainer.innerHTML += `
                                        <div class="card mb-3 shadow-sm ${cardBg}">
                                            <div class="card-body">
                                                <h6 class="mb-1"><strong>User:</strong> ${
                                                    log.username
                                                }</h6>
                                                <p class="mb-1"><strong>Action:</strong> ${
                                                    log.actions
                                                        ? `<i class="bi bi-sticky me-1"></i>${log.actions}`
                                                        : "-"
                                                }</p>
                                                <p class="mb-0"><strong>Date:</strong> ${formattedDate}</p>
                                            </div>
                                        </div>`;
                            }
                        });
                    } else {
                        if (tbody) tbody.innerHTML = `<tr><td colspan="3" class="td-notes text-center">No logs found</td></tr>`;
                        if (cardContainer) cardContainer.innerHTML = `<div class="alert alert-info text-center">No logs found</div>`;
                    }
                })
                .catch((error) => {
                    console.error("Error fetching user logs:", error);
                    if (tbody) tbody.innerHTML = `<tr><td colspan="3" class="td-notes text-center text-danger">Error loading logs</td></tr>`;
                    if (cardContainer) cardContainer.innerHTML = `<div class="alert alert-danger text-center">Error loading logs</div>`;
                });
        }

        // Event listeners
        selectUser?.addEventListener("change", fetchUserLogs);
        filterButton?.addEventListener("click", fetchUserLogs);

        // Initial load
        fetchUserLogs();
    }
});