<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    @csrf
                    <input type="hidden" id="edit_user_id" name="user_id">

                    <fieldset>
                        <label>Username</label>
                        <input type="text" class="form-control" id="edit_username" name="username" disabled>
                    </fieldset>

                    <fieldset>
                        <label>New Password (leave blank to keep current)</label>
                        <div class="has-toggle">
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <i role="button" class="bi bi-eye" id="togglePassword"></i>
                        </div>
                    </fieldset>

                    <fieldset>
                        <label>User Role</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="SuperAdmin">Super-Admin</option>
                            <option value="SubAdmin">Sub-Admin</option>
                            <option value="User">User</option>
                        </select>
                    </fieldset>

                    <button type="submit" class="btn btn-primary justify-content-center fw-bold text-white">Update
                        User</button>
                </form>
            </div>
        </div>
    </div>
</div>