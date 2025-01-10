<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
        }

        .navbar {
            background-color: #007bff;
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
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 600px; /* Adjust max width as needed */
            margin: 0 auto;
        }

        #search-input {
            width: 100%;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            border-radius: 5px;
            border: 1px solid #ccc;
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
            <a class="navbar-brand" href="#">IMS (Inventory Management System)</a>

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
                        <a class="nav-link" href="#">Settings</a>
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
            src="path_to_user_image.jpg" 
            alt="User Profile" 
            class="rounded-circle mb-2" 
            style="width: 80px; height: 80px; object-fit: cover;"
        >
        <!-- Display user's name -->
        <h5>{{ session('user_name', 'User Name') }}</h5>
    </div>

        <h5 class="text-center">Navigation</h5>
        <nav class="nav flex-column">
            <a class="nav-link active" href="#" onclick="loadContent('dashboard')">Dashboard</a>
            <a class="nav-link" href="#" onclick="loadContent('orders')">Orders</a>
            <a class="nav-link" href="#" onclick="loadContent('unreceived')">Unreceived</a>
            <a class="nav-link" href="#" onclick="loadContent('receiving')">Received</a>
            <a class="nav-link" href="#" onclick="loadContent('labeling')">Labeling</a>
            <a class="nav-link" href="#" onclick="loadContent('validation')">Validation</a>
            <a class="nav-link" href="#" onclick="loadContent('testing')">Testing</a>
            <a class="nav-link" href="#" onclick="loadContent('cleaning')">Cleaning</a>
            <a class="nav-link" href="#" onclick="loadContent('packing')">Packing</a>
            <a class="nav-link" href="#" onclick="loadContent('stockroom')">Stockroom</a>
        </nav>
    </div>

    <!-- Content -->
    <div id="main-content" class="content">
        <div id="dynamic-content">
            <h3>Select a module from the sidebar</h3>
        </div>
    </div>

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
    const topSearch = document.getElementById('top-search');
    const searchInput = document.getElementById('search-input');

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

    // Function to dynamically load content based on module selection
    function loadContent(module) {
        let content = '';
        let showSearch = true; // Default is to show search bar

        if (module == 'dashboard') {
            showSearch = false; // Show search bar in non-dashboard modules
        } else {
            showSearch = true; // Hide search bar in dashboard module
        }

        switch(module) {
            case 'dashboard':
                content = '<h3>Dashboard Content</h3><p>Here is your dashboard overview.</p>';
                break;
            case 'orders':
                content = '<h3>Orders Content</h3><p>Here are your orders.</p>';
                break;
            case 'unreceived':
                content = '<h3>Unreceived Content</h3><p>Here are the unreceived items.</p>';
                break;
            case 'receiving':
                content = '<h3>Received Content</h3><p>Here are the received items.</p>';
                break;
            case 'labeling':
                content = '<h3>Labeling Content</h3><p>Here is your labeling information.</p>';
                break;
            case 'validation':
                content = '<h3>Validation Content</h3><p>Here is the validation information.</p>';
                break;
            case 'testing':
                content = '<h3>Testing Content</h3><p>Here are the testing details.</p>';
                break;
            case 'cleaning':
                content = '<h3>Cleaning Content</h3><p>Here are the cleaning details.</p>';
                break;
            case 'packing':
                content = '<h3>Packing Content</h3><p>Here are the packing details.</p>';
                break;
            case 'stockroom':
                content = '<h3>Stockroom Content</h3><p>Here are the stockroom details.</p>';
                break;
            default:
                content = '<h3>Select a module from the sidebar</h3>';
        }
        dynamicContent.innerHTML = content;

        // Show or hide the search bar based on the module
        if (showSearch) {
            topSearch.style.display = 'flex'; // Show search for non-dashboard modules
        } else {
            topSearch.style.display = 'none'; // Hide search on dashboard
        }

        // Update active class for the clicked module
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        navLinks.forEach(link => link.classList.remove('active'));
        document.querySelector(`.sidebar .nav-link[href="#"][onclick*="${module}"]`).classList.add('active');
    }

    // Implement dynamic search (filter content based on input)
    searchInput.addEventListener('input', function() {
        let searchTerm = searchInput.value.toLowerCase();
        // You can add logic here to filter content based on the search term
        // For example, matching content with the search input
    });
    </script>
</body>
</html>
