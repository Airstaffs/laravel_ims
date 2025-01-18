<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ session('site_title', 'IMS') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
        }

        .navbar {
            background-color: {{ session('theme_color', '#007bff') }};
            transition: margin-left 0.3s ease-in-out, padding-left 0.3s ease-in-out;
        }

        .navbar-brand, .nav-link {
            color: #fff !important;
        }

  

        .sidebar {
            height: 100vh;
            background: #343a40;
            padding: 1rem;
            color: #fff;
            position: fixed;
            top: 0;
            left: -240px; /* Start with sidebar hidden */
            width: 240px;
            transition: left 0.3s ease-in-out;
            z-index: 1050;
        }

        .sidebar.visible {
            left: 0; /* Show the sidebar */
        }

        .sidebar .nav-link {
            color: #adb5bd;
            position: relative;
            z-index: 1; /* Ensure it's clickable */
        }

        .sidebar .nav-link.active {
            color: #fff;
            background-color: #495057;
            border-radius: 5px;
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #fff;
            cursor: pointer;
        }

        .content {
            margin-left: 0;
            padding: 2rem;
            transition: margin-left 0.3s ease-in-out;
        }

        .content.sidebar-visible {
            margin-left: 240px;
        }

        #burger-menu {
            display: block;
        }

        #burger-menu.hidden {
            display: none; /* Hide burger menu when sidebar is visible */
        }

        .navbar-brand.shifted {
            margin-left: 240px; /* Shift logo/name when sidebar is visible */
            transition: margin-left 0.3s ease-in-out;
        }

        footer {
            text-align: center;
            margin-top: 2rem;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                left: -100%; /* Fully hide sidebar in mobile view */
            }

            .sidebar.visible {
                left: 0; /* Show the sidebar in full screen */
            }

            .content {
                margin-left: 0 !important; /* Prevent shifting of content */
            }

            .navbar-brand.shifted {
                margin-left: 0 !important; /* Reset navbar logo position */
            }

            #burger-menu {
                display: block; /* Ensure burger menu is always visible */
            }
        }

        #top-search {
            display: none;  /* Hide by default */
            align-items: center;
            width: 100%;
            max-width: 600px; /* Adjust max width as needed */
            margin: 0 auto;
        }

        #top-search.show {
            display: flex;  /* Only display when the 'show' class is added */
        }

        #search-input {
            width: 100%;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
            /* Ensure tab text is visible */
    .nav-tabs .nav-link {
        color: black !important; /* Set text color to black */
        font-weight: bold;      /* Make it stand out */
    }
    .nav-tabs .nav-link.active {
        color: white !important; /* Set text color to white for the active tab */
        background-color: #007bff !important; /* Blue background for active tab */
    }

          #storeList .list-group-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    margin-bottom: 5px;
    border-radius: 5px;
    background-color: #f8f9fa;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

#storeList .list-group-item:hover {
    background-color: #e9ecef;
}

#storeList .edit-store-btn {
    background-color: #007bff;
    border: none;
    color: white;
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
}

#storeList .edit-store-btn:hover {
    background-color: #0056b3;
}          

.d-flex button {
    margin-left: 5px; /* Adjust spacing between buttons */
}
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav id="top-navbar" class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <button id="burger-menu" class="navbar-toggler" type="button">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand" href="#">
            @if(session('logo'))
                <img src="{{ asset('storage/' . session('logo')) }}" alt="Logo" style="max-width: 50px; max-height: 50px;">
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

        <div class="search-bar d-flex align-items-center mx-auto" id="top-search">
            <input 
                type="text" 
                class="form-control" 
                placeholder="Search..." 
                aria-label="Search"
                id="search-input"
            />
        </div>

            <!-- Navbar Collapse for Desktop -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto text-center">
                    <!-- Profile -->
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center justify-content-center" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                            <i class="bi bi-person me-2"></i>
                            <span class="d-none d-lg-inline">Profile</span>
                        </a>
                    </li>

                    <!-- Settings -->
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center justify-content-center" href="#" data-bs-toggle="modal" data-bs-target="#settingsModal">
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
                    </li>
                </ul>
            </div>

        </div>
    </nav>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar">
        <button id="close-btn" class="close-btn">&times;</button>

           <!-- User Info Section -->
           <div class="user-info">
    <!-- Display user's profile picture -->
    <img 
        src="{{ session('profile_picture', 'default-profile.jpg') }}" 
        alt="User Profile" 
        class="rounded-circle mb-2" 
        style="width: 80px; height: 80px; object-fit: cover;">
    
    <!-- Display user's name -->
    <h5>{{ session('user_name', 'User Name') }}</h5>
