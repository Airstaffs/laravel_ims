<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Cache Control -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <!-- Title and CSRF -->
    <title>{{ session('site_title', 'IMS') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- CSS Assets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

    <!-- App-specific CSS via Vite -->
    @vite('resources/css/app.css')

    <!-- JS Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <!-- Tooltip Initialization -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tooltipTriggerList = [...document.querySelectorAll('[data-bs-toggle="tooltip"]')];
            tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
        });
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Bootstrap JS (REQUIRED for dropdowns to work) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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
    </style>
</head>

<body>
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

                $moduleColumns = ['humanresource', 'order', 'unreceived', 'receiving', 'labeling', 'testing', 'cleaning', 'packing', 'stockroom', 'validation', 'fnsku', 'productionarea', 'rts', 'returnscanner', 'fbmorder', 'notfound', 'asinoption', 'houseage', 'asinlist', 'printer'];

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
            $defaultModule = $mainModule ?: ($subModules[0] ?? 'dashboard');

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
                'notfound' => 'Not Found',
                'houseage' => 'Houseage',
                'printer' => 'Printer'
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Function to highlight the current active page based on URL
            function setActiveNavLink() {
                const currentPath = window.location.pathname;
                const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');

                navLinks.forEach(link => {
                    // Remove active class from all links
                    link.classList.remove('active');
                });

                // If we have a main module, make sure it's always active first
                const mainModule = window.mainModule;
                if (mainModule) {
                    const mainModuleLink = document.querySelector(`[data-module="${mainModule}"]`);
                    if (mainModuleLink) {
                        mainModuleLink.classList.add('active');
                        return; // Exit early, main module should always be active
                    }
                }

                // Fallback: check if link href matches current path
                navLinks.forEach(link => {
                    if (link.getAttribute('href') === currentPath) {
                        link.classList.add('active');
                    }
                });
            }

            // Initialize active link on page load
            setActiveNavLink();

            // Set up close button functionality
            const closeBtn = document.getElementById('close-btn');
            if (closeBtn) {
                closeBtn.addEventListener('click', closeSidebar);
            }

            // Ensure navigation order is correct on page load
            setTimeout(() => {
                const nav = document.querySelector('nav.nav.flex-column');
                if (nav && window.mainModule) {
                    // Force reorder navigation if needed
                    const mainModuleLink = nav.querySelector(`[data-module="${window.mainModule}"]`);
                    if (mainModuleLink && mainModuleLink !== nav.firstElementChild) {
                        // Move main module to top
                        nav.insertBefore(mainModuleLink, nav.firstElementChild);
                        mainModuleLink.classList.add('active');
                    }
                }
            }, 100);
        });

        // Function to highlight clicked nav link
        function highlightNavLink(element) {
            // Remove active class from all nav links
            const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
            navLinks.forEach(link => link.classList.remove('active'));

            // Add active class to clicked link
            element.classList.add('active');
        }

        // Function to close the sidebar
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('main-content');
            const burgerMenu = document.getElementById('burger-menu');
            const navbarBrand = document.querySelector('.navbar-brand');

            // Remove visible class from sidebar
            if (sidebar) sidebar.classList.remove('visible');

            // Remove sidebar-visible class from content
            if (content) content.classList.remove('sidebar-visible');

            // Show burger menu again
            if (burgerMenu) burgerMenu.classList.remove('hidden');

            // Reset navbar brand position
            if (navbarBrand) navbarBrand.classList.remove('shifted');
        }
    </script>

    <script>
        window.defaultComponent = "{{ strtolower(session('main_module', 'dashboard')) }}";
        window.allowedModules = @json(array_map('strtolower', session('sub_modules', [])));
        window.mainModule = "{{ strtolower(session('main_module', 'dashboard')) }}";
        window.customModules = ['printcustominvoice', 'fbashipmentinbound', 'mskucreation', 'scheduling'];
    </script>

    <div id="main-content" class="content">
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
    @include('dashboard.modals.printer')
    @include('dashboard.modals.settings.settings-modal')
    @include('dashboard.modals.profiles.profiles-modal')
    @include('dashboard.modals.break')

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const profileModal = document.getElementById('profileModal');

            profileModal.addEventListener('shown.bs.modal', function () {
                const defaultTab = document.querySelector('#attendance-tab');
                const defaultTabPane = document.querySelector('#attendance');

                // Ensure Bootstrap properly activates the tab
                if (defaultTab && defaultTabPane) {
                    new bootstrap.Tab(defaultTab).show();
                }
            });

            profileModal.addEventListener('hidden.bs.modal', function () {
                // Reset all tabs
                document.querySelectorAll('#profileTab .nav-link').forEach(tab => {
                    tab.classList.remove('active');
                    tab.setAttribute('aria-selected', 'false');
                });

                document.querySelectorAll('#profileTabContent .tab-pane').forEach(tabPane => {
                    tabPane.classList.remove('show', 'active');
                });

                // Reapply the default tab using Bootstrap's method
                const defaultTab = document.querySelector('#attendance-tab');
                if (defaultTab) {
                    new bootstrap.Tab(defaultTab).show();
                }
            });
        });
    </script>

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

    <div id="announcementModal" class="modal-overlay-announcement">
        <div class="modal-box-announce">
            <div class="modal-header-announce">
                <h2 id="announcementTitle">Announcement</h2>
                <button class="close-btn-announce" onclick="closeAnnouncement()">×</button>
            </div>
            <div class="modal-body-announce">
                <p id="announcementMessage"></p>
                <small id="announcementDuration"></small>
                <div class="readby-announce">
                    <strong>Read by:</strong> <span id="announcementReadBy">None</span>
                </div>
            </div>
            <div class="modal-footer-announce">
                <button id="ackBtn-announce" onclick="acknowledgeAnnouncement()">Acknowledge</button>
            </div>
        </div>
    </div>

    <!-- Manage Announcements Modal -->
    <div class="modal fade" id="annManageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable ann-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Announcements</h5>
                    <button type="button" class="btn btn-primary btn-sm" onclick="ANN.openCompose()">
                        <i class="bi bi-plus-lg"></i> New
                    </button>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <form class="row g-2 align-items-end" onsubmit="event.preventDefault(); ANN.refreshManage();">
                        <div class="col-auto">
                            <label class="form-label small mb-1">Status</label>
                            <select id="annFilterStatus" class="form-select form-select-sm"
                                onchange="ANN.refreshManage()">
                                <option value="all">All</option>
                                <option value="active">Active</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                        <div class="col">
                            <label class="form-label small mb-1">Search (title/message)</label>
                            <input id="annFilterQ" type="search" class="form-control form-control-sm"
                                placeholder="Search…" oninput="ANN.debouncedRefresh()" />
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-outline-secondary btn-sm" type="button"
                                onclick="ANN.refreshManage()">Refresh</button>
                        </div>
                    </form>

                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="width:70px;">#</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Window</th>
                                    <th>Recipients</th>
                                    <th style="width:180px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="annManageTbody">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Loading…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>



    <!-- Compose/Edit Announcement Modal -->
    <div class="modal fade" id="annComposeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="annComposeForm" onsubmit="ANN.submitCompose(); return false;">
                    <div class="modal-header">
                        <h5 class="modal-title" id="annComposeTitle">New Announcement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            onclick="ANN.resetCompose()"></button>
                    </div>


                    <!-- 👇 modal-body will scroll if too tall -->
                    <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                        <input type="hidden" id="annId" value="">

                        <div class="mb-2">
                            <label class="form-label">Title<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="annTitle" maxlength="255" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" id="annMessage" rows="5"></textarea>
                        </div>

                        <div class="row g-2">
                            <div class="col">
                                <label class="form-label">Start (local)</label>
                                <input type="datetime-local" class="form-control" id="annStartAt">
                            </div>
                            <div class="col">
                                <label class="form-label">End (local)</label>
                                <input type="datetime-local" class="form-control" id="annEndAt">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Status</label>
                            <div class="btn-group" role="group" aria-label="Status">
                                <input type="radio" class="btn-check" name="annStatus" id="annStatusDraft"
                                    autocomplete="off" checked>
                                <label class="btn btn-outline-secondary" for="annStatusDraft">Draft</label>

                                <input type="radio" class="btn-check" name="annStatus" id="annStatusActive"
                                    autocomplete="off">
                                <label class="btn btn-outline-success" for="annStatusActive">Active</label>
                            </div>
                        </div>

                        <hr>

                        <div class="mb-2">
                            <label class="form-label">Recipients</label>

                            <div class="d-flex flex-wrap gap-3 align-items-center mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="annGroupPH"
                                        onchange="ANN.applyGroupSelection()">
                                    <label class="form-check-label" for="annGroupPH">PH group</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="annGroupUS"
                                        onchange="ANN.applyGroupSelection()">
                                    <label class="form-check-label" for="annGroupUS">US group</label>
                                </div>

                                <div class="ms-auto">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                                        onclick="ANN.checkAll(true)">Check all</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="ANN.checkAll(false)">Uncheck all</button>
                                </div>
                            </div>

                            <input id="annRecipientsFilter" type="search" class="form-control form-control-sm mb-2"
                                placeholder="Filter recipients…" oninput="ANN.filterRecipients()">

                            <div id="annRecipientsList" class="list-group" style="max-height: 250px; overflow-y: auto;">
                            </div>
                            <small class="text-muted">Leave empty to send to everyone.</small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-light" type="button" data-bs-dismiss="modal"
                            onclick="ANN.resetCompose()">Cancel</button>
                        <button class="btn btn-primary" type="submit" id="annSaveBtn">Save</button>
                        <button class="btn btn-success" type="button" onclick="ANN.submitCompose('active')"
                            id="annPublishBtn">Save & Activate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.ANN = window.ANN || {};

        document.addEventListener('DOMContentLoaded', function () {

            const API = {
                employees: '/hr/employees',
                adminList: '/hr/announcements/admin',
                save: '/hr/announcements/save',
                toggle: '/hr/announcements/toggle-active',
            };

            // State
            let employees = [];
            let manageRows = [];

            // Modals
            let manageModal = null, composeModal = null;

            // Utils
            const qs = (s, r = document) => r.querySelector(s);
            const qsa = (s, r = document) => Array.from(r.querySelectorAll(s));
            const escapeHtml = (s) => String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            const fmtWindow = (r) => {
                const s = r.start_at || null, e = r.end_at || null;
                if (!s && !e) return '—';
                if (s && e) return `${s} — ${e}`;
                if (s) return `from ${s}`;
                return `until ${e}`;
            };
            const badge = (ok) => ok
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Draft</span>';
            const debounce = (fn, t = 300) => { let to; return (...a) => { clearTimeout(to); to = setTimeout(() => fn(...a), t); }; };

            function ensureModals() {
                const m1 = qs('#annManageModal');
                const m2 = qs('#annComposeModal');
                if (m1) manageModal = bootstrap.Modal.getOrCreateInstance(m1, { backdrop: 'static' });
                if (m2) composeModal = bootstrap.Modal.getOrCreateInstance(m2, { backdrop: 'static' });
            }

            function getCheckedRecipientIds() {
                return qsa('input[name="annRecipient"]:checked')
                    .map(cb => Number(cb.value));
            }

            function setCheckedRecipientIds(ids) {
                const want = new Set((ids || []).map(Number));
                qsa('input[name="annRecipient"]').forEach(cb => {
                    cb.checked = want.has(Number(cb.value));
                });
            }

            function renderRecipientsList() {
                const box = qs('#annRecipientsList');
                if (!box) return;

                // full list for filtering
                box.innerHTML = employees.map(e => {
                    const id = String(e.id);
                    const name = (e.name || e.username || ('#' + id));
                    const acct = e.accounttype ? ` (${e.accounttype})` : '';
                    const dataName = (name + ' ' + (e.accounttype || '')).toLowerCase();

                    return `
      <label class="list-group-item d-flex align-items-center gap-2"
             data-name="${escapeHtml(dataName)}"
             data-acct="${escapeHtml(e.accounttype || '')}">
        <input class="form-check-input m-0" type="checkbox"
               name="annRecipient" value="${id}">
        <span>${escapeHtml(name)}${escapeHtml(acct)}</span>
      </label>`;
                }).join('');
            }

            // ===== Manage view =====
            function onOpenManage() { refreshManage(); }

            function refreshManage() {
                const tbody = qs('#annManageTbody');
                if (!tbody) return;
                const status = (qs('#annFilterStatus')?.value || 'all');
                const q = (qs('#annFilterQ')?.value || '').trim();

                const params = new URLSearchParams();
                if (status !== 'all') params.set('status', status);
                if (q) params.set('q', q);

                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>';

                fetch(`${API.adminList}${params.toString() ? `?${params.toString()}` : ''}`)
                    .then(r => r.json())
                    .then(rows => { manageRows = Array.isArray(rows) ? rows : []; renderManageTable(); })
                    .catch(() => { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Failed to load.</td></tr>'; });
            }

            function renderManageTable() {
                const tbody = qs('#annManageTbody');
                if (!tbody) return;

                if (!manageRows.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No announcements.</td></tr>';
                    return;
                }
                const toName = id => (employees.find(e => String(e.id) === String(id))?.name) || `#${id}`;
                const toNames = arr => (Array.isArray(arr) ? arr.map(toName).slice(0, 3).join(', ') + (arr.length > 3 ? '…' : '') : 'everyone');

                tbody.innerHTML = manageRows.map(r => `
      <tr>
        <td>${r.id}</td>
        <td>${escapeHtml(r.title || '')}</td>
        <td>${badge(!!r.is_active)}</td>
        <td>${fmtWindow(r)}</td>
        <td>${Array.isArray(r.recipients) && r.recipients.length ? toNames(r.recipients) : 'Everyone'}</td>
        <td class="text-nowrap">
          <button class="btn btn-sm btn-outline-primary me-1" onclick="ANN.openCompose(${r.id})">
            <i class="bi bi-pencil"></i> Edit
          </button>
          <button class="btn btn-sm ${r.is_active ? 'btn-outline-warning' : 'btn-outline-success'}"
                  onclick="ANN.toggleActive(${r.id}, ${r.is_active ? 'false' : 'true'})">
            ${r.is_active ? 'Deactivate' : 'Activate'}
          </button>
        </td>
      </tr>
    `).join('');
            }

            // ===== Compose form =====
            function resetCompose() {
                const ttl = qs('#annComposeTitle'); if (ttl) ttl.textContent = 'New Announcement';

                const id = qs('#annId'); if (id) id.value = '';
                const t = qs('#annTitle'); if (t) t.value = '';
                const m = qs('#annMessage'); if (m) m.value = '';
                const s = qs('#annStartAt'); if (s) s.value = '';
                const e = qs('#annEndAt'); if (e) e.value = '';

                const rd = qs('#annStatusDraft'); if (rd) rd.checked = true;
                const ra = qs('#annStatusActive'); if (ra) ra.checked = false;

                const gph = qs('#annGroupPH'); if (gph) gph.checked = false;
                const gus = qs('#annGroupUS'); if (gus) gus.checked = false;

                qsa('#annRecipients option').forEach(o => o.selected = false);
            }

            function openCompose(id = null) {
                resetCompose();

                // always (re)render list before we select items
                renderRecipientsList();

                if (id != null) {
                    const row = manageRows.find(r => String(r.id) === String(id));
                    if (row) {
                        qs('#annComposeTitle').textContent = `Edit #${row.id}`;
                        qs('#annId').value = row.id;
                        qs('#annTitle').value = row.title || '';
                        qs('#annMessage').value = row.message || '';
                        qs('#annStartAt').value = row.start_at ? row.start_at.replace(' ', 'T').slice(0, 16) : '';
                        qs('#annEndAt').value = row.end_at ? row.end_at.replace(' ', 'T').slice(0, 16) : '';
                        if (row.is_active) {
                            qs('#annStatusActive').checked = true;
                            qs('#annStatusDraft').checked = false;
                        }
                        // recipients (checkboxes)
                        const ids = Array.isArray(row.recipients) ? row.recipients.map(Number) : [];
                        setCheckedRecipientIds(ids);
                    }
                }

                // close Manage first to avoid double focustrap
                const manageEl = qs('#annManageModal');
                const showCompose = () => composeModal && composeModal.show();

                if (manageEl && manageEl.classList.contains('show') && manageModal) {
                    manageEl.addEventListener('hidden.bs.modal', showCompose, { once: true });
                    manageModal.hide();
                } else {
                    showCompose();
                }
            }

            function submitCompose(mode = null) {
                const id = qs('#annId').value || null;
                const title = qs('#annTitle').value.trim();
                const msg = qs('#annMessage').value || '';
                const start = qs('#annStartAt').value || null;
                const end = qs('#annEndAt').value || null;
                const active = qs('#annStatusActive').checked;
                const save_mode = mode ? mode : (active ? 'active' : 'draft');

                if (!title) { alert('Title is required.'); return; }
                if (start && end && (new Date(start) > new Date(end))) { alert('Start must be before End.'); return; }

                const recipients = getCheckedRecipientIds();

                fetch(API.save, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ id: id ? Number(id) : null, title, message: msg, start_at: start, end_at: end, save_mode, recipients }),
                    credentials: 'include'
                })
                    .then(r => r.json())
                    .then(d => {
                        if (!d || d.success === false) throw new Error(d?.error || 'Save failed');
                        refreshManage();
                        composeModal && composeModal.hide();
                    })
                    .catch(e => alert(e.message || 'Save failed.'));
            }

            function checkAll(flag) {
                qsa('input[name="annRecipient"]').forEach(cb => cb.checked = !!flag);
            }

            function filterRecipients(q) {
                const term = (q || '').toLowerCase();
                document.querySelectorAll('#annRecipientsGrid > label').forEach(el => {
                    const name = el.getAttribute('data-name') || '';
                    el.style.display = name.includes(term) ? '' : 'none';
                });
            }

            // Quick group toggle
            function applyGroupSelection() {
                const ph = !!qs('#annGroupPH')?.checked;
                const us = !!qs('#annGroupUS')?.checked;

                if (!ph && !us) return; // nothing chosen -> leave manual selection as-is

                const want = new Set(
                    employees
                        .filter(e =>
                            (ph && e.accounttype === 'PH') ||
                            (us && e.accounttype === 'US'))
                        .map(e => Number(e.id))
                );

                qsa('input[name="annRecipient"]').forEach(cb => {
                    cb.checked = want.has(Number(cb.value));
                });
            }

            // Filter by text (exposed on ANN)
            function filterRecipients() {
                const term = (qs('#annRecipientsFilter')?.value || '').trim().toLowerCase();
                qsa('#annRecipientsList .list-group-item').forEach(li => {
                    const hay = (li.getAttribute('data-name') || '').toLowerCase();
                    li.style.display = hay.includes(term) ? '' : 'none';
                });
            }

            function toggleActive(id, makeActive) {
                fetch(API.toggle, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ id: Number(id), make_active: !!makeActive }),
                    credentials: 'include'
                })
                    .then(r => r.json())
                    .then(d => {
                        if (!d || d.success === false) throw new Error(d?.error || 'Toggle failed');
                        refreshManage();
                    })
                    .catch(e => alert(e.message || 'Toggle failed'));
            }

            function renderRecipients() {
                const grid = document.getElementById('annRecipientsGrid');
                if (!grid) return;

                grid.innerHTML = employees.map(e => `
    <label class="form-check d-flex align-items-center gap-2" data-name="${(e.name || e.username || '').toLowerCase()}"
           data-type="${(e.accounttype || '').toUpperCase()}">
      <input class="form-check-input ann-rec" type="checkbox" value="${e.id}">
      <span>${escapeHtml(e.name || e.username || ('#' + e.id))}${e.accounttype ? ` <small class="text-muted">(${e.accounttype})</small>` : ''}</span>
    </label>
  `).join('');
            }

            function getSelectedRecipientIds() {
                return Array.from(document.querySelectorAll('.ann-rec:checked'))
                    .map(cb => Number(cb.value));
            }

            function init() {
                ensureModals();

                // prefetch employees then render checkbox list
                fetch(API.employees)
                    .then(r => r.json())
                    .then(data => { employees = Array.isArray(data) ? data : []; renderRecipientsList(); })
                    .catch(() => { employees = []; renderRecipientsList(); });

                // wire filter input
                const f = qs('#annRecipientsFilter');
                if (f) f.addEventListener('input', filterRecipients);

                window.ANN.debouncedRefresh = debounce(refreshManage, 300);
            }

            // Expose API to HTML handlers
            window.ANN = {
                ...(window.ANN || {}),
                filterRecipients,
                applyGroupSelection,
                checkAll,
                init,
                onOpenManage,
                refreshManage,
                debouncedRefresh: () => { },
                openCompose,
                resetCompose,
                submitCompose,
                toggleActive,

                filterRecipients,
                applyGroupSelection,
                checkAll,
            };

            // auto-init once DOM is ready
            init();
        });
    </script>


    <script>
        (() => {
            const ANN_ENDPOINT = 'hr/dash/announcements';
            const ACK_ENDPOINT = 'hr/dash/announcements/acknowledge';
            const POLL_MS = 60_000; // every minute

            let lastShownId = null;
            let isFetching = false;
            let controller = null;

            function isModalOpen() {
                const el = document.getElementById('announcementModal');
                return el && el.classList.contains('show');
            }

            function renderAnnouncement(ann) {
                document.getElementById("announcementTitle").innerText = ann.title ?? 'Announcement';
                document.getElementById("announcementMessage").innerText = ann.message ?? '';
                const start = ann.start_at || '';
                const end = ann.end_at || '';
                document.getElementById("announcementDuration").innerText =
                    (start || end) ? `Duration: ${start} → ${end}` : '';
                const readbyText = (Array.isArray(ann.readby) && ann.readby.length) ? ann.readby.join(", ") : "None";
                document.getElementById("announcementReadBy").innerText = readbyText;

                window.__currentAnnouncementId = ann.id;
            }

            function showAnnouncement(ann) {
                renderAnnouncement(ann);
                lastShownId = ann.id;
                openAnnouncement(); // uses .show class
            }

            async function fetchAnnouncements() {
                if (isFetching) return [];
                isFetching = true;
                controller?.abort();
                controller = new AbortController();
                try {
                    const res = await fetch(ANN_ENDPOINT, { credentials: 'same-origin', signal: controller.signal });
                    const list = await res.json();
                    return Array.isArray(list) ? list : [];
                } catch (err) {
                    if (err.name !== 'AbortError') console.error('Error loading announcements:', err);
                    return [];
                } finally {
                    isFetching = false;
                }
            }

            async function checkAndShow() {
                if (isModalOpen()) return;   // don't interrupt the user
                if (document.hidden) return; // pause when tab not visible

                const list = await fetchAnnouncements();
                if (!list.length) return;

                const ann = list[0]; // your API already filters by time & acknowledgements
                if (ann && ann.id !== lastShownId) {
                    showAnnouncement(ann);
                }
            }

            // Expose open/close helpers (use .show class)
            window.openAnnouncement = function () {
                document.getElementById('announcementModal').classList.add('show');
            };
            window.closeAnnouncement = function () {
                document.getElementById('announcementModal').classList.remove('show');
            };

            // Expose acknowledge with success close + debounce next show
            window.acknowledgeAnnouncement = async function () {
                const annId = window.__currentAnnouncementId;
                if (!annId) return closeAnnouncement();

                try {
                    const res = await fetch(ACK_ENDPOINT, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": "{{ csrf_token() }}"
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ announcement_id: annId })
                    });
                    const resp = await res.json();
                    if (resp && resp.success) {
                        closeAnnouncement();
                        lastShownId = annId; // don't reshow the same one on the next tick
                    } else {
                        alert(resp?.message || 'Failed to acknowledge.');
                    }
                } catch {
                    alert('Network error.');
                }
            };

            // Boot + poll
            document.addEventListener("DOMContentLoaded", () => {
                // initial check
                checkAndShow();

                // avoid duplicate intervals (e.g., hot reload)
                if (window.__announcementPollHandle) clearInterval(window.__announcementPollHandle);

                // poll every minute
                window.__announcementPollHandle = setInterval(checkAndShow, POLL_MS);

                // also check when the tab becomes visible again
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) checkAndShow();
                });
            });
        })();
    </script>

    <!-- Notifications Dropdown Modal -->
    <div class="modal fade" id="notifModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="notifModalTitle">Notifications</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Expanded Table View -->
                    <div id="notifExpandedView">
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                <label for="moduleFilter" class="form-label me-2">Filter by Module:</label>
                                <select id="moduleFilter" class="form-select form-select-sm d-inline-block w-auto">
                                    <option value="">All</option>
                                </select>
                            </div>
                        </div>
                        <div id="notifExpandedTable"></div>
                    </div>

                    <!-- Single Notification View -->
                    <div id="notifDetailView" class="d-none">
                        <div id="notifDetailContent"></div>
                        <button id="backToExpanded" class="btn btn-secondary btn-sm mt-3">Back</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let notificationsData = []; // will store fetched notifications
        let currentSort = { key: null, asc: true }; // sort state

        document.addEventListener('DOMContentLoaded', function () {
            const userId = @json(Auth::id());
            const csrfToken = '{{ csrf_token() }}';

            const notifModal = document.getElementById('notifModal');
            const expandedView = document.getElementById('notifExpandedView');
            const detailView = document.getElementById('notifDetailView');
            const notifExpandedTable = document.getElementById('notifExpandedTable');
            const notifDetailContent = document.getElementById('notifDetailContent');

            const notifBadges = [
                document.getElementById('notifBadgeMobile'),
                document.getElementById('notifBadgeDesktop')
            ];

            function updateBadge() {
                fetch(`/notifications/unread-count/${userId}`)
                    .then(res => res.json())
                    .then(data => {
                        const count = data.unread_count;

                        notifBadges.forEach(badge => {
                            if (!badge) return;

                            if (count > 0) {
                                badge.textContent = count;
                                badge.style.display = 'inline-block';
                            } else {
                                badge.style.display = 'none';
                                badge.textContent = '';
                            }
                        });
                    });
            }

            function markAsRead(notifId) {
                return fetch(`/notifications/mark-read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ notif_id: notifId, user_id: userId })
                }).then(res => res.json());
            }

            function renderExpandedTable(data) {
                let html = `
            <div class="table-responsive">
              <table class="table table-bordered table-sm align-middle">
                <thead>
                  <tr>
                    <th>Module</th>
                    <th>Title</th>
                    <th>Subtitle</th>
                    <th>Content</th>
                    <th>Severity</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
        `;

                data.forEach(item => {
                    html += `
              <tr class="notif-row ${item.read_status === 'unread' ? 'fw-bold' : ''}" data-item='${JSON.stringify(item)}'>
                <td>${item.module}</td>
                <td>${item.title}</td>
                <td>${item.subtitle || ''}</td>
                <td>${item.content || ''}</td>
                <td>${item.severity}</td>
                <td>${new Date(item.notif_created_at).toLocaleString()}</td>
              </tr>`;
                });

                html += '</tbody></table></div>';
                notifExpandedTable.innerHTML = html;

                notifExpandedTable.querySelectorAll('.notif-row').forEach(row => {
                    row.addEventListener('click', () => {
                        const item = JSON.parse(row.getAttribute('data-item'));
                        markAsRead(item.notif_id).then(() => {
                            updateBadge();
                            showSingleNotification(item);
                        });
                    });
                });
            }

            function showSingleNotification(item) {
                notifDetailContent.innerHTML = `
            <table class="table table-sm">
                <tbody>
                    <tr><th>Module</th><td>${item.module}</td></tr>
                    <tr><th>Title</th><td>${item.title}</td></tr>
                    <tr><th>Subtitle</th><td>${item.subtitle || ''}</td></tr>
                    <tr><th>Content</th><td>${item.content || ''}</td></tr>
                    <tr><th>Severity</th><td>${item.severity}</td></tr>
                    <tr><th>Date</th><td>${new Date(item.notif_created_at).toLocaleString()}</td></tr>
                </tbody>
            </table>`;
                expandedView.classList.add('d-none');
                detailView.classList.remove('d-none');
            }

            document.getElementById('backToExpanded').addEventListener('click', () => {
                detailView.classList.add('d-none');
                expandedView.classList.remove('d-none');
                // Reload table to update read_status
                fetch(`/notifications/user/${userId}`)
                    .then(res => res.json())
                    .then(data => renderExpandedTable(data));
            });

            notifModal.addEventListener('shown.bs.modal', () => {
                fetch(`/notifications/user/${userId}`)
                    .then(res => res.json())
                    .then(data => renderExpandedTable(data));
            });

            notifModal.addEventListener('shown.bs.modal', () => {
                updateBadge();
                fetch(`/notifications/user/${userId}`)
                    .then(res => res.json())
                    .then(data => renderExpandedTable(data));
            });

            updateBadge();
            setInterval(updateBadge, 30000);
        });
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Dashboard loaded - initializing security measures...');

            // Check for CSRF token on page load
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

            // PREVENT BACK BUTTON ACCESS AFTER LOGOUT
            preventBackButtonAccess();

            // Initialize logout system
            initializeLogoutSystem();

            // Start session management
            startSessionManagement();
        });



        // PREVENT BACK BUTTON ACCESS - MULTIPLE METHODS
        function preventBackButtonAccess() {
            console.log('Setting up back button prevention...');

            // Method 1: History manipulation
            history.pushState(null, null, window.location.href);
            window.addEventListener('popstate', function (event) {
                console.log('Back button pressed - checking authentication...');

                // Check if user is still authenticated
                fetch('/check-auth', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => {
                        if (response.status === 401 || response.status === 419) {
                            console.log('User not authenticated - redirecting to login');
                            window.location.replace('/login');
                        } else {
                            // User is authenticated, push state again
                            history.pushState(null, null, window.location.href);
                        }
                    })
                    .catch(() => {
                        console.log('Auth check failed - redirecting to login');
                        window.location.replace('/login');
                    });
            });

            // Method 2: Page show event (handles browser cache)
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) {
                    console.log('Page loaded from cache - checking authentication...');
                    // Page was loaded from cache (back button)
                    fetch('/check-auth', {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(response => {
                            if (response.status === 401 || response.status === 419) {
                                console.log('User not authenticated - clearing cache and redirecting');
                                // Clear browser cache and redirect
                                if ('caches' in window) {
                                    caches.keys().then(names => {
                                        names.forEach(name => {
                                            caches.delete(name);
                                        });
                                    });
                                }
                                window.location.replace('/login');
                            }
                        })
                        .catch(() => {
                            window.location.replace('/login');
                        });
                }
            });

            // Method 3: Visibility change (tab switching)
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    // Page became visible again
                    console.log('Page became visible - checking authentication...');
                    setTimeout(() => {
                        fetch('/check-auth', {
                            method: 'GET',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                            .then(response => {
                                if (response.status === 401 || response.status === 419) {
                                    console.log('Session expired - redirecting to login');
                                    window.location.replace('/login');
                                }
                            })
                            .catch(() => {
                                // Network error or auth failed
                                console.log('Auth check failed on visibility change');
                            });
                    }, 1000);
                }
            });

            // Method 4: Disable browser navigation buttons via CSS (add to your CSS)
            const style = document.createElement('style');
            style.textContent = `
            /* Only disable selection for UI elements, not content */
            .sidebar, .navbar, .btn, .modal-header, .nav-link {
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;
            }
            
            /* Disable right-click context menu but keep text selection */
            body {
                -webkit-touch-callout: none;
            }
        `;
            document.head.appendChild(style);

            // Method 5: Keyboard shortcuts prevention
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
                    // Allow refresh but check auth after
                    setTimeout(checkAuthStatus, 100);
                }
            });
        }

        // LOGOUT SYSTEM
        function initializeLogoutSystem() {
            console.log('Initializing logout system...');

            // Set up confirm logout button
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

        // MAIN LOGOUT FUNCTION
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

        // ACTUAL LOGOUT EXECUTION
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

        // MODAL FUNCTIONS
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

        // SESSION MANAGEMENT
        function startSessionManagement() {
            console.log('Starting session management...');

            // Refresh token immediately on page load
            setTimeout(refreshCsrfToken, 1000);

            // Set up intervals
            setInterval(keepSessionAlive, 300000); // Every 5 minutes
            setInterval(refreshCsrfToken, 900000); // Every 15 minutes
            setInterval(checkAuthStatus, 120000); // Every 2 minutes

            console.log('Session management intervals started');
        }

        function keepSessionAlive() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken) return;

            fetch('/keep-alive', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken.getAttribute('content')
                }
            })
                .then(response => {
                    if (response.status === 419 || response.status === 401) {
                        console.log('Session expired, redirecting to login');
                        window.location.replace('/login');
                    }
                })
                .catch(error => {
                    console.log('Keep-alive failed:', error);
                });
        }

        function refreshCsrfToken() {
            console.log('Refreshing CSRF token...');

            fetch('/csrf-token', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.token) {
                        // Update meta tag
                        const metaTag = document.querySelector('meta[name="csrf-token"]');
                        if (metaTag) {
                            metaTag.setAttribute('content', data.token);
                            console.log('CSRF token refreshed successfully');
                        }

                        // Update all forms
                        document.querySelectorAll('form input[name="_token"]').forEach(input => {
                            input.value = data.token;
                        });
                    }
                })
                .catch(error => {
                    console.error('Token refresh failed:', error);
                });
        }

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

        // FORCE CACHE CLEAR ON LOGOUT
        function clearBrowserCache() {
            // Clear service worker caches
            if ('caches' in window) {
                caches.keys().then(function (names) {
                    for (let name of names) {
                        caches.delete(name);
                    }
                });
            }

            // Clear session storage
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.clear();
            }

            // Clear local storage
            if (typeof localStorage !== 'undefined') {
                localStorage.clear();
            }
        }

        // DISABLE RIGHT-CLICK CONTEXT MENU (OPTIONAL)
        document.addEventListener('contextmenu', function (e) {
            e.preventDefault();
            return false;
        });

        // GLOBAL ERROR HANDLER
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

        console.log('Complete security system loaded successfully');
    </script>

    <!-- Footer -->
    <x-footer></x-footer>

    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const burgerMenu = document.getElementById('burger-menu');
        const closeBtn = document.getElementById('close-btn');
        const navbarBrand = document.querySelector('.navbar-brand');
        const dynamicContent = document.getElementById('dynamic-content');
        const searchContainer = document.getElementById('top-search');
        const searchInput = document.getElementById('search-input');
        let showSearch = false; // Initially hide search for dashboard

        // Function to toggle sidebar visibility
        burgerMenu.addEventListener('click', () => {
            const isMobile = window.innerWidth <= 768;
            if (isMobile) {
                sidebar.classList.toggle('visible');
            } else {
                sidebar.classList.toggle('visible');
                mainContent.classList.toggle('sidebar-visible');
                navbarBrand.classList.toggle('shifted');
                burgerMenu.classList.toggle('hidden');
            }
        });

        // Hide sidebar when close button is clicked
        closeBtn.addEventListener('click', () => {
            sidebar.classList.remove('visible');
            if (window.innerWidth > 768) {
                mainContent.classList.remove('sidebar-visible');
                navbarBrand.classList.remove('shifted');
                burgerMenu.classList.remove('hidden');
            }
        });

        function initSearch(module) {
            const searchInput = document.querySelector('#top-search input');
            const dataTable = document.querySelector('.custom-table tbody'); // For table view
            const mobileView = document.querySelector('.mobile-view'); // For mobile view

            if (searchInput && (dataTable || mobileView)) {
                searchInput.addEventListener("input", function () {
                    const filter = searchInput.value.toLowerCase();

                    if (dataTable) {
                        // Handle search for table view
                        const rows = dataTable.querySelectorAll("tr");
                        rows.forEach(row => {
                            const cells = row.querySelectorAll("td");
                            let rowText = '';
                            cells.forEach(cell => {
                                rowText += cell.textContent.toLowerCase();
                            });
                            row.style.display = rowText.includes(filter) ? "" : "none";
                        });
                    }

                    if (mobileView) {
                        // Handle search for mobile view (card layout)
                        const rows = mobileView.querySelectorAll(".custom-table-row");
                        rows.forEach(row => {
                            let rowText = row.textContent.toLowerCase();
                            row.style.display = rowText.includes(filter) ? "" : "none";
                        });
                    }
                });
            }
        }
    </script>

    {{-- Scripts--}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        window.routes = {
            fetchUsers: "{{ route('user') }}",
            addUser: "{{ route('add-user') }}",
            updateUser: "{{ url('/update-user') }}",
            deleteUser: "{{ url('/delete-user') }}"
        };
    </script>

    <script src="{{ asset('js/settings-modal.js') }}"></script>
    <script src="{{ asset('js/profiles-modal.js') }}"></script>
    <script src="{{ asset('js/break-modal.js') }}"></script>
    <script src="{{ asset('js/attendance.js') }}"></script>
    <script src="{{ asset('js/account-record.js') }}"></script>
    <script src="{{ asset('js/account-privilege.js') }}"></script>
    @stack('modals')
</body>

</html>