<div class="tab-pane fade" id="user" role="tabpanel" aria-labelledby="user-tab">
    <h3 class="text-center">Add User</h3>

    <form action="{{ route('add-user') }}" method="POST" class="addUserForm" id="addUserForm">
        @csrf
        <!-- Username -->
        <fieldset>
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control w-100" id="username" name="username" placeholder="Enter username"
                required>
        </fieldset>

        <!-- Password -->
        <fieldset>
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter password"
                    required>
                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#password">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </fieldset>

        <!-- Confirm Password -->
        <fieldset>
            <label for="password_confirmation" class="form-label">Confirm Password</label>
            <div class="input-group">
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
                    placeholder="Confirm password" required>
                <button type="button" class="btn btn-outline-secondary toggle-password"
                    data-target="#password_confirmation">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </fieldset>

        <!-- User Role -->
        <fieldset>
            <label for="userRole" class="form-label">User Role</label>
            <select class="form-select form-control w-100" id="userRole" name="role">
                <option value="SuperAdmin">Super-Admin</option>
                <option value="SubAdmin">Sub-Admin</option>
                <option value="User">User</option>
            </select>
        </fieldset>

        <div class="d-flex justify-content-between align-items-center gap-2">
            <button type="submit" class="btn btn-primary w-100 text-white justify-content-center fw-bold">Add
                User</button>
            <button type="button" class="btn btn-info w-100 text-white justify-content-center fw-bold"
                data-bs-toggle="modal" data-bs-target="#userListModal">
                <i class="bi bi-people me-2"></i>Show User List
            </button>
        </div>
    </form>
</div>