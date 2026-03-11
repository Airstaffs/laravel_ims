<template>
    <Dialog
        :visible="settingsVisible"
        @update:visible="handleClose"
        modal
        header="Settings"
        :style="{ width: '90%', maxWidth: '1200px' }"
        class="settings-modal"
    >
        <TabView class="settings-tabs" v-model:activeIndex="activeTabIndex">
            <TabPanel>
                <template #header>
                    <i class="bi bi-palette"></i>
                    <span> Title & Design</span>
                </template>

                <div class="tab-content">
                    <h3 class="text-center mb-4">Title & Design Settings</h3>

                    <Message
                        v-if="successMessage"
                        severity="success"
                        :closable="true"
                        @close="successMessage = ''"
                    >
                        {{ successMessage }}
                    </Message>

                    <form
                        @submit.prevent="saveDesignSettings"
                        class="design-form"
                    >
                        <!-- Site Title -->
                        <div class="form-field">
                            <label for="siteTitle" class="form-label"
                                >Site Title</label
                            >
                            <InputText
                                id="siteTitle"
                                v-model="designForm.siteTitle"
                                placeholder="Enter site title"
                                class="w-full"
                                required
                            />
                        </div>

                        <Divider />

                        <!-- Theme Color -->
                        <div class="form-field">
                            <label for="themeColor" class="form-label"
                                >Theme Color (for buttons and UI
                                elements)</label
                            >
                            <div class="color-picker-wrapper">
                                <ColorPicker
                                    v-model="designForm.themeColor"
                                    format="hex"
                                    defaultColor="#007bff"
                                />
                                <InputText
                                    v-model="designForm.themeColor"
                                    placeholder="#007bff"
                                    class="color-input"
                                />
                            </div>
                            <small class="text-muted"
                                >This color will be applied to buttons and
                                primary UI elements</small
                            >
                        </div>

                        <Divider />

                        <!-- Logo Upload -->
                        <div class="form-field">
                            <label for="logoUpload" class="form-label"
                                >Upload Logo</label
                            >
                            <FileUpload
                                mode="basic"
                                accept="image/*"
                                :maxFileSize="2000000"
                                @select="onLogoSelect"
                                chooseLabel="Choose Logo"
                                class="w-full"
                            />
                            <div
                                v-if="currentLogo || logoPreview"
                                class="logo-preview mt-3"
                            >
                                <img
                                    :src="logoPreview || currentLogo"
                                    alt="Logo"
                                    class="preview-image"
                                />
                            </div>
                        </div>

                        <Button
                            type="submit"
                            label="Save Changes"
                            icon="pi pi-check"
                            :loading="isSaving"
                            class="mt-4 w-full save-button"
                        />
                    </form>
                </div>
            </TabPanel>

            <TabPanel>
                <template #header>
                    <i class="bi bi-person-plus"></i>
                    <span> Add User</span>
                </template>

                <div class="scrollable-content">
                    <div class="tab-content">
                        <div class="user-list-header">
                            <h3>User Management</h3>
                            <Button
                                label="Add User"
                                icon="pi pi-user-plus"
                                @click="showAddUserDialog = true"
                                class="add-user-btn"
                            />
                        </div>

                        <!-- Search/Filter -->
                        <div class="search-bar">
                            <IconField iconPosition="left">
                                <InputIcon class="pi pi-search"></InputIcon>
                                <InputText
                                    v-model="userSearchQuery"
                                    placeholder="Search by username..."
                                    class="w-full search-input"
                                />
                            </IconField>
                        </div>

                        <div class="table-wrapper">
                            <DataTable
                                :value="filteredUsers"
                                :loading="loadingUsers"
                                stripedRows
                                class="user-table"
                                :paginator="filteredUsers.length > 10"
                                :rows="10"
                            >
                                <Column
                                    field="username"
                                    header="Username"
                                    sortable
                                >
                                    <template #body="slotProps">
                                        <span
                                            class="username-link"
                                            @click="
                                                goToUserPrivileges(
                                                    slotProps.data,
                                                )
                                            "
                                        >
                                            {{ slotProps.data.username }}
                                        </span>
                                    </template>
                                </Column>
                                <Column field="role" header="Role" sortable>
                                    <template #body="slotProps">
                                        <Tag
                                            :value="slotProps.data.role"
                                            :severity="
                                                getRoleSeverity(
                                                    slotProps.data.role,
                                                )
                                            "
                                        />
                                    </template>
                                </Column>
                                <Column
                                    field="created_at"
                                    header="Created"
                                    sortable
                                >
                                    <template #body="slotProps">
                                        {{
                                            formatDate(
                                                slotProps.data.created_at,
                                            )
                                        }}
                                    </template>
                                </Column>
                                <Column
                                    header="Actions"
                                    headerStyle="width: 8rem; text-align: center"
                                    bodyStyle="text-align: center"
                                >
                                    <template #body="slotProps">
                                        <Button
                                            icon="pi pi-pencil"
                                            severity="primary"
                                            text
                                            rounded
                                            size="small"
                                            @click="editUser(slotProps.data)"
                                            aria-label="Edit"
                                            class="mr-1"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            severity="danger"
                                            text
                                            rounded
                                            size="small"
                                            @click="
                                                confirmDeleteUser(
                                                    slotProps.data,
                                                )
                                            "
                                            aria-label="Delete"
                                        />
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="empty-state">
                                        <i
                                            class="pi pi-users"
                                            style="
                                                font-size: 3rem;
                                                color: #94a3b8;
                                            "
                                        ></i>
                                        <p>
                                            {{
                                                userSearchQuery
                                                    ? "No users found matching your search"
                                                    : "No users found"
                                            }}
                                        </p>
                                    </div>
                                </template>
                            </DataTable>
                        </div>
                    </div>
                </div>

                <!-- Add User Dialog -->
                <Dialog
                    v-model:visible="showAddUserDialog"
                    modal
                    header="Add New User"
                    :style="{ width: '95%', maxWidth: '500px' }"
                    class="add-user-dialog"
                >
                    <form @submit.prevent="addUser" class="user-form">
                        <!-- Username -->
                        <div class="form-field">
                            <label for="username" class="form-label"
                                >Username</label
                            >
                            <InputText
                                id="username"
                                v-model="userForm.username"
                                placeholder="Enter username"
                                class="w-full"
                                required
                            />
                        </div>

                        <!-- Password -->
                        <div class="form-field">
                            <label for="password" class="form-label"
                                >Password</label
                            >
                            <Password
                                id="password"
                                v-model="userForm.password"
                                placeholder="Enter password"
                                toggleMask
                                :feedback="false"
                                inputClass="w-full"
                                required
                            />
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-field">
                            <label
                                for="password_confirmation"
                                class="form-label"
                                >Confirm Password</label
                            >
                            <Password
                                id="password_confirmation"
                                v-model="userForm.password_confirmation"
                                placeholder="Confirm password"
                                toggleMask
                                :feedback="false"
                                inputClass="w-full"
                                required
                            />
                        </div>

                        <!-- User Role -->
                        <div class="form-field">
                            <label for="userRole" class="form-label"
                                >User Role</label
                            >
                            <Dropdown
                                id="userRole"
                                v-model="userForm.role"
                                :options="roleOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select a role"
                                class="w-full"
                            />
                        </div>

                        <!-- Account Type - NEW -->
                        <div class="form-field">
                            <label for="accountType" class="form-label"
                                >Account Type</label
                            >
                            <Dropdown
                                id="accountType"
                                v-model="userForm.accounttype"
                                :options="accountTypeOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select account type"
                                class="w-full"
                                required
                            />
                        </div>

                        <div class="button-group">
                            <Button
                                type="submit"
                                label="Add User"
                                icon="pi pi-check"
                                :loading="isAddingUser"
                                class="w-full"
                            />
                            <Button
                                type="button"
                                label="Cancel"
                                icon="pi pi-times"
                                severity="secondary"
                                @click="showAddUserDialog = false"
                                class="w-full"
                            />
                        </div>
                    </form>
                </Dialog>

                <!-- Edit User Dialog -->
                <Dialog
                    v-model:visible="showEditUser"
                    modal
                    header="Edit User"
                    :style="{ width: '95%', maxWidth: '500px' }"
                    class="edit-user-dialog"
                >
                    <form @submit.prevent="updateUser" class="edit-user-form">
                        <div class="form-field">
                            <label for="edit_username" class="form-label"
                                >Username</label
                            >
                            <InputText
                                id="edit_username"
                                v-model="editForm.username"
                                placeholder="Username"
                                class="w-full"
                                disabled
                            />
                            <small class="text-muted"
                                >Username cannot be changed</small
                            >
                        </div>

                        <div class="form-field">
                            <label for="edit_role" class="form-label"
                                >User Role</label
                            >
                            <Dropdown
                                id="edit_role"
                                v-model="editForm.role"
                                :options="roleOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select a role"
                                class="w-full"
                            />
                        </div>

                        <!-- Account Type - NEW -->
                        <div class="form-field">
                            <label for="edit_accounttype" class="form-label"
                                >Account Type</label
                            >
                            <Dropdown
                                id="edit_accounttype"
                                v-model="editForm.accounttype"
                                :options="accountTypeOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select account type"
                                class="w-full"
                                required
                            />
                        </div>

                        <div class="form-field">
                            <label for="edit_password" class="form-label"
                                >New Password (leave blank to keep
                                current)</label
                            >
                            <Password
                                id="edit_password"
                                v-model="editForm.password"
                                placeholder="Enter new password"
                                toggleMask
                                :feedback="false"
                                inputClass="w-full"
                            />
                        </div>

                        <div class="button-group">
                            <Button
                                type="submit"
                                label="Update User"
                                icon="pi pi-check"
                                :loading="isUpdatingUser"
                                class="w-full"
                            />
                            <Button
                                type="button"
                                label="Cancel"
                                icon="pi pi-times"
                                severity="secondary"
                                @click="showEditUser = false"
                                class="w-full"
                            />
                        </div>
                    </form>
                </Dialog>
            </TabPanel>

            <TabPanel>
                <template #header>
                    <i class="bi bi-shop"></i>
                    <span> Store List</span>
                </template>

                <div class="scrollable-content">
                    <div class="tab-content">
                        <div class="store-list-header">
                            <h3>Store List</h3>
                            <Button
                                label="Add Store"
                                icon="pi pi-plus"
                                @click="showAddStoreDialog = true"
                                class="add-store-btn"
                            />
                        </div>

                        <div class="store-list-wrapper">
                            <div v-if="loadingStores" class="loading-state">
                                <i
                                    class="pi pi-spin pi-spinner"
                                    style="font-size: 2rem"
                                ></i>
                                <p>Loading stores...</p>
                            </div>

                            <div
                                v-else-if="stores.length === 0"
                                class="empty-state"
                            >
                                <i
                                    class="bi bi-shop"
                                    style="font-size: 3rem; color: #94a3b8"
                                ></i>
                                <p>No stores found</p>
                                <Button
                                    label="Add Your First Store"
                                    icon="pi pi-plus"
                                    @click="showAddStoreDialog = true"
                                    class="mt-3"
                                />
                            </div>

                            <div v-else class="store-list">
                                <div
                                    v-for="store in stores"
                                    :key="store.store_id"
                                    class="store-item"
                                >
                                    <div class="store-info">
                                        <span class="store-name">{{
                                            store.storename
                                        }}</span>
                                        <small
                                            v-if="store.abbreviation"
                                            class="store-abbr"
                                        >
                                            ({{ store.abbreviation }})
                                        </small>
                                    </div>
                                    <div class="store-actions">
                                        <Button
                                            icon="pi pi-pencil"
                                            severity="secondary"
                                            text
                                            rounded
                                            @click="editStore(store)"
                                            aria-label="Edit"
                                            class="mr-1"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            severity="danger"
                                            text
                                            rounded
                                            @click="confirmDeleteStore(store)"
                                            aria-label="Delete"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add Store Dialog -->
                <Dialog
                    v-model:visible="showAddStoreDialog"
                    modal
                    header="Add New Store"
                    :style="{ width: '95%', maxWidth: '500px' }"
                    class="add-store-dialog"
                >
                    <form @submit.prevent="addStore" class="store-form">
                        <div class="form-field">
                            <label for="storeName" class="form-label"
                                >Store Name</label
                            >
                            <InputText
                                id="storeName"
                                v-model="storeForm.storename"
                                placeholder="Enter store name"
                                class="w-full"
                                required
                            />
                        </div>

                        <div class="form-field">
                            <label for="storeAbbr" class="form-label"
                                >Store Abbreviation</label
                            >
                            <InputText
                                id="storeAbbr"
                                v-model="storeForm.Strabbreviation"
                                placeholder="Enter abbreviation (optional)"
                                class="w-full"
                            />
                        </div>

                        <div class="button-group">
                            <Button
                                type="submit"
                                label="Add Store"
                                icon="pi pi-check"
                                :loading="isAddingStore"
                                class="w-full"
                            />
                            <Button
                                type="button"
                                label="Cancel"
                                icon="pi pi-times"
                                severity="secondary"
                                @click="showAddStoreDialog = false"
                                class="w-full"
                            />
                        </div>
                    </form>
                </Dialog>

                <!-- Edit Store Dialog -->
                <Dialog
                    v-model:visible="showEditStoreDialog"
                    modal
                    header="Edit Store"
                    :style="{ width: '95%', maxWidth: '500px' }"
                    class="edit-store-dialog"
                >
                    <form @submit.prevent="updateStore" class="edit-store-form">
                        <div class="form-field">
                            <label for="editStoreName" class="form-label"
                                >Store Name</label
                            >
                            <InputText
                                id="editStoreName"
                                v-model="editStoreForm.storename"
                                placeholder="Store name"
                                class="w-full"
                                required
                            />
                        </div>

                        <div class="form-field">
                            <label for="editClientID" class="form-label"
                                >Client ID</label
                            >
                            <InputText
                                id="editClientID"
                                v-model="editStoreForm.client_id"
                                placeholder="Client ID"
                                class="w-full"
                            />
                        </div>

                        <div class="form-field">
                            <label for="editClientSecret" class="form-label"
                                >Client Secret</label
                            >
                            <InputText
                                id="editClientSecret"
                                v-model="editStoreForm.client_secret"
                                placeholder="Client Secret"
                                class="w-full"
                            />
                        </div>

                        <div class="form-field">
                            <label for="editRefreshToken" class="form-label"
                                >Refresh Token</label
                            >
                            <InputText
                                id="editRefreshToken"
                                v-model="editStoreForm.refresh_token"
                                placeholder="Refresh Token"
                                class="w-full"
                            />
                        </div>

                        <div class="form-field">
                            <label for="editMerchantID" class="form-label"
                                >Merchant ID</label
                            >
                            <InputText
                                id="editMerchantID"
                                v-model="editStoreForm.MerchantID"
                                placeholder="Merchant ID"
                                class="w-full"
                            />
                        </div>

                        <div class="form-field">
                            <label for="editMarketplace" class="form-label"
                                >Marketplace</label
                            >
                            <MultiSelect
                                id="editMarketplace"
                                v-model="editStoreForm.selectedMarketplaces"
                                :options="marketplaceOptions"
                                optionLabel="name"
                                optionValue="value"
                                placeholder="Select marketplaces"
                                class="w-full"
                                display="chip"
                            />
                            <small class="text-muted"
                                >Selected marketplaces will be saved as
                                comma-separated values</small
                            >
                        </div>

                        <div class="form-field">
                            <label
                                for="editMarketplaceDisplay"
                                class="form-label"
                                >Marketplace (Display)</label
                            >
                            <InputText
                                id="editMarketplaceDisplay"
                                v-model="editStoreForm.Marketplace"
                                placeholder="Auto-generated from selection"
                                class="w-full"
                                readonly
                                disabled
                            />
                        </div>

                        <div class="form-field">
                            <label for="editMarketplaceID" class="form-label"
                                >Marketplace ID (Display)</label
                            >
                            <InputText
                                id="editMarketplaceID"
                                v-model="editStoreForm.MarketplaceID"
                                placeholder="Auto-generated from selection"
                                class="w-full"
                                readonly
                                disabled
                            />
                        </div>

                        <div class="button-group">
                            <Button
                                type="submit"
                                label="Update Store"
                                icon="pi pi-check"
                                :loading="isUpdatingStore"
                                class="w-full"
                            />
                            <Button
                                type="button"
                                label="Cancel"
                                icon="pi pi-times"
                                severity="secondary"
                                @click="showEditStoreDialog = false"
                                class="w-full"
                            />
                        </div>
                    </form>
                </Dialog>
            </TabPanel>

            <TabPanel>
                <template #header>
                    <i class="bi bi-shield-lock"></i>
                    <span> Privileges</span>
                </template>

                <div class="scrollable-content">
                    <div class="tab-content privileges-content">
                        <h3 class="text-center mb-4">User Privileges</h3>

                        <form
                            @submit.prevent="savePrivileges"
                            class="privileges-form"
                        >
                            <!-- User Selection -->
                            <div class="form-field">
                                <label for="privilegeUser" class="form-label"
                                    >Select User</label
                                >
                                <Dropdown
                                    id="privilegeUser"
                                    v-model="privilegeForm.selectedUserId"
                                    :options="users"
                                    optionLabel="username"
                                    optionValue="id"
                                    placeholder="Select a user"
                                    class="w-full"
                                    @change="onUserChange"
                                    :loading="loadingUsers"
                                />
                            </div>

                            <!-- Main Module Selection -->
                            <div
                                class="form-field"
                                v-if="privilegeForm.selectedUserId"
                            >
                                <label class="form-label">Main Module</label>
                                <div class="privilege-grid">
                                    <div
                                        v-for="module in mainModules"
                                        :key="module.value"
                                        class="privilege-item"
                                    >
                                        <RadioButton
                                            v-model="privilegeForm.mainModule"
                                            :inputId="'main_' + module.value"
                                            :value="module.value"
                                            name="mainModule"
                                        />
                                        <label
                                            :for="'main_' + module.value"
                                            class="privilege-label"
                                        >
                                            {{ module.label }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Sub-Modules Selection -->
                            <div
                                class="form-field"
                                v-if="privilegeForm.selectedUserId"
                            >
                                <label class="form-label">Sub-Modules</label>
                                <div class="privilege-grid">
                                    <div
                                        v-for="module in subModules"
                                        :key="module.value"
                                        class="privilege-item"
                                    >
                                        <Checkbox
                                            v-model="privilegeForm.subModules"
                                            :inputId="'sub_' + module.value"
                                            :value="module.value"
                                            name="subModules"
                                        />
                                        <label
                                            :for="'sub_' + module.value"
                                            class="privilege-label"
                                        >
                                            {{ module.label }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Store Access Selection -->
                            <div
                                class="form-field"
                                v-if="
                                    privilegeForm.selectedUserId &&
                                    userStores.length > 0
                                "
                            >
                                <label class="form-label">Store Access</label>
                                <div class="privilege-grid">
                                    <div
                                        v-for="store in userStores"
                                        :key="store.store_column"
                                        class="privilege-item"
                                    >
                                        <Checkbox
                                            v-model="privilegeForm.stores"
                                            :inputId="
                                                'store_' + store.store_column
                                            "
                                            :value="store.store_column"
                                            name="stores"
                                        />
                                        <label
                                            :for="'store_' + store.store_column"
                                            class="privilege-label"
                                        >
                                            {{ store.store_name }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <Button
                                v-if="privilegeForm.selectedUserId"
                                type="submit"
                                label="Save Privileges"
                                icon="pi pi-check"
                                :loading="isSavingPrivileges"
                                class="mt-4 w-full save-privileges-btn"
                            />
                        </form>
                    </div>
                </div>
            </TabPanel>

            <TabPanel>
                <template #header>
                    <i class="bi bi-clock"></i>
                    <span> Time Record</span>
                </template>

                <div class="scrollable-content">
                    <div class="tab-content time-record-content">
                        <h3 class="text-center mb-3">User Time Record</h3>

                        <form
                            @submit.prevent="filterTimeRecords"
                            class="time-record-form"
                        >
                            <!-- Inline Filters -->
                            <div class="inline-filters">
                                <div class="filter-item">
                                    <Dropdown
                                        id="timeRecordUser"
                                        v-model="timeRecordForm.selectedUserId"
                                        :options="users"
                                        optionLabel="username"
                                        optionValue="id"
                                        placeholder="Select user"
                                        class="w-full"
                                        :loading="loadingUsers"
                                        @change="filterTimeRecords"
                                    />
                                </div>
                                <div class="filter-item">
                                    <Calendar
                                        id="startDate"
                                        v-model="timeRecordForm.startDate"
                                        dateFormat="yy-mm-dd"
                                        placeholder="Start date"
                                        showIcon
                                        iconDisplay="input"
                                        class="w-full"
                                        @date-select="filterTimeRecords"
                                    />
                                </div>
                                <div class="filter-item">
                                    <Calendar
                                        id="endDate"
                                        v-model="timeRecordForm.endDate"
                                        dateFormat="yy-mm-dd"
                                        placeholder="End date"
                                        showIcon
                                        iconDisplay="input"
                                        class="w-full"
                                        @date-select="filterTimeRecords"
                                    />
                                </div>
                            </div>
                        </form>

                        <!-- Time Records Table -->
                        <div
                            class="time-records-table"
                            v-if="timeRecords.length > 0"
                        >
                            <DataTable
                                :value="timeRecords"
                                stripedRows
                                class="records-table"
                                :paginator="timeRecords.length > 10"
                                :rows="10"
                            >
                                <Column
                                    field="details"
                                    header="Details"
                                    style="min-width: 200px"
                                >
                                    <template #body="slotProps">
                                        <div class="detail-cell">
                                            {{ slotProps.data.details }}
                                        </div>
                                    </template>
                                </Column>
                                <Column
                                    field="total_hours"
                                    header="Total Hours"
                                    style="width: 150px"
                                >
                                    <template #body="slotProps">
                                        <Tag
                                            :value="
                                                slotProps.data.total_hours ===
                                                'In Progress'
                                                    ? slotProps.data.total_hours
                                                    : slotProps.data
                                                          .total_hours + ' hrs'
                                            "
                                            :severity="
                                                slotProps.data.total_hours ===
                                                'In Progress'
                                                    ? 'warning'
                                                    : 'info'
                                            "
                                        />
                                    </template>
                                </Column>
                                <Column
                                    field="notes"
                                    header="Notes"
                                    style="min-width: 200px"
                                >
                                    <template #body="slotProps">
                                        <div class="notes-cell">
                                            {{
                                                slotProps.data.notes ||
                                                "No notes"
                                            }}
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>

                        <!-- Loading State -->
                        <div v-else-if="loadingTimeRecords" class="empty-state">
                            <i
                                class="pi pi-spin pi-spinner"
                                style="font-size: 2rem; color: #007bff"
                            ></i>
                            <p>Loading time records...</p>
                        </div>

                        <!-- Empty State -->
                        <div v-else-if="hasFiltered" class="empty-state">
                            <i
                                class="bi bi-clock-history"
                                style="font-size: 3rem; color: #94a3b8"
                            ></i>
                            <p>
                                No time records found for the selected criteria
                            </p>
                        </div>

                        <!-- Initial State -->
                        <div v-else class="empty-state">
                            <i
                                class="bi bi-filter"
                                style="font-size: 3rem; color: #94a3b8"
                            ></i>
                            <p>
                                Select a user and date range to view time
                                records
                            </p>
                        </div>
                    </div>
                </div>
            </TabPanel>

            <TabPanel>
                <template #header>
                    <i class="bi bi-person-lines-fill"></i>
                    <span> User Logs</span>
                </template>

                <div class="scrollable-content">
                    <div class="tab-content user-logs-content">
                        <h3 class="text-center mb-3">User Logs</h3>

                        <form
                            @submit.prevent="filterUserLogs"
                            class="user-logs-form"
                        >
                            <!-- Inline Filters -->
                            <div class="inline-filters">
                                <div class="filter-item">
                                    <Dropdown
                                        id="userLogsUser"
                                        v-model="userLogsForm.selectedUserId"
                                        :options="users"
                                        optionLabel="username"
                                        optionValue="id"
                                        placeholder="Select user (optional)"
                                        class="w-full"
                                        :loading="loadingUsers"
                                        showClear
                                        @change="filterUserLogs"
                                    />
                                </div>
                                <div class="filter-item">
                                    <Calendar
                                        id="startDateLogs"
                                        v-model="userLogsForm.startDate"
                                        dateFormat="yy-mm-dd"
                                        placeholder="Start date (optional)"
                                        showIcon
                                        iconDisplay="input"
                                        class="w-full"
                                        showClear
                                        @date-select="filterUserLogs"
                                        @clear-click="filterUserLogs"
                                    />
                                </div>
                                <div class="filter-item">
                                    <Calendar
                                        id="endDateLogs"
                                        v-model="userLogsForm.endDate"
                                        dateFormat="yy-mm-dd"
                                        placeholder="End date (optional)"
                                        showIcon
                                        iconDisplay="input"
                                        class="w-full"
                                        showClear
                                        @date-select="filterUserLogs"
                                        @clear-click="filterUserLogs"
                                    />
                                </div>
                                <div class="filter-item">
                                    <Button
                                        type="button"
                                        label="Clear All"
                                        icon="pi pi-filter-slash"
                                        severity="secondary"
                                        @click="clearUserLogsFilters"
                                        :disabled="!hasLogsFilters"
                                    />
                                </div>
                            </div>
                        </form>

                        <!-- User Logs Table -->
                        <div class="user-logs-table" v-if="userLogs.length > 0">
                            <DataTable
                                :value="userLogs"
                                stripedRows
                                class="logs-table"
                                :paginator="userLogs.length > 10"
                                :rows="10"
                                :loading="loadingUserLogs"
                            >
                                <Column
                                    field="username"
                                    header="Username"
                                    style="width: 150px"
                                    sortable
                                >
                                    <template #body="slotProps">
                                        <div class="username-cell">
                                            <i
                                                class="bi bi-person-circle me-2"
                                            ></i>
                                            <strong>{{
                                                slotProps.data.username
                                            }}</strong>
                                        </div>
                                    </template>
                                </Column>
                                <Column
                                    field="actions"
                                    header="User Actions"
                                    style="min-width: 300px; max-width: 500px"
                                >
                                    <template #body="slotProps">
                                        <div class="action-cell">
                                            {{ slotProps.data.actions }}
                                        </div>
                                    </template>
                                </Column>
                                <Column
                                    field="datetimelogs"
                                    header="Date & Time"
                                    style="width: 200px"
                                    sortable
                                >
                                    <template #body="slotProps">
                                        <div class="date-cell">
                                            <i
                                                class="bi bi-calendar-event me-1"
                                            ></i>
                                            {{
                                                formatLogDate(
                                                    slotProps.data.datetimelogs,
                                                )
                                            }}
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>

                        <!-- Loading State -->
                        <div v-else-if="loadingUserLogs" class="empty-state">
                            <i
                                class="pi pi-spin pi-spinner"
                                style="font-size: 2rem; color: #007bff"
                            ></i>
                            <p>Loading user logs...</p>
                        </div>

                        <!-- Empty State -->
                        <div v-else-if="hasFilteredLogs" class="empty-state">
                            <i
                                class="bi bi-journal-x"
                                style="font-size: 3rem; color: #94a3b8"
                            ></i>
                            <p>No logs found for the selected criteria</p>
                            <Button
                                label="Clear Filters"
                                icon="pi pi-filter-slash"
                                @click="clearUserLogsFilters"
                                class="mt-3"
                            />
                        </div>

                        <!-- Initial State -->
                        <div v-else class="empty-state">
                            <i
                                class="bi bi-filter"
                                style="font-size: 3rem; color: #94a3b8"
                            ></i>
                            <p>Select filters to view user logs</p>
                            <small class="text-muted"
                                >All filters are optional - leave empty to see
                                all logs</small
                            >
                        </div>
                    </div>
                </div>
            </TabPanel>

            <TabPanel>
                <template #header>
                    <i class="bi bi-printer"></i>
                    <span> Printers</span>
                </template>

                <div class="scrollable-content">
                    <div class="tab-content printers-content">
                        <!-- Sub-tabs for Printer Management -->
                        <div class="printer-subtabs">
                            <Button
                                :class="[
                                    'printer-subtab-btn',
                                    { active: activePrinterTab === 'all' },
                                ]"
                                @click="activePrinterTab = 'all'"
                            >
                                <i class="bi bi-list-ul me-1"></i>
                                <span>All Printers</span>
                            </Button>
                            <Button
                                :class="[
                                    'printer-subtab-btn',
                                    { active: activePrinterTab === 'small' },
                                ]"
                                @click="switchPrinterTab('small')"
                            >
                                <i class="bi bi-tag me-1"></i>
                                <span>Small Label</span>
                            </Button>
                            <Button
                                :class="[
                                    'printer-subtab-btn',
                                    {
                                        active:
                                            activePrinterTab === 'instruction',
                                    },
                                ]"
                                @click="switchPrinterTab('instruction')"
                            >
                                <i class="bi bi-card-text me-1"></i>
                                <span>Instruction Card</span>
                            </Button>
                            <Button
                                :class="[
                                    'printer-subtab-btn',
                                    { active: activePrinterTab === 'married' },
                                ]"
                                @click="switchPrinterTab('married')"
                            >
                                <i class="bi bi-arrow-through-heart me-1"></i>
                                <span>Married Printers</span>
                            </Button>
                            <Button
                                :class="[
                                    'printer-subtab-btn',
                                    {
                                        active:
                                            activePrinterTab === 'identifier',
                                    },
                                ]"
                                @click="switchPrinterTab('identifier')"
                            >
                                <i class="bi bi-file-text me-1"></i>
                                <span>Identifiers</span>
                            </Button>
                        </div>

                        <!-- All Printers Tab -->
                        <div
                            v-show="activePrinterTab === 'all'"
                            class="printer-tab-content"
                        >
                            <div class="printer-actions-header">
                                <Button
                                    label="Add Printer"
                                    icon="pi pi-plus"
                                    @click="showAddPrinterDialog = true"
                                    class="add-printer-btn"
                                />
                            </div>

                            <!-- Printers Table -->
                            <div class="printers-table-wrapper">
                                <DataTable
                                    v-if="allPrinters.length > 0"
                                    :value="allPrinters"
                                    :loading="loadingPrinters"
                                    stripedRows
                                    :paginator="allPrinters.length > 10"
                                    :rows="10"
                                    class="printers-table"
                                >
                                    <Column
                                        field="printername"
                                        header="Printer Name"
                                        sortable
                                    ></Column>
                                    <Column
                                        field="printer_type"
                                        header="Type"
                                        sortable
                                    >
                                        <template #body="slotProps">
                                            <Tag
                                                :value="
                                                    getPrinterTypeLabel(
                                                        slotProps.data
                                                            .printer_type,
                                                    )
                                                "
                                                :severity="
                                                    slotProps.data
                                                        .printer_type ===
                                                    'small_label'
                                                        ? 'info'
                                                        : 'success'
                                                "
                                            />
                                        </template>
                                    </Column>
                                    <Column header="IP Address:Port">
                                        <template #body="slotProps">
                                            {{ slotProps.data.printerip }}:{{
                                                slotProps.data.port || "9100"
                                            }}
                                        </template>
                                    </Column>
                                    <Column field="status" header="Status">
                                        <template #body="slotProps">
                                            <Tag
                                                :value="
                                                    slotProps.data.status ||
                                                    'unknown'
                                                "
                                                :severity="
                                                    getStatusSeverity(
                                                        slotProps.data.status,
                                                    )
                                                "
                                            />
                                        </template>
                                    </Column>
                                    <Column
                                        header="Actions"
                                        headerStyle="width: 10rem"
                                    >
                                        <template #body="slotProps">
                                            <Button
                                                icon="pi pi-pencil"
                                                severity="primary"
                                                text
                                                rounded
                                                @click="
                                                    editPrinter(slotProps.data)
                                                "
                                                class="mr-1"
                                            />
                                            <Button
                                                icon="pi pi-trash"
                                                severity="danger"
                                                text
                                                rounded
                                                @click="
                                                    confirmDeletePrinter(
                                                        slotProps.data,
                                                    )
                                                "
                                            />
                                        </template>
                                    </Column>

                                    <template #empty>
                                        <div class="empty-state">
                                            <i
                                                class="bi bi-printer"
                                                style="
                                                    font-size: 3rem;
                                                    color: #94a3b8;
                                                "
                                            ></i>
                                            <p>No printers found</p>
                                        </div>
                                    </template>
                                </DataTable>

                                <div
                                    v-else-if="!loadingPrinters"
                                    class="empty-state"
                                >
                                    <i
                                        class="bi bi-printer"
                                        style="font-size: 3rem; color: #94a3b8"
                                    ></i>
                                    <p>No printers found</p>
                                </div>

                                <div v-else class="loading-state">
                                    <i
                                        class="pi pi-spin pi-spinner"
                                        style="font-size: 2rem"
                                    ></i>
                                    <p>Loading printers...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Small Label Tab -->
                        <div
                            v-show="activePrinterTab === 'small'"
                            class="printer-tab-content"
                        >
                            <DataTable
                                v-if="smallLabelPrinters.length > 0"
                                :value="smallLabelPrinters"
                                :loading="loadingPrinters"
                                stripedRows
                                :paginator="smallLabelPrinters.length > 10"
                                :rows="10"
                                class="printers-table"
                            >
                                <Column
                                    field="printername"
                                    header="Printer Name"
                                    sortable
                                ></Column>
                                <Column header="IP Address:Port">
                                    <template #body="slotProps">
                                        {{ slotProps.data.printerip }}:{{
                                            slotProps.data.port || "9100"
                                        }}
                                    </template>
                                </Column>
                                <Column field="status" header="Status">
                                    <template #body="slotProps">
                                        <Tag
                                            :value="
                                                slotProps.data.status ||
                                                'unknown'
                                            "
                                            :severity="
                                                getStatusSeverity(
                                                    slotProps.data.status,
                                                )
                                            "
                                        />
                                    </template>
                                </Column>
                                <Column header="Actions">
                                    <template #body="slotProps">
                                        <Button
                                            icon="pi pi-pencil"
                                            severity="primary"
                                            text
                                            rounded
                                            @click="editPrinter(slotProps.data)"
                                            class="mr-1"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            severity="danger"
                                            text
                                            rounded
                                            @click="
                                                confirmDeletePrinter(
                                                    slotProps.data,
                                                )
                                            "
                                        />
                                    </template>
                                </Column>
                            </DataTable>
                            <div v-else class="empty-state">
                                <i
                                    class="bi bi-tag"
                                    style="font-size: 3rem; color: #94a3b8"
                                ></i>
                                <p>No small label printers found</p>
                            </div>
                        </div>

                        <!-- Instruction Card Tab -->
                        <div
                            v-show="activePrinterTab === 'instruction'"
                            class="printer-tab-content"
                        >
                            <DataTable
                                v-if="instructionCardPrinters.length > 0"
                                :value="instructionCardPrinters"
                                :loading="loadingPrinters"
                                stripedRows
                                :paginator="instructionCardPrinters.length > 10"
                                :rows="10"
                                class="printers-table"
                            >
                                <Column
                                    field="printername"
                                    header="Printer Name"
                                    sortable
                                ></Column>
                                <Column header="IP Address:Port">
                                    <template #body="slotProps">
                                        {{ slotProps.data.printerip }}:{{
                                            slotProps.data.port || "9100"
                                        }}
                                    </template>
                                </Column>
                                <Column field="status" header="Status">
                                    <template #body="slotProps">
                                        <Tag
                                            :value="
                                                slotProps.data.status ||
                                                'unknown'
                                            "
                                            :severity="
                                                getStatusSeverity(
                                                    slotProps.data.status,
                                                )
                                            "
                                        />
                                    </template>
                                </Column>
                                <Column header="Actions">
                                    <template #body="slotProps">
                                        <Button
                                            icon="pi pi-pencil"
                                            severity="primary"
                                            text
                                            rounded
                                            @click="editPrinter(slotProps.data)"
                                            class="mr-1"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            severity="danger"
                                            text
                                            rounded
                                            @click="
                                                confirmDeletePrinter(
                                                    slotProps.data,
                                                )
                                            "
                                        />
                                    </template>
                                </Column>
                            </DataTable>
                            <div v-else class="empty-state">
                                <i
                                    class="bi bi-card-text"
                                    style="font-size: 3rem; color: #94a3b8"
                                ></i>
                                <p>No instruction card printers found</p>
                            </div>
                        </div>

                        <!-- Married Printers Tab -->
                        <div
                            v-show="activePrinterTab === 'married'"
                            class="printer-tab-content"
                        >
                            <div class="printer-actions-header">
                                <Button
                                    label="Marry Printers"
                                    icon="pi pi-heart"
                                    @click="showMarryPrinterDialog = true"
                                    class="marry-printer-btn"
                                    severity="success"
                                />
                            </div>

                            <Message severity="info" class="mb-3">
                                <strong
                                    >Married printers allow you to pair a small
                                    label printer with an instruction card
                                    printer for synchronized printing.</strong
                                >
                            </Message>

                            <!-- Married Printers List -->
                            <div
                                v-if="marriedPrinters.length > 0"
                                class="married-printers-list"
                            >
                                <div
                                    v-for="marriage in marriedPrinters"
                                    :key="marriage.id"
                                    class="married-printer-card"
                                >
                                    <div class="marriage-header">
                                        <div class="marriage-icon">
                                            <i class="bi bi-heart-fill"></i>
                                        </div>
                                        <div class="marriage-title">
                                            <h6>
                                                {{ marriage.marriage_name }}
                                            </h6>
                                            <small
                                                v-if="marriage.description"
                                                class="text-muted"
                                                >{{
                                                    marriage.description
                                                }}</small
                                            >
                                        </div>
                                        <Button
                                            icon="pi pi-trash"
                                            severity="danger"
                                            text
                                            rounded
                                            @click="
                                                confirmDeleteMarriage(marriage)
                                            "
                                        />
                                    </div>
                                    <div class="marriage-printers">
                                        <div class="printer-pair">
                                            <div class="printer-box">
                                                <i class="bi bi-tag-fill"></i>
                                                <span>{{
                                                    marriage.small_label_printer_name
                                                }}</span>
                                            </div>
                                            <i
                                                class="bi bi-arrow-left-right"
                                            ></i>
                                            <div class="printer-box">
                                                <i class="bi bi-card-text"></i>
                                                <span>{{
                                                    marriage.instruction_card_printer_name
                                                }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else-if="!loadingMarriages"
                                class="empty-state"
                            >
                                <i
                                    class="bi bi-heart"
                                    style="font-size: 3rem; color: #94a3b8"
                                ></i>
                                <p>No married printers found</p>
                            </div>
                        </div>
                        <div
                            v-show="activePrinterTab === 'identifier'"
                            class="printer_tab_content"
                        >
                            <DataTable
                                :value="uniqueIdentifiersData"
                                :loading="loadingUniqueIdentifiers"
                                class="printers-table"
                            >
                                <Column header="ID Name" field="name" />
                                <Column header="Start" field="start" />
                                <Column header="End" field="end" />
                                <!-- <Column header="Quantity" field="QTY" />
                                <Column header="Sticker" field="sticker" /> -->
                                <Column header="Actions">
                                    <template #body="slotProps">
                                        <Button
                                            icon="pi pi-pencil"
                                            severity="primary"
                                            text
                                            rounded
                                            class="mr-1"
                                            @click="
                                                showSetStartDialogFunc(
                                                    slotProps,
                                                )
                                            "
                                        />
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </div>
                </div>

                <!-- Add Printer Dialog -->
                <Dialog
                    v-model:visible="showAddPrinterDialog"
                    modal
                    header="Add New Printer"
                    :style="{ width: '600px' }"
                    class="add-printer-dialog"
                >
                    <form @submit.prevent="addPrinter" class="printer-form">
                        <div class="printer-form-header">
                            <div class="printer-icon-wrapper">
                                <i class="bi bi-printer-fill"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0">Configure New Printer</h6>
                                <small class="text-muted"
                                    >Set up printer for your operations</small
                                >
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-label">
                                    <i class="bi bi-printer me-1"></i>
                                    Printer Name
                                    <span class="text-danger">*</span>
                                </label>
                                <InputText
                                    v-model="printerForm.printer_name"
                                    placeholder="Enter printer name"
                                    required
                                    class="w-full"
                                />
                            </div>

                            <div class="form-field">
                                <label class="form-label">
                                    <i class="bi bi-tag me-1"></i>
                                    Type
                                    <span class="text-danger">*</span>
                                </label>
                                <Dropdown
                                    v-model="printerForm.printer_type"
                                    :options="printerTypeOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Select Type"
                                    class="w-full"
                                    required
                                />
                            </div>

                            <div class="form-field">
                                <label class="form-label">
                                    <i class="bi bi-globe me-1"></i>
                                    IP Address
                                    <span class="text-danger">*</span>
                                </label>
                                <InputText
                                    v-model="printerForm.ip_address"
                                    placeholder="192.168.1.100"
                                    required
                                    class="w-full"
                                />
                            </div>

                            <div class="form-field">
                                <label class="form-label">
                                    <i class="bi bi-plug me-1"></i>
                                    Port
                                </label>
                                <InputText
                                    v-model.number="printerForm.port"
                                    type="number"
                                    :min="1"
                                    :max="65535"
                                    placeholder="9100"
                                    class="w-full"
                                />
                            </div>

                            <div class="form-field full-width">
                                <label class="form-label">
                                    <i class="bi bi-card-text me-1"></i>
                                    Description
                                    <span class="text-muted">(Optional)</span>
                                </label>
                                <Textarea
                                    v-model="printerForm.description"
                                    rows="2"
                                    placeholder="Optional notes about this printer..."
                                    class="w-full"
                                />
                            </div>
                        </div>

                        <div class="form-footer">
                            <Button
                                label="Cancel"
                                icon="pi pi-times"
                                severity="secondary"
                                @click="showAddPrinterDialog = false"
                            />
                            <Button
                                type="submit"
                                label="Add Printer"
                                icon="pi pi-check"
                                :loading="isAddingPrinter"
                            />
                        </div>
                    </form>
                </Dialog>

                <!-- Edit Printer Dialog - FIXED FIELD NAMES -->
                <Dialog
                    v-model:visible="showEditPrinterDialog"
                    modal
                    header="Edit Printer"
                    :style="{ width: '600px' }"
                    class="edit-printer-dialog"
                >
                    <form
                        @submit.prevent="updatePrinter"
                        class="edit-printer-form"
                    >
                        <div class="printer-form-header">
                            <div class="printer-icon-wrapper">
                                <i class="bi bi-gear-fill"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0">Update Printer Settings</h6>
                                <small class="text-muted"
                                    >Modify Configuration Settings</small
                                >
                            </div>
                        </div>

                        <div class="form-field">
                            <label class="form-label">
                                <i class="bi bi-printer me-1"></i>
                                Printer Name
                                <span class="text-danger">*</span>
                            </label>
                            <InputText
                                v-model="editPrinterForm.printer_name"
                                required
                                class="w-full"
                            />
                        </div>

                        <div class="form-field">
                            <label class="form-label">
                                <i class="bi bi-tag me-1"></i>
                                Type
                                <span class="text-danger">*</span>
                            </label>
                            <Dropdown
                                v-model="editPrinterForm.printer_type"
                                :options="printerTypeOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full"
                                required
                            />
                        </div>

                        <div class="form-row">
                            <div class="form-field">
                                <label class="form-label">
                                    <i class="bi bi-globe me-1"></i>
                                    IP Address
                                    <span class="text-danger">*</span>
                                </label>
                                <InputText
                                    v-model="editPrinterForm.ip_address"
                                    required
                                    class="w-full"
                                />
                            </div>

                            <div class="form-field">
                                <label class="form-label">
                                    <i class="bi bi-plug me-1"></i>
                                    Port
                                </label>
                                <InputText
                                    v-model.number="editPrinterForm.port"
                                    type="number"
                                    :min="1"
                                    :max="65535"
                                    placeholder="9100"
                                    class="w-full"
                                />
                            </div>
                        </div>

                        <div class="form-field">
                            <label class="form-label">
                                <i class="bi bi-circle-fill me-1"></i>
                                Status
                            </label>
                            <Dropdown
                                v-model="editPrinterForm.status"
                                :options="statusOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full"
                            />
                        </div>

                        <div class="form-field">
                            <label class="form-label">
                                <i class="bi bi-card-text me-1"></i>
                                Description
                                <span class="text-muted">(Optional)</span>
                            </label>
                            <Textarea
                                v-model="editPrinterForm.description"
                                rows="3"
                                class="w-full"
                            />
                        </div>

                        <div class="form-footer">
                            <Button
                                label="Cancel"
                                icon="pi pi-times"
                                severity="secondary"
                                @click="showEditPrinterDialog = false"
                            />
                            <Button
                                type="submit"
                                label="Save Changes"
                                icon="pi pi-check"
                                :loading="isUpdatingPrinter"
                            />
                        </div>
                    </form>
                </Dialog>

                <!-- Marry Printers Dialog -->
                <Dialog
                    v-model:visible="showMarryPrinterDialog"
                    modal
                    header="Marry Printers"
                    :style="{ width: '650px' }"
                    class="marry-printer-dialog"
                >
                    <form @submit.prevent="marryPrinters" class="printer-form">
                        <div class="marriage-info-card">
                            <div class="marriage-icon-wrapper">
                                <i class="bi bi-heart-fill"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Create Printer Marriage</h6>
                                <p class="mb-0 text-muted">
                                    Join two printers to work together
                                    seamlessly for synchronized printing
                                    operations.
                                </p>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-label">
                                    <i class="bi bi-tag-fill me-1"></i>
                                    Small Label Printer
                                    <span class="text-danger">*</span>
                                </label>
                                <Dropdown
                                    v-model="
                                        marriageForm.small_label_printer_id
                                    "
                                    :options="smallLabelPrinters"
                                    optionLabel="printername"
                                    optionValue="printerid"
                                    placeholder="Choose your label printer..."
                                    class="w-full"
                                    required
                                />
                            </div>

                            <div class="form-field">
                                <label class="form-label">
                                    <i class="bi bi-card-text me-1"></i>
                                    Instruction Card Printer
                                    <span class="text-danger">*</span>
                                </label>
                                <Dropdown
                                    v-model="
                                        marriageForm.instruction_card_printer_id
                                    "
                                    :options="instructionCardPrinters"
                                    optionLabel="printername"
                                    optionValue="printerid"
                                    placeholder="Choose your card printer..."
                                    class="w-full"
                                    required
                                />
                            </div>

                            <div class="form-field full-width">
                                <label class="form-label">
                                    <i class="bi bi-tag me-1"></i>
                                    Marriage Name
                                    <span class="text-danger">*</span>
                                </label>
                                <InputText
                                    v-model="marriageForm.marriage_name"
                                    placeholder="e.g., Production Line Alpha, Warehouse Station 1"
                                    required
                                    class="w-full"
                                />
                            </div>

                            <div class="form-field full-width">
                                <label class="form-label">
                                    <i class="bi bi-card-text me-1"></i>
                                    Description
                                    <span class="text-muted">(Optional)</span>
                                </label>
                                <Textarea
                                    v-model="marriageForm.description"
                                    rows="3"
                                    placeholder="Describe the purpose of this printer marriage, location, or any special notes..."
                                    class="w-full"
                                />
                            </div>
                        </div>

                        <div class="form-footer">
                            <Button
                                label="Cancel"
                                icon="pi pi-times"
                                severity="secondary"
                                @click="showMarryPrinterDialog = false"
                            />
                            <Button
                                type="submit"
                                label="Create Marriage"
                                icon="pi pi-heart"
                                severity="success"
                                :loading="isMarryingPrinters"
                            />
                        </div>
                    </form>
                </Dialog>

                <!---- SET START NUMBER ---->
                <Dialog
                    v-model:visible="showSetStartDialog"
                    modal
                    header="Set Start Count"
                    :style="{ width: '600px' }"
                >
                    <form action="" @submit.prevent="updateStartValueCount">
                        <div class="form-field">
                            <label for="">Input Count</label>
                            <InputText
                                type="number"
                                size="small"
                                v-model="identifierEditForm.startCountValue"
                                class="w-100"
                            />
                        </div>

                        <div class="form-footer">
                            <Button
                                type="submit"
                                :label="
                                    isUpdatingStartCount ? 'Saving' : 'Save'
                                "
                                :loading="isUpdatingStartCount"
                                icon="pi pi-save"
                                severity="success"
                            />
                        </div>
                    </form>
                </Dialog>
            </TabPanel>
        </TabView>
    </Dialog>
</template>

<script>
import Dialog from "primevue/dialog";
import TabView from "primevue/tabview";
import TabPanel from "primevue/tabpanel";
import InputText from "primevue/inputtext";
import ColorPicker from "primevue/colorpicker";
import Button from "primevue/button";
import Message from "primevue/message";
import Divider from "primevue/divider";
import Password from "primevue/password";
import Dropdown from "primevue/dropdown";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Tag from "primevue/tag";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import FileUpload from "primevue/fileupload";
import RadioButton from "primevue/radiobutton";
import Checkbox from "primevue/checkbox";
import MultiSelect from "primevue/multiselect";
import Calendar from "primevue/calendar";
import Textarea from "primevue/textarea";

import Swal from "sweetalert2";

export default {
    name: "SettingsModal",
    components: {
        Dialog,
        TabView,
        TabPanel,
        InputText,
        ColorPicker,
        Button,
        Message,
        Divider,
        Password,
        Dropdown,
        DataTable,
        Column,
        Tag,
        IconField,
        InputIcon,
        FileUpload,
        RadioButton,
        Checkbox,
        MultiSelect,
        Calendar,
        Textarea,
    },
    props: {
        settingsVisible: {
            type: Boolean,
            default: false,
        },
    },
    emits: ["update:settingsVisible"],
    data() {
        return {
            activeTabIndex: 0,
            successMessage: "",
            isSaving: false,
            designForm: {
                siteTitle: "",
                themeColor: "#007bff",
            },
            logoFile: null,
            logoPreview: null,
            currentLogo: null,
            currentThemeColor: "#007bff",
            // Add User form
            userForm: {
                username: "",
                password: "",
                password_confirmation: "",
                role: "User",
                accounttype: "",
            },
            roleOptions: [
                { label: "Super-Admin", value: "SuperAdmin" },
                { label: "Sub-Admin", value: "SubAdmin" },
                { label: "User", value: "User" },
            ],
            isAddingUser: false,
            showUserList: false,
            showAddUserDialog: false,
            users: [],
            loadingUsers: false,
            userSearchQuery: "",
            showEditUser: false,
            editForm: {
                id: null,
                username: "",
                role: "User",
                password: "",
                accounttype: "",
            },
            isUpdatingUser: false,
            // Store management
            stores: [],
            loadingStores: false,
            showAddStoreDialog: false,
            showEditStoreDialog: false,
            isAddingStore: false,
            isUpdatingStore: false,
            storeForm: {
                storename: "",
                Strabbreviation: "",
            },
            editStoreForm: {
                store_id: null,
                storename: "",
                client_id: "",
                client_secret: "",
                refresh_token: "",
                MerchantID: "",
                Marketplace: "",
                MarketplaceID: "",
                selectedMarketplaces: [],
            },
            marketplaceOptions: [],
            // Privileges management
            privilegeForm: {
                selectedUserId: null,
                mainModule: "",
                subModules: [],
                stores: [],
            },
            mainModules: [
                { label: "Human Resource", value: "humanresource" },
                { label: "Order", value: "order" },
                { label: "Unreceived", value: "unreceived" },
                { label: "Received", value: "receiving" },
                { label: "Labeling", value: "labeling" },
                { label: "Testing", value: "testing" },
                { label: "Cleaning", value: "cleaning" },
                { label: "Packing", value: "packing" },
                { label: "Stockroom", value: "stockroom" },
                { label: "Validation", value: "validation" },
                { label: "Production Area", value: "productionarea" },
                { label: "RTS", value: "rts" },
                { label: "Return Scanner", value: "returnscanner" },
                { label: "FBM Order", value: "fbmorder" },
                { label: "Shipment", value: "shipment" },
                { label: "Sold Items", value: "soldlist" },
                { label: "Not Found", value: "notfound" },
                { label: "Houseage", value: "houseage" },
                { label: "Auxiliary Label", value: "auxiliary" },
                { label: "Inventory Statistics", value: "inventorystatistics" },
                { label: "Reconciliation", value: "reconciliation" },
                {label: "Supplies & Components", value: "suppliescomponents"},
                 {label: "Switcheru List", value: "switcheru"},

            ],
            subModules: [
                { label: "Human Resource", value: "humanresource" },
                { label: "Order", value: "order" },
                { label: "Unreceived", value: "unreceived" },
                { label: "Received", value: "receiving" },
                { label: "Labeling", value: "labeling" },
                { label: "Testing", value: "testing" },
                { label: "Cleaning", value: "cleaning" },
                { label: "Packing", value: "packing" },
                { label: "Stockroom", value: "stockroom" },
                { label: "Validation", value: "validation" },
                { label: "FNSKU", value: "fnsku" },
                { label: "ASIN List", value: "asinlist" },
                { label: "Production Area", value: "productionarea" },
                { label: "RTS", value: "rts" },
                { label: "Return Scanner", value: "returnscanner" },
                { label: "FBM Order", value: "fbmorder" },
                { label: "Shipment", value: "shipment" },
                { label: "Sold Items", value: "soldlist" },
                { label: "Not Found", value: "notfound" },
                { label: "ASIN Option", value: "asinoption" },
                { label: "Houseage", value: "houseage" },
                { label: "Printer", value: "printer" },
                { label: "Announcement", value: "announcement" },
                { label: "Auxiliary Label", value: "auxiliary" },
                { label: "Inventory Statistics", value: "inventorystatistics" },
                { label: "Reconciliation", value: "reconciliation" },
                {label: "Supplies & Components", value: "suppliescomponents"},
                {label: "Switcheru List", value: "switcheru"},
            ],
            userStores: [],
            isSavingPrivileges: false,
            // Time Record management
            timeRecordForm: {
                selectedUserId: null,
                startDate: null,
                endDate: null,
            },
            timeRecords: [],
            loadingTimeRecords: false,
            hasFiltered: false,

            // User Logs management
            userLogsForm: {
                selectedUserId: null,
                startDate: null,
                endDate: null,
            },
            userLogs: [],
            loadingUserLogs: false,
            hasFilteredLogs: false,

            // Printer management
            activePrinterTab: "all",
            allPrinters: [],
            showAddPrinterDialog: false,
            showEditPrinterDialog: false,
            showMarryPrinterDialog: false,
            isAddingPrinter: false,
            isUpdatingPrinter: false,

            printerForm: {
                printer_name: "",
                printer_type: "",
                ip_address: "",
                port: 9100,
                description: "",
            },

            editPrinterForm: {
                printerid: null,
                printer_name: "",
                printer_type: "",
                ip_address: "",
                port: 9100,
                status: "active",
                description: "",
            },

            printerTypeOptions: [
                { label: "🏷️ Small Label", value: "small_label" },
                { label: "📋 Instruction Card", value: "instruction_card" },
            ],

            statusOptions: [
                { label: "Active", value: "active" },
                { label: "Inactive", value: "inactive" },
                { label: "Maintenance", value: "maintenance" },
            ],

            // Married printers
            showMarryPrinterForm: false,
            isMarryingPrinters: false,
            loadingMarriages: false,
            marriedPrinters: [],

            marriageForm: {
                small_label_printer_id: null,
                instruction_card_printer_id: null,
                marriage_name: "",
                description: "",
            },
            uniqueIdentifiersData: [],
            identifierEditForm: {
                startCountValue: null,
                name: "",
            },
            isUpdatingStartCount: false,
            showSetStartDialog: false,
            loadingUniqueIdentifiers: false,

            accountTypeOptions: [
                { label: "Philippines (PH)", value: "PH" },
                { label: "United States (US)", value: "US" },
            ],
        };
    },
    watch: {
        activeTabIndex(newIndex, oldIndex) {
            switch (newIndex) {
                case 0:
                    this.loadCurrentSettings();
                    break;
                case 1:
                    this.fetchUsers();
                    break;
                case 2:
                    this.fetchStores();
                    break;
                case 3:
                    this.fetchUsers();
                    break;
                case 4:
                    this.fetchUsers();
                    if (oldIndex !== newIndex) {
                        this.timeRecordForm.selectedUserId = null;
                        this.timeRecordForm.startDate = null;
                        this.timeRecordForm.endDate = null;
                        this.timeRecords = [];
                        this.hasFiltered = false;
                    }
                    break;
                case 5:
                    this.fetchUsers();
                    if (oldIndex !== newIndex) {
                        this.userLogsForm.selectedUserId = null;
                        this.userLogsForm.startDate = null;
                        this.userLogsForm.endDate = null;
                        this.userLogs = [];
                        this.hasFilteredLogs = false;
                    }
                    break;
                case 6:
                    this.fetchPrinters();
                    if (this.activePrinterTab === "married") {
                        this.fetchMarriedPrinters();
                    }
                    break;
            }
        },

        activePrinterTab(newTab) {
            if (newTab === "married" && this.marriedPrinters.length === 0) {
                this.fetchMarriedPrinters();
            } else if (newTab === "identifier") {
                this.fetchIdentifiers();
            }
        },
    },
    mounted() {
        this.loadCurrentSettings();
        this.applyThemeColor();
        this.fetchUsers();
        this.fetchStores();
        this.fetchPrinters();
    },
    beforeUnmount() {},
    computed: {
        filteredUsers() {
            if (!this.userSearchQuery) {
                return this.users;
            }

            const query = this.userSearchQuery.toLowerCase();
            return this.users.filter((user) =>
                user.username.toLowerCase().includes(query),
            );
        },

        hasLogsFilters() {
            return (
                this.userLogsForm.selectedUserId !== null ||
                this.userLogsForm.startDate !== null ||
                this.userLogsForm.endDate !== null
            );
        },

        smallLabelPrinters() {
            return this.allPrinters.filter(
                (p) => p.printer_type === "small_label",
            );
        },

        instructionCardPrinters() {
            return this.allPrinters.filter(
                (p) => p.printer_type === "instruction_card",
            );
        },
    },
    methods: {
        async loadCurrentSettings() {
            try {
                // Fetch current settings from Laravel API
                const response = await fetch("/get-system-design-data", {
                    headers: {
                        Accept: "application/json",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                });

                if (response.ok) {
                    const data = await response.json();
                    this.designForm.siteTitle = data.site_title || "";
                    this.designForm.themeColor = data.theme_color || "#007bff";
                    this.currentThemeColor = data.theme_color || "#007bff";

                    if (data.logo) {
                        this.currentLogo = `/storage/${data.logo}`;
                    }

                    // Apply the theme color immediately
                    this.applyThemeColor();
                }
            } catch (error) {
                console.error("Failed to load settings:", error);
            }
        },

        applyThemeColor() {
            // Get theme color from session meta tag or use current form value
            const themeMeta = document.querySelector(
                'meta[name="session-theme_color"]',
            );
            const themeColor =
                this.currentThemeColor || themeMeta?.content || "#007bff";

            // Apply to CSS variables for navbar and other elements
            document.documentElement.style.setProperty(
                "--navbar-bg",
                themeColor,
            );
            document.documentElement.style.setProperty(
                "--theme-color",
                themeColor,
            );
            document.documentElement.style.setProperty(
                "--theme-color-dark",
                this.darkenColor(themeColor, 20),
            );
            document.documentElement.style.setProperty(
                "--theme-color-light",
                this.lightenColor(themeColor, 90),
            );
        },

        darkenColor(hex, percent) {
            const num = parseInt(hex.replace("#", ""), 16);
            const amt = Math.round(2.55 * percent);
            const R = Math.max((num >> 16) - amt, 0);
            const G = Math.max(((num >> 8) & 0x00ff) - amt, 0);
            const B = Math.max((num & 0x0000ff) - amt, 0);
            return (
                "#" +
                (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1)
            );
        },

        lightenColor(hex, percent) {
            const num = parseInt(hex.replace("#", ""), 16);
            const amt = Math.round(2.55 * percent);
            const R = Math.min((num >> 16) + amt, 255);
            const G = Math.min(((num >> 8) & 0x00ff) + amt, 255);
            const B = Math.min((num & 0x0000ff) + amt, 255);
            return (
                "#" +
                (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1)
            );
        },

        onLogoSelect(event) {
            this.logoFile = event.files[0];

            // Create preview
            const reader = new FileReader();
            reader.onload = (e) => {
                this.logoPreview = e.target.result;
            };
            reader.readAsDataURL(this.logoFile);
        },

        async saveDesignSettings() {
            this.isSaving = true;

            try {
                const formData = new FormData();
                formData.append("site_title", this.designForm.siteTitle);
                formData.append("theme_color", this.designForm.themeColor);

                if (this.logoFile) {
                    formData.append("logo", this.logoFile);
                }

                // Use your Laravel route
                const response = await fetch("/update-system-design", {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                });

                if (response.ok) {
                    this.successMessage = "System design updated successfully!";
                    this.currentThemeColor = this.designForm.themeColor;

                    // Clear logo file after successful upload
                    this.logoFile = null;
                    this.logoPreview = null;

                    // Reload settings
                    await this.loadCurrentSettings();

                    // Show success message
                    await Swal.fire({
                        icon: "success",
                        title: "Success",
                        text: "System design updated successfully!",
                        timer: 2000,
                        showConfirmButton: false,
                    });

                    // Reload page to reflect changes in navbar
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    const errorData = await response.json();
                    throw new Error(
                        errorData.message || "Failed to save settings",
                    );
                }
            } catch (error) {
                console.error("Save error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        error.message ||
                        "Failed to save settings. Please try again.",
                });
            } finally {
                this.isSaving = false;
            }
        },

        async addUser() {
            // Validate passwords match
            if (
                this.userForm.password !== this.userForm.password_confirmation
            ) {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Passwords do not match!",
                });
                return;
            }

            // Validate account type
            if (!this.userForm.accounttype) {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Please select an account type!",
                });
                return;
            }

            this.isAddingUser = true;

            try {
                const formData = new FormData();
                formData.append("username", this.userForm.username);
                formData.append("password", this.userForm.password);
                formData.append(
                    "password_confirmation",
                    this.userForm.password_confirmation,
                );
                formData.append("role", this.userForm.role);
                formData.append("accounttype", this.userForm.accounttype); // Add this

                const response = await fetch("/add-user", {
                    method: "POST",
                    body: formData,
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                });

                const data = await response.json();

                if (!response.ok || data.success === false) {
                    throw new Error(data.message || "Failed to add user");
                }

                await Swal.fire({
                    icon: "success",
                    title: "User added",
                    text: "The new user has been created successfully.",
                    confirmButtonText: "OK",
                });

                // Reset form
                this.userForm = {
                    username: "",
                    password: "",
                    password_confirmation: "",
                    role: "User",
                    accounttype: "", // Add this
                };

                // Show user list and refresh
                this.showAddUserDialog = false;
                this.showUserList = true;
                await this.fetchUsers();
            } catch (error) {
                console.error("Add user error:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Add user failed",
                    text: error.message || "Error adding user",
                    confirmButtonText: "OK",
                });
            } finally {
                this.isAddingUser = false;
            }
        },

        async fetchUsers() {
            this.loadingUsers = true;
            try {
                const response = await fetch("/users", {
                    method: "GET",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                });

                const data = await response.json();

                if (response.ok && data.status === "success") {
                    this.users = data.data || [];
                } else {
                    throw new Error(data.message || "Failed to fetch users");
                }
            } catch (error) {
                console.error("Fetch users error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        error.message ||
                        "Failed to load users. Please try again.",
                });
            } finally {
                this.loadingUsers = false;
            }
        },

        getRoleSeverity(role) {
            const severityMap = {
                SuperAdmin: "danger",
                SubAdmin: "warning",
                User: "info",
            };
            return severityMap[role] || "info";
        },

        formatDate(dateString) {
            if (!dateString) return "";
            const date = new Date(dateString);
            return date.toLocaleDateString("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric",
            });
        },

        editUser(user) {
            this.editForm = {
                id: user.id,
                username: user.username,
                role: user.role,
                password: "",
                accounttype: user.accounttype,
            };
            this.showEditUser = true;
        },

        async updateUser() {
            this.isUpdatingUser = true;

            try {
                const formData = new FormData();
                formData.append("role", this.editForm.role);

                // Only include password if it's not empty
                if (this.editForm.password) {
                    formData.append("password", this.editForm.password);
                }

                const response = await fetch(
                    `/update-user/${this.editForm.id}`,
                    {
                        method: "POST",
                        body: formData,
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                        },
                    },
                );

                const data = await response.json();

                if (!response.ok || data.success === false) {
                    throw new Error(data.message || "Failed to update user");
                }

                await Swal.fire({
                    icon: "success",
                    title: "User updated",
                    text: "The user details were updated successfully.",
                    confirmButtonText: "OK",
                });

                this.showEditUser = false;
                this.showUserList = true;
                await this.fetchUsers();
            } catch (error) {
                console.error("Update user error:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Update failed",
                    text: error.message || "Error updating user",
                    confirmButtonText: "OK",
                });
            } finally {
                this.isUpdatingUser = false;
            }
        },

        async confirmDeleteUser(user) {
            const result = await Swal.fire({
                title: "Are you sure?",
                text: `Do you really want to delete ${user.username}?`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Yes, delete it!",
                cancelButtonText: "Cancel",
            });

            if (result.isConfirmed) {
                await this.deleteUser(user.id);
            }
        },

        async deleteUser(userId) {
            try {
                const response = await fetch(`/delete-user/${userId}`, {
                    method: "DELETE",
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                        Accept: "application/json",
                    },
                });

                if (response.ok) {
                    await Swal.fire({
                        icon: "success",
                        title: "Deleted",
                        text: "User deleted successfully.",
                        confirmButtonText: "OK",
                    });

                    await this.fetchUsers();
                } else {
                    const errorData = await response.json();
                    throw new Error(
                        errorData.message || "Failed to delete user",
                    );
                }
            } catch (error) {
                console.error("Delete user error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Delete failed",
                    text: error.message || "Error deleting user",
                    confirmButtonText: "OK",
                });
            }
        },

        async fetchStores() {
            this.loadingStores = true;
            try {
                const response = await fetch("/get-stores", {
                    method: "GET",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                });

                const data = await response.json();

                if (response.ok && data.stores) {
                    this.stores = data.stores || [];
                } else {
                    throw new Error(data.message || "Failed to fetch stores");
                }
            } catch (error) {
                console.error("Fetch stores error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        error.message ||
                        "Failed to load stores. Please try again.",
                });
            } finally {
                this.loadingStores = false;
            }
        },

        async addStore() {
            if (!this.storeForm.storename.trim()) {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Store name cannot be empty.",
                });
                return;
            }

            // Check if store already exists
            const storeExists = this.stores.some(
                (store) =>
                    store.storename.toLowerCase() ===
                    this.storeForm.storename.toLowerCase(),
            );

            if (storeExists) {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Store name already exists. Please choose a different name.",
                });
                return;
            }

            this.isAddingStore = true;

            try {
                const response = await fetch("/add-store", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                    body: JSON.stringify({
                        storename: this.storeForm.storename,
                        Strabbreviation: this.storeForm.Strabbreviation || "",
                    }),
                });

                const data = await response.json();

                if (!response.ok || data.success === false) {
                    throw new Error(data.message || "Failed to add store");
                }

                await Swal.fire({
                    icon: "success",
                    title: "Store added",
                    text: `Store "${data.store.storename}" added successfully!`,
                    confirmButtonText: "OK",
                });

                // Reset form
                this.storeForm = {
                    storename: "",
                    Strabbreviation: "",
                };

                this.showAddStoreDialog = false;
                await this.fetchStores();
            } catch (error) {
                console.error("Add store error:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Add store failed",
                    text: error.message || "Error adding store",
                    confirmButtonText: "OK",
                });
            } finally {
                this.isAddingStore = false;
            }
        },

        async editStore(store) {
            try {
                // Fetch marketplaces first
                await this.fetchMarketplaces();

                const response = await fetch(`/get-store/${store.store_id}`, {
                    method: "GET",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                });

                const data = await response.json();

                if (response.ok && data.store) {
                    // Parse existing marketplace selections
                    const selectedMarketplaces = [];
                    if (data.store.MarketplaceID) {
                        const ids = data.store.MarketplaceID.split(",").map(
                            (id) => id.trim(),
                        );
                        selectedMarketplaces.push(...ids);
                    }

                    this.editStoreForm = {
                        store_id: data.store.store_id,
                        storename: data.store.storename || "",
                        client_id: data.store.client_id || "",
                        client_secret: data.store.client_secret || "",
                        refresh_token: data.store.refresh_token || "",
                        MerchantID: data.store.MerchantID || "",
                        Marketplace: data.store.Marketplace || "",
                        MarketplaceID: data.store.MarketplaceID || "",
                        selectedMarketplaces: selectedMarketplaces,
                    };
                    this.showEditStoreDialog = true;
                } else {
                    throw new Error(
                        data.message || "Failed to fetch store details",
                    );
                }
            } catch (error) {
                console.error("Fetch store error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        error.message ||
                        "An error occurred while fetching store details.",
                });
            }
        },

        async updateStore() {
            if (!this.editStoreForm.store_id) {
                Swal.fire({
                    icon: "error",
                    title: "Missing Store ID",
                    text: "Store ID is missing. Please try again.",
                    confirmButtonText: "OK",
                });
                return;
            }

            this.isUpdatingStore = true;

            try {
                // Prepare the data object with only non-null values
                const updateData = {
                    storename: this.editStoreForm.storename,
                };

                // Only include optional fields if they have values
                if (this.editStoreForm.client_id) {
                    updateData.client_id = this.editStoreForm.client_id;
                }
                if (this.editStoreForm.client_secret) {
                    updateData.client_secret = this.editStoreForm.client_secret;
                }
                if (this.editStoreForm.refresh_token) {
                    updateData.refresh_token = this.editStoreForm.refresh_token;
                }
                if (this.editStoreForm.MerchantID) {
                    updateData.MerchantID = this.editStoreForm.MerchantID;
                }
                if (this.editStoreForm.Marketplace) {
                    updateData.Marketplace = this.editStoreForm.Marketplace;
                }
                if (this.editStoreForm.MarketplaceID) {
                    updateData.MarketplaceID = this.editStoreForm.MarketplaceID;
                }

                console.log("Sending update data:", updateData);

                const response = await fetch(
                    `/update-store/${this.editStoreForm.store_id}`,
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                        },
                        body: JSON.stringify(updateData),
                    },
                );

                const data = await response.json();
                console.log("Server response:", data);

                if (!response.ok || data.success === false) {
                    throw new Error(data.message || "Failed to update store");
                }

                await Swal.fire({
                    icon: "success",
                    title: "Updated",
                    text: "Store updated successfully.",
                    confirmButtonText: "OK",
                });

                this.showEditStoreDialog = false;
                await this.fetchStores();
            } catch (error) {
                console.error("Update store error:", error);
                console.error("Error details:", {
                    message: error.message,
                    stack: error.stack,
                    response: error.response,
                });

                await Swal.fire({
                    icon: "error",
                    title: "Update failed",
                    text:
                        error.message ||
                        "An error occurred while updating the store.",
                    confirmButtonText: "OK",
                });
            } finally {
                this.isUpdatingStore = false;
            }
        },

        async confirmDeleteStore(store) {
            const result = await Swal.fire({
                title: "Delete store?",
                text: `Are you sure you want to delete ${store.storename}? This action cannot be undone.`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Delete",
                cancelButtonText: "Cancel",
                reverseButtons: true,
            });

            if (result.isConfirmed) {
                await this.deleteStore(store.store_id);
            }
        },

        async deleteStore(storeId) {
            try {
                const response = await fetch(`/delete-store/${storeId}`, {
                    method: "DELETE",
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                        Accept: "application/json",
                    },
                });

                const data = await response.json();

                if (!response.ok || data.success === false) {
                    throw new Error(data.message || "Failed to delete store");
                }

                await Swal.fire({
                    icon: "success",
                    title: "Deleted",
                    text: "Store has been deleted.",
                    confirmButtonText: "OK",
                });

                await this.fetchStores();
            } catch (error) {
                console.error("Delete store error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Delete failed",
                    text: error.message || "Error deleting store",
                    confirmButtonText: "OK",
                });
            }
        },

        async onUserChange() {
            if (this.privilegeForm.selectedUserId) {
                await this.fetchUserPrivileges(
                    this.privilegeForm.selectedUserId,
                );
            }
        },

        async fetchUserPrivileges(userId) {
            try {
                const response = await fetch(`/get-user-privileges/${userId}`, {
                    method: "GET",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                });

                const data = await response.json();

                if (response.ok) {
                    // Set main module
                    this.privilegeForm.mainModule = data.main_module || "";

                    // Set sub-modules (convert object to array of checked values)
                    this.privilegeForm.subModules = [];
                    if (data.sub_modules) {
                        Object.keys(data.sub_modules).forEach((key) => {
                            if (data.sub_modules[key] === true) {
                                this.privilegeForm.subModules.push(key);
                            }
                        });
                    }

                    // Set stores
                    this.privilegeForm.stores = [];
                    if (
                        data.privileges_stores &&
                        Array.isArray(data.privileges_stores)
                    ) {
                        this.userStores = data.privileges_stores;
                        data.privileges_stores.forEach((store) => {
                            if (store.is_checked) {
                                this.privilegeForm.stores.push(
                                    store.store_column,
                                );
                            }
                        });
                    }
                } else {
                    throw new Error(
                        data.message || "Failed to fetch user privileges",
                    );
                }
            } catch (error) {
                console.error("Fetch user privileges error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text:
                        error.message ||
                        "Failed to load user privileges. Please try again.",
                });
            }
        },

        async savePrivileges() {
            if (!this.privilegeForm.selectedUserId) {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Please select a user first.",
                });
                return;
            }

            if (!this.privilegeForm.mainModule) {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Please select a main module.",
                });
                return;
            }

            this.isSavingPrivileges = true;

            try {
                const response = await fetch("/save-user-privileges", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                    body: JSON.stringify({
                        user_id: this.privilegeForm.selectedUserId,
                        main_module: this.privilegeForm.mainModule,
                        sub_modules: this.privilegeForm.subModules,
                        privileges_stores: this.privilegeForm.stores,
                    }),
                });

                const data = await response.json();

                if (!response.ok || data.success === false) {
                    throw new Error(
                        data.message || "Failed to save privileges",
                    );
                }

                await Swal.fire({
                    icon: "success",
                    title: "Success",
                    text: "User privileges saved successfully!",
                    confirmButtonText: "OK",
                });

                // Refresh privileges for the selected user
                await this.fetchUserPrivileges(
                    this.privilegeForm.selectedUserId,
                );

                // If the updated user is the current logged-in user, reload the page
                const loggedInUserId = parseInt(
                    document.querySelector('meta[name="user-id"]')?.content ||
                        "0",
                );
                if (this.privilegeForm.selectedUserId === loggedInUserId) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            } catch (error) {
                console.error("Save privileges error:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Save failed",
                    text: error.message || "Error saving privileges",
                    confirmButtonText: "OK",
                });
            } finally {
                this.isSavingPrivileges = false;
            }
        },

        goToUserPrivileges(user) {
            // Switch to Privileges tab (index 3: 0=Design, 1=Users, 2=Stores, 3=Privileges)
            this.activeTabIndex = 3;

            // Set the selected user
            this.privilegeForm.selectedUserId = user.id;

            // Fetch the user's privileges
            this.$nextTick(() => {
                this.fetchUserPrivileges(user.id);
            });
        },

        async fetchMarketplaces() {
            try {
                const response = await fetch("/fetch-marketplaces", {
                    method: "GET",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                });

                const data = await response.json();

                if (response.ok && Array.isArray(data)) {
                    this.marketplaceOptions = data.map((marketplace) => ({
                        name:
                            marketplace.name ||
                            marketplace.label ||
                            marketplace.value,
                        value:
                            marketplace.value ||
                            marketplace.id ||
                            marketplace.name,
                    }));
                } else {
                    throw new Error("Failed to fetch marketplaces");
                }
            } catch (error) {
                console.error("Fetch marketplaces error:", error);
                this.marketplaceOptions = [];
            }
        },

        async filterTimeRecords() {
            // Don't filter if required fields are empty
            if (
                !this.timeRecordForm.selectedUserId ||
                !this.timeRecordForm.startDate ||
                !this.timeRecordForm.endDate
            ) {
                return;
            }

            this.loadingTimeRecords = true;
            this.hasFiltered = true;

            try {
                const startDate = this.formatDateForAPI(
                    this.timeRecordForm.startDate,
                );
                const endDate = this.formatDateForAPI(
                    this.timeRecordForm.endDate,
                );

                const response = await fetch(
                    `/get-time-records/${this.timeRecordForm.selectedUserId}?start_date=${startDate}&end_date=${endDate}`,
                    {
                        method: "GET",
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                        },
                    },
                );

                // Check if response is JSON
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new Error(
                        "Invalid response from server. Please check the endpoint configuration.",
                    );
                }

                const data = await response.json();

                if (response.ok) {
                    // Transform the data using timezone formatter
                    this.timeRecords = data.map((record) => {
                        // Use timezone formatter for dates and times
                        const timeInFormatted = this.$formatDateTime(
                            record.TimeIn,
                        );
                        const timeOutFormatted = record.TimeOut
                            ? this.$formatDateTime(record.TimeOut)
                            : null;

                        // Calculate total hours using the formatter
                        const totalHours = this.$calculateHours(
                            record.TimeIn,
                            record.TimeOut,
                        );

                        return {
                            details: `${this.$formatDate(
                                record.TimeIn,
                            )} - ${this.$formatTime(record.TimeIn)}${
                                timeOutFormatted
                                    ? " to " + this.$formatTime(record.TimeOut)
                                    : " (Still clocked in)"
                            }`,
                            total_hours: totalHours || "In Progress",
                            notes: record.notes || "No notes",
                            timeIn: record.TimeIn,
                            timeOut: record.TimeOut,
                        };
                    });
                } else {
                    throw new Error("Failed to fetch time records");
                }
            } catch (error) {
                console.error("Fetch time records error:", error);
                this.timeRecords = [];
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: error.message || "Failed to load time records.",
                });
            } finally {
                this.loadingTimeRecords = false;
            }
        },

        formatDate(date) {
            if (!date) return "";
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, "0");
            const day = String(d.getDate()).padStart(2, "0");
            return `${year}-${month}-${day}`;
        },

        // ==================== USER LOGS WITH TIMEZONE ====================

        async filterUserLogs() {
            this.loadingUserLogs = true;
            this.hasFilteredLogs = true;

            try {
                // Build query parameters
                const params = new URLSearchParams();

                if (this.userLogsForm.selectedUserId) {
                    params.append("user_id", this.userLogsForm.selectedUserId);
                }

                if (this.userLogsForm.startDate) {
                    params.append(
                        "start_date_logs",
                        this.formatDateForAPI(this.userLogsForm.startDate),
                    );
                }

                if (this.userLogsForm.endDate) {
                    params.append(
                        "end_date_logs",
                        this.formatDateForAPI(this.userLogsForm.endDate),
                    );
                }

                const url = `/get-user-logs${
                    params.toString() ? "?" + params.toString() : ""
                }`;

                const response = await fetch(url, {
                    method: "GET",
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                });

                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new Error("Invalid response from server.");
                }

                const data = await response.json();

                if (response.ok) {
                    this.userLogs = data || [];
                } else {
                    throw new Error("Failed to fetch user logs");
                }
            } catch (error) {
                console.error("Fetch user logs error:", error);
                this.userLogs = [];
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Failed to load user logs. Please try again.",
                });
            } finally {
                this.loadingUserLogs = false;
            }
        },

        clearUserLogsFilters() {
            this.userLogsForm = {
                selectedUserId: null,
                startDate: null,
                endDate: null,
            };
            this.userLogs = [];
            this.hasFilteredLogs = false;
        },

        // Updated method to use timezone formatter
        formatLogDate(dateString) {
            if (!dateString) return "";

            // Use the timezone formatter
            return this.$formatDateTime(dateString);
        },

        formatDateForAPI(date) {
            if (!date) return "";
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, "0");
            const day = String(d.getDate()).padStart(2, "0");
            return `${year}-${month}-${day}`;
        },

        // ==================== PRINTER MANAGEMENT ====================

        async fetchPrinters() {
            this.loadingPrinters = true;
            try {
                const response = await fetch(
                    "/api/printer-management/get-printers",
                    {
                        method: "GET",
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                        },
                    },
                );

                const data = await response.json();

                if (response.ok && data.printers) {
                    this.allPrinters = data.printers;
                } else {
                    throw new Error("Failed to fetch printers");
                }
            } catch (error) {
                console.error("Fetch printers error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Failed to load printers. Please try again.",
                });
            } finally {
                this.loadingPrinters = false;
            }
        },

        switchPrinterTab(tab) {
            this.activePrinterTab = tab;
            if (tab === "married" && this.marriedPrinters.length === 0) {
                this.fetchMarriedPrinters();
            }
        },

        async addPrinter() {
            this.isAddingPrinter = true;

            try {
                const response = await fetch(
                    "/api/printer-management/add-printer",
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                        },
                        body: JSON.stringify(this.printerForm),
                    },
                );

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || "Failed to add printer");
                }

                await Swal.fire({
                    icon: "success",
                    title: "Success",
                    text: "Printer added successfully!",
                    confirmButtonText: "OK",
                });

                // Reset form
                this.printerForm = {
                    printer_name: "",
                    printer_type: "",
                    ip_address: "",
                    port: 9100,
                    description: "",
                };

                this.showAddPrinterDialog = false;
                await this.fetchPrinters();
            } catch (error) {
                console.error("Add printer error:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: error.message || "Failed to add printer",
                    confirmButtonText: "OK",
                });
            } finally {
                this.isAddingPrinter = false;
            }
        },

        editPrinter(printer) {
            this.editPrinterForm = {
                printerid: printer.printerid,
                printer_name: printer.printername,
                printer_type: printer.printer_type,
                ip_address: printer.printerip,
                port: printer.port || 9100,
                status: printer.status || "active",
                description: printer.description || "",
            };
            this.showEditPrinterDialog = true;
        },

        async updatePrinter() {
            this.isUpdatingPrinter = true;

            try {
                const response = await fetch(
                    `/api/printer-management/update-printer/${this.editPrinterForm.printerid}`,
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                        },
                        body: JSON.stringify(this.editPrinterForm),
                    },
                );

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || "Failed to update printer");
                }

                await Swal.fire({
                    icon: "success",
                    title: "Success",
                    text: "Printer updated successfully!",
                    confirmButtonText: "OK",
                });

                this.showEditPrinterDialog = false;
                await this.fetchPrinters();
            } catch (error) {
                console.error("Update printer error:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: error.message || "Failed to update printer",
                    confirmButtonText: "OK",
                });
            } finally {
                this.isUpdatingPrinter = false;
            }
        },

        async confirmDeletePrinter(printer) {
            const result = await Swal.fire({
                title: "Delete printer?",
                text: `Are you sure you want to delete ${printer.printername}? This action cannot be undone.`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Delete",
                cancelButtonText: "Cancel",
            });

            if (result.isConfirmed) {
                await this.deletePrinter(printer.printerid);
            }
        },

        async deletePrinter(printerId) {
            try {
                const response = await fetch(
                    `/api/printer-management/delete-printer/${printerId}`,
                    {
                        method: "DELETE",
                        headers: {
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                            Accept: "application/json",
                        },
                    },
                );

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || "Failed to delete printer");
                }

                await Swal.fire({
                    icon: "success",
                    title: "Deleted",
                    text: "Printer has been deleted.",
                    confirmButtonText: "OK",
                });

                await this.fetchPrinters();
            } catch (error) {
                console.error("Delete printer error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: error.message || "Failed to delete printer",
                    confirmButtonText: "OK",
                });
            }
        },

        getPrinterTypeLabel(type) {
            const option = this.printerTypeOptions.find(
                (o) => o.value === type,
            );
            return option ? option.label : type;
        },

        getStatusSeverity(status) {
            const severityMap = {
                active: "success",
                inactive: "secondary",
                maintenance: "warning",
                unknown: "danger",
            };
            return severityMap[status] || "secondary";
        },

        // ==================== MARRIED PRINTERS ====================

        async fetchMarriedPrinters() {
            this.isLoadingMarriedPrinters = true;
            this.marriedPrintersError = "";

            try {
                // FIX: Changed from 'married-printers' to 'get-married-printers'
                const response = await fetch(
                    "/api/printer-management/get-married-printers",
                    {
                        method: "GET",
                        headers: {
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                        },
                    },
                );

                if (!response.ok) {
                    throw new Error(
                        `HTTP ${response.status}: Failed to load married printers`,
                    );
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(
                        data.message || "Failed to load married printers",
                    );
                }

                // Map the data to match your template structure
                this.marriedPrinters = data.marriages.map((marriage) => ({
                    marriage_name: marriage.marriage_name,
                    description: marriage.description,
                    small_label_id: marriage.small_label_printer.printer_id,
                    small_label_printer_name:
                        marriage.small_label_printer.printer_name,
                    small_label_ip: marriage.small_label_printer.ip_address,
                    instruction_card_id:
                        marriage.instruction_card_printer.printer_id,
                    instruction_card_printer_name:
                        marriage.instruction_card_printer.printer_name,
                    instruction_card_ip:
                        marriage.instruction_card_printer.ip_address,
                }));
            } catch (error) {
                console.error("Fetch married printers error:", error);
                this.marriedPrintersError =
                    error.message || "Failed to load married printers";
                this.marriedPrinters = [];
            } finally {
                this.isLoadingMarriedPrinters = false;
            }
        },

        async marryPrinters() {
            this.isMarryingPrinters = true;

            try {
                const response = await fetch(
                    "/api/printer-management/marry-printers",
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                        },
                        body: JSON.stringify({
                            small_label_printer_id:
                                this.marriageForm.small_label_printer_id,
                            instruction_card_printer_id:
                                this.marriageForm.instruction_card_printer_id,
                            marriage_name: this.marriageForm.marriage_name,
                            description: this.marriageForm.description,
                        }),
                    },
                );

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.message || "Failed to create marriage",
                    );
                }

                await Swal.fire({
                    icon: "success",
                    title: "Success",
                    text: "Printers married successfully!",
                    confirmButtonText: "OK",
                });

                // Reset form
                this.marriageForm = {
                    small_label_printer_id: null,
                    instruction_card_printer_id: null,
                    marriage_name: "",
                    description: "",
                };

                this.showMarryPrinterDialog = false;
                await this.fetchMarriedPrinters();
                await this.fetchPrinters(); // Refresh all printers to show updated marriage status
            } catch (error) {
                console.error("Marry printers error:", error);
                await Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: error.message || "Failed to marry printers",
                    confirmButtonText: "OK",
                });
            } finally {
                this.isMarryingPrinters = false;
            }
        },

        async confirmDeleteMarriage(marriage) {
            const result = await Swal.fire({
                title: "Delete marriage?",
                html: `Are you sure you want to delete the marriage <strong>"${marriage.marriage_name}"</strong>?<br><br>This will unlink:<br>• ${marriage.small_label_printer_name}<br>• ${marriage.instruction_card_printer_name}`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Yes, delete it!",
                cancelButtonText: "Cancel",
            });

            if (result.isConfirmed) {
                await this.deleteMarriage(marriage);
            }
        },

        async deleteMarriage(marriage) {
            try {
                // FIX: Changed from 'unmarry-printers' to 'divorce-printers'
                const response = await fetch(
                    `/api/printer-management/divorce-printers/${marriage.small_label_id}`,
                    {
                        method: "DELETE",
                        headers: {
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                            Accept: "application/json",
                        },
                    },
                );

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.message || "Failed to delete marriage",
                    );
                }

                await Swal.fire({
                    icon: "success",
                    title: "Deleted",
                    text: "Marriage has been deleted successfully.",
                    confirmButtonText: "OK",
                });

                await this.fetchMarriedPrinters();
                await this.fetchPrinters(); // Refresh all printers to show updated status
            } catch (error) {
                console.error("Delete marriage error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: error.message || "Failed to delete marriage",
                    confirmButtonText: "OK",
                });
            }
        },

        // ###################### UNIQUE IDENTIFIER STICKERS ##################################

        showSetStartDialogFunc(data) {
            ((this.showSetStartDialog = true),
                (this.identifierEditForm.name = data.data.name));
        },

        async fetchIdentifiers() {
            try {
                this.loadingUniqueIdentifiers = true;
                const response = await fetch("/settings/getOrderIdentifiers", {
                    method: "GET",
                    headers: {
                        Accept: "application/json",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                });

                const data = await response.json();

                this.uniqueIdentifiersData = data?.data || [];
            } catch (error) {
                console.error("Failed to fetch the data:", error);
            } finally {
                this.loadingUniqueIdentifiers = false;
            }
        },

        async updateStartValueCount() {
            try {
                this.isUpdatingStartCount = true;
                const response = await fetch("/settings/updateStartCount", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": document.querySelector(
                            'meta[name="csrf-token"]',
                        ).content,
                    },
                    body: JSON.stringify({
                        name: this.identifierEditForm.name,
                        start: this.identifierEditForm.startCountValue,
                    }),
                });

                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Updated",
                        text: "Start count updated successfully",
                        confirmButtonText: "OK",
                    });
                    this.identifierEditForm.startCountValue = null;
                    this.showSetStartDialog = false;
                    await this.fetchIdentifiers();
                }
            } catch (error) {
                console.error("Failed to fetch the data:", error);
            } finally {
                this.isUpdatingStartCount = false;
            }
        },

        handleClose(value) {
            if (!value) {
                this.activeTabIndex = 0;
            }

            this.$emit("update:settingsVisible", value);
        },
    },
};
</script>

<style src="./SettingsModalGlobal.css"></style>
<style scoped src="./SettingsModal.css"></style>
