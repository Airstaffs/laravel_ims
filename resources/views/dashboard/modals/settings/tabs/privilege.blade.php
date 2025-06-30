<div class="tab-pane fade" id="privilege" role="tabpanel" aria-labelledby="privilege-tab">
    <h3 class="text-center mb-4">User Privileges</h3>

    <form id="privilegeForm">
        @csrf

        @php
            use App\Models\User;
            $Allusers = User::all();
            $selectedUser = request('user_id')
                ? User::find(request('user_id'))
                : User::where('username', 'admin')->first();
        @endphp

        {{-- User Selection --}}
        <fieldset>
            <label for="selectUser">Select User</label>
            <select id="selectUser" name="user_id" class="form-select" required>
                @if (count($Allusers) > 0)
                    @foreach ($Allusers as $userOption)
                        <option value="{{ $userOption->id }}" {{ auth()->id() == $userOption->id ? 'selected' : '' }}>
                            {{ $userOption->username }}
                        </option>
                    @endforeach
                @else
                    <option disabled selected>No users found</option>
                @endif
            </select>
        </fieldset>

        {{-- Dynamic Containers (Populated by JS) --}}
        <fieldset id="mainModuleContainer"></fieldset>
        <fieldset id="subModuleContainer"></fieldset>
        <fieldset id="storeContainer"></fieldset>

        <button type="submit" class="btn btn-primary justify-content-center fw-bold text-white mt-2">
            Save Privileges
        </button>
    </form>
</div>