</div>

        <h5 class="text-center">Navigation</h5>
    
        <nav class="nav flex-column">
            <a class="nav-link active" href="#" id="dashboard" onclick="loadContent('dashboard', 'dashboard')">System Clock</a>
            <a class="nav-link" href="#" onclick="loadContent('Order', 'Order')">Orders</a>
            <a class="nav-link" href="#" onclick="loadContent('Unreceived', 'Unreceived')">Unreceived</a>
            <a class="nav-link" href="#" onclick="loadContent('Receiving', 'Receiving')">Received</a>
            <a class="nav-link" href="#" onclick="loadContent('Labeling', 'Labeling')">Labeling</a>
            <a class="nav-link" href="#" onclick="loadContent('Validation', 'Validation')">Validation</a>
            <a class="nav-link" href="#" onclick="loadContent('Testing', 'Testing')">Testing</a>
            <a class="nav-link" href="#" onclick="loadContent('Cleaning', 'Cleaning')">Cleaning</a>
            <a class="nav-link" href="#" onclick="loadContent('Packing', 'Packing')">Packing</a>
            <a class="nav-link" href="#" onclick="loadContent('Stockroom', 'Stockroom')">Stockroom</a>
        </nav>


       
    </div>

    <!-- Content -->
    <div id="main-content" class="content">
        <div id="dynamic-content">
            <h3>Select a module from the sidebar</h3>
        </div>
    </div>

 <!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="settingsModalLabel">Admin Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="settingsTab" role="tablist">
                    <!-- Combined Tab for Title & Design -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="design-tab" data-bs-toggle="tab" data-bs-target="#design" type="button" role="tab" aria-controls="design" aria-selected="true">Title & Design</button>
                    </li>
                    <!-- Add User Tab -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="user-tab" data-bs-toggle="tab" data-bs-target="#user" type="button" role="tab" aria-controls="user" aria-selected="false">Add User</button>
                    </li>
               
                    <!-- Add Store List Tab -->
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="store-tab" data-bs-toggle="tab" data-bs-target="#store" type="button" role="tab" aria-controls="store" aria-selected="false">Store List</button>
                </li>
                    
                </ul>
         <!-- Combined Tab for Title & Design -->
                <div class="tab-content mt-3" id="settingsTabContent">
                    <!-- Title & Design Tab -->
                    <div class="tab-pane fade show active" id="design" role="tabpanel" aria-labelledby="design-tab">
                        <h5>Title & Design Settings</h5>
                       <!-- Title & Design Settings Form -->
                            <form action="{{ route('update.system.design') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                @method('POST')
                                <!-- Site Title -->
                                <div class="mb-3">
                                    <label for="siteTitle" class="form-label">Site Title</label>
                                    <input type="text" class="form-control" id="siteTitle" name="site_title" placeholder="Enter site title" value="{{ $systemDesign->site_title ?? '' }}" required>
                                </div>
                                <!-- Theme Color -->
                                <div class="mb-3">
                                    <label for="themeColor" class="form-label">Theme Color</label>
                                    <input type="color" class="form-control" id="themeColor" name="theme_color" value="{{ $systemDesign->theme_color ?? '#007bff' }}" required>
                                </div>
                                <!-- Logo Upload -->
                                <div class="mb-3">
                                    <label for="logoUpload" class="form-label">Upload Logo</label>
                                    <input type="file" class="form-control" id="logoUpload" name="logo">
                                    @if (!empty($systemDesign->logo))
                                        <p>Current Logo: <img src="{{ asset('storage/' . $systemDesign->logo) }}" alt="Logo" width="100"></p>
                                    @endif
                                </div>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                    </div>

                    <!-- Add User Tab -->
                    <div class="tab-pane fade" id="user" role="tabpanel" aria-labelledby="user-tab">
                    <h5>Add User</h5>
                    <form action="{{ route('add-user') }}" method="POST">
                        @csrf
                        <!-- Username -->
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Enter username" required>
                        </div>
                        
                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Confirm password" required>
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#password_confirmation">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- User Role -->
                        <div class="mb-3">
                            <label for="userRole" class="form-label">User Role</label>
                            <select class="form-select" id="userRole" name="role">
                                <option value="SuperAdmin">Super-Admin</option>
                                <option value="SubAdmin">Sub-Admin</option>
                                <option value="User">User</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </form>
                </div>
         
                <!-- Store List Tab Content -->
                <div class="tab-pane fade" id="store" role="tabpanel" aria-labelledby="store-tab">
                    <h5>Store List</h5>
                    <!-- Store List Display -->
                    <div id="storeListContainer">
                    <ul id="storeList" class="list-group">
                    <!-- New stores will be appended here dynamically -->
                </ul>

                    </div>
                    <!-- Add Store Button -->
                    <button class="btn btn-primary" id="addStoreButton">Add Store</button>
                </div>
            <!-- Store List Tab Content END-->   

                </div>
          </div>
          <!--   <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div> -->
        </div>
    </div>
