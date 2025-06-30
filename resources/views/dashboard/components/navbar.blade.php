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
                    <a class="nav-link d-flex align-items-center justify-content-center" href="#" data-bs-toggle="modal"
                        data-bs-target="#profileModal">
                        <i class="bi bi-person me-2"></i>
                        <span class="d-none d-lg-inline">Profile</span>
                    </a>
                </li>

                <!-- Settings -->
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-center" href="#" data-bs-toggle="modal"
                        data-bs-target="#settingsModal">
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
                </li>

                <!-- Place this form outside of the navbar, preferably right after the closing </nav> tag -->
                <form id="force-logout-form" action="{{ url('/force-logout') }}" method="GET" style="display: none;">
                </form>

            </ul>
        </div>

    </div>
</nav>