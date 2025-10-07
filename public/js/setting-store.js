document.addEventListener("DOMContentLoaded", function () {
    // Store management functions
    document
        .getElementById("addStoreButton")
        ?.addEventListener("click", function () {
            $("#addStoreModal").modal("show");
            $("#settingsModal").modal("hide");
        });

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
                .post("/add-store", {
                    storename: storeName,
                    Strabbreviation: Strabbreviation,
                })
                .then((response) => {
                    if (!response.data.success)
                        throw new Error("Failed to add store.");

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

                    // ✅ Success SWAL
                    Swal.fire({
                        icon: "success",
                        title: "Store added",
                        text: `Store "${response.data.store.storename}" added successfully!`,
                        confirmButtonText: "OK",
                    });

                    const addStoreModal = bootstrap.Modal.getInstance(
                        document.getElementById("addStoreModal")
                    );
                    addStoreModal?.hide();

                    // If this is inside a submit handler, make sure `e` is that event
                    // and not undefined.
                    e.target.reset();

                    const settingsModal = new bootstrap.Modal(
                        document.getElementById("settingsModal")
                    );
                    const addStoreModalEl =
                        document.getElementById("addStoreModal");

                    addStoreModalEl.addEventListener(
                        "hidden.bs.modal",
                        function handler() {
                            settingsModal.show();

                            const storeTab =
                                document.getElementById("store-tab");
                            const tabInstance =
                                bootstrap.Tab.getOrCreateInstance(storeTab);
                            tabInstance.show();

                            addStoreModalEl.removeEventListener(
                                "hidden.bs.modal",
                                handler
                            );
                        }
                    );
                })
                .catch((error) => {
                    console.error("Store Add Error:", error);
                    const message =
                        error?.response?.data?.message ||
                        error.message ||
                        "An error occurred while saving the store.";

                    Swal.fire({
                        icon: "error",
                        title: "Save failed",
                        text: message,
                        confirmButtonText: "OK",
                    });
                });
        });

    // Fetch and display the list of stores on page load
    document.addEventListener("DOMContentLoaded", function () {
        fetchStoreList();
    });

    function fetchStoreList() {
        axios
            .get("/get-stores")
            .then((response) => {
                const storeList = document.getElementById("storeList");
                if (storeList) {
                    storeList.innerHTML = "";

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

    $("#store-tab").on("click", function () {
        fetchStoreList();
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

        const currentStates = new Map();
        document
            .querySelectorAll('input[name="privileges_stores[]"]')
            .forEach((input) => {
                currentStates.set(input.value, input.checked);
            });

        let storeListHTML = '<h6>Stores</h6><div class="row mb-3">';

        stores.forEach((store) => {
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

    // Delete Store functionality
    document.addEventListener("click", async function (e) {
        const btn = e.target.closest(".delete-store-btn");
        if (!btn) return;

        const storeId = btn.dataset.id;
        const li = btn.closest("li");
        const storeName =
            btn.dataset.name ||
            li?.querySelector(".store-name")?.textContent ||
            "this store";

        const result = await Swal.fire({
            title: "Delete store?",
            text: `Are you sure you want to delete ${storeName}? This action cannot be undone.`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Delete",
            cancelButtonText: "Cancel",
            reverseButtons: true,
        });

        if (!result.isConfirmed) return;

        try {
            const { data } = await axios.delete(`/delete-store/${storeId}`);
            if (!data?.success) {
                throw new Error(data?.message || "Failed to delete store.");
            }

            li?.remove();

            await Swal.fire({
                icon: "success",
                title: "Deleted",
                text: "Store has been deleted.",
                confirmButtonText: "OK",
            });
        } catch (err) {
            await Swal.fire({
                icon: "error",
                title: "Delete failed",
                text:
                    err?.response?.data?.message ||
                    err.message ||
                    "An error occurred while deleting the store. Please try again later.",
            });
        }
    });

    $(document).on("click", ".edit-store-btn", function () {
        const storeId = $(this).data("id");
        $("#settingsModal").modal("hide");
        axios
            .get(`/get-store/${storeId}`)
            .then((response) => {
                const store = response.data.store;

                $("#editStoreId").val(store.store_id);
                $("#editStoreName").val(store.storename);
                $("#editClientID").val(store.client_id);
                $("#editClientSecret").val(store.client_secret);
                $("#editRefreshToken").val(store.refresh_token);
                $("#editMerchantID").val(store.MerchantID);
                $("#editMarketplace").val(store.Marketplace);
                $("#editMarketplaceID").val(store.MarketplaceID);

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
            e.preventDefault();

            const storeId = document.getElementById("editStoreId").value.trim();
            if (!storeId) {
                Swal.fire({
                    icon: "error",
                    title: "Missing Store ID",
                    text: "Store ID is missing. Please try again.",
                    confirmButtonText: "OK",
                });
                return;
            }

            const updatedStoreData = {
                store_id: storeId,
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

            axios
                .post("/update-store/" + storeId, updatedStoreData, {
                    headers: {
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute("content"),
                    },
                })
                .then((response) => {
                    if (response.data.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Updated",
                            text: "Store updated successfully.",
                            confirmButtonText: "OK",
                        }).then(() => {
                            fetchStoreList();
                            $("#editStoreModal").modal("hide");
                            $("#settingsModal").modal("show");
                            $("#store-tab").tab("show");
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Update Failed",
                            text:
                                response.data.message ||
                                "Failed to update store.",
                            confirmButtonText: "OK",
                        });
                    }
                })
                .catch((error) => {
                    console.error("Error updating store:", error);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "An error occurred while updating the store.",
                        confirmButtonText: "OK",
                    });
                });
        });

    document
        .querySelector("#editStoreModal .btn-close")
        ?.addEventListener("click", function () {
            $("#settingsModal").modal("show");
            $("#store-tab").tab("show");
        });

    function fetchMarketplaces() {
        console.log("Modal is shown, fetching marketplaces...");
        axios
            .get("/fetch-marketplaces")
            .then((response) => {
                const marketplaceSelect =
                    document.getElementById("selectMarketplace");

                if (marketplaceSelect) {
                    marketplaceSelect.innerHTML = "";

                    if (response.data.length === 0) {
                        const placeholder = document.createElement("option");
                        placeholder.textContent = "No marketplaces available";
                        placeholder.disabled = true;
                        marketplaceSelect.appendChild(placeholder);
                        return;
                    }

                    response.data.forEach((marketplace) => {
                        const option = document.createElement("option");
                        option.value =
                            marketplace.value ??
                            marketplace.id ??
                            marketplace.name;
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

        const editMarketplace = document.getElementById("editMarketplace");
        const editMarketplaceID = document.getElementById("editMarketplaceID");

        if (!editMarketplace || !editMarketplaceID) return;

        const currentNames = editMarketplace.value
            .split(",")
            .map((name) => name.trim());
        const currentIDs = editMarketplaceID.value
            .split(",")
            .map((id) => id.trim());

        selectedOptions.forEach((option) => {
            if (!currentNames.includes(option.textContent)) {
                currentNames.push(option.textContent);
                currentIDs.push(option.value);
            }
        });

        editMarketplace.value = currentNames.filter(Boolean).join(", ");
        editMarketplaceID.value = currentIDs.filter(Boolean).join(", ");
    }

    document
        .getElementById("editStoreModal")
        ?.addEventListener("show.bs.modal", fetchMarketplaces);
    document
        .getElementById("selectMarketplace")
        ?.addEventListener("change", updateMarketplaceFields);
});
