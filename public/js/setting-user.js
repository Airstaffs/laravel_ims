document.addEventListener("DOMContentLoaded", function () {
    const settingsModalEl = document.getElementById("settingsModal");
    const userListModal = document.getElementById("userListModal");
    const editUserModal = document.getElementById("editUserModal");
    const deleteUserModal = document.getElementById("deleteUserModal");
    const addUserForm = document.getElementById("addUserForm");

    // ---- helpers (keep your existing csrf() helper too)
    function extractLaravelErrors(errPayload) {
        // errPayload expected { message?: string, errors?: {field:[msgs]} }
        if (!errPayload) return "Something went wrong.";
        if (errPayload.errors && typeof errPayload.errors === "object") {
            const list = Object.values(errPayload.errors)
                .flat()
                .filter(Boolean);
            if (list.length) return list.join("\n");
        }
        return errPayload.message || "Something went wrong.";
    }

    // User Management
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
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger btn-delete"
                                            data-user-id="${user.id}"
                                            data-username="${user.username}"
                                            aria-label="Delete ${user.username}">
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

    userListModal?.addEventListener("hidden.bs.modal", function () {
        const editOpen = document
            .getElementById("editUserModal")
            ?.classList.contains("show");
        if (!editOpen) {
            // e.g., focus something or clean up if needed
        }
    });

    // Add User Form (REPLACE your current addUserForm submit listener with this)
    addUserForm?.addEventListener("submit", async function (e) {
        e.preventDefault();

        const submitBtn = this.querySelector('button[type="submit"]');
        const formData = new FormData(this);

        // Optional: basic client-side check for matching passwords
        const pass = formData.get("password");
        const pass2 = formData.get("password_confirmation");
        if (pass !== pass2) {
            Swal.fire({
                icon: "error",
                title: "Passwords don’t match",
                text: "Please confirm your password again.",
            });
            return;
        }

        // Disable UI while request is running
        submitBtn && (submitBtn.disabled = true);

        try {
            const res = await fetch(window.routes.addUser, {
                method: "POST",
                body: formData,
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN":
                        document.querySelector("meta[name='csrf-token']")
                            ?.content || "",
                },
            });

            // Try to parse JSON even on non-2xx
            const ct = res.headers.get("content-type") || "";
            const payload = ct.includes("application/json")
                ? await res.json().catch(() => ({}))
                : {};

            if (!res.ok || (payload && payload.success === false)) {
                const msg = extractLaravelErrors(payload);
                throw new Error(msg);
            }

            // Success UX
            this.reset();
            await Swal.fire({
                icon: "success",
                title: "User added",
                text: "The new user has been created successfully.",
                confirmButtonText: "OK",
            });

            // Close settings (if open), open list, refresh data
            document.activeElement?.blur();
            bootstrap.Modal.getInstance(settingsModalEl)?.hide();
            bootstrap.Modal.getOrCreateInstance(userListModal).show();
            fetchUsers();
        } catch (err) {
            console.error("Add User Error:", err);
            await Swal.fire({
                icon: "error",
                title: "Add user failed",
                text: err?.message || "Error adding user",
                confirmButtonText: "OK",
            });
        } finally {
            submitBtn && (submitBtn.disabled = false);
        }
    });

    // Expose Edit Function
    window.editUser = function (userId, username, role) {
        document.activeElement?.blur();
        bootstrap.Modal.getInstance(userListModal)?.hide();
        bootstrap.Modal.getInstance(settingsModalEl)?.hide();

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
        ?.addEventListener("submit", async function (e) {
            e.preventDefault();

            const userId = document.getElementById("edit_user_id").value;
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');

            // If password left blank, don’t send it (keep current)
            if (!formData.get("password")) {
                formData.delete("password");
            }

            submitBtn && (submitBtn.disabled = true);

            try {
                const res = await fetch(
                    `${window.routes.updateUser}/${userId}`,
                    {
                        method: "POST", // or PUT if your route uses PUT/PATCH
                        body: formData,
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                            "X-CSRF-TOKEN":
                                document.querySelector(
                                    "meta[name='csrf-token']"
                                )?.content || "",
                        },
                    }
                );

                const ct = res.headers.get("content-type") || "";
                const payload = ct.includes("application/json")
                    ? await res.json().catch(() => ({}))
                    : {};

                if (!res.ok || (payload && payload.success === false)) {
                    const msg = extractLaravelErrors(payload);
                    throw new Error(msg);
                }

                await Swal.fire({
                    icon: "success",
                    title: "User updated",
                    text: "The user details were updated successfully.",
                    confirmButtonText: "OK",
                });

                bootstrap.Modal.getInstance(editUserModal)?.hide();
                bootstrap.Modal.getOrCreateInstance(userListModal).show();
                fetchUsers();
            } catch (err) {
                console.error("Update User Error:", err);
                await Swal.fire({
                    icon: "error",
                    title: "Update failed",
                    text: err?.message || "Error updating user",
                    confirmButtonText: "OK",
                });
            } finally {
                submitBtn && (submitBtn.disabled = false);
            }
        });

    editUserModal?.addEventListener("hidden.bs.modal", () => {
        if (editUserModal.contains(document.activeElement)) {
            document.activeElement.blur();
        }

        const userListModalInstance =
            bootstrap.Modal.getOrCreateInstance(userListModal);
        userListModalInstance.show();
    });

    // ---- helpers
    const csrf = () =>
        document.querySelector("meta[name='csrf-token']")?.content;

    async function deleteUserById(id, triggerEl, username) {
        if (!id) return;

        // Ask for confirmation first
        const confirm = await Swal.fire({
            title: "Are you sure?",
            text: username
                ? `Do you really want to delete ${username}?`
                : "Do you really want to delete this user?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "Yes, delete it!",
            cancelButtonText: "Cancel",
        });

        if (!confirm.isConfirmed) {
            return; // user clicked Cancel
        }

        if (triggerEl) triggerEl.disabled = true;

        try {
            const res = await fetch(`${window.routes.deleteUser}/${id}`, {
                method: "DELETE",
                headers: {
                    "X-CSRF-TOKEN": csrf(),
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            // Be robust to 204 or non-JSON responses
            let data = {};
            const ct = res.headers.get("content-type") || "";
            if (ct.includes("application/json")) {
                data = await res.json().catch(() => ({}));
            }

            const ok =
                data && typeof data.success !== "undefined"
                    ? !!data.success
                    : res.ok;

            if (!ok) throw new Error(data.message || "Error deleting user");

            // Success modal
            await Swal.fire({
                icon: "success",
                title: "Deleted",
                text: username
                    ? `${username} was deleted successfully.`
                    : "User deleted successfully.",
                confirmButtonText: "OK",
            });

            fetchUsers?.();
        } catch (error) {
            console.error("Delete Error:", error);

            // Error modal
            await Swal.fire({
                icon: "error",
                title: "Delete failed",
                text: error.message || "Error deleting user",
                confirmButtonText: "OK",
            });
        } finally {
            if (triggerEl) triggerEl.disabled = false;
        }
    }

    // ---- delegated click handler (works for future-added rows too)
    document.addEventListener("click", (e) => {
        const btn = e.target.closest(".btn-delete");
        if (!btn) return;

        const { userId, username } = btn.dataset;
        deleteUserById(userId, btn, username);
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

    // Add the missing function for showing delete confirmation
    window.showDeleteConfirmation = function (userId, username) {
        deleteUserId = userId;
        bootstrap.Modal.getOrCreateInstance(deleteUserModal).show();
    };
});
