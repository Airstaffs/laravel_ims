<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Cache Control -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <!-- Title and CSRF Token -->
    <title>{{ session('site_title', 'IMS') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ Auth::id() }}">

    <!-- Bootstrap CSS (Single Version - Latest Stable) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- App-specific CSS via Vite -->
    @vite('resources/css/app.css')

    <!-- Inline Theme Styles -->
    <style>
        .navbar {
            background-color:
                {{ session('theme_color', '#007bff') }}
            ;
            transition: margin-left 0.3s ease-in-out, padding-left 0.3s ease-in-out;
        }

        .sidebar-nav .nav-link.active {
            color: #fff;
            background-color:
                {{ session('theme_color', '#007bff') }}
            ;
            border-radius: 5px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        /* Loading indicator for Vue components */
        [v-cloak] {
            display: none;
        }

        /* Session status indicator styles */
        .session-indicator {
            transition: background-color 0.3s ease;
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">
    <!-- Navbar -->
    @include('dashboard.components.navbar')

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar">
        <button id="close-btn" class="close-btn">&times;</button>

        <!-- User Info -->
        <div class="user-info text-center">
            <img src="{{ session('profile_picture', 'default-profile.jpg') }}" alt="User Profile"
                class="rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
            <h5>{{ session('user_name', 'User Name') }}</h5>
        </div>

        <h5 class="text-center">Navigation</h5>

        @php
            use Illuminate\Support\Facades\Auth;

            // Refresh user data from DB
            $currentUser = Auth::user();
            $subModules = [];
            $mainModule = '';

            if ($currentUser) {
                $freshUser = \App\Models\User::find($currentUser->id);
                $mainModule = strtolower($freshUser->main_module ?: '');

                $moduleColumns = [
                    'humanresource',
                    'order',
                    'unreceived',
                    'receiving',
                    'labeling',
                    'testing',
                    'cleaning',
                    'packing',
                    'stockroom',
                    'validation',
                    'fnsku',
                    'productionarea',
                    'rts',
                    'returnscanner',
                    'fbmorder',
                    'shipment',
                    'soldlist',
                    'returnlist',
                    'notfound',
                    'asinoption',
                    'houseage',
                    'suppliescomponents',
                    'reconciliation',
                    'asinlist',
                    'printer',
                    'auxiliary',
                    'inventorystatistics',


                ];

                foreach ($moduleColumns as $column) {
                    // Only add to subModules if it's enabled AND not the main module
                    if (!empty($freshUser->{$column}) && $column !== $mainModule) {
                        $subModules[] = strtolower($column);
                    }
                }

                session(['main_module' => $mainModule, 'sub_modules' => $subModules]);
            } else {
                $mainModule = strtolower(session('main_module', ''));
                $subModules = array_map('strtolower', session('sub_modules', []));
            }

            // Remove duplication - ensure main module is not in sub modules
            $subModules = array_filter($subModules, fn($mod) => $mod !== $mainModule);

            // Fallback to first submodule or dashboard
            $defaultModule = $mainModule ?: $subModules[0] ?? 'dashboard';

            $modules = [
                'humanresource' => 'Human Resource',
                'order' => 'Order',
                'asinoption' => 'Asin Option',
                'unreceived' => 'Unreceived',
                'receiving' => 'Received',
                'labeling' => 'Labeling',
                'validation' => 'Validation',
                'testing' => 'Testing',
                'cleaning' => 'Cleaning',
                'packing' => 'Packing',
                'stockroom' => 'Stockroom',
                'productionarea' => 'Production Area',
                'rts' => 'RTS',
                'returnscanner' => 'Return Scanner',
                'fbmorder' => 'FBM Order',
                'shipment' => 'Shipment',
                'soldlist' => 'Sold Items',
                'notfound' => 'Not Found',
                'houseage' => 'Houseage',
                'suppliescomponents' => 'Supplies & Components',
                'printer' => 'Printer',
                'auxiliary' => 'Auxiliary Label',
                'inventorystatistics' => 'Inventory Statistics',
                'reconciliation' => 'Reconciliation',

            ];

            function hasAccess($module, $mainModule, $subModules): bool
            {
                $module = strtolower($module);
                return $module === 'dashboard' || $module === $mainModule || in_array($module, $subModules);
            }
        @endphp

        <!-- Client-side Setup -->
        <script>
            window.defaultComponent = "{{ $defaultModule }}";
            window.mainModule = "{{ $mainModule }}";
            window.allowedModules = @json($subModules);
            window.customModules = ['printcustominvoice', 'fbashipmentinbound', 'mskucreation', 'scheduling'];

            console.log('Session Modules:', {
                defaultComponent: window.defaultComponent,
                allowedModules: window.allowedModules,
                mainModule: window.mainModule
            });
        </script>

        <!-- Navigation Links -->
        <nav class="nav flex-column sidebar-nav">
            {{-- Display main module if it exists --}}
            @if ($mainModule && isset($modules[$mainModule]))
                <a class="nav-link active" href="/{{ $mainModule }}"
                    onclick="window.loadContent('{{ $mainModule }}'); highlightNavLink(this); closeSidebar(); return false;">
                    {{ $modules[$mainModule] }}
                </a>
            @endif

            {{-- Loop through sub-modules, excluding the main module --}}
            @foreach ($subModules as $module)
                @if (isset($modules[$module]) && $module !== $mainModule)
                    @if ($module === 'asinoption')
                        <!-- Special handling for ASIN Option - show modal instead of loading component -->
                        <a class="nav-link" href="#"
                            onclick="showAsinOptionModal(); highlightNavLink(this); closeSidebar(); return false;">
                            {{ $modules[$module] }}
                        </a>
                    @elseif ($module === 'printer')
                        <!-- Regular module handling for printer -->
                        <a class="nav-link" href="/{{ $module }}"
                            onclick="window.loadContent('{{ $module }}'); highlightNavLink(this); closeSidebar(); return false;">
                            {{ $modules[$module] }}
                        </a>
                    @else
                        <!-- Regular module handling -->
                        <a class="nav-link" href="/{{ $module }}"
                            onclick="window.loadContent('{{ $module }}'); highlightNavLink(this); closeSidebar(); return false;">
                            {{ $modules[$module] }}
                        </a>
                    @endif
                @endif
            @endforeach
        </nav>
    </div>

    <div id="main-content" class="content flex-grow-1">
        <div id="app">
            <!-- Hidden component triggers -->
            @foreach ($modules as $module => $label)
                <a id="{{ $module }}Link" style="display:none" href="#" @click.prevent="loadContent('{{ $module }}')">
                    {{ $label }}
                </a>
            @endforeach

            <!-- Vue component with main module as default -->
            <component :is="currentComponent" :key="currentComponent">
            </component>
        </div>

        <div id="dynamic-content">
            @vite(['resources/js/app.js'])
        </div>
    </div>

    @include('dashboard.modals.asinoption')

    <!-- NOTES Modal -->
    <div class="modal fade" id="editNotesModal" tabindex="-1" aria-labelledby="editNotesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editNotesModalLabel">Edit Notes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editNotesForm">
                        @csrf
                        <input type="hidden" id="recordId" name="recordId">
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="updateNotes()">Update</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Notification for adding user-->
    @if (session('success'))
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="successToast" class="toast align-items-center text-bg-success border-0 show" role="alert"
                aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        {{ session('success') }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                        aria-label="Close"></button>
                </div>
            </div>
        </div>
    @endif

    <!-- Error Notification -->
    @if (session('error'))
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="errorToast" class="toast align-items-center text-bg-danger border-0 show" role="alert"
                aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        {{ session('error') }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                        aria-label="Close"></button>
                </div>
            </div>
        </div>
    @endif

    <!-- Validation Errors -->
    @if ($errors->any())
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="validationToast" class="toast align-items-center text-bg-warning border-0 show" role="alert"
                aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                        aria-label="Close"></button>
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Automatically show all toasts on page load
            const toastElList = [].slice.call(document.querySelectorAll('.toast'));
            toastElList.forEach(function (toastEl) {
                new bootstrap.Toast(toastEl).show();
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            // Add click event listeners to all toggle-password buttons
            document.querySelectorAll('.toggle-password').forEach(button => {
                button.addEventListener('click', () => {
                    const targetInput = document.querySelector(button.getAttribute('data-target'));
                    const icon = button.querySelector('i');

                    if (targetInput.type === 'password') {
                        targetInput.type = 'text'; // Show password
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    } else {
                        targetInput.type = 'password'; // Hide password
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    }
                });
            });
        });
    </script>

    <!-- Audio Elements -->
    <audio id="clockin-sound" src="/sounds/clockin2.mp3"></audio>
    <audio id="clockout-sound" src="/sounds/clockout2.mp3"></audio>
    <audio id="clockin-question-sound" src="/sounds/clockin_question.mp3"></audio>
    <audio id="clockout-question-sound" src="/sounds/clockout_question.mp3"></audio>
    <audio id="error-sound" src="/sounds/error2.mp3"></audio>
    <audio id="logout-sound" src="/sounds/logout.mp3"></audio>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Success</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="fs-4" id="successMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">Error</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="fs-4">{{ session('error') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Get the audio elements
            const clockinSound = document.getElementById('clockin-sound');
            const clockoutSound = document.getElementById('clockout-sound');
            const errorSound = document.getElementById('error-sound');
            // Get the modal and message elements
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            const successMessage = document.getElementById('successMessage');

            // Check conditions for playing sounds
            @if (session('success_clockin'))
                successMessage.textContent = "{{ session('success_clockin') }}";
                successModal.show();
                clockinSound.play();
            @endif

            @if (session('success_clockout'))
                successMessage.textContent = "{{ session('success_clockout') }}";
                successModal.show();
                clockoutSound.play();
            @endif

                // Show error modal and play error sound if an error message exists
                @if (session('error'))
                    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                    errorModal.show();
                    errorSound.play();
                @endif
        });
    </script>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to logout?</p>
                    <small class="text-muted">You will be redirected to the login page.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmLogout">
                        <i class="bi bi-box-arrow-right me-1"></i>
                        Yes, Logout
                    </button>
                </div>
            </div>
        </div>
    </div>

    @env('production')
        <script>
            document.addEventListener('contextmenu', (e) => e.preventDefault());
        </script>
    @endenv

    <script>
        // ============================================
        // CONFIGURATION
        // ============================================
        const SESSION_CONFIG = {
            CHECK_AUTH_INTERVAL: 120000, // 2 minutes
            KEEP_ALIVE_INTERVAL: 300000, // 5 minutes
        };

        // ============================================
        // INITIALIZATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Dashboard loaded - initializing security measures...');

            // Check for CSRF token on page load
            validateCsrfToken();

            // PREVENT BACK BUTTON ACCESS AFTER LOGOUT
            preventBackButtonAccess();

            // Initialize logout system
            initializeLogoutSystem();

            // Start session management (managed by app.js)
            console.log('✅ Session management delegated to app.js');
        });

        // ============================================
        // CSRF TOKEN VALIDATION
        // ============================================
        function validateCsrfToken() {
            let csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken) {
                console.error('CSRF token meta tag missing from page head');
                // Try to add it if missing
                const head = document.getElementsByTagName('head')[0];
                const meta = document.createElement('meta');
                meta.name = 'csrf-token';
                meta.content = '{{ csrf_token() }}';
                head.appendChild(meta);
                csrfToken = meta;
            }
            console.log('CSRF token found:', csrfToken.getAttribute('content').substring(0, 10) + '...');
        }

        // ============================================
        // PREVENT BACK BUTTON ACCESS
        // ============================================
        function preventBackButtonAccess() {
            console.log('Setting up back button prevention...');

            // Method 1: History manipulation
            history.pushState(null, null, window.location.href);
            window.addEventListener('popstate', function (event) {
                console.log('Back button pressed - checking authentication...');
                checkAuthStatus();
                history.pushState(null, null, window.location.href);
            });

            // Method 2: Page show event (handles browser cache)
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) {
                    console.log('Page loaded from cache - checking authentication...');
                    checkAuthStatus();
                }
            });

            // Method 3: Visibility change (tab switching)
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    console.log('Page became visible - checking authentication...');
                    setTimeout(checkAuthStatus, 1000);
                }
            });

            // Method 4: Keyboard shortcuts prevention
            document.addEventListener('keydown', function (e) {
                // Prevent Alt + Left Arrow (back)
                if (e.altKey && e.keyCode === 37) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }

                // Prevent Alt + Right Arrow (forward)
                if (e.altKey && e.keyCode === 39) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }

                // Prevent Backspace (back in some browsers)
                if (e.keyCode === 8 && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    return false;
                }

                // Prevent F5 refresh in some cases
                if (e.keyCode === 116) {
                    setTimeout(checkAuthStatus, 100);
                }
            });
        }

        // ============================================
        // LOGOUT SYSTEM
        // ============================================
        function initializeLogoutSystem() {
            console.log('Initializing logout system...');

            const confirmBtn = document.getElementById('confirmLogout');
            if (confirmBtn) {
                // Remove any existing listeners first
                const newBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

                // Add single event listener
                newBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Logout confirmed by user');

                    // Hide the modal first
                    const modal = bootstrap.Modal.getInstance(document.getElementById('logoutModal'));
                    if (modal) {
                        modal.hide();
                    }

                    // Small delay to let modal close, then logout
                    setTimeout(performLogout, 300);
                });
            }
        }

        // ============================================
        // MAIN LOGOUT FUNCTION
        // ============================================
        function performLogout() {
            console.log('Logout initiated...');

            // Show loading indicator
            const confirmBtn = document.getElementById('confirmLogout');
            if (confirmBtn) {
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging out...';
                confirmBtn.disabled = true;
            }

            // Clear any stored data immediately
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.clear();
            }
            if (typeof localStorage !== 'undefined') {
                localStorage.clear();
            }

            // Try to get fresh CSRF token first
            fetch('/csrf-token', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Fresh CSRF token obtained');
                    doLogoutWithToken(data.token);
                })
                .catch(error => {
                    console.log('Failed to get fresh token, using existing token');
                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    const token = csrfMeta ? csrfMeta.getAttribute('content') : '';

                    if (token) {
                        doLogoutWithToken(token);
                    } else {
                        // Last resort - redirect to force logout
                        console.log('No token available, using force logout');
                        window.location.replace('/force-logout');
                    }
                });
        }

        // ============================================
        // ACTUAL LOGOUT EXECUTION
        // ============================================
        function doLogoutWithToken(token) {
            console.log('Executing logout with token:', token.substring(0, 10) + '...');

            // Create and submit a form (most reliable method)
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/logout';
            form.style.display = 'none';

            // Add CSRF token
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = token;
            form.appendChild(tokenInput);

            // Add to DOM and submit
            document.body.appendChild(form);

            console.log('Submitting logout form...');

            // Clear cache before logout
            if ('caches' in window) {
                caches.keys().then(names => {
                    names.forEach(name => {
                        caches.delete(name);
                    });
                });
            }

            // Submit form
            form.submit();

            // Cleanup after delay
            setTimeout(() => {
                if (document.body.contains(form)) {
                    document.body.removeChild(form);
                }
            }, 2000);

            // Fallback redirect in case form submission fails
            setTimeout(() => {
                window.location.replace('/login');
            }, 3000);
        }

        // ============================================
        // MODAL FUNCTIONS
        // ============================================
        function showLogoutModal() {
            console.log('Showing logout modal...');

            // Check if CSRF token exists and is not empty
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken || !csrfToken.getAttribute('content')) {
                console.error('CSRF token missing, refreshing page...');
                window.location.reload();
                return;
            }

            const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            logoutModal.show();

            // Play logout question sound
            const logoutSound = document.getElementById('logout-sound');
            if (logoutSound) {
                logoutSound.play().catch(e => console.log('Sound play failed:', e));
            }
        }

        // ============================================
        // SESSION MANAGEMENT (Delegated to app.js)
        // ============================================
        function checkAuthStatus() {
            fetch('/check-auth', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    if (response.status === 401 || response.status === 419) {
                        console.log('Authentication check failed - redirecting to login');
                        window.location.replace('/login');
                    }
                })
                .catch(error => {
                    console.log('Auth check failed:', error);
                    // Don't redirect on network errors, only on auth failures
                });
        }

        // ============================================
        // GLOBAL ERROR HANDLER
        // ============================================
        window.addEventListener('error', function (e) {
            if (e.message && e.message.includes('419')) {
                console.log('Caught 419 error globally');
                window.location.replace('/login');
            }
        });

        window.addEventListener('unhandledrejection', function (event) {
            if (event.reason && event.reason.message && (
                event.reason.message.includes('419') ||
                event.reason.message.includes('401') ||
                event.reason.message.includes('Unauthenticated')
            )) {
                console.log('Caught authentication error in promise rejection');
                window.location.replace('/login');
            }
        });

        // ============================================
        // KANBAN NOTIFICATION
        // ============================================
        function getKanbanNotif() {
            const user = @json(Auth::user());

            fetch('/user/kanban/notification', {
                method: 'POST',
                body: JSON.stringify({
                    userId: user.id
                }),
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
                .then(response => response.json())
                .then(data => {
                    console.log(data.mentionedCount || 0, "data.mentionedCount || 0;")
                    window.kanbanMentionedCount = data.mentionedCount || 0;

                    if (data.mentionedCount > 0) {
                        ["kanbanNotifMobile", "kanbanNotifDesktop"].forEach(id => {
                            const el = document.getElementById(id)
                            if (el) {
                                el.style.display = "inline"
                                el.textContent = data.mentionedCount
                            }
                        })
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }

        console.log('✅ Complete security system loaded successfully');
    </script>

    <!-- Footer -->
    <x-footer></x-footer>

    <celebrateannouncement></celebrateannouncement>

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
        </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    @if (Auth::check())
        <script>
            window.user = @json(Auth::user());
        </script>
    @else
        <script>
            window.user = null;
        </script>
    @endif

    @vite(['resources/js/app.js'])

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            [...tooltipTriggerList].forEach(el => new bootstrap.Tooltip(el));
        });
    </script>
</body>

</html>