</div>


<!-- Add Store Modal -->

<div class="modal fade" id="addStoreModal" tabindex="-1" aria-labelledby="addStoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addStoreForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStoreModalLabel">Add New Store</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="newStoreName" class="form-label">Store Name</label>
                        <input type="text" class="form-control" id="newStoreName" name="storename" placeholder="Enter store name" required>
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
<div class="modal fade" id="editStoreModal" tabindex="-1" aria-labelledby="editStoreModalLabel" aria-hidden="true">
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


<!-- PROFILE Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">PROFILE</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="settingsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab" aria-controls="attendance" aria-selected="true">Attendance</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="userprofile-tab" data-bs-toggle="tab" data-bs-target="#userprofile" type="button" role="tab" aria-controls="userprofile" aria-selected="false">User</button>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="settingsTabContent">
                    <!-- Attendance Tab -->
                    <div class="tab-pane fade show active text-center" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
                        <h5>Attendance / Clock-in & Clock-out</h5>

                        <!-- Time, Day, and Date Display -->
                        <div class="mb-3">
                            <div id="current-time" style="font-size: 3rem; font-weight: bold;"></div>
                            <div id="current-day" style="font-size: 1.5rem; margin-top: 10px;"></div>
                            <div style="display:none;" id="current-date" style="font-size: 1.2rem; margin-top: 5px; color: #6c757d;"></div>
                        </div>

                        <!-- Clock In/Out Buttons -->
                        <div class="d-flex justify-content-center gap-3 mt-3">
                            <!-- Clock In Button -->
                            <form action="{{ route('attendance.clockin') }}" method="POST" id="clockin-form">
                                @csrf
                                <button type="button" 
                                        class="btn {{ !$lastRecord || ($lastRecord && $lastRecord->TimeIn && $lastRecord->TimeOut) ? 'btn-primary' : 'btn-secondary' }} px-5 py-3 fs-5" 
                                        style="min-width: 15%;"
                                        onclick="confirmClockIn()" 
                                        {{ !$lastRecord || ($lastRecord && $lastRecord->TimeIn && $lastRecord->TimeOut) ? '' : 'disabled' }}>
                                    Clock In
                                </button>
                            </form>

                            <!-- Clock Out Button -->
                            <form action="{{ route('attendance.clockout') }}" method="POST" id="clockout-form">
                                @csrf
                                <button type="button" 
                                        class="btn {{ $lastRecord && $lastRecord->TimeIn && !$lastRecord->TimeOut ? 'btn-primary' : 'btn-secondary' }} px-5 py-3 fs-5" 
                                        style="min-width: 15%;"
                                        onclick="confirmClockOut()" 
                                        {{ $lastRecord && $lastRecord->TimeIn && !$lastRecord->TimeOut ? '' : 'disabled' }}>
                                    Clock Out
                                </button>
                            </form>
                        </div>


                        <!-- Computations for Today's Hours and This Week's Hours -->
                        <div class="mt-4 p-3 bg-light border rounded">
                            <p><strong>Today's Hours:</strong> {{ $todayHoursFormatted ?? '0:00' }}</p>
                            <p><strong>This Week's Hours:</strong> {{ $weekHoursFormatted ?? '0:00' }}</p>
                        </div>

                        <!-- Attendance Table -->
                        <div class="table-responsive mt-4">
                            <table class="table table-bordered table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Computed Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($employeeClocks as $clock)
                                    <tr>
                                        <!-- Time In -->
                                        <td>
                                            {{ \Carbon\Carbon::parse($clock->TimeIn)->format('h:i A') }}
                                            <div class="text-muted">
                                                {{ \Carbon\Carbon::parse($clock->TimeIn)->format('M d, Y') }}
                                            </div>
                                        </td>

                                        <!-- Time Out -->
                                        <td>
                                            @if ($clock->TimeOut)
                                                {{ \Carbon\Carbon::parse($clock->TimeOut)->format('h:i A') }}
                                                <div class="text-muted">
                                                    {{ \Carbon\Carbon::parse($clock->TimeOut)->format('M d, Y') }}
                                                </div>
                                            @else
                                                <span class="text-danger">Not yet timed out</span>
                                            @endif
                                        </td>

                                        <!-- Computed Hours -->
                                        <td>
                                        @php
                                            // Ensure consistent timezone for TimeIn and TimeOut
                                            $timeIn = \Carbon\Carbon::parse($clock->TimeIn)->setTimezone('America/Los_Angeles'); // Parse TimeIn without altering timezone
                                            $timeOut = $clock->TimeOut
                                                ? \Carbon\Carbon::parse($clock->TimeOut)->setTimezone('America/Los_Angeles')
                                                : now()->setTimezone('America/Los_Angeles')->subHours(8); // Subtract 8 hours from now()

                                            // Calculate total minutes worked
                                            $totalMinutes = $timeIn->diffInMinutes($timeOut);

                                            // Convert total minutes into hours and remaining minutes
                                            $hours = floor($totalMinutes / 60); // Get whole hours
                                            $minutes = $totalMinutes % 60; // Get remaining minutes
                                        @endphp

                                            @if ($clock->TimeIn)
                                                {{ $hours }} hrs {{ $minutes }} mins
                                                <div class="text-muted">
                                                    @if (!$clock->TimeOut)
                                                        (Calculated until now)
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-danger">No Time In</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

    

                    </div>

                    <!-- Add User Tab -->
                    <div class="tab-pane fade" id="userprofile" role="tabpanel" aria-labelledby="user-tab">
                        <h5>User</h5>
                        <form action="{{ route('update-password') }}" method="POST">
                            @csrf
                            <!-- Username -->
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="myusername" name="myusername" placeholder="Enter username" value="{{ session('user_name', 'User Name') }}" required>
                            </div>

                            <!-- Password -->
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="newpassword" name="password" placeholder="Enter password" required>
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="newpassword_confirmation" name="password_confirmation" placeholder="Confirm password" required>
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#password_confirmation">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">UPDATE</button>
                        </form>
                    </div>
					
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
 
 axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

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
    axios.post('/add-store', { storename: storeName })
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
                $('#addStoreModal').modal('hide');
                $('#settingsModal').modal('show');
                $('#store-tab').tab('show');
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
document.addEventListener('DOMContentLoaded', function () {
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

document.getElementById('editStoreForm').addEventListener('submit', function (e) {
    e.preventDefault(); // Prevent default form submission

    const storeId = document.getElementById('editStoreId').value.trim();
    if (!storeId) {
        alert('Store ID is missing. Please try again.');
        return;
    }

    // Gather the updated data from the form
    const updatedStoreData = {
        store_id: storeId,  // Should match the store_id column in the database
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
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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
    <div id="successToast" class="toast align-items-center text-bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                {{ session('success') }}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
@endif

<!-- Error Notification -->
@if (session('error'))
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="errorToast" class="toast align-items-center text-bg-danger border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                {{ session('error') }}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
@endif

<!-- Validation Errors -->
@if ($errors->any())
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="validationToast" class="toast align-items-center text-bg-warning border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
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
        const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
        logoutModal.show();
        logoutSound.play();
    }

    // Handle the "Yes" button click in the modal
    document.getElementById('confirmLogout').addEventListener('click', function () {
        document.getElementById('logout-form').submit();
    });
</script>

    <!-- Footer -->
    <footer>
        &copy; 2025 IMS (Inventory Management System). All rights reserved.
    </footer>

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

    // Toggle sidebar visibility
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

    // Hide sidebar and reset content layout for all devices
    closeBtn.addEventListener('click', () => {
        sidebar.classList.remove('visible');
        if (window.innerWidth > 768) {
            mainContent.classList.remove('sidebar-visible');
            navbarBrand.classList.remove('shifted');
            burgerMenu.classList.remove('hidden');
        }
    });

    function loadContent(module) {
    const dynamicContent = document.getElementById('dynamic-content');
    const searchContainer = document.getElementById('top-search');
    const searchInput = document.querySelector('#top-search input');
    
    // Get the base URL of your application
    const baseUrl = window.location.origin;  // E.g., 'http://127.0.0.1:8000'

    // Construct the URL dynamically using the module name
    const url = `${baseUrl}/Systemmodule/${module}Module/${module}`;

    // Fetch the content for the selected module
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            dynamicContent.innerHTML = html;  // Load the module's HTML content into the dynamic content container
            initSearch(module); // Initialize search for the dynamically loaded content
        })
        .catch(error => {
            dynamicContent.innerHTML = '<p>Error loading content.</p>';
            console.error('Error:', error);
        });

    // Show or hide the search bar based on the module
    if (module !== 'dashboard') {
        searchContainer.classList.add('show'); // Show the search bar
        searchInput.style.display = 'flex'; // Ensure input is visible
    } else {
        searchContainer.classList.remove('show'); // Hide the search bar
        searchInput.style.display = 'none';
        searchInput.value = ''; // Clear the input value
    }

    // Update active class for navigation links
    const navLinks = document.querySelectorAll('.nav .nav-link');
    navLinks.forEach(link => link.classList.remove('active'));
    const activeLink = document.querySelector(`.nav .nav-link[onclick*="${module}"]`);
    if (activeLink) activeLink.classList.add('active');

    searchInput.value = ''; // Clear the search field when switching modules
    const rows = document.querySelectorAll('.custom-table tbody tr');
    rows.forEach(row => row.style.display = ""); // Reset rows display to default
}

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
            currentDayElement.textContent = pacificDay; // Display the day
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
</body>
</html>