<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ session('site_title', 'IMS') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">

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

            <div class="search-bar d-flex align-items-center mx-auto" id="top-search">
                <input 
                    type="text" 
                    class="form-control" 
                    placeholder="Search..." 
                    aria-label="Search"
                    id="search-input"
                />
            </div>
        
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#settingsModal">Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Logout</a>
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

                    
                </div>
            </div>
             <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
    
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
  </script>
</body>
</html>