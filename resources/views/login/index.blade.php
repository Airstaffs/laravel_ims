<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ session('site_title', 'IMS') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: {{ session('theme_color', '#007bff') }};
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .login-container {
            max-width: 380px;
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.25);
            color: #333;
        }
        .login-container h3 {
            text-align: center;
            font-weight: bold;
            margin-bottom: 1.5rem;
            color: #007bff;
        }
        .form-control {
            border-radius: 5px;
            height: 45px;
        }
        .btn-primary {
            background: {{ session('theme_color', '#007bff') }};
            border: none;
            height: 45px;
            border-radius: 5px;
            font-weight: bold;
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .form-label {
            font-weight: 500;
            color: #555;
        }
        .login-container .form-text {
            font-size: 0.875rem;
            color: #666;
        }
        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #fff;
            color: #333;
            padding: 0.5rem 0;
            text-align: center;
            font-size: 0.875rem;
            box-shadow: 0 -2px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h3>Welcome to IMS</h3>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
            <div class="form-text text-center mt-3">Forgot your password? <a href="#" class="text-primary">Reset here</a></div>
        </form>
    </div>

    <footer>
        &copy; 2025 IMS (Inventory Management System)
    </footer>

    <script>
        // Check if there are session messages
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: "{{ session('success') }}",
            }).then(() => {
                // Redirect after success message
                window.location.href = "{{ url('/dashboard/Systemdashboard') }}"; // Modify URL as needed
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: "{{ session('error') }}",
            });
        @endif

        @if($errors->any())
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: "{{ $errors->first() }}",
            });
        @endif
    </script>
</body>
</html>
