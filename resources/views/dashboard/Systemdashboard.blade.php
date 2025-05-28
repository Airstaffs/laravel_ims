<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ session('site_title', 'IMS') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
    <style>
        .navbar {
            background-color: {{ session('theme_color', '#007bff') }};
            transition: margin-left 0.3s ease-in-out, padding-left 0.3s ease-in-out;
        }

        .sidebar-nav .nav-link.active {
            color: #fff;
            background-color: {{ session('theme_color', '#007bff') }};
            border-radius: 5px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav id="top-navbar" class="navbar navbar-expand-lg">
        <div class="navbar-container">
            <div class="left-container">
                <button id="burger-menu" class="navbar-toggler" type="button">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <a class="navbar-brand" href="#">
                    @if (session('logo'))
                        <img src="{{ asset('storage/' . session('logo')) }}" alt="Logo"
                            style="max-width: 50px; max-height: 50px;">
                    @endif
                    {{ session('site_title', 'IMS') }}
                </a>

                <!-- Icons Always Visible on Mobile -->
                <div class="d-flex align-items-center ms-auto d-lg-none">
                    <!-- Profile Icon -->
                    <a class="nav-link p-2" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                        <i class="bi bi-person"></i>
                    </a>
                    <!-- Settings Icon -->
                    <a class="nav-link p-2" href="#" data-bs-toggle="modal" data-bs-target="#settingsModal">
                        <i class="bi bi-gear"></i>
                    </a>
                    <!-- Logout Icon -->
                    <a class="nav-link p-2" href="#" onclick="event.preventDefault(); showLogoutModal();">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>

            <div id="appsearch">
                <searching @search="fetchInventory" />
            </div>

            <!-- Navbar Collapse for Desktop -->
            <div class="collapse" id="navbarNav">
                <ul class="navbar-nav text-center">
                    <!-- Profile -->
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center justify-content-center" href="#"
                            data-bs-toggle="modal" data-bs-target="#profileModal">
                            <i class="bi bi-person me-2"></i>
                            <span class="d-none d-lg-inline">Profile</span>
                        </a>
                    </li>

                    <!-- Settings -->
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center justify-content-center" href="#"
                            data-bs-toggle="modal" data-bs-target="#settingsModal">
                            <i class="bi bi-gear me-2"></i>
                            <span class="d-none d-lg-inline">Settings</span>
                        </a>
                    </li>

                    <!-- Logout -->
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center justify-content-center" href="#"
                            onclick="event.preventDefault(); showLogoutModal();">
                            <i class="bi bi-box-arrow-right me-2"></i>
                            <span class="d-none d-lg-inline">Logout</span>
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                        <form id="logout-expired-form" action="{{ route('logout.expired') }}" method="GET"
                            style="display: none;">
                        </form>

                    </li>
                </ul>
            </div>

        </div>
    </nav>

    <!-- Updated Sidebar -->
    <div id="sidebar" class="sidebar">
        <button id="close-btn" class="close-btn">&times;</button>

        <!-- User Info Section -->
        <div class="user-info">
            <img src="{{ session('profile_picture', 'default-profile.jpg') }}" alt="User Profile"
                class="rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">

            <h5>{{ session('user_name', 'User Name') }}</h5>
        </div>

        <h5 class="text-center">Navigation</h5>
     
<?php
use Illuminate\Support\Facades\Auth;

// Get fresh user data instead of relying on session
$currentUser = Auth::user();
if ($currentUser) {
    // Get fresh data from database
    $userId = $currentUser->id;
    $freshUser = \App\Models\User::find($userId);
    
    $mainModule = strtolower($freshUser->main_module ?: '');
    
    // Build sub modules array from database
    $subModules = [];
    $moduleColumns = ['order', 'unreceived', 'receiving', 'labeling', 'testing', 
                      'cleaning', 'packing', 'stockroom', 'validation', 'fnsku', 
                      'productionarea', 'returnscanner', 'fbmorder', 'notfound'];
    
    foreach ($moduleColumns as $column) {
        if ($currentUser->{$column} && $column !== $mainModule) {
            $subModules[] = $column;
        }
    }
    
    // Update session with fresh data
    session(['main_module' => $mainModule]);
    session(['sub_modules' => $subModules]);
} else {
    // Fallback to session if no user
    $mainModule = strtolower(session('main_module', ''));
    $subModules = array_map('strtolower', session('sub_modules', []));
}

// Remove main module from sub modules if it exists
$subModules = array_filter($subModules, function($module) use ($mainModule) {
    return $module !== $mainModule;
});

// Fallback for main module
$defaultModule = $mainModule ?: ($subModules ? reset($subModules) : 'dashboard');

function checkPermission($module, $mainModule, $subModules)
{
    // Convert to lowercase for comparison
    $module = strtolower($module);
    $mainModule = strtolower($mainModule);
    $subModules = array_map('strtolower', (array) $subModules);

    if ($module === 'dashboard') {
        return true;
    }
    // A module is permitted if it's the main module OR in sub modules (but not both)
    return $module === $mainModule || in_array($module, $subModules);
}

$modules = [
    'order' => 'Order',
    'unreceived' => 'Unreceived',
    'receiving' => 'Received',
    'labeling' => 'Labeling',
    'validation' => 'Validation',
    'testing' => 'Testing',
    'cleaning' => 'Cleaning',
    'packing' => 'Packing',
    'fnsku' => 'Fnsku',
    'stockroom' => 'Stockroom',
    'productionarea' => 'Production Area',
    'fbashipmentinbound' => 'FBA Inbound Shipment',
    'returnscanner' => 'Return Scanner',
    'fbmorder' => 'FBM Order',
    'notfound' => 'Not Found',
];
?>
        <script>
            // Make sure these are set properly with filtering
            window.defaultComponent = "<?= session('main_module', 'dashboard') ?>".toLowerCase();
            window.mainModule = "<?= session('main_module', 'dashboard') ?>".toLowerCase();

            // Filter out main module from allowed modules
            const rawSubModules = <?= json_encode(array_map('strtolower', session('sub_modules', []))) ?>;
            window.allowedModules = rawSubModules.filter(module => module !== window.mainModule);

            // Add this for debugging
            console.log('Session Modules:', {
                defaultComponent: window.defaultComponent,
                allowedModules: window.allowedModules,
                mainModule: window.mainModule,
                hasReturnScanner: window.allowedModules.includes('returnscanner')
            });
        </script>

        <!-- Updated Navigation structure with improved highlighting -->
        <nav class="nav flex-column sidebar-nav">
            <?php if ($mainModule): ?>
            <a class="nav-link <?= request()->segment(1) == $mainModule ? 'active' : '' ?>" href="/<?= $mainModule ?>"
                onclick="window.loadContent('<?= $mainModule ?>'); highlightNavLink(this); closeSidebar(); return false;">
                <?= $modules[$mainModule] ?? ucfirst($mainModule) ?>
            </a>
            <?php endif; ?>

            <?php foreach ($modules as $module => $label): ?>
            <?php
            // Only show in navigation if:
            // 1. User has permission for this module
            // 2. It's not the main module (avoid duplication)
            if (checkPermission($module, $mainModule, $subModules) && $module !== $mainModule):
            ?>
            <a class="nav-link <?= request()->segment(1) == $module ? 'active' : '' ?>" href="/<?= $module ?>"
                onclick="window.loadContent('<?= $module ?>'); highlightNavLink(this); closeSidebar(); return false;">
                <?= $label ?>
            </a>
            <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to highlight the current active page based on URL
            function setActiveNavLink() {
                const currentPath = window.location.pathname;
                const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');

                navLinks.forEach(link => {
                    // Remove active class from all links
                    link.classList.remove('active');

                    // Check if link href matches current path
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
        window.defaultComponent = "<?= session('main_module', 'dashboard') ?>".toLowerCase();
        window.allowedModules = <?= json_encode(array_map('strtolower', session('sub_modules', []))) ?>;
        window.mainModule = "<?= session('main_module', 'dashboard') ?>".toLowerCase();
        window.customModules = ['printcustominvoice', 'fbashipmentinbound'];
    </script>

    <div id="main-content" class="content">
        <div id="app">
            <!-- Hidden component triggers -->
            <?php foreach ($modules as $module => $label): ?>
            <a id="<?= $module ?>Link" style="display:none" href="#"
                @click.prevent="loadContent('<?= $module ?>')">
                <?= $label ?>
            </a>
            <?php endforeach; ?>

            <!-- Vue component with main module as default -->
            <component :is="currentComponent" :key="currentComponent">
            </component>
        </div>

        <div id="dynamic-content">
            @vite(['resources/js/app.js'])
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const settingsModal = document.getElementById('settingsModal');

            settingsModal.addEventListener('shown.bs.modal', function() {
                const defaultTab = document.querySelector('#design-tab');
                const defaultTabPane = document.querySelector('#design');

                // Ensure Bootstrap properly activates the tab
                if (defaultTab && defaultTabPane) {
                    new bootstrap.Tab(defaultTab).show();
                }
            });

            settingsModal.addEventListener('hidden.bs.modal', function() {
                // Reset all tabs
                document.querySelectorAll('#settingsTab .nav-link').forEach(tab => {
                    tab.classList.remove('active');
                    tab.setAttribute('aria-selected', 'false');
                });

                document.querySelectorAll('#settingsTabContent .tab-pane').forEach(tabPane => {
                    tabPane.classList.remove('show', 'active');
                });

                // Reapply the default tab using Bootstrap's method
                const defaultTab = document.querySelector('#design-tab');
                if (defaultTab) {
                    new bootstrap.Tab(defaultTab).show();
                }
            });
        });
    </script>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="settingsModalLabel">Admin Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <ul class="nav nav-tabs" id="settingsTab" role="tablist">
                        <li class="nav-item active" id="design-tab" data-bs-toggle="tab" data-bs-target="#design"
                            type="button" role="tab" aria-controls="design" aria-selected="true">
                            <i class="bi bi-palette"></i>
                            <span> Title & Design</span>
                        </li>
                        <li class="nav-item" id="user-tab" data-bs-toggle="tab" data-bs-target="#user"
                            type="button" role="tab" aria-controls="user" aria-selected="false">
                            <i class="bi bi-person-plus"></i>
                            <span> Add User</span>
                        </li>
                        <li class="nav-item" id="store-tab" data-bs-toggle="tab" data-bs-target="#store"
                            type="button" role="tab" aria-controls="store" aria-selected="false">
                            <i class="bi bi-shop"></i>
                            <span> Store List</span>
                        </li>
                        <li class="nav-item" id="privilege-tab" data-bs-toggle="tab" data-bs-target="#privilege"
                            type="button" role="tab" aria-controls="privilege" aria-selected="false">
                            <i class="bi bi-shield-lock"></i>
                            <span> Privileges</span>
                        </li>
                        <li class="nav-item" id="usertimerecord-tab" data-bs-toggle="tab"
                            data-bs-target="#usertimerecord" type="button" role="tab"
                            aria-controls="usertimerecord" aria-selected="false">
                            <i class="bi bi-clock"></i>
                            <span> Time Record</span>
                        </li>
                        <li class="nav-item" id="userlogs-tab" data-bs-toggle="tab" data-bs-target="#userlogs"
                            type="button" role="tab" aria-controls="userlogs" aria-selected="false">
                            <i class="bi bi-person-lines-fill"></i>
                            <span> User Logs</span>
                        </li>
                    </ul>

                    <!-- Combined Tab for Title & Design -->
                    <div class="tab-content" id="settingsTabContent">
                        <!-- Title & Design Tab -->
                        <div class="tab-pane fade show active" id="design" role="tabpanel"
                            aria-labelledby="design-tab">
                            <h3 class="text-center">Title & Design Settings</h3>
                            <!-- Title & Design Settings Form -->
                            <form action="{{ route('update.system.design') }}" method="POST" class="tblnDsgnForm"
                                enctype="multipart/form-data">
                                @csrf
                                @method('POST')
                                <!-- Site Title -->
                                <fieldset>
                                    <label for="siteTitle" class="form-label">Site Title</label>
                                    <input type="text" class="form-control" id="siteTitle" name="site_title"
                                        placeholder="Enter site title" value="{{ $systemDesign->site_title ?? '' }}"
                                        required>
                                </fieldset>

                                <hr class="dashed m-0">

                                <!-- Theme Color -->
                                <fieldset>
                                    <label for="themeColor" class="form-label">Theme Color</label>
                                    <input type="color" class="form-control" id="themeColor" name="theme_color"
                                        value="{{ $systemDesign->theme_color ?? '#007bff' }}" required>
                                </fieldset>

                                <hr class="dashed m-0">

                                <!-- Logo Upload -->
                                <fieldset>
                                    <label for="logoUpload" class="form-label">Upload Logo</label>
                                    <input type="file" class="form-control" id="logoUpload" name="logo">
                                    @if (!empty($systemDesign->logo))
                                        <p>Current Logo: <img src="{{ asset('storage/' . $systemDesign->logo) }}"
                                                alt="Logo" width="100"></p>
                                    @endif
                                </fieldset>
                                <button type="submit" class="btn btn-process">Save Changes</button>
                            </form>
                        </div>

                        <!-- Add User Tab -->
                        <div class="tab-pane fade" id="user" role="tabpanel" aria-labelledby="user-tab">
                            <h3 class="text-center">Add User</h3>

                            <form action="{{ route('add-user') }}" method="POST" class="addUserForm"
                                id="addUserForm">
                                @csrf
                                <!-- Username -->
                                <fieldset>
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control w-100" id="username" name="username"
                                        placeholder="Enter username" required>
                                </fieldset>

                                <!-- Password -->
                                <fieldset>
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password"
                                            placeholder="Enter password" required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password"
                                            data-target="#password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </fieldset>

                                <!-- Confirm Password -->
                                <fieldset>
                                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password_confirmation"
                                            name="password_confirmation" placeholder="Confirm password" required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password"
                                            data-target="#password_confirmation">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </fieldset>

                                <!-- User Role -->
                                <fieldset class="mb-3">
                                    <label for="userRole" class="form-label">User Role</label>
                                    <select class="form-select form-control w-100" id="userRole" name="role">
                                        <option value="SuperAdmin">Super-Admin</option>
                                        <option value="SubAdmin">Sub-Admin</option>
                                        <option value="User">User</option>
                                    </select>
                                </fieldset>

                                <div class="d-flex justify-content-between align-items-center">
                                    <button type="submit"
                                        class="btn btn-primary w-100 text-white justify-content-center fw-bold">Add
                                        User</button>
                                    <button type="button"
                                        class="btn btn-info w-100 text-white justify-content-center fw-bold"
                                        data-bs-toggle="modal" data-bs-target="#userListModal">
                                        <i class="bi bi-people me-2"></i>Show User List
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Store List Tab Content -->
                        <div class="tab-pane fade" id="store" role="tabpanel" aria-labelledby="store-tab">
                            <h3 class="text-center">Store List</h3>
                            <!-- Store List Display -->
                            <div class="storeListContainer">
                                <div id="storeListContainer">
                                    <ul id="storeList" class="list-group">
                                        <!-- New stores will be appended here dynamically -->
                                    </ul>
                                </div>
                                <!-- Add Store Button -->
                                <button class="btn btn-process" id="addStoreButton">Add Store</button>
                            </div>
                        </div>
                        <!-- Store List Tab Content END-->

                        <div class="tab-pane fade" id="privilege" role="tabpanel" aria-labelledby="privilege-tab">
                            <h5>User Privileges</h5>
                            <form id="privilegeForm">
                                @csrf
                                <!-- Select User -->
                                @php
                                    // Fetch all users directly in the Blade view
                                    $Allusers = \App\Models\User::all();
                                    // Determine which user is selected (default to admin if no user is selected)
                                    $selectedUser = request()->has('user_id')
                                        ? \App\Models\User::find(request('user_id'))
                                        : \App\Models\User::where('username', 'admin')->first();
                                @endphp

                                <label for="selectUser" class="form-label">Select User</label>
                                <select class="form-select" id="selectUser" name="user_id" required>
                                    <!-- Default option (Select User) -->

                                    @foreach ($Allusers as $userOption)
                                        <option value="{{ $userOption->id }}"
                                            {{ isset($selectedUser) && $selectedUser->id == $userOption->id ? 'selected' : '' }}>
                                            {{ $userOption->username }}
                                        </option>
                                    @endforeach
                                </select>

                                <!-- Main Module -->
                                <div id="mainModuleContainer"></div>

                                <!-- Sub-Modules Privileges -->
                                <div id="subModuleContainer"></div>

                                <!-- Stores -->
                                <div id="storeContainer"></div>

                                <button type="submit" class="btn btn-primary">Save Privileges</button>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="usertimerecord" role="tabpanel"
                            aria-labelledby="usertimerecord-tab">
                            <h5>USER TIME RECORD</h5>

                            <!-- User Selection Form -->
                            <form id="usertimerecord" class="mb-4">
                                @csrf
                                <div class="row align-items-center g-2">
                                    <!-- Changed to align-items-center and added g-2 for gap -->
                                    <div class="col-auto">
                                        <select class="form-select" id="selectUserDrop" name="user_id" required>
                                            @foreach ($Allusers as $userOption)
                                                <option value="{{ $userOption->id }}"
                                                    {{ isset($selectedUser) && $selectedUser->id == $userOption->id ? 'selected' : '' }}>
                                                    {{ $userOption->username }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-auto">
                                        <input type="date" class="form-control" id="start_date" name="start_date"
                                            placeholder="Start Date">
                                    </div>

                                    <div class="col-auto">
                                        <input type="date" class="form-control" id="end_date" name="end_date"
                                            placeholder="End Date">
                                    </div>

                                    <div class="col-auto">
                                        <button type="button" class="btn btn-primary"
                                            id="filterRecords">Filter</button>
                                    </div>
                                </div>
                            </form>

                            <!-- Time Records Table -->
                            <div class="table-responsive-notes mt-3">
                                <table class="table-notes table-striped-notes table-bordered-notes">
                                    <thead class="table-header-notes">
                                        <tr class="tr-notes">
                                            <th class="th-notes">Details</th>
                                            <th class="th-notes">Total Hours</th>
                                            <th class="th-notes">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody id="timeRecordsBody" class="tbody-notes">
                                        <!-- Data populated through JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="userlogs" role="tabpanel" aria-labelledby="userlogs-tab">
                            <h5>USER LOGS</h5>

                            <!-- User Selection Form -->
                            <form id="userlogs" class="mb-4">
                                @csrf
                                <div class="row align-items-center g-2">
                                    <!-- Changed to align-items-center and added g-2 for gap -->
                                    <div class="col-auto">
                                        <select class="form-select" id="selectUserDrop_logs" name="user_id" required>
                                            @foreach ($Allusers as $userOption)
                                                <option value="{{ $userOption->id }}"
                                                    {{ isset($selectedUser) && $selectedUser->id == $userOption->id ? 'selected' : '' }}>
                                                    {{ $userOption->username }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-auto">
                                        <input type="date" class="form-control" id="start_date_logs"
                                            name="start_date_logs" placeholder="Start Date">
                                    </div>

                                    <div class="col-auto">
                                        <input type="date" class="form-control" id="end_date_logs"
                                            name="end_date_logs" placeholder="End Date">
                                    </div>

                                    <div class="col-auto">
                                        <button type="button" class="btn btn-primary"
                                            id="filter_logs">Filter</button>
                                    </div>
                                </div>
                            </form>

                            <!-- Table -->
                            <div class="table-responsive-notes mt-3">
                                <table class="table-notes table-striped-notes table-bordered-notes">
                                    <thead class="table-header-notes">
                                        <tr class="tr-notes">
                                            <th class="th-notes">User</th>
                                            <th class="th-notes">Actions</th>
                                            <th class="th-notes">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody id="userlogsData" class="tbody-notes">
                                        <!-- Data populated through JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const selectUser = document.getElementById('selectUserDrop');
                                const startDate = document.getElementById('start_date');
                                const endDate = document.getElementById('end_date');
                                const filterButton = document.getElementById('filterRecords');
                                const isMobile = window.innerWidth < 768;

                                // Function to format date
                                function formatDate(date) {
                                    const options = {
                                        month: 'short',
                                        day: 'numeric',
                                        year: 'numeric'
                                    };
                                    return new Date(date).toLocaleDateString('en-US', options);
                                }

                                // Function to format time
                                function formatTime(date) {
                                    const options = {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        hour12: true
                                    };
                                    return new Date(date).toLocaleTimeString('en-US', options);
                                }

                                // Function to fetch and display time records
                                function fetchTimeRecords() {
                                    const userId = selectUser.value;
                                    const start = startDate.value;
                                    const end = endDate.value;

                                    fetch(`/get-time-records/${userId}?start_date=${start}&end_date=${end}`)
                                        .then(response => response.json())
                                        .then(data => {
                                            const tbody = document.getElementById('timeRecordsBody');
                                            tbody.innerHTML = '';

                                            data.forEach(record => {
                                                const timeIn = new Date(record.TimeIn);
                                                const timeOut = record.TimeOut ? new Date(record.TimeOut) : null;
                                                const totalHours = record.TimeOut ? calculateHours(timeIn, timeOut) :
                                                    'Active';
                                                const formattedDate = formatDate(timeIn);

                                                const row = `
                                <tr class="tr-notes">
                                    <td class="td-notes">
                                        ${formattedDate}<br>
                                        <small class="text-muted-notes">IN: ${formatTime(timeIn)}</small><br>
                                        <small class="text-muted-notes">OUT: ${timeOut ? formatTime(timeOut) : 'Not clocked out'}</small>
                                    </td>
                                    <td class="td-notes">${totalHours}</td>
                                    ${isMobile ?
                                        `<td class="td-notes">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            ${record.Notes ?
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                `<div class="notes-icon">
                                                    <i class="bi bi-sticky"></i>
                                                    <span class="tooltip-notes">${record.Notes}</span>
                                                </div>` :
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                '-'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            }
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        </td>` :
                                        `<td class="td-notes notes-column">${record.Notes || '-'}</td>`
                                    }
                                </tr>
                                `;
                                                tbody.innerHTML += row;
                                            });
                                        })
                                        .catch(error => {
                                            console.error('Error fetching time records:', error);
                                        });
                                }

                                // Calculate hours between two dates
                                function calculateHours(timeIn, timeOut) {
                                    const diff = timeOut - timeIn;
                                    const hours = Math.floor(diff / (1000 * 60 * 60));
                                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                                    return `${hours}h ${minutes}m`;
                                }

                                // Event listeners
                                selectUser.addEventListener('change', fetchTimeRecords);
                                filterButton.addEventListener('click', fetchTimeRecords);

                                // Initial load
                                fetchTimeRecords();
                            });
                        </script>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const selectUser = document.getElementById('selectUserDrop_logs');
                                const startDate_logs = document.getElementById('start_date_logs');
                                const endDate_logs = document.getElementById('end_date_logs');
                                const filterButton_logs = document.getElementById('filter_logs');
                                const isMobile = window.innerWidth < 768;

                                // Function to format date and time
                                function formatDateTime(dateTime) {
                                    const date = new Date(dateTime);
                                    return date.toLocaleString('en-US', {
                                        month: 'short',
                                        day: 'numeric',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        hour12: true
                                    });
                                }
                                // Function to format date only
                                function formatDate(dateTime) {
                                    const date = new Date(dateTime);
                                    const options = {
                                        month: 'short',
                                        day: 'numeric',
                                        year: 'numeric'
                                    };
                                    return date.toLocaleDateString('en-US', options);
                                }

                                // Function to fetch and display user logs
                                function fetchUserLogs() {
                                    const params = new URLSearchParams({
                                        user_id: selectUser.value,
                                        start_date_logs: startDate_logs.value, // Changed from start_date
                                        end_date_logs: endDate_logs.value
                                    });

                                    fetch(`/get-user-logs?${params}`)
                                        .then(response => response.json())
                                        .then(data => {
                                            const tbody = document.getElementById('userlogsData');
                                            tbody.innerHTML = '';

                                            data.forEach(log => {
                                                const row = `
                                    <tr class="tr-notes">
                                        <td class="td-notes">${log.username}</td>

                                        ${isMobile ?
                                            `<td class="td-notes">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                ${log.actions ?
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    `<div class="notes-icon">
                                                        <i class="bi bi-sticky"></i>
                                                        <span class="tooltip-notes">${log.actions}</span>
                                                    </div>` :
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    '-'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                }
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            </td>` :
                                            `<td class="td-notes notes-column">${log.actions || '-'}</td>`
                                        }
                                        <td class="td-notes">${formatDate(log.datetimelogs)}</td>
                                    </tr>
                                `;
                                                tbody.innerHTML += row;
                                            });

                                            if (data.length === 0) {
                                                tbody.innerHTML = `
                                    <tr class="tr-notes">
                                        <td colspan="3" class="td-notes text-center">No logs found</td>
                                    </tr>
                                `;
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Error fetching user logs:', error);
                                            document.getElementById('userlogsData').innerHTML = `
                                <tr class="tr-notes">
                                    <td colspan="3" class="td-notes text-center text-danger">Error loading logs</td>
                                </tr>
                            `;
                                        });
                                }

                                // Event listeners
                                selectUser.addEventListener('change', fetchUserLogs);
                                filterButton_logs.addEventListener('click', fetchUserLogs);

                                // Initial load
                                fetchUserLogs();
                            });
                        </script>


                    </div>
                </div>
                <!--   <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div> -->
            </div>
        </div>
    </div>

    <script>
        // Initialize when DOM is loaded
        document.addEventListener("DOMContentLoaded", function() {
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

            selectUser.addEventListener('change', function() {
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

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                try {
                    // Refresh CSRF token before submitting
                    await refreshCsrfToken();

                    const formData = collectFormData();
                    const response = await saveUserPrivileges(formData);

                    if (response.success) {
                        showNotification('Success', 'User privileges saved successfully!', 'success');

                        // Fetch updated user privileges
                        await fetchUserPrivileges(formData.user_id);

                        // Create navigation data with proper filtering
                        const mainModuleDb = response.main_module || formData.main_module.toLowerCase().replace(
                            /\s+/g, '');
                        const subModulesDb = response.sub_modules || [];

                        // Ensure sub_modules doesn't contain main_module
                        const filteredSubModules = subModulesDb.filter(module =>
                            module.toLowerCase().replace(/\s+/g, '') !== mainModuleDb
                        );

                        const navigationData = {
                            main_module: mainModuleDb,
                            sub_modules: filteredSubModules,
                            modules: {
                                'order': 'Order',
                                'unreceived': 'Unreceived',
                                'receiving': 'Received',
                                'labeling': 'Labeling',
                                'validation': 'Validation',
                                'testing': 'Testing',
                                'cleaning': 'Cleaning',
                                'packing': 'Packing',
                                'fnsku': 'FNSKU',
                                'stockroom': 'Stockroom',
                                'productionarea': 'Production Area',
                                'returnscanner': 'Return Scanner',
                                'fbmorder': 'FBM Order'
                            }
                        };

                        // Update navigation immediately
                        updateUserNavigation(navigationData);

                        // Update Vue component if available
                        if (window.appInstance) {
                            forceComponentUpdate(mainModuleDb);
                        }

                        // Close the modal
                        const modalEl = document.getElementById('settingsModal');
                        modalEl.style.display = 'none';
                        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                        document.body.classList.remove('modal-open');
                        document.body.style.removeProperty('padding-right');
                        modalEl.classList.remove('show');

                        // Re-bind modal trigger
                        const settingsButton = document.querySelector(
                            '[data-bs-toggle="modal"][data-bs-target="#settingsModal"]');
                        if (settingsButton) {
                            settingsButton.setAttribute('data-bs-toggle', 'modal');
                            settingsButton.setAttribute('data-bs-target', '#settingsModal');
                        }

                        form.classList.remove('was-validated');
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
    
    // Debug logging
    console.log('Collecting form data:', {
        main_module: mainModuleValue,
        sub_modules: subModules,
        main_module_radio: mainModuleRadio
    });

    return {
        user_id: parseInt(document.getElementById('selectUser').value, 10),
        main_module: mainModuleValue,  // This will be "Received" if that's selected
        sub_modules: subModules,       // These will be database column names like "receiving"
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
                            'order': 'Order',
                            'unreceived': 'Unreceived',
                            'receiving': 'Received',
                            'labeling': 'Labeling',
                            'validation': 'Validation',
                            'testing': 'Testing',
                            'cleaning': 'Cleaning',
                            'packing': 'Packing',
                            'fnsku': 'FNSKU',
                            'stockroom': 'Stockroom',
                            'productionarea': 'Production Area',
                            'returnscanner': 'Return Scanner',
                            'fbmorder': 'FBM Order',
                            'notfound': 'Not Found'
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
                'Not Found': 'notfound' 
            };

            const mainModules = ['Order', 'Unreceived', 'Received', 'Labeling', 'Testing', 'Cleaning', 'Packing',
                'Stockroom', 'Validation', 'FNSKU', 'Production Area', 'Return Scanner', 'FBM Order', 'Not Found'
            ];

       const mainModuleHTML = `
        <h6>Main Module</h6>
        <div class="row mb-3">
            ${mainModules.map(module => {
                // Get the database column name for comparison
                const dbColumnName = moduleMapping[module] || module.toLowerCase().replace(/\s+/g, '');
                const isChecked = data.main_module === dbColumnName ? 'checked' : '';

                return `
                    <div class="col-4 form-check mb-2 px-10">
                        <input class="form-check-input" type="radio" name="main_module"
                               value="${module}" ${isChecked} required>
                        <label class="form-check-label">${module}</label>
                    </div>
                `;
            }).join('')}
        </div>
    `;
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
                { db: 'notfound', 
                    display: 'Not Found' 
                }
            ];

          const subModulesHTML = `
        <h6>Sub-Modules</h6>
        <div class="row mb-3">
            ${subModules.map(module => `
                <div class="col-4 form-check mb-2 px-10">
                    <input class="form-check-input" type="checkbox" name="sub_modules[]"
                           value="${module.db}"
                           ${data.sub_modules && data.sub_modules[module.db] === true ? 'checked' : ''}>
                    <label class="form-check-label">${module.display}</label>
                </div>
            `).join('')}
        </div>
`;
            document.getElementById('subModuleContainer').innerHTML = subModulesHTML;
        }

        function updateStores(data) {
            const storeHTML = `
    <h6>Stores</h6>
    <div class="row mb-3">
        ${data.privileges_stores && data.privileges_stores.length > 0
            ? data.privileges_stores.map(store => `
         <div class="col-4 form-check mb-2">
            <input class="form-check-input" type="checkbox" name="privileges_stores[]"
                value="${store.store_column}" ${store.is_checked ? 'checked' : ''}>
             <label class="form-check-label">${store.store_name}</label>
              </div>
               `).join('')
            : '<p>No stores available</p>'
        }
    </div>
`;
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
                        'order': 'Order',
                        'unreceived': 'Unreceived',
                        'receiving': 'Received',
                        'labeling': 'Labeling',
                        'testing': 'Testing',
                        'cleaning': 'Cleaning',
                        'packing': 'Packing',
                        'stockroom': 'Stockroom',
                        'validation': 'Validation',
                        'fnsku': 'FNSKU',
                        'productionarea': 'Production Area',
                        'returnscanner': 'Return Scanner',
                        'fbashipmentinbound': 'FBA Inbound Shipment',
                        'fbmorder': 'FBM Order',
                        'notfound': 'Not Found' // Add this mapping
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
                'order': 'Order',
                'unreceived': 'Unreceived',
                'receiving': 'Received',
                'labeling': 'Labeling',
                'testing': 'Testing',
                'cleaning': 'Cleaning',
                'packing': 'Packing',
                'stockroom': 'Stockroom',
                'validation': 'Validation',
                'fnsku': 'FNSKU',
                'productionarea': 'Production Area',
                'returnscanner': 'Return Scanner',
                'fbashipmentinbound': 'FBA Inbound Shipment',
                'fbmorder': 'FBM Order',
                 'notfound': 'Not Found' // Add this mapping
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
        window.onload = function() {
            const selectedUserId = document.getElementById('selectUser')?.value;
            if (selectedUserId) {
                fetchUserPrivileges(selectedUserId);
            }
        };
    </script>

    <!-- Add Store Modal -->
    <div class="modal fade" id="addStoreModal" tabindex="-1" aria-labelledby="addStoreModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="addStoreForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addStoreModalLabel">Add New Store</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="newStoreName" class="form-label">Store Name</label>
                            <input type="text" class="form-control" id="newStoreName" name="storename"
                                placeholder="Enter store name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save Store</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Store Modal -->
    <div class="modal fade" id="editStoreModal" tabindex="-1" aria-labelledby="editStoreModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStoreModalLabel">Edit Store</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editStoreForm">
                        <input type='hidden' id="editStoreId">
                        <div class="mb-3">
                            <label for="editStoreName" class="form-label">Store Name</label>
                            <input type="text" class="form-control" id="editStoreName" required>
                        </div>
                        <div class="mb-3">
                            <label for="editClientID" class="form-label">Client ID</label>
                            <input type="text" class="form-control" id="editClientID">
                        </div>
                        <div class="mb-3">
                            <label for="editClientSecret" class="form-label">Client Secret</label>
                            <input type="text" class="form-control" id="editClientSecret">
                        </div>
                        <div class="mb-3">
                            <label for="editRefreshToken" class="form-label">Refresh Token</label>
                            <input type="text" class="form-control" id="editRefreshToken">
                        </div>
                        <div class="mb-3">
                            <label for="editMerchantID" class="form-label">Merchant ID</label>
                            <input type="text" class="form-control" id="editMerchantID">
                        </div>

                        <div class="mb-3">
                            <label for="editMarketplace" class="form-label">Select Marketplace</label>
                            <select class="form-select" id="selectMarketplace" multiple>
                                <!-- Options will be populated dynamically -->
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="editMarketplace" class="form-label">Marketplace</label>
                            <input type="text" class="form-control" id="editMarketplace">
                        </div>

                        <div class="mb-3">
                            <label for="editMarketplaceID" class="form-label">Marketplace ID</label>
                            <input type="text" class="form-control" id="editMarketplaceID">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- User List Modal -->
    <div class="modal fade" id="userListModal" tabindex="-1" aria-labelledby="userListModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userListModalLabel">User List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="userTableBody">
                                <!-- Users will be dynamically inserted here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel"
        aria-hidden="true">
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

                        <!-- Username -->
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>

                        <!-- Password (Optional) -->
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">New Password (leave blank to keep
                                current)</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="edit_password" name="password">
                                <button type="button" class="btn btn-outline-secondary toggle-password"
                                    data-target="#edit_password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- User Role -->
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">User Role</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="SuperAdmin">Super-Admin</option>
                                <option value="SubAdmin">Sub-Admin</option>
                                <option value="User">User</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Update User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this user?
                    <p class="text-danger" id="delete-user-name"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const profileModal = document.getElementById('profileModal');

            profileModal.addEventListener('shown.bs.modal', function() {
                const defaultTab = document.querySelector('#attendance-tab');
                const defaultTabPane = document.querySelector('#attendance');

                // Ensure Bootstrap properly activates the tab
                if (defaultTab && defaultTabPane) {
                    new bootstrap.Tab(defaultTab).show();
                }
            });

            profileModal.addEventListener('hidden.bs.modal', function() {
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

    <!-- PROFILE Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">Profile</h5>
                    <button type="button" class="btn btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <ul class="nav nav-tabs" id="profileTab" role="tablist">
                        <li class="nav-item active" id="attendance-tab" data-bs-toggle="tab"
                            data-bs-target="#attendance" type="button" role="tab" aria-controls="attendance"
                            aria-selected="true">
                            <i class="bi bi-calendar-check"></i>
                            <span>Attendance</span>
                        </li>
                        <li class="nav-item" id="userprofile-tab" data-bs-toggle="tab" data-bs-target="#userprofile"
                            type="button" role="tab" aria-controls="userprofile" aria-selected="false">
                            <i class="bi bi-person"></i>
                            <span>Account</span>
                        </li>
                        <li class="nav-item" id="timerecord-tab" data-bs-toggle="tab" data-bs-target="#timerecord"
                            type="button" role="tab" aria-controls="timerecord" aria-selected="false">
                            <i class="bi bi-clock"></i>
                            <span>Record</span>
                        </li>
                        <li class="nav-item" id="myprivileges-tab" data-bs-toggle="tab"
                            data-bs-target="#myprivileges" type="button" role="tab"
                            aria-controls="myprivileges" aria-selected="false">
                            <i class="bi bi-shield-lock"></i>
                            <span>My Privileges</span>
                        </li>
                    </ul>

                    <div class="tab-content" id="settingsTabContent">
                        <!-- Attendance Tab -->
                        <div class="tab-pane fade show active text-center" id="attendance" role="tabpanel"
                            aria-labelledby="attendance-tab">
                            <h3>Attendance / Clock-in & Clock-out</h3>

                            <!-- Time, Day, and Date Display -->
                            <div
                                class="attendance-info-container d-flex flex-column justify-content-start align-items-stretch">
                                <div class="date-container">
                                    <div id="current-time"></div>
                                    <div id="current-day"></div>
                                    <div style="display:none;" id="current-date"></div>
                                </div>

                                <!-- Clock In/Out Buttons -->
                                <div class="d-flex justify-content-center gap-3">
                                    <!-- Clock In Button -->
                                    <form action="{{ route('attendance.clockin') }}" method="POST"
                                        id="clockin-form">
                                        @csrf
                                        <button type="button"
                                            class="btn {{ !$lastRecord || ($lastRecord && $lastRecord->TimeIn && $lastRecord->TimeOut) ? 'btn-clockin' : 'btn-clockout' }}"
                                            onclick="confirmClockIn()"
                                            {{ !$lastRecord || ($lastRecord && $lastRecord->TimeIn && $lastRecord->TimeOut) ? '' : 'disabled' }}>
                                            Clock In
                                        </button>
                                    </form>

                                    <!-- Clock Out Button -->
                                    <form action="{{ route('attendance.clockout') }}" method="POST"
                                        id="clockout-form">
                                        @csrf
                                        <button type="button"
                                            class="btn {{ $lastRecord && $lastRecord->TimeIn && !$lastRecord->TimeOut ? 'btn-clockin' : 'btn-clockout' }}"
                                            onclick="confirmClockOut()"
                                            {{ $lastRecord && $lastRecord->TimeIn && !$lastRecord->TimeOut ? '' : 'disabled' }}>
                                            Clock Out
                                        </button>
                                    </form>
                                </div>

                                <!-- Computations for Today's Hours and This Week's Hours -->
                                <div class="p-3 bg-light border rounded">
                                    <p><strong>Today's Hours:</strong>
                                        <span id="today-hours">{{ $todayHoursFormatted ?? '0:00' }}
                                        </span>
                                    </p>
                                    <p><strong>This Week's Hours:</strong> <span
                                            id="week-hours">{{ $weekHoursFormatted ?? '0:00' }}</span></p>
                                </div>
                            </div>

                            <!-- Attendance Table -->
                            <table class="table table-bordered table-hover desktop">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Computed Hours</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($employeeClocksThisweek as $clockwk)
                                        <tr data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="{{ $clockwk->Notes }}">

                                            <!-- Time In -->
                                            <td>
                                                <div
                                                    class="d-flex flex-column justify-content-start align-items-center gap-2">
                                                    <span>{{ \Carbon\Carbon::parse($clockwk->TimeIn)->format('h:i A') }}</span>
                                                    <sup><b> {{ \Carbon\Carbon::parse($clockwk->TimeIn)->format('M d, Y') }}
                                                        </b></sup>
                                                </div>
                                            </td>

                                            <!-- Time Out -->
                                            <td>
                                                <div
                                                    class="d-flex flex-column justify-content-start align-items-center gap-2">
                                                    @if ($clockwk->TimeOut)
                                                        <span>{{ \Carbon\Carbon::parse($clockwk->TimeOut)->format('h:i A') }}</span>
                                                        <sup><b> {{ \Carbon\Carbon::parse($clockwk->TimeOut)->format('M d, Y') }}
                                                            </b></sup>
                                                    @else
                                                        <span class="badge badge-danger">Not yet timed out</span>
                                                    @endif
                                                </div>
                                            </td>

                                            <!-- Computed Hours -->
                                            <td>
                                                <div id="computed-hours-{{ $clockwk->ID }}"
                                                    class="d-flex flex-column justify-content-start align-items-center gap-2">
                                                    <sup><b> Not yet calculated </b></sup>
                                                </div>
                                            </td>

                                            <!-- Update Button -->
                                            <td style="display:none;">
                                                <button class="btn btn-primary update-computed-hours d-none"
                                                    data-id="{{ $clockwk->ID }}"
                                                    data-timein="{{ $clockwk->TimeIn }}"
                                                    data-timeout="{{ $clockwk->TimeOut }}">
                                                    Update
                                                </button>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center align-items-center">
                                                    <button class="btn btn-sm btn-primary m-0 text-white"
                                                        data-bs-toggle="modal" data-bs-target="#editNotesModal"
                                                        onclick="populateNotesModal('{{ $clockwk->ID }}', '{{ $clockwk->Notes }}')">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <!-- Mobile Attendance -->
                            <div class="container mobile">
                                @foreach ($employeeClocksThisweek as $index => $clockwk)
                                    <div class="mobile-card mb-3 shadow-sm {{ $index % 2 == 0 ? 'bg-light' : 'bg-white' }}"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        title="{{ $clockwk->Notes }}">
                                        <div class="card-body">
                                            <div>
                                                <h6 class="mb-0">Date :</h6>
                                                <p class="mb-0">
                                                    {{ \Carbon\Carbon::parse($clockwk->TimeIn)->format('M d, Y') }}</p>
                                            </div>
                                            <!-- Time In -->
                                            <div>
                                                <h6 class="mb-0">Time In :</h6>
                                                <p class="mb-0">
                                                    {{ \Carbon\Carbon::parse($clockwk->TimeIn)->format('h:i A') }}</p>
                                            </div>

                                            <!-- Time Out -->
                                            <div>
                                                <h6 class="mb-0">Time Out :</h6>
                                                @if ($clockwk->TimeOut)
                                                    <p class="mb-0">
                                                        {{ \Carbon\Carbon::parse($clockwk->TimeOut)->format('h:i A') }}
                                                    </p>
                                                @else
                                                    <span class="badge bg-danger">Not yet timed out</span>
                                                @endif
                                            </div>

                                            <!-- Computed Hours -->
                                            <div>
                                                <h6 class="mb-0">Computed Hours :</h6>
                                                <div id="computed-hours-{{ $clockwk->ID }}">
                                                    <small><strong>Not yet calculated</strong></small>
                                                </div>
                                            </div>

                                            <!-- Notes Edit -->
                                            <div class="notes-container">
                                                <button class="btn btn-sm btn-primary text-white"
                                                    data-bs-toggle="modal" data-bs-target="#editNotesModal"
                                                    onclick="populateNotesModal('{{ $clockwk->ID }}', '{{ $clockwk->Notes }}')">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                        </div>

                        <!-- Tab -->
                        <div class="tab-pane fade" id="userprofile" role="tabpanel"
                            aria-labelledby="userprofile-tab">
                            <h3 class="text-center">Change Password</h3>

                            <form action="{{ route('update-password') }}" method="POST" class="changePwdForm">
                                @csrf
                                <!-- Username -->
                                <!-- <fieldset>
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="myusername" name="myusername"
                                        placeholder="Enter username" value="{{ session('user_name', 'User Name') }}"
                                        required>
                                </fieldset> -->

                                <!-- Password -->
                                <fieldset>
                                    <label for="password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="newpassword" name="password"
                                            placeholder="Enter password" required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password"
                                            data-target="#password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </fieldset>

                                <hr class="dashed m-0">

                                <!-- Confirm Password -->
                                <fieldset>
                                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="newpassword_confirmation"
                                            name="password_confirmation" placeholder="Confirm password" required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password"
                                            data-target="#password_confirmation">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </fieldset>

                                <button type="submit" class="btn btn-primary btn-process text-white">Change
                                    Password</button>
                            </form>
                        </div>

                        <!--  Tab -->
                        <div class="tab-pane fade show text-center" id="timerecord" role="tabpanel"
                            aria-labelledby="timerecord-tab">

                            <!-- Date Range Filter -->
                            <form id="filter-form" class="filterForm">
                                <!-- Start Date -->
                                <div class="form-group">
                                    <label for="start-date" class="form-label visually-hidden">Start Date:</label>
                                    <input type="date" class="form-control" id="start-date" name="start_date"
                                        placeholder="Start Date">
                                </div>

                                <!-- End Date -->
                                <div class="form-group">
                                    <label for="end-date" class="form-label visually-hidden">End Date:</label>
                                    <input type="date" class="form-control" id="end-date" name="end_date"
                                        placeholder="End Date">
                                </div>

                                <!-- Filter Button -->
                                <button type="button" id="filter-button" class="btn btn-primary">Filter</button>
                            </form>

                            <!-- Computations -->
                            <strong>
                                <p>Total Hours: <span id="total-hours">0:00</span></p>
                            </strong>

                            <!-- Attendance Table -->
                            <div class="table-responsive d-none d-md-block">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Date</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Computed Hours</th>
                                        </tr>
                                    </thead>
                                    <tbody id="attendance-table-body">
                                        <!-- Default Rows Will Be Loaded Dynamically -->
                                    </tbody>
                                </table>
                            </div>

                            <!-- Mobile Card View -->
                            <div class="container d-block d-md-none" id="attendance-card-container">
                                <!-- Cards will be injected dynamically -->
                            </div>
                        </div>

                        <!-- Tab -->
                        <div class="tab-pane fade show" id="myprivileges" role="tabpanel"
                            aria-labelledby="myprivileges-tab">
                            <h5 style="font-weight: bold; color: #333;">Account Privileges</h5>
                            <div class="row">

                                <!-- First Column -->
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="order"
                                            name="order" value="1" disabled>
                                        <label class="" for="order"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Order
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="unreceived"
                                            name="unreceived" value="1" disabled>
                                        <label class="" for="unreceived"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Unreceived
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="receiving"
                                            name="receiving" value="1" disabled>
                                        <label class="" for="receiving"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Receiving
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="labeling"
                                            name="labeling" value="1" disabled>
                                        <label class="" for="labeling"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Labeling
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="fnsku"
                                            name="fnsku" value="1" disabled>
                                        <label class="" for="fnsku"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            FNSKU
                                        </label>
                                    </div>
                                </div>

                                <!-- Second Column -->
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="testing"
                                            name="testing" value="1" disabled>
                                        <label class="" for="testing"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Testing
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="cleaning"
                                            name="cleaning" value="1" disabled>
                                        <label class="" for="cleaning"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Cleaning
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="packing"
                                            name="packing" value="1" disabled>
                                        <label class="" for="packing"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Packing
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="stockroom"
                                            name="stockroom" value="1" disabled>
                                        <label class="" for="stockroom"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Stockroom
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="validation"
                                            name="validation" value="1" disabled>
                                        <label class="" for="validation"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Validation
                                        </label>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NOTES Modal -->
    <div class="modal fade" id="editNotesModal" tabindex="-1" aria-labelledby="editNotesModalLabel"
        aria-hidden="true">
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editNotesModal = document.getElementById('editNotesModal');
            const profileModal = document.getElementById('profileModal');

            // Listen for the hidden.bs.modal event on the notes modal
            editNotesModal.addEventListener('hidden.bs.modal', function() {
                // Remove any remaining backdrop
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
                // Show the profile modal again
                const profileModalInstance = new bootstrap.Modal(profileModal);
                profileModalInstance.show();
                // Ensure the attendance tab is active
                const attendanceTab = document.querySelector('#attendance-tab');
                if (attendanceTab) {
                    attendanceTab.click();
                }
            });

            // When notes modal is about to show
            editNotesModal.addEventListener('show.bs.modal', function() {
                // Hide the profile modal properly
                const profileModalInstance = bootstrap.Modal.getInstance(profileModal);
                if (profileModalInstance) {
                    profileModalInstance.hide();
                }
            });
        });

        function populateNotesModal(recordId, notes) {
            // Get modal instance
            const editNotesModal = new bootstrap.Modal(document.getElementById('editNotesModal'));

            // Set the values
            document.getElementById('recordId').value = recordId;
            document.getElementById('notes').value = notes;
        }

        function updateNotes() {
            const recordId = document.getElementById('recordId').value;
            const notes = document.getElementById('notes').value;
            const editNotesModal = bootstrap.Modal.getInstance(document.getElementById('editNotesModal'));

            // Send an AJAX request to update the Notes
            fetch(`/update-notes/${recordId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        notes: notes
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hide the notes modal first
                        editNotesModal.hide();
                        // Remove backdrop if present
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) {
                            backdrop.remove();
                        }
                        // Show success message
                        alert(data.message);
                        // Reload the page
                        location.reload();
                    } else {
                        alert('Failed to update notes.');
                    }
                })
                .catch(error => {
                    console.error('Error updating notes:', error);
                    alert('An error occurred. Please try again.');
                });
        }
    </script>

    <script>
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute(
            'content');

        // Show the add store modal and hide the settings modal
        document.getElementById('addStoreButton').addEventListener('click', function() {
            // Show the add store modal
            $('#addStoreModal').modal('show');
            $('#settingsModal').modal('hide');
        });

        // Add Store Submission
        document.getElementById('addStoreForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const storeName = document.getElementById('newStoreName').value.trim();

            // Check if store name already exists in the list
            const existingStores = Array.from(document.getElementById('storeList').getElementsByTagName('li'));
            const storeExists = existingStores.some(store => store.textContent.includes(storeName));

            if (storeExists) {
                alert('Store name already exists. Please choose a different name.');
                return; // Prevent adding the store if the name already exists
            }

            // Send the data to the Laravel backend
            axios.post('/add-store', {
                    storename: storeName
                })
                .then(response => {
                    if (response.data.success) {
                        const storeList = document.getElementById('storeList');
                        const newStoreItem = document.createElement('li');
                        newStoreItem.classList.add('list-group-item');
                        newStoreItem.innerHTML = `
                    ${response.data.store.storename}
                    <div class="d-flex justify-content-end">
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
                        storeList.appendChild(newStoreItem);

                        // Hide the add store modal
                        $('#addStoreModal').modal('hide');

                        // Ensure the modal is fully closed before opening settings modal
                        $('#addStoreModal').on('hidden.bs.modal', function() {
                            $('#settingsModal').modal('show');

                            // Ensure the store tab is active
                            $('.nav-tabs .nav-link').removeClass('active');
                            $('.tab-content .tab-pane').removeClass('active show');

                            $('#store-tab').addClass('active');
                            $('#store-tab-pane').addClass('active show');
                        });
                    } else {
                        alert('Failed to add store');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving the store.');
                });
        });




        // Fetch and display the list of stores on page load
        document.addEventListener('DOMContentLoaded', function() {
            fetchStoreList();
        });

        // Function to fetch and display store list from the server
        function fetchStoreList() {
            axios.get('/get-stores')
                .then(response => {
                    const storeList = document.getElementById('storeList');
                    storeList.innerHTML = ''; // Clear the list before populating it

                    response.data.stores.forEach(store => {
                        const listItem = document.createElement('li');
                        listItem.classList.add('list-group-item');
                        listItem.innerHTML = `
                    ${store.storename}
                    <div class="d-flex justify-content-end">
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
                .catch(error => {
                    console.error('Error fetching stores:', error);
                });
        }

        // Re-fetch store list when switching to the "Store List" tab
        $('#store-tab').on('click', function() {
            fetchStoreList(); // Re-fetch the store list when the tab is clicked
        });

        function refreshStoreList() {
            const userId = document.getElementById('selectUser').value;
            if (!userId) {
                console.warn('No user selected');
                return;
            }

            showLoadingIndicator();

            fetch(`/fetchNewlyAddedStoreCol?user_id=${userId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.stores) {
                        updateStoreList(data.stores);
                    }
                })
                .catch(error => {
                    console.error('Error fetching store list:', error);
                    showErrorMessage('Failed to load stores. Please try again.');
                })
                .finally(() => {
                    hideLoadingIndicator();
                });
        }

        function updateStoreList(stores) {
            const storeContainer = document.getElementById('storeContainer');

            // Save current checkbox states
            const currentStates = new Map();
            document.querySelectorAll('input[name="privileges_stores[]"]').forEach(input => {
                currentStates.set(input.value, input.checked);
            });

            let storeListHTML = '<h6>Stores</h6><div class="row mb-3">';

            stores.forEach(store => {
                // Check if we have a saved state, otherwise use the server state
                const isChecked = currentStates.has(store.store_column) ?
                    currentStates.get(store.store_column) :
                    store.is_checked;

                storeListHTML += `
            <div class="col-4 form-check mb-2">
                <input class="form-check-input"
                       type="checkbox"
                       name="privileges_stores[]"
                       value="${store.store_column}"
                       ${isChecked ? 'checked' : ''}>
                <label class="form-check-label">${store.store_name}</label>
            </div>`;
            });

            storeListHTML += '</div>';
            storeContainer.innerHTML = storeListHTML;
        }

        function showLoadingIndicator() {
            const container = document.getElementById('storeContainer');
            container.innerHTML += '<div class="loading-spinner">Loading stores...</div>';
        }

        function hideLoadingIndicator() {
            const spinner = document.querySelector('.loading-spinner');
            if (spinner) {
                spinner.remove();
            }
        }

        function showErrorMessage(message) {
            document.getElementById('storeContainer').innerHTML =
                `<div class="alert alert-danger">${message}</div>`;
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize privilege tab listener
            const privilegeTab = document.getElementById('privilege-tab');
            if (privilegeTab) {
                privilegeTab.addEventListener('click', function() {
                    const userId = document.getElementById('selectUser').value;
                    if (userId) {
                        refreshStoreList();
                    }
                });
            }

            // Initialize select user change listener
            const selectUser = document.getElementById('selectUser');
            if (selectUser) {
                selectUser.addEventListener('change', function() {
                    if (this.value) {
                        refreshStoreList();
                    }
                });
            }
        });
        // Delete Store functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-store-btn')) {
                const storeId = e.target.dataset.id;

                // Confirm before deleting
                if (confirm('Are you sure you want to delete this store?')) {
                    // Send the delete request to the backend
                    axios.delete(`/delete-store/${storeId}`)
                        .then(response => {
                            if (response.data.success) {
                                const storeItem = e.target.closest('li');
                                storeItem.remove();
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting store:', error);
                            alert('An error occurred while deleting the store. Please try again later.');
                        });
                }
            }
        });

        $(document).on('click', '.edit-store-btn', function() {
            const storeId = $(this).data('id');
            $('#settingsModal').modal('hide');
            // Fetch the store details using the store ID
            axios.get(`/get-store/${storeId}`)
                .then(response => {
                    const store = response.data.store;

                    // Populate the modal with the current store details
                    $('#editStoreId').val(store.store_id);
                    $('#editStoreName').val(store.storename);
                    $('#editClientID').val(store.client_id);
                    $('#editClientSecret').val(store.client_secret);
                    $('#editRefreshToken').val(store.refresh_token);
                    $('#editMerchantID').val(store.MerchantID);
                    $('#editMarketplace').val(store.Marketplace);
                    $('#editMarketplaceID').val(store.MarketplaceID);

                    // Show the modal
                    $('#editStoreModal').modal('show');
                })
                .catch(error => {
                    console.error('Error fetching store details:', error);
                    alert('An error occurred while fetching store details.');
                });
        });

        document.getElementById('editStoreForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const storeId = document.getElementById('editStoreId').value.trim();
            if (!storeId) {
                alert('Store ID is missing. Please try again.');
                return;
            }

            // Gather the updated data from the form
            const updatedStoreData = {
                store_id: storeId, // Should match the store_id column in the database
                storename: document.getElementById('editStoreName').value.trim() || null,
                client_id: document.getElementById('editClientID').value.trim() || null,
                client_secret: document.getElementById('editClientSecret').value.trim() || null,
                refresh_token: document.getElementById('editRefreshToken').value.trim() || null,
                MerchantID: document.getElementById('editMerchantID').value.trim() || null,
                Marketplace: document.getElementById('editMarketplace').value.trim() || null,
                MarketplaceID: document.getElementById('editMarketplaceID').value.trim() || null
            };

            console.log(updatedStoreData);

            // Send request to update store
            axios.post('/update-store/' + storeId, updatedStoreData, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                            'content')
                    }
                })
                .then(response => {
                    console.log(response);
                    if (response.data.success) {
                        alert('Store updated successfully');
                        fetchStoreList();
                        $('#editStoreModal').modal('hide');
                        $('#settingsModal').modal('show');
                        $('#store-tab').tab('show');
                    } else {
                        // Display the error message returned by the server
                        alert(response.data.message || 'Failed to update store');
                    }
                })
                .catch(error => {
                    console.error('Error updating store:', error);
                    alert('An error occurred while updating the store.');
                });
        });


        // Alternatively, if you're using the close button explicitly, you can handle it like this:
        document.querySelector('#editStoreModal .btn-close').addEventListener('click', function() {
            // Show the settings modal and select the store tab after closing the edit modal
            $('#settingsModal').modal('show');
            $('#store-tab').tab('show'); // This activates the store tab
        });


        function fetchMarketplaces() {
            console.log("Modal is shown, fetching marketplaces..."); // Check if the modal is opening
            axios.get('/fetch-marketplaces')
                .then(response => {
                    const marketplaceSelect = document.getElementById('selectMarketplace');
                    marketplaceSelect.innerHTML = ''; // Clear existing options

                    response.data.forEach(marketplace => {
                        const option = document.createElement('option');
                        option.value = marketplace.value; // Set the 'value' field
                        option.textContent = marketplace.name; // Display the 'name' field
                        marketplaceSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error fetching marketplaces:', error);
                });
        }

        function updateMarketplaceFields() {
            const marketplaceSelect = document.getElementById('selectMarketplace');
            const selectedOptions = Array.from(marketplaceSelect.selectedOptions);

            // Retrieve existing values from the input fields
            const currentNames = document.getElementById('editMarketplace').value.split(',').map(name => name.trim());
            const currentIDs = document.getElementById('editMarketplaceID').value.split(',').map(id => id.trim());

            // Add new values, avoiding duplicates
            selectedOptions.forEach(option => {
                if (!currentNames.includes(option.textContent)) {
                    currentNames.push(option.textContent);
                    currentIDs.push(option.value);
                }
            });

            // Update the fields with the updated values
            document.getElementById('editMarketplace').value = currentNames.filter(Boolean).join(', ');
            document.getElementById('editMarketplaceID').value = currentIDs.filter(Boolean).join(', ');
        }

        // Attach event listeners
        document.getElementById('editStoreModal').addEventListener('show.bs.modal', fetchMarketplaces);
        document.getElementById('selectMarketplace').addEventListener('change', updateMarketplaceFields);
    </script>

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
        document.addEventListener('DOMContentLoaded', function() {
            // Automatically show all toasts on page load
            const toastElList = [].slice.call(document.querySelectorAll('.toast'));
            toastElList.forEach(function(toastEl) {
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
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel"
        aria-hidden="true">
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
        document.addEventListener('DOMContentLoaded', function() {
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
                    Are you sure you want to logout?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-danger" id="confirmLogout">Yes</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const logoutSound = document.getElementById('logout-sound');
        // Show the logout confirmation modal
        function showLogoutModal() {

            if (document.getElementsByName('_token').length === 0) {
                window.location.href = '/logout';
                return;
            }
            const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            logoutModal.show();
            logoutSound.play();
        }

        // Handle the "Yes" button click in the modal
        document.getElementById('confirmLogout').addEventListener('click', function() {
            document.getElementById('logout-form').submit();
        });


        function keepSessionAlive() {
            fetch('/keep-alive', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).catch(error => console.log('Session refresh failed:', error));
        }

        // Ping every 10 minutes
        setInterval(keepSessionAlive, 600000);
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
                searchInput.addEventListener("input", function() {
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



        document.addEventListener('DOMContentLoaded', () => {
            // Function to update time, day, and date in US Pacific Time
            function updateTime() {
                const currentTimeElement = document.getElementById('current-time');
                const currentDayElement = document.getElementById('current-day');
                const currentDateElement = document.getElementById('current-date');

                if (currentTimeElement && currentDayElement && currentDateElement) {
                    // Get current date and time in US Pacific Time
                    const now = new Date();

                    // Format the time in 12-hour format with AM/PM
                    const pacificTime = new Intl.DateTimeFormat('en-US', {
                        timeZone: 'America/Los_Angeles',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true, // Enable 12-hour format
                    }).formatToParts(now);

                    // Extract time parts
                    const hours = pacificTime.find(part => part.type === 'hour').value;
                    const minutes = pacificTime.find(part => part.type === 'minute').value;
                    const seconds = pacificTime.find(part => part.type === 'second').value;
                    const period = pacificTime.find(part => part.type === 'dayPeriod').value; // AM or PM

                    const formattedTime = `${hours}:${minutes}:${seconds} ${period}`;

                    // Get day and date in Pacific Time
                    const pacificDay = new Intl.DateTimeFormat('en-US', {
                        timeZone: 'America/Los_Angeles',
                        weekday: 'long',
                    }).format(now);

                    const pacificDate = new Intl.DateTimeFormat('en-US', {
                        timeZone: 'America/Los_Angeles',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                    }).format(now);

                    // Update the elements
                    currentTimeElement.textContent = formattedTime; // Display time with AM/PM
                    currentDayElement.textContent = pacificDay + " , " + pacificDate; // Display the day
                    currentDateElement.textContent = pacificDate; // Display the date
                }
            }

            // Update the time, day, and date immediately and then every second
            updateTime();
            setInterval(updateTime, 1000);
        });
    </script>

    <script>
        const clockin_question_Sound = document.getElementById('clockin-question-sound');
        const clockout_question_Sound = document.getElementById('clockout-question-sound');

        function confirmClockIn() {
            clockin_question_Sound.play();
            if (confirm('Are you sure you want to Clock In?')) {
                document.getElementById('clockin-form').submit();
            }
        }

        function confirmClockOut() {
            clockout_question_Sound.play();
            if (confirm('Are you sure you want to Clock Out?')) {
                document.getElementById('clockout-form').submit();
            }
        }
    </script>

    <script>
        $(document).ready(function() {
            function updateComputedHours(clockId, timeIn, timeOut) {
                $.ajax({
                    url: "{{ route('update.computed.hours') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        timeIn: timeIn,
                        timeOut: timeOut,
                    },
                    success: function(response) {
                        const computedCell = $(`#computed-hours-${clockId}`);
                        computedCell.html(`${response.hours} hrs ${response.minutes} mins`);
                        if (response.message) {
                            computedCell.append(`<div class="text-muted">(${response.message})</div>`);
                        }
                    },
                    error: function(error) {
                        console.error("Error updating computed hours:", error);
                    }
                });
            }

            // Function to loop through all rows and update computed hours
            function updateAllComputedHours() {
                $('.update-computed-hours').each(function() {
                    const clockId = $(this).data('id'); // Get clock ID
                    const timeIn = $(this).data('timein'); // Get TimeIn
                    const timeOut = $(this).data('timeout'); // Get TimeOut (or null)

                    updateComputedHours(clockId, timeIn, timeOut);
                });
            }

            // Call updateAllComputedHours every 30 seconds
            setInterval(updateAllComputedHours, 30000); // 30,000 milliseconds = 30 seconds

            // Optionally, call it once when the page loads
            updateAllComputedHours();

            function updateHours() {
                // Make sure the CSRF token is set up globally first
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });

                $.ajax({
                    url: "{{ route('attendance.update.hours') }}",
                    type: "POST",
                    success: function(response) {
                        // Update Today's Hours and This Week's Hours
                        $('#today-hours').text(response.todayHours);
                        $('#week-hours').text(response.weekHours);
                    },
                    error: function(error) {
                        console.error("Error updating hours:", error);
                    }
                });
            }

            // Call updateHours every 30 seconds
            setInterval(updateHours, 30000); // 30,000 milliseconds = 30 seconds

            // Optionally, call it once when the page loads
            updateHours();
        });
    </script>

    <script>
        $(document).ready(function() {

            // Function to fetch attendance data
            function fetchAttendanceData(startDate = null, endDate = null) {
                $.ajax({
                    url: "{{ route('attendance.filter.ajax') }}", // AJAX route
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        start_date: startDate,
                        end_date: endDate
                    },
                    success: function(response) {
                        const tableBody = $('#attendance-table-body');
                        const cardContainer = $('#attendance-card-container');
                        const totalHoursSpan = $('#total-hours');
                        tableBody.empty(); // Clear the table body
                        cardContainer.empty();
                        let totalMinutes = 0;

                        if (response.employeeClocks.length > 0) {
                            response.employeeClocks.forEach(function(clock, index) {
                                const timeIn = new Date(clock.time_in);
                                const timeOut = clock.time_out ?
                                    new Date(clock.time_out) :
                                    new Date(new Date().toLocaleString('en-US', {
                                        timeZone: 'America/Los_Angeles'
                                    }));

                                const diffInMinutes = Math.round((timeOut - timeIn) / 60000);
                                totalMinutes += diffInMinutes;
                                const hours = Math.floor(diffInMinutes / 60);
                                const minutes = diffInMinutes % 60;
                                const timeInStr = timeIn.toLocaleTimeString([], {
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                                const timeOutStr = clock.time_out ?
                                    timeOut.toLocaleTimeString([], {
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    }) :
                                    '<span class="text-danger">Not yet timed out</span>';
                                const cardBg = index % 2 === 0 ? 'bg-light' : 'bg-white';

                                // Add to table (desktop)
                                tableBody.append(`
                        <tr>
                            <td><b>${timeIn.toLocaleDateString()}</b></td>
                            <td>${timeInStr}</td>
                            <td>${clock.time_out ? timeOutStr : '<span class="text-danger">Not yet timed out</span>'}</td>
                            <td>${hours} hrs ${minutes} mins</td>
                        </tr>
                    `);

                                // Add to card (mobile)
                                cardContainer.append(`
                        <div class="card mb-3 shadow-sm ${cardBg}">
                            <div class="card-body">
                                <div class="mb-2">
                                    <h6 class="mb-0">Date</h6>
                                    <p class="mb-0"><b>${timeIn.toLocaleDateString()}</b></p>
                                </div>
                                <div class="mb-2">
                                    <h6 class="mb-0">Time In</h6>
                                    <p class="mb-0">${timeInStr}</p>
                                </div>
                                <div class="mb-2">
                                    <h6 class="mb-0">Time Out</h6>
                                    ${clock.time_out ? `<p class="mb-0">${timeOutStr}</p>` : `<span class="badge bg-danger">Not yet timed out</span>`}
                                </div>
                                <div class="mb-2">
                                    <h6 class="mb-0">Computed Hours</h6>
                                    <p class="mb-0">${hours} hrs ${minutes} mins</p>
                                </div>
                            </div>
                        </div>
                    `);
                            });

                            const totalHours = Math.floor(totalMinutes / 60);
                            const totalRemainingMinutes = totalMinutes % 60;
                            totalHoursSpan.text(`${totalHours} hrs ${totalRemainingMinutes} mins`);

                        } else {
                            tableBody.append(`
                    <tr>
                        <td colspan="4" class="text-center">No records found.</td>
                    </tr>
                `);
                            cardContainer.append(`
                    <div class="alert alert-info text-center" role="alert">
                        No records found.
                    </div>
                `);
                            totalHoursSpan.text('0:00');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error fetching data:", error);
                    }
                });
            }

            // Load default 10 rows on page load
            fetchAttendanceData();

            // Fetch data on filter button click
            $('#filter-button').on('click', function() {
                const startDate = $('#start-date').val();
                const endDate = $('#end-date').val();
                fetchAttendanceData(startDate, endDate);
            });

        });

        document.addEventListener('DOMContentLoaded', function() {
            function autoClockOut() {
                const lastRecordTimeIn =
                    "{{ $verylastRecord ? $verylastRecord->TimeIn : null }}"; // Fetch TimeIn from server-side variable
                if (!lastRecordTimeIn) return; // Exit if no TimeIn is available

                // Convert TimeIn to a Date object
                const timeInDate = new Date(lastRecordTimeIn);
                const currentDate = new Date(
                    new Date().toLocaleString('en-US', {
                        timeZone: 'America/Los_Angeles'
                    })
                );

                // Check if TimeIn is not today
                const isNotToday = timeInDate.toLocaleDateString() !== currentDate.toLocaleDateString();

                // Check if TimeIn is more than 8 hours ago
                const eightHoursAgo = new Date(currentDate.getTime() - 8 * 60 * 60 *
                    1000); // Subtract 8 hours from the current time
                const isMoreThan8HoursAgo = timeInDate < eightHoursAgo;

                // Auto clock out if either condition is true
                if (isNotToday || isMoreThan8HoursAgo) {
                    console.log("Auto Clocking Out: TimeIn is not today or more than 8 hours ago.");

                    // Send the request to auto clock-out
                    fetch("{{ route('auto-clockout') }}", {
                            method: "POST",
                            headers: {
                                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute(
                                    'content'),
                                "Content-Type": "application/json",
                            },
                            body: JSON.stringify({}),
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                //console.log(data.message);
                                // Reload the page after a short delay to show updated data
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                //console.error(data.message);
                            }
                        })
                        .catch(error => {
                            //console.error("Error during auto clock-out:", error);
                        });
                }
            }

            // Call the function after 30 seconds
            setTimeout(autoClockOut, 30000);
            autoClockOut();
        });

        // Fetch privileges data when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            let lastPrivileges = null; // Store last fetched privileges

            // Function to fetch privileges and update checkboxes if there are changes
            function fetchPrivileges() {
                fetch('{{ route('myprivileges') }}')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            const privileges = data.data;

                            // Compare with last fetched data to detect changes
                            if (JSON.stringify(lastPrivileges) !== JSON.stringify(privileges)) {
                                console.log("Privileges updated, applying changes...");

                                // Update checkboxes dynamically
                                document.getElementById('order').checked = privileges.order === 1;
                                document.getElementById('unreceived').checked = privileges.unreceived === 1;
                                document.getElementById('receiving').checked = privileges.receiving === 1;
                                document.getElementById('labeling').checked = privileges.labeling === 1;
                                document.getElementById('testing').checked = privileges.testing === 1;
                                document.getElementById('cleaning').checked = privileges.cleaning === 1;
                                document.getElementById('packing').checked = privileges.packing === 1;
                                document.getElementById('stockroom').checked = privileges.stockroom === 1;
                                document.getElementById('validation').checked = privileges.validation === 1;
                                document.getElementById('fnsku').checked = privileges.fnsku === 1;

                                // Store the new data as the last fetched data
                                lastPrivileges = privileges;
                            }
                        } else {
                            console.error('Error fetching privileges:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }

            // Fetch privileges initially when page loads
            fetchPrivileges();

            // Set interval to check for updates every 5 seconds
            //setInterval(fetchPrivileges, 5000);
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userListModal = document.getElementById('userListModal');
            const settingsModal = document.getElementById('settingsModal');
            const addUserForm = document.getElementById('addUserForm');

            // Function to fetch and display users
            function fetchUsers() {
                fetch('{{ route('user') }}')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            const userTableBody = document.getElementById('userTableBody');
                            let html = '';

                            data.data.forEach(user => {
                                const createdAt = new Date(user.created_at).toLocaleString();
                                const badgeClass = user.role === 'SuperAdmin' ? 'bg-danger' :
                                    (user.role === 'SubAdmin' ? 'bg-warning' : 'bg-info');

                                html += `
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
                            </tr>
                        `;
                            });

                            userTableBody.innerHTML = html ||
                                '<tr><td colspan="4" class="text-center">No users found</td></tr>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('userTableBody').innerHTML =
                            '<tr><td colspan="4" class="text-center text-danger">Error loading users</td></tr>';
                    });
            }

            // User List Modal event handlers
            userListModal.addEventListener('hidden.bs.modal', function(event) {
                // Check if edit modal is being shown
                const editModalElement = document.getElementById('editUserModal');
                if (editModalElement.classList.contains('show')) {
                    return; // Don't do anything if edit modal is being shown
                }

                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
            });

            userListModal.addEventListener('show.bs.modal', function() {
                const settingsModalInstance = bootstrap.Modal.getInstance(settingsModal);
                if (settingsModalInstance) {
                    settingsModalInstance.hide();
                }
                fetchUsers();
            });

            // Add User Form handler
            if (addUserForm) {
                addUserForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);

                    fetch('{{ route('add-user') }}', {
                            method: 'POST',
                            body: formData,
                            // Don't manually set Content-Type when using FormData
                            // Let the browser handle it automatically
                        })
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(data => Promise.reject(data));
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                const settingsModalInstance = bootstrap.Modal.getInstance(
                                    settingsModal);
                                if (settingsModalInstance) {
                                    settingsModalInstance.hide();
                                }

                                const backdrop = document.querySelector('.modal-backdrop');
                                if (backdrop) {
                                    backdrop.remove();
                                }

                                this.reset();
                                alert('User added successfully!');

                                const userListModalInstance = new bootstrap.Modal(userListModal);
                                userListModalInstance.show();
                                fetchUsers();
                            } else {
                                throw new Error(data.message || 'Failed to add user');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            // Improve error display
                            let errorMessage = 'Error adding user. Please try again.';

                            if (error.message) {
                                errorMessage = error.message;
                            } else if (error.detailed_errors && error.detailed_errors.length > 0) {
                                errorMessage = error.detailed_errors.join('\n');
                            }

                            alert(errorMessage);
                        });
                });
            }
            // Edit User Functions
            window.editUser = function(userId, username, role) {
                // Get modal instances
                const userListModalInstance = bootstrap.Modal.getInstance(document.getElementById(
                    'userListModal'));
                const settingsModalInstance = bootstrap.Modal.getInstance(document.getElementById(
                    'settingsModal'));

                // Hide both modals
                if (userListModalInstance) {
                    userListModalInstance.hide();
                }
                if (settingsModalInstance) {
                    settingsModalInstance.hide();
                }

                setTimeout(() => {
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                }, 100);

                // Populate edit form
                document.getElementById('edit_user_id').value = userId;
                document.getElementById('edit_username').value = username;
                document.getElementById('edit_role').value = role;
                document.getElementById('edit_password').value = '';

                setTimeout(() => {
                    const editModalInstance = new bootstrap.Modal(document.getElementById(
                        'editUserModal'));
                    editModalInstance.show();
                }, 150);
            };

            // Edit form submission handler
            document.getElementById('editUserForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const userId = document.getElementById('edit_user_id').value;

                fetch(`/update-user/${userId}`, {
                        method: 'POST',
                        body: new FormData(this),
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .getAttribute('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('User updated successfully!');
                            const editModal = bootstrap.Modal.getInstance(document.getElementById(
                                'editUserModal'));
                            editModal.hide();

                            const userListModal = new bootstrap.Modal(document.getElementById(
                                'userListModal'));
                            userListModal.show();
                            fetchUsers();
                        } else {
                            alert(data.message || 'Error updating user');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating user');
                    });
            });

            // Edit modal hidden event handler
            document.getElementById('editUserModal').addEventListener('hidden.bs.modal', function(event) {
                event.stopPropagation();

                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }

                setTimeout(() => {
                    const userListModalInstance = new bootstrap.Modal(document.getElementById(
                        'userListModal'));
                    userListModalInstance.show();
                }, 100);
            });

            // Delete User Functions
            let deleteUserId = null;

            window.showDeleteConfirmation = function(userId, username) {
                deleteUserId = userId;
                document.getElementById('delete-user-name').textContent = username;
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
                deleteModal.show();
            };

            document.getElementById('confirmDelete').addEventListener('click', function() {
                if (deleteUserId) {
                    fetch(`/delete-user/${deleteUserId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    .getAttribute('content')
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('User deleted successfully!');
                                const deleteModal = bootstrap.Modal.getInstance(document.getElementById(
                                    'deleteUserModal'));
                                deleteModal.hide();
                                fetchUsers();
                            } else {
                                alert(data.message || 'Error deleting user');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error deleting user');
                        });
                }
            });
        });
    </script>

</body>

</html>
