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
                            stockroom: 'Stockroom',
                            productionarea: 'Production Area',
                            returnscanner: 'Return Scanner',
                            fbmorder: 'FBM Order',
                            houseage: 'Houseage',
                            printer: 'Printer',
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

    // Add this new function to refresh CSRF token
    async function refreshCsrfToken() {
        try {
            const response = await fetch('/csrf-token');
            const data = await response.json();
            document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.token);
            return true;
        } catch (error) {
            console.error('Error refreshing CSRF token:', error);
            return false;
        }
    }

    function collectFormData() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Get the main module value
        const mainModuleRadio = document.querySelector('input[name="main_module"]:checked');
        const mainModuleValue = mainModuleRadio ? mainModuleRadio.value : '';

        // Get all checked sub-modules - these will be database column names
        const subModuleCheckboxes = document.querySelectorAll('input[name="sub_modules[]"]:checked');
        const subModules = Array.from(subModuleCheckboxes).map(checkbox => checkbox.value);

        // 🔴 DEBUG: Specific logging for printer
        const printerCheckbox = document.querySelector('input[name="sub_modules[]"][value="printer"]');
        console.log('Printer checkbox details:', {
            exists: !!printerCheckbox,
            checked: printerCheckbox ? printerCheckbox.checked : false,
            value: printerCheckbox ? printerCheckbox.value : null,
            included_in_submodules: subModules.includes('printer')
        });

        // Debug logging
        console.log('Collecting form data:', {
            main_module: mainModuleValue,
            sub_modules: subModules,
            main_module_radio: mainModuleRadio,
            printer_specifically: subModules.includes('printer')
        });

        return {
            user_id: parseInt(document.getElementById('selectUser').value, 10),
            main_module: mainModuleValue,
            sub_modules: subModules,
            privileges_stores: [...document.querySelectorAll('input[name="privileges_stores[]"]:checked')].map(input =>
                input.value),
            _token: csrfToken
        };
    }

    async function saveUserPrivileges(formData) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        try {
            console.log('=== SAVING USER PRIVILEGES ===');
            console.log('Form data being sent:', formData);
            
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
            console.log('Server response:', result);

            if (result.success) {
                console.log('=== PRIVILEGES SAVED SUCCESSFULLY ===');
                
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
                        'stockroom': 'Stockroom',
                        'productionarea': 'Production Area',
                        'returnscanner': 'Return Scanner',
                        'fbmorder': 'FBM Order',
                        'notfound': 'Not Found',
                        'houseage': 'Houseage',
                        'printer': 'Printer',
                    }
                };

                console.log('Navigation data being passed:', navigationData);

                // Update navigation with the server response data
                updateUserNavigation(navigationData);

                // 🔴 REMOVED: Don't call session refresh here as it's overriding the navigation
                // The session refresh is called by the checkForUpdates interval function
                // which is causing the printer module to disappear
                
                /*
                console.log('=== REFRESHING SESSION (without navigation update) ===');
                const refreshResponse = await fetch('/refresh-user-session', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                const refreshResult = await refreshResponse.json();
                console.log('Session refresh result (navigation not updated):', refreshResult);
                */

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
            'Production Area': 'productionarea',
            'Return Scanner': 'returnscanner',
            'FBM Order': 'fbmorder',
            'Not Found': 'notfound',
            'Houseage': 'houseage',
        };

        const mainModules = ['Order', 'Unreceived', 'Received', 'Labeling', 'Testing', 'Cleaning', 'Packing',
            'Stockroom', 'Validation','Production Area', 'Return Scanner', 'FBM Order', 'Not Found', 'Houseage'
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
        },
        {
            db: 'printer',
            display: 'Printer'
        }
        ];

        // 🔴 DEBUG: Log the data being processed
        console.log('updateSubModules called with data:', data);
        console.log('Sub-modules data:', data.sub_modules);

        const subModulesHTML = `
            <label>Sub-Modules</label>
            <div class="main-module__container">
                ${subModules.map(module => {
                    // 🔴 DEBUG: Log each module processing
                    const isChecked = data.sub_modules && data.sub_modules[module.db] === true;
                    console.log(`Processing ${module.db}: ${isChecked ? 'CHECKED' : 'NOT CHECKED'}`);
                    
                    return `<div>
                        <input class="form-check-input" type="checkbox" name="sub_modules[]"
                                value="${module.db}"
                                ${isChecked ? 'checked' : ''}>
                        <span>${module.display}</span>
                    </div>`;
                }).join('')}
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
        console.log('🔴 initializePrivilegeChecker: Starting...');
        
        // FIXED: Load current user privileges on page load
        loadCurrentUserPrivileges();
        
        // Then set up the interval for periodic checks
        setInterval(checkForUpdates, 30000);
    }

    // NEW FUNCTION: Load privileges on page load
    async function loadCurrentUserPrivileges() {
        try {
            console.log('🔴 loadCurrentUserPrivileges: Fetching current user privileges on page load...');
            const response = await fetch('/check-user-privileges');
            const data = await response.json();

            if (data.success) {
                console.log('🔴 loadCurrentUserPrivileges: Data received:', data);
                
                const mainModule = data.main_module ? data.main_module.toLowerCase().replace(/\s+/g, '') : '';
                
                const modules = data.modules || {
                    'asinoption': 'ASIN Option',
                    'order': 'Order',
                    'unreceived': 'Unreceived',
                    'receiving': 'Received',
                    'labeling': 'Labeling',
                    'validation': 'Validation',
                    'testing': 'Testing',
                    'cleaning': 'Cleaning',
                    'packing': 'Packing',
                    'stockroom': 'Stockroom',
                    'productionarea': 'Production Area',
                    'returnscanner': 'Return Scanner',
                    'fbmorder': 'FBM Order',
                    'notfound': 'Not Found',
                    'houseage': 'Houseage',
                    'printer': 'Printer',
                };

                const subModules = data.sub_modules ?
                    data.sub_modules
                        .map(m => m.toLowerCase().replace(/\s+/g, ''))
                        .filter(moduleLower => moduleLower !== mainModule) : [];

                console.log('🔴 loadCurrentUserPrivileges: Setting navigation with printer?', subModules.includes('printer'));

                window.defaultComponent = mainModule;
                window.allowedModules = subModules;
                window.mainModule = mainModule;

                updateUserNavigation({
                    main_module: mainModule,
                    sub_modules: subModules,
                    modules: modules
                });
            }
        } catch (error) {
            console.error('🔴 loadCurrentUserPrivileges: Error loading privileges', error);
        }
    }

    async function checkForUpdates() {
        try {
            console.log('🔴 checkForUpdates: Starting...');
            const response = await fetch('/check-user-privileges');
            const data = await response.json();

            console.log('🔴 checkForUpdates: Raw server response:', JSON.parse(JSON.stringify(data)));

            if (data.success) {
                // Check if printer exists in the response
                const hasPrinterInResponse = data.sub_modules && data.sub_modules.includes('printer');
                console.log('🔴 checkForUpdates: Printer in server response?', hasPrinterInResponse);

                const mainModule = data.main_module ? data.main_module.toLowerCase().replace(/\s+/g, '') : '';
                
                // Use modules from response or defaults
                const modules = data.modules || {
                    'asinoption': 'ASIN Option',
                    'order': 'Order',
                    'unreceived': 'Unreceived',
                    'receiving': 'Received',
                    'labeling': 'Labeling',
                    'validation': 'Validation',
                    'testing': 'Testing',
                    'cleaning': 'Cleaning',
                    'packing': 'Packing',
                    'stockroom': 'Stockroom',
                    'productionarea': 'Production Area',
                    'returnscanner': 'Return Scanner',
                    'fbmorder': 'FBM Order',
                    'notfound': 'Not Found',
                    'houseage': 'Houseage',
                    'printer': 'Printer',
                };

                // Process sub-modules without filtering based on module existence
                const subModules = data.sub_modules ?
                    data.sub_modules
                        .map(m => m.toLowerCase().replace(/\s+/g, ''))
                        .filter(moduleLower => moduleLower !== mainModule) : [];

                console.log('🔴 checkForUpdates: Processed sub-modules:', subModules);
                console.log('🔴 checkForUpdates: Printer included?', subModules.includes('printer'));

                window.defaultComponent = mainModule;
                window.allowedModules = subModules;
                window.mainModule = mainModule;

                updateUserNavigation({
                    main_module: mainModule,
                    sub_modules: subModules,
                    modules: modules
                });
            }
        } catch (error) {
            console.error('🔴 checkForUpdates: Error', error);
        }
    }

    function updateUserNavigation(data) {
        const nav = document.querySelector('nav.nav.flex-column');
        if (!nav) {
            console.error('🔴 updateUserNavigation: Nav element not found!');
            return;
        }

        console.log('🔴 updateUserNavigation: Called with data:', JSON.parse(JSON.stringify(data)));
        console.log('🔴 updateUserNavigation: Printer in sub_modules?', data.sub_modules?.includes('printer'));

        const defaultModules = {
            'asinoption': 'ASIN Option',
            'order': 'Order',
            'unreceived': 'Unreceived',
            'receiving': 'Received',
            'labeling': 'Labeling',
            'validation': 'Validation',
            'testing': 'Testing',
            'cleaning': 'Cleaning',
            'packing': 'Packing',
            'stockroom': 'Stockroom',
            'productionarea': 'Production Area',
            'returnscanner': 'Return Scanner',
            'fbmorder': 'FBM Order',
            'notfound': 'Not Found',
            'houseage': 'Houseage',
            'printer': 'Printer',
        };

        const modules = data.modules || defaultModules;
        let navHTML = '';

        const mainModuleLower = data.main_module ? data.main_module.toLowerCase().replace(/\s+/g, '') : '';

        // Add main module first
        if (mainModuleLower && modules[mainModuleLower]) {
            if (mainModuleLower === 'asinoption') {
                navHTML += `
            <a class="nav-link active" href="#"
               data-module="${mainModuleLower}"
               onclick="showAsinOptionModal(); highlightNavLink(this); closeSidebar(); return false;">
                ${modules[mainModuleLower]}
            </a>`;
            } else {
                navHTML += `
            <a class="nav-link active" href="#"
               data-module="${mainModuleLower}"
               onclick="window.loadContent('${mainModuleLower}'); highlightNavLink(this); closeSidebar(); return false;">
                ${modules[mainModuleLower]}
            </a>`;
            }
        }

        // Process sub-modules
        if (Array.isArray(data.sub_modules)) {
            console.log('🔴 updateUserNavigation: Processing sub-modules:', data.sub_modules);
            
            const filteredSubModules = data.sub_modules
                .map(m => m.toLowerCase().replace(/\s+/g, ''))
                .filter(moduleLower => moduleLower !== mainModuleLower);

            console.log('🔴 updateUserNavigation: Filtered sub-modules:', filteredSubModules);

            filteredSubModules.forEach(moduleLower => {
                if (moduleLower === 'printer') {
                    console.log('🔴 updateUserNavigation: ADDING PRINTER TO NAV');
                }
                
                if (modules[moduleLower]) {
                    if (moduleLower === 'asinoption') {
                        navHTML += `
                <a class="nav-link" href="#"
                   data-module="${moduleLower}"
                   onclick="showAsinOptionModal(); highlightNavLink(this); closeSidebar(); return false;">
                    ${modules[moduleLower]}
                </a>`;
                    } else {
                        navHTML += `
                <a class="nav-link" href="#"
                   data-module="${moduleLower}"
                   onclick="window.loadContent('${moduleLower}'); highlightNavLink(this); closeSidebar(); return false;">
                    ${modules[moduleLower]}
                </a>`;
                    }
                } else {
                    console.warn('🔴 updateUserNavigation: Module not found in mapping:', moduleLower);
                }
            });
        }

        console.log('🔴 updateUserNavigation: Setting nav HTML, contains printer?', navHTML.includes('printer'));
        nav.innerHTML = navHTML;

        // Verify printer link after update
        setTimeout(() => {
            const printerLink = nav.querySelector('[data-module="printer"]');
            console.log('🔴 updateUserNavigation: Printer link exists after update?', !!printerLink);
        }, 100);

        // Update window variables
        window.mainModule = mainModuleLower;
        window.allowedModules = data.sub_modules ?
            data.sub_modules.map(m => m.toLowerCase().replace(/\s+/g, '')).filter(m => m !== mainModuleLower) : [];
        window.defaultComponent = mainModuleLower;

        console.log('🔴 updateUserNavigation: Final state:', {
            mainModule: window.mainModule,
            allowedModules: window.allowedModules,
            printerInAllowedModules: window.allowedModules.includes('printer')
        });
    }

    // Debug monitoring code
    if (window.loadContent) {
        const originalLoadContent = window.loadContent;
        window.loadContent = function(module) {
            console.log('🔴 DEBUG: loadContent called with module:', module);
            if (module === 'printer') {
                console.log('🔴 PRINTER MODULE BEING LOADED');
            }
            return originalLoadContent.apply(this, arguments);
        };
    }

    // Add MutationObserver to watch for navigation changes
    const navObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList') {
                const nav = document.querySelector('nav.nav.flex-column');
                if (nav) {
                    const printerLink = nav.querySelector('[data-module="printer"]');
                    console.log('🔴 NAV MUTATION: Printer link exists?', !!printerLink);
                    if (!printerLink) {
                        console.log('🔴 PRINTER REMOVED! Current nav HTML:', nav.innerHTML);
                        console.trace('Stack trace for printer removal');
                    }
                }
            }
        });
    });

    // Start observing navigation changes
    const navElement = document.querySelector('nav.nav.flex-column');
    if (navElement) {
        navObserver.observe(navElement, { childList: true, subtree: true });
    }

    // Check if there's any code that might be filtering printer specifically
    const originalFilter = Array.prototype.filter;
    Array.prototype.filter = function(...args) {
        const result = originalFilter.apply(this, args);
        
        // Check if this array operation is removing printer
        if (this.includes && this.includes('printer') && !result.includes('printer')) {
            console.warn('🔴 ARRAY FILTER REMOVED PRINTER:', {
                original: this,
                filtered: result,
                filterFunction: args[0].toString()
            });
        }
        
        return result;
    };

    // Also check session storage / local storage for any printer-specific handling
    console.log('🔴 Session Storage:', Object.keys(sessionStorage).filter(k => k.includes('printer')));
    console.log('🔴 Local Storage:', Object.keys(localStorage).filter(k => k.includes('printer')));

    // Check if printer module file exists or if there's a 404
    if (window.loadContent) {
        // Test load printer module
        setTimeout(() => {
            console.log('🔴 TEST: Attempting to load printer module...');
            fetch('/printer')  // Adjust the URL based on your routing
                .then(response => {
                    console.log('🔴 Printer module response status:', response.status);
                    return response.text();
                })
                .then(html => {
                    console.log('🔴 Printer module HTML length:', html.length);
                })
                .catch(error => {
                    console.error('🔴 Error loading printer module:', error);
                });
        }, 2000);
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