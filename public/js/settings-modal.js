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
        .addEventListener("click", function () {
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

            axios
                .post("/add-store", { storename: storeName })
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
        container.innerHTML +=
            '<div class="loading-spinner">Loading stores...</div>';
    }

    function hideLoadingIndicator() {
        const spinner = document.querySelector(".loading-spinner");
        if (spinner) {
            spinner.remove();
        }
    }

    function showErrorMessage(message) {
        document.getElementById(
            "storeContainer"
        ).innerHTML = `<div class="alert alert-danger">${message}</div>`;
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
        .addEventListener("submit", function (e) {
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
        .addEventListener("click", function () {
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
            })
            .catch((error) => {
                console.error("Error fetching marketplaces:", error);
                alert("Failed to load marketplaces.");
            });
    }

    function updateMarketplaceFields() {
        const marketplaceSelect = document.getElementById("selectMarketplace");
        const selectedOptions = Array.from(marketplaceSelect.selectedOptions);

        // Retrieve existing values from the input fields
        const currentNames = document
            .getElementById("editMarketplace")
            .value.split(",")
            .map((name) => name.trim());
        const currentIDs = document
            .getElementById("editMarketplaceID")
            .value.split(",")
            .map((id) => id.trim());

        // Add new values, avoiding duplicates
        selectedOptions.forEach((option) => {
            if (!currentNames.includes(option.textContent)) {
                currentNames.push(option.textContent);
                currentIDs.push(option.value);
            }
        });

        // Update the fields with the updated values
        document.getElementById("editMarketplace").value = currentNames
            .filter(Boolean)
            .join(", ");
        document.getElementById("editMarketplaceID").value = currentIDs
            .filter(Boolean)
            .join(", ");
    }

    // Attach event listeners
    document
        .getElementById("editStoreModal")
        .addEventListener("show.bs.modal", fetchMarketplaces);
    document
        .getElementById("selectMarketplace")
        .addEventListener("change", updateMarketplaceFields);

    // Settings -  Time Record & Userlogs  -----
    let scriptInitialized = false;
    let userLogsScriptInitialized = false;

    const settingsTab = document.getElementById("settingsTab");
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
    });

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

        function fetchTimeRecords() {
            const userId = selectUser.value || CURRENT_USER_ID;

            // Get current date in YYYY-MM-DD format
            const today = new Date().toISOString().split("T")[0];

            // Default to 2000-01-01 if empty
            const start = startDate.value || "2025-01-01";
            const end = endDate.value || today;

            // Populate date inputs visually if empty
            if (!startDate.value) startDate.value = start;
            if (!endDate.value) endDate.value = end;

            // Validate range
            if (new Date(start) > new Date(end)) {
                alert("Please select a valid date range.");
                return;
            }

            // Loading placeholders
            tbody.innerHTML = `<tr><td colspan="3" class="text-center">Loading records...</td></tr>`;
            mobileContainer.innerHTML = `<div class="alert alert-info text-center">Loading records...</div>`;

            // Fetch records
            fetch(
                `/get-time-records/${userId}?start_date=${start}&end_date=${end}`
            )
                .then((response) => response.json())
                .then((data) => {
                    tbody.innerHTML = "";
                    mobileContainer.innerHTML = "";

                    if (data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="3" class="text-center">No logs found</td></tr>`;
                        mobileContainer.innerHTML = `<div class="alert alert-info text-center">No logs found</div>`;
                        return;
                    }

                    data.forEach((record, index) =>
                        renderRecord(record, index)
                    );
                })
                .catch((error) => {
                    console.error("Error fetching time records:", error);
                    tbody.innerHTML = `<tr><td colspan="3" class="text-danger text-center">Error loading records</td></tr>`;
                    mobileContainer.innerHTML = `<div class="alert alert-danger text-center">Error loading records</div>`;
                });
        }

        // Event listeners
        selectUser.addEventListener("change", fetchTimeRecords);
        filterButton.addEventListener("click", fetchTimeRecords);

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
            const userId = selectUser.value || CURRENT_USER_ID;
            const today = new Date().toISOString().split("T")[0];
            const start = startDate.value || "2025-01-01";
            const end = endDate.value || today;

            // Fill inputs visually if empty
            if (!startDate.value) startDate.value = start;
            if (!endDate.value) endDate.value = end;

            const params = new URLSearchParams({
                user_id: userId,
                start_date_logs: start,
                end_date_logs: end,
            });

            // Show loading state
            tbody.innerHTML = `<tr><td colspan="3" class="text-center">Loading logs...</td></tr>`;
            cardContainer.innerHTML = `<div class="alert alert-info text-center">Loading logs...</div>`;

            fetch(`/get-user-logs?${params}`)
                .then((response) => response.json())
                .then((data) => {
                    tbody.innerHTML = "";
                    cardContainer.innerHTML = "";

                    if (data.length > 0) {
                        data.forEach((log, index) => {
                            const formattedDate = formatDate(log.datetimelogs);
                            const actions = log.actions || "-";
                            const cardBg =
                                index % 2 === 0 ? "bg-light" : "bg-white";

                            // Desktop table row
                            tbody.innerHTML += `
                                    <tr class="tr-notes">
                                        <td class="td-notes">${log.username}</td>
                                        <td class="td-notes notes-column">${actions}</td>
                                        <td class="td-notes">${formattedDate}</td>
                                    </tr>`;

                            // Mobile card
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
                        });
                    } else {
                        tbody.innerHTML = `<tr><td colspan="3" class="td-notes text-center">No logs found</td></tr>`;
                        cardContainer.innerHTML = `<div class="alert alert-info text-center">No logs found</div>`;
                    }
                })
                .catch((error) => {
                    console.error("Error fetching user logs:", error);
                    tbody.innerHTML = `<tr><td colspan="3" class="td-notes text-center text-danger">Error loading logs</td></tr>`;
                    cardContainer.innerHTML = `<div class="alert alert-danger text-center">Error loading logs</div>`;
                });
        }

        // Event listeners
        selectUser.addEventListener("change", fetchUserLogs);
        filterButton.addEventListener("click", fetchUserLogs);

        // Initial load
        fetchUserLogs();
    }
});
