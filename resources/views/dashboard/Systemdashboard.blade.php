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

                $moduleColumns = ['order', 'unreceived', 'receiving', 'labeling', 'testing', 'cleaning', 'packing', 'stockroom', 'validation', 'fnsku', 'productionarea', 'returnscanner', 'fbmorder', 'notfound', 'asinoption', 'houseage', 'asinlist','printer'];

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
                'fbashipmentinbound' => 'FBA Inbound Shipment',
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
        window.customModules = ['printcustominvoice', 'fbashipmentinbound', 'mskucreation'];
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
    @include('dashboard.modals.settings.settings-modal')

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

    <!-- PROFILE Modal -->
    <div class="modal profile fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">Profile</h5>
                    <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                        <li class="nav-item" id="myprivileges-tab" data-bs-toggle="tab" data-bs-target="#myprivileges"
                            type="button" role="tab" aria-controls="myprivileges" aria-selected="false">
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
                                <!-- Clock In/Out Buttons -->
                                <div class="d-flex justify-content-center gap-3">
                                    <!-- Clock In Button -->
                                    <button type="button"
                                        class="btn {{ !$lastRecord || ($lastRecord && $lastRecord->TimeIn && $lastRecord->TimeOut) ? 'btn-clockin' : 'btn-clockout' }}"
                                        onclick="confirmClockIn()" data-route="{{ route('attendance.clockin') }}"
                                        id="clockin-button" {{ !$lastRecord || ($lastRecord && $lastRecord->TimeIn && $lastRecord->TimeOut) ? '' : 'disabled' }}>
                                        Clock In
                                    </button>

                                    <!-- Clock Out Button -->
                                    <button type="button"
                                        class="btn {{ $lastRecord && $lastRecord->TimeIn && !$lastRecord->TimeOut ? 'btn-clockin' : 'btn-clockout' }}"
                                        onclick="confirmClockOut()" data-route="{{ route('attendance.clockout') }}"
                                        id="clockout-button" {{ $lastRecord && $lastRecord->TimeIn && !$lastRecord->TimeOut ? '' : 'disabled' }}>
                                        Clock Out
                                    </button>
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
                            <div class="attendance-table">
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
                                                        data-id="{{ $clockwk->ID }}" data-timein="{{ $clockwk->TimeIn }}"
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
                            </div>

                            <!-- Mobile Attendance -->
                            <div class="container mobile">
                                @foreach ($employeeClocksThisweek as $index => $clockwk)
                                    <div class="mobile-card mb-3 shadow-sm {{ $index % 2 == 0 ? 'bg-light' : 'bg-white' }}"
                                        data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $clockwk->Notes }}">
                                        <div class="card-body">
                                            <div>
                                                <h6 class="mb-0">Date :</h6>
                                                <p class="mb-0">
                                                    {{ \Carbon\Carbon::parse($clockwk->TimeIn)->format('M d, Y') }}
                                                </p>
                                            </div>
                                            <!-- Time In -->
                                            <div>
                                                <h6 class="mb-0">Time In :</h6>
                                                <p class="mb-0">
                                                    {{ \Carbon\Carbon::parse($clockwk->TimeIn)->format('h:i A') }}
                                                </p>
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
                                                <button class="btn btn-sm btn-primary text-white" data-bs-toggle="modal"
                                                    data-bs-target="#editNotesModal"
                                                    onclick="populateNotesModal('{{ $clockwk->ID }}', '{{ $clockwk->Notes }}')">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                        </div>

                        <!-- Account Tab -->
                        <div class="tab-pane fade" id="userprofile" role="tabpanel" aria-labelledby="userprofile-tab">
                            <ul class="nav list-unstyled" id="accountTab" role="tablist">
                                <li role="presentation">
                                    <button class="btn btn-account active" id="changepass-tab" data-bs-toggle="tab"
                                        data-bs-target="#changepass" type="button" role="tab" aria-controls="changepass"
                                        aria-selected="true">
                                        Change Password
                                    </button>
                                </li>
                                <li role="presentation">
                                    <button class="btn btn-account" id="timezone-tab" data-bs-toggle="tab"
                                        data-bs-target="#timezone" type="button" role="tab" aria-controls="timezone"
                                        aria-selected="false">
                                        Timezone Settings
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="accountTabContent">
                                <div class="tab-pane fade show active" id="changepass" role="tabpanel"
                                    aria-labelledby="changepass-tab">
                                    <form action="{{ route('update-password') }}" method="POST" class="changePwdForm">
                                        @csrf
                                        <fieldset>
                                            <label for="password" class="form-label">New Password</label>
                                            <div class="has-toggle">
                                                <input type="password" class="form-control" id="newpassword"
                                                    name="password" placeholder="Enter password" required>
                                                <i role="button" class="bi bi-eye toggle-password"
                                                    id="toggleNewPassword" data-target="#password"></i>
                                            </div>
                                        </fieldset>

                                        <hr class="dashed m-0">

                                        <fieldset>
                                            <label for="password_confirmation" class="form-label">Confirm
                                                Password</label>
                                            <div class="has-toggle">
                                                <input type="password" class="form-control" id="confirmpassword"
                                                    name="password_confirmation" placeholder="Confirm password"
                                                    required>
                                                <i role="button" class="bi bi-eye toggle-password"
                                                    id="toggleConfirmPassword" data-target="#password"></i>
                                            </div>
                                        </fieldset>

                                        <button type="submit" class="btn btn-primary btn-process text-white">Change
                                            Password</button>
                                    </form>
                                </div>

                                <div class="tab-pane fade" id="timezone" role="tabpanel" aria-labelledby="timezone-tab">
                                    <form id="timezoneForm" class="timezoneForm">
                                        @csrf
                                        @php
                                            $allTimezones = collect(timezone_identifiers_list())
                                                ->map(function ($tz) {
                                                    $dt = new DateTime('now', new DateTimeZone($tz));
                                                    $offset = $dt->getOffset();
                                                    $hours = intdiv($offset, 3600);
                                                    $minutes = abs($offset % 3600) / 60;
                                                    $sign = $offset >= 0 ? '+' : '-';
                                                    $formattedOffset = sprintf("UTC %s%02d:%02d", $sign, abs($hours), $minutes);
                                                    return [
                                                        'tz' => $tz,
                                                        'offset' => $offset,
                                                        'label' => "($formattedOffset) $tz"
                                                    ];
                                                });

                                            $grouped = $allTimezones->sortBy('offset')->groupBy('offset');

                                            $limitedTimezones = $grouped->map(function ($group) {
                                                return $group->take(2);
                                            })->flatten(1);

                                            if (!$limitedTimezones->pluck('tz')->contains('America/Los_Angeles')) {
                                                $la = $allTimezones->firstWhere('tz', 'America/Los_Angeles');
                                                $limitedTimezones->push($la);
                                            }

                                            $timezones = $limitedTimezones->sortBy('offset');
                                        @endphp

                                        <!-- Timezone Dropdown -->
                                        <fieldset>
                                            <label for="usertimezone">Preferred Timezone</label>
                                            <select class="form-select" id="usertimezone" name="usertimezone" required>
                                                @foreach($timezones as $tz)
                                                    <option value="{{ $tz['tz'] }}" {{ ($timezone_setting['usertimezone'] ?? 'UTC') === $tz['tz'] ? 'selected' : '' }}>
                                                        {{ $tz['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            <div class="has-checkbox">
                                                <input class="form-check-input" type="checkbox" id="auto_sync"
                                                    name="auto_sync" {{ $timezone_setting['auto_sync'] ?? false ? 'checked' : '' }}>
                                                <label class="form-check-label" for="auto_sync">
                                                    Automatically Sync Timezone
                                                </label>
                                            </div>
                                        </fieldset>

                                        <button type="submit" class="btn btn-process">Update Timezone</button>
                                    </form>

                                    <!-- Flash success box -->
                                    <div id="timezoneSuccessBox"
                                        class="alert alert-success alert-dismissible fade show mt-3 d-none"
                                        role="alert">
                                        <span id="timezoneSuccessMsg"></span>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"
                                            aria-label="Close"></button>
                                    </div>

                                    <!-- JavaScript to control timezone logic -->
                                    <script>
                                        document.addEventListener("DOMContentLoaded", function () {
                                            const autoSyncCheckbox = document.getElementById('auto_sync');
                                            const timezoneSelect = document.getElementById('usertimezone');

                                            const setSelectState = () => {
                                                if (autoSyncCheckbox.checked) {
                                                    timezoneSelect.disabled = true;
                                                    timezoneSelect.value = 'America/Los_Angeles';
                                                } else {
                                                    timezoneSelect.disabled = false;
                                                }
                                            };

                                            // Initial check on load
                                            setSelectState();

                                            // Event listener for toggle
                                            autoSyncCheckbox.addEventListener('change', setSelectState);
                                        });
                                    </script>

                                </div>
                            </div>
                        </div>

                        <!-- Record Tab -->
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

                        <!-- Privileges Tab -->
                        <div class="tab-pane fade show" id="myprivileges" role="tabpanel"
                            aria-labelledby="myprivileges-tab">
                            <h5 style="font-weight: bold; color: #333;">Account Privileges</h5>
                            <div class="row">

                                <!-- First Column -->
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="order" name="order"
                                            value="1" disabled>
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
                                        <input class="form-check-input" type="checkbox" id="receiving" name="receiving"
                                            value="1" disabled>
                                        <label class="" for="receiving"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Receiving
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="labeling" name="labeling"
                                            value="1" disabled>
                                        <label class="" for="labeling"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Labeling
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="fnsku" name="fnsku"
                                            value="1" disabled>
                                        <label class="" for="fnsku"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            FNSKU
                                        </label>
                                    </div>
                                </div>

                                <!-- Second Column -->
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="testing" name="testing"
                                            value="1" disabled>
                                        <label class="" for="testing"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Testing
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="cleaning" name="cleaning"
                                            value="1" disabled>
                                        <label class="" for="cleaning"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Cleaning
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="packing" name="packing"
                                            value="1" disabled>
                                        <label class="" for="packing"
                                            style="font-size: 16px; font-weight: 500; color: #000;">
                                            Packing
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="stockroom" name="stockroom"
                                            value="1" disabled>
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

    <script>
        function setupPasswordToggle(toggleId, inputId) {
            const toggleElement = document.getElementById(toggleId);
            const inputElement = document.getElementById(inputId);

            if (!toggleElement || !inputElement) return;

            toggleElement.addEventListener('click', () => {
                const isPasswordVisible = inputElement.type === 'text';
                inputElement.type = isPasswordVisible ? 'password' : 'text';

                toggleElement.classList.toggle('bi-eye', isPasswordVisible);
                toggleElement.classList.toggle('bi-eye-slash', !isPasswordVisible);
            });
        }

        // Initialize toggles
        setupPasswordToggle('toggleNewPassword', 'newpassword');
        setupPasswordToggle('toggleConfirmPassword', 'confirmpassword');
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const editNotesModal = document.getElementById('editNotesModal');
            const profileModal = document.getElementById('profileModal');

            // Listen for the hidden.bs.modal event on the notes modal
            editNotesModal.addEventListener('hidden.bs.modal', function () {
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
            editNotesModal.addEventListener('show.bs.modal', function () {
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
        document.addEventListener('DOMContentLoaded', function () {
            const clockinSound = document.getElementById('clockin-question-sound');
            const clockoutSound = document.getElementById('clockout-question-sound');

            function sendAjaxClock(route, successCallback) {
                fetch(route, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            if (typeof successCallback === 'function') successCallback();
                        } else {
                            alert(data.message || 'Something went wrong.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Server error. Please try again.');
                    });
            }

            window.confirmClockIn = function () {
                clockinSound.play();
                if (confirm('Are you sure you want to Clock In?')) {
                    const route = document.getElementById('clockin-button').getAttribute('data-route');
                    sendAjaxClock(route, () => location.reload());
                }
            }

            window.confirmClockOut = function () {
                clockoutSound.play();
                if (confirm('Are you sure you want to Clock Out?')) {
                    const route = document.getElementById('clockout-button').getAttribute('data-route');
                    sendAjaxClock(route, () => location.reload());
                }
            }
        });
    </script>

    <script>
        $(document).ready(function () {
            function updateComputedHours(clockId, timeIn, timeOut) {
                $.ajax({
                    url: "{{ route('update.computed.hours') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        timeIn: timeIn,
                        timeOut: timeOut,
                    },
                    success: function (response) {
                        const computedCell = $(`#computed-hours-${clockId}`);
                        computedCell.html(`${response.hours} hrs ${response.minutes} mins`);
                        if (response.message) {
                            computedCell.append(`<div class="text-muted">(${response.message})</div>`);
                        }
                    },
                    error: function (error) {
                        console.error("Error updating computed hours:", error);
                    }
                });
            }

            // Function to loop through all rows and update computed hours
            function updateAllComputedHours() {
                $('.update-computed-hours').each(function () {
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
                    success: function (response) {
                        // Update Today's Hours and This Week's Hours
                        $('#today-hours').text(response.todayHours);
                        $('#week-hours').text(response.weekHours);
                    },
                    error: function (error) {
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
        $(document).ready(function () {

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
                    success: function (response) {
                        const tableBody = $('#attendance-table-body');
                        const cardContainer = $('#attendance-card-container');
                        const totalHoursSpan = $('#total-hours');
                        tableBody.empty(); // Clear the table body
                        cardContainer.empty();
                        let totalMinutes = 0;

                        if (response.employeeClocks.length > 0) {
                            response.employeeClocks.forEach(function (clock, index) {
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
                    error: function (xhr, status, error) {
                        console.error("Error fetching data:", error);
                    }
                });
            }

            // Load default 10 rows on page load
            fetchAttendanceData();

            // Fetch data on filter button click
            $('#filter-button').on('click', function () {
                const startDate = $('#start-date').val();
                const endDate = $('#end-date').val();
                fetchAttendanceData(startDate, endDate);
            });

        });

        document.addEventListener('DOMContentLoaded', function () {
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
        document.addEventListener('DOMContentLoaded', function () {
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
        document.addEventListener('DOMContentLoaded', function () {
            const checkbox = document.getElementById('auto_sync');
            const tzSelect = document.getElementById('usertimezone');

            function toggleSelect() {
                tzSelect.disabled = checkbox.checked;
            }

            checkbox.addEventListener('change', toggleSelect);
            toggleSelect();
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('timezoneForm');
            const successBox = document.getElementById('timezoneSuccessBox');
            const successMsg = document.getElementById('timezoneSuccessMsg');

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(form);

                fetch("{{ route('update-timezone') }}", {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
                    },
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            successMsg.textContent = data.message;
                            successBox.classList.remove('d-none');
                        } else {
                            alert('Update failed.');
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                        alert('Something went wrong.');
                    });
            });
        });
    </script>

    {{-- Scripts--}}
    <script>
        window.routes = {
            fetchUsers: "{{ route('user') }}",
            addUser: "{{ route('add-user') }}",
            updateUser: "{{ url('/update-user') }}",
            deleteUser: "{{ url('/delete-user') }}"
        };
    </script>

    <script src="{{ asset('js/settings-modal.js') }}"></script>
</body>
</html>