<script>
    // Initialize when DOM is loaded
    document.addEventListener("DOMContentLoaded", function () {
        const privilegeForm = document.getElementById('privilegeForm');
        if (privilegeForm) {
            initializeUserSelect();
            initializePrivilegeForm();
        } else {
            initializePrivilegeChecker();
        }
    });

    // Admin Functions
    function initializeUserSelect() {
        const selectUser = document.getElementById('selectUser');

        selectUser.addEventListener('change', function () {
            const selectedValue = this.value;

            Array.from(this.options).forEach(option => {
                option.style.display = option.value === selectedValue ? 'none' : 'block';
            });

            if (selectedValue !== "") {
                const defaultOption = selectUser.querySelector('option[value=""]');
                if (defaultOption) {
                    defaultOption.style.display = 'none';
                }
            }

            if (selectedValue) {
                fetchUserPrivileges(selectedValue);
            }
        });
    }

    function initializePrivilegeForm() {
        const form = document.getElementById('privilegeForm');

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            try {
                await refreshCsrfToken();
                const formData = collectFormData();
                const response = await saveUserPrivileges(formData);

                if (response.success) {
                    showNotification('Success', 'User privileges saved successfully!', 'success');

                    await fetchUserPrivileges(formData.user_id);

                    const mainModuleDb = (response.main_module || formData.main_module || '').toLowerCase().replace(/\s+/g, '');
                    const subModulesDb = response.sub_modules || [];
                    const filteredSubModules = subModulesDb.filter(module =>
                        module.toLowerCase().replace(/\s+/g, '') !== mainModuleDb
                    );

                    const navigationData = {
                        main_module: mainModuleDb,
                        sub_modules: filteredSubModules,
                        modules: {
                            asinoption: 'ASIN Option',
                            order: 'Order',
                            unreceived: 'Unreceived',
                            receiving: 'Received',
                            labeling: 'Labeling',
                            validation: 'Validation',
                            testing: 'Testing',
                            cleaning: 'Cleaning',
                            packing: 'Packing',
                            fnsku: 'FNSKU',
                            stockroom: 'Stockroom',
                            productionarea: 'Production Area',
                            returnscanner: 'Return Scanner',
                            fbmorder: 'FBM Order',
                            houseage: 'Houseage',
                            asinlist: 'ASIN List',
                        }
                    };

                    // ✅ Only update navigation if selected user is the current user
                    if (parseInt(formData.user_id) === parseInt(window.loggedInUserId)) {
                        updateUserNavigation(navigationData);

                        if (window.appInstance) {
                            forceComponentUpdate(mainModuleDb);
                        }
                    }

                    // Modal & Form Cleanup
                    const modalEl = document.getElementById('settingsModal');
                    if (modalEl) {
                        modalEl.style.display = 'none';
                        modalEl.classList.remove('show');
                        document.body.classList.remove('modal-open');
                        document.body.style.removeProperty('padding-right');
                        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                    }

                    if (form) {
                        form.classList.remove('was-validated');
                    }
                    initializeUserSelect();

                } else {
                    showNotification('Error', response.message || 'Failed to save privileges', 'error');
                }

            } catch (error) {
                console.error('Error in form submission:', error);
                showNotification('Error', 'An unexpected error occurred', 'error');
            }
        });
    }

    function collectFormData() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Get the main module value
        const mainModuleRadio = document.querySelector('input[name="main_module"]:checked');
        const mainModuleValue = mainModuleRadio ? mainModuleRadio.value : '';

        // Get all checked sub-modules - these will be database column names
        const subModuleCheckboxes = document.querySelectorAll('input[name="sub_modules[]"]:checked');
        const subModules = Array.from(subModuleCheckboxes).map(checkbox => checkbox.value);

        // Debug logging
        console.log('Collecting form data:', {
            main_module: mainModuleValue,
            sub_modules: subModules,
            main_module_radio: mainModuleRadio
        });

        return {
            user_id: parseInt(document.getElementById('selectUser').value, 10),
            main_module: mainModuleValue, // This will be "Received" if that's selected
            sub_modules: subModules, // These will be database column names like "receiving"
            privileges_stores: [...document.querySelectorAll('input[name="privileges_stores[]"]:checked')].map(input =>
                input.value),
            _token: csrfToken
        };
    }

    async function saveUserPrivileges(formData) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        try {
            // First save the privileges
            const response = await fetch('/save-user-privileges', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                // Update the navigation immediately with the response data
                const navigationData = {
                    main_module: result.main_module || formData.main_module,
                    sub_modules: result.sub_modules || [],
                    modules: {
                        'asinoption': 'ASIN Option',
                        'order': 'Order',
                        'unreceived': 'Unreceived',
                        'receiving': 'Received',
                        'labeling': 'Labeling',
                        'validation': 'Validation',
                        'testing': 'Testing',
                        'cleaning': 'Cleaning',
                        'packing': 'Packing',
                        //'fnsku': 'FNSKU',
                        'stockroom': 'Stockroom',
                        'productionarea': 'Production Area',
                        'returnscanner': 'Return Scanner',
                        'fbmorder': 'FBM Order',
                        'notfound': 'Not Found',
                        'houseage': 'Houseage',
                    }
                };

                // Update navigation immediately
                updateUserNavigation(navigationData);

                // Force session refresh
                const refreshResponse = await fetch('/refresh-user-session', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                const refreshResult = await refreshResponse.json();
                if (refreshResult.success) {
                    // Update with refreshed data
                    updateUserNavigation({
                        main_module: refreshResult.main_module,
                        sub_modules: refreshResult.sub_modules,
                        modules: navigationData.modules
                    });
                }

                return result;
            }

            return result;
        } catch (error) {
            console.error('Error in save process:', error);
            throw error;
        }
    }

    async function fetchUserPrivileges(userId) {
        try {
            const response = await fetch(`/get-user-privileges/${userId}`);
            const data = await response.json();
            updateForm(data);
        } catch (error) {
            console.error('Error fetching user privileges:', error);
            showNotification('Error', 'Failed to fetch user privileges', 'error');
        }
    }

    function updateForm(data) {
        if (!data) {
            console.error("No data received for user privileges");
            return;
        }

        updateMainModule(data);
        updateSubModules(data);
        updateStores(data);
    }

    function updateMainModule(data) {
        // Define the mapping for consistent database column names
        const moduleMapping = {
            'Order': 'order',
            'Unreceived': 'unreceived',
            'Received': 'receiving',
            'Labeling': 'labeling',
            'Testing': 'testing',
            'Cleaning': 'cleaning',
            'Packing': 'packing',
            'Stockroom': 'stockroom',
            'Validation': 'validation',
            'FNSKU': 'fnsku',
            'Production Area': 'productionarea',
            'Return Scanner': 'returnscanner',
            'FBM Order': 'fbmorder',
            'Not Found': 'notfound',
            'Houseage': 'houseage',
        };

        const mainModules = ['Order', 'Unreceived', 'Received', 'Labeling', 'Testing', 'Cleaning', 'Packing',
            'Stockroom', 'Validation', 'FNSKU', 'Production Area', 'Return Scanner', 'FBM Order', 'Not Found', 'Houseage'
        ];

        const mainModuleHTML = `
        <label>Main Module</label>
        <div class="main-module__container">
            ${mainModules.map(module => {
            // Get the database column name for comparison
            const dbColumnName = moduleMapping[module] || module.toLowerCase().replace(/\s+/g, '');
            const isChecked = data.main_module === dbColumnName ? 'checked' : '';

            return `<div>
                        <input class="form-check-input" type="radio" name="main_module"
                                value="${module}" ${isChecked} required>
                        <span>${module}</span>
                    </div>`;
        }).join('')}
        </div>`;
        document.getElementById('mainModuleContainer').innerHTML = mainModuleHTML;
    }

    function updateSubModules(data) {
        const subModules = [{
            db: 'order',
            display: 'Order'
        },
        {
            db: 'unreceived',
            display: 'Unreceived'
        },
        {
            db: 'receiving',
            display: 'Received'
        },
        {
            db: 'labeling',
            display: 'Labeling'
        },
        {
            db: 'testing',
            display: 'Testing'
        },
        {
            db: 'cleaning',
            display: 'Cleaning'
        },
        {
            db: 'packing',
            display: 'Packing'
        },
        {
            db: 'stockroom',
            display: 'Stockroom'
        },
        {
            db: 'validation',
            display: 'Validation'
        },
        {
            db: 'fnsku',
            display: 'FNSKU'
        },
        {
            db: 'asinlist',
            display: 'ASIN List'
        },
        {
            db: 'productionarea',
            display: 'Production Area'
        },
        {
            db: 'returnscanner',
            display: 'Return Scanner'
        },
        {
            db: 'fbmorder',
            display: 'FBM Order'
        },
        {
            db: 'notfound',
            display: 'Not Found'
        },
        {
            db: 'asinoption',
            display: 'ASIN Option'
        },
        {
            db: 'houseage',
            display: 'Houseage'
        }
        ];

        const subModulesHTML = `
        <label>Sub-Modules</label>
        <div class="main-module__container">
            ${subModules.map(module => `<div>
                    <input class="form-check-input" type="checkbox" name="sub_modules[]"
                            value="${module.db}"
                            ${data.sub_modules && data.sub_modules[module.db] === true ? 'checked' : ''}>
                    <span>${module.display}</span>
                </div>`).join('')}
        </div>`;
        document.getElementById('subModuleContainer').innerHTML = subModulesHTML;
    }

    function updateStores(data) {
        const storeHTML = `
        <label>Stores</label>
        <div class="main-module__container">
            ${data.privileges_stores && data.privileges_stores.length > 0
                ? data.privileges_stores.map(store => `
                    <div>
                        <input class="form-check-input" type="checkbox" name="privileges_stores[]"
                            value="${store.store_column}" ${store.is_checked ? 'checked' : ''}>
                        <span>${store.store_name}</span>
                        </div>
                        `).join('')
                : '<p>No stores available</p>'
            }
        </div>`;
        document.getElementById('storeContainer').innerHTML = storeHTML;
    }

    // Navigation Update Functions
    function initializePrivilegeChecker() {
        setInterval(checkForUpdates, 5000);
    }

    async function checkForUpdates() {
        try {
            const response = await fetch('/check-user-privileges');
            const data = await response.json();

            if (data.success) {
                console.log('Checking for updates:', data);

                // Ensure all module names are lowercase without spaces
                const mainModule = data.main_module ? data.main_module.toLowerCase().replace(/\s+/g, '') : '';
                const subModules = data.sub_modules ?
                    data.sub_modules
                        .map(m => m.toLowerCase().replace(/\s+/g, ''))
                        .filter(m => m !== mainModule) : // Ensure main module is not in sub modules
                    [];

                window.defaultComponent = mainModule;
                window.allowedModules = subModules;
                window.mainModule = mainModule;

                // Create proper modules object for display
                const modules = {
                    'asinoption': 'ASIN Option',
                    'order': 'Order',
                    'unreceived': 'Unreceived',
                    'receiving': 'Received',
                    'labeling': 'Labeling',
                    'testing': 'Testing',
                    'cleaning': 'Cleaning',
                    'packing': 'Packing',
                    'stockroom': 'Stockroom',
                    'validation': 'Validation',
                    //'fnsku': 'FNSKU',
                    'productionarea': 'Production Area',
                    'returnscanner': 'Return Scanner',
                    'fbashipmentinbound': 'FBA Inbound Shipment',
                    'fbmorder': 'FBM Order',
                    'notfound': 'Not Found',
                    'houseage': 'Houseage' // Add this mapping
                };

                updateUserNavigation({
                    main_module: mainModule,
                    sub_modules: subModules,
                    modules: modules
                });
            }
        } catch (error) {
            console.error('Error checking privileges:', error);
        }
    }

    function updateUserNavigation(data) {
        const nav = document.querySelector('nav.nav.flex-column');
        if (!nav) return;

        console.log('Updating navigation with:', data);

        // Ensure modules mapping includes all lowercase keys
        const defaultModules = {
            'asinoption': 'ASIN Option',
            'order': 'Order',
            'unreceived': 'Unreceived',
            'receiving': 'Received',
            'labeling': 'Labeling',
            'testing': 'Testing',
            'cleaning': 'Cleaning',
            'packing': 'Packing',
            'stockroom': 'Stockroom',
            'validation': 'Validation',
            //  'fnsku': 'FNSKU',
            'productionarea': 'Production Area',
            'returnscanner': 'Return Scanner',
            'fbashipmentinbound': 'FBA Inbound Shipment',
            'fbmorder': 'FBM Order',
            'notfound': 'Not Found',
            'houseage': 'Houseage'
        };

        // Use provided modules or default modules
        const modules = data.modules || defaultModules;

        let navHTML = '';

        // Normalize main module
        const mainModuleLower = data.main_module ? data.main_module.toLowerCase().replace(/\s+/g, '') : '';

        // Add main module if it exists
        if (mainModuleLower) {
            navHTML += `
            <a class="nav-link active" href="#"
               data-module="${mainModuleLower}"
               onclick="window.loadContent('${mainModuleLower}'); highlightNavLink(this); closeSidebar(); return false;">
                ${modules[mainModuleLower] || capitalizeFirst(data.main_module)}
            </a>`;
        }

        // Add sub modules, explicitly filtering out the main module
        if (Array.isArray(data.sub_modules)) {
            // Filter and normalize sub_modules
            const filteredSubModules = data.sub_modules
                .map(m => m.toLowerCase().replace(/\s+/g, ''))
                .filter(moduleLower => moduleLower !== mainModuleLower);

            filteredSubModules.forEach(moduleLower => {
                navHTML += `
                <a class="nav-link" href="#"
                   data-module="${moduleLower}"
                   onclick="window.loadContent('${moduleLower}'); highlightNavLink(this); closeSidebar(); return false;">
                    ${modules[moduleLower] || capitalizeFirst(moduleLower)}
                </a>`;
            });
        }

        nav.innerHTML = navHTML;

        // Ensure window variables are updated with properly filtered data
        window.mainModule = mainModuleLower;
        window.allowedModules = data.sub_modules ?
            data.sub_modules.map(m => m.toLowerCase().replace(/\s+/g, '')).filter(m => m !== mainModuleLower) : [];
        window.defaultComponent = mainModuleLower;

        // Update Vue component if needed
        if (mainModuleLower && window.appInstance) {
            window.appInstance.forceUpdate(mainModuleLower);
        }

        console.log('Navigation updated. Main:', window.mainModule, 'Allowed:', window.allowedModules);
    }

    function forceComponentUpdate(moduleName) {
        if (!window.appInstance) return;

        console.log('Forcing update to component:', moduleName);
        window.appInstance.currentComponent = null;

        setTimeout(() => {
            window.appInstance.currentComponent = moduleName;
            console.log('Component updated to:', moduleName);
        }, 0);
    }

    function capitalizeFirst(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    function showNotification(title, message, type) {
        alert(`${title}: ${message}`);
    }

    // Initialize form when page loads
    window.onload = function () {
        const selectedUserId = document.getElementById('selectUser')?.value;
        if (selectedUserId) {
            fetchUserPrivileges(selectedUserId);
        }
    };
</script>