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
            background-color: #f0f2f5;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            background: #fff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            color: #333;
        }

        .login-container h3 {
            text-align: center;
            font-weight: 600;
            margin-bottom: 2rem;
            color: #4285F4;
        }

        .form-control {
            border-radius: 8px;
            height: 50px;
            font-size: 1rem;
        }

        .btn-primary {
            background-color: #4285F4;
            border: none;
            height: 50px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1rem;
        }

        .btn-primary:hover {
            background-color: #3367D6;
        }

        .google-login-btn {
            background-color: #fff;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 500;
            color: #555;
            height: 50px;
            border-radius: 8px;
            margin-top: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .google-login-btn:hover {
            background-color: #f7f7f7;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .google-login-btn img {
            height: 24px;
        }

        .form-text a {
            color: #4285F4;
            text-decoration: none;
        }

        footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            background: #fff;
            color: #666;
            text-align: center;
            padding: 0.75rem 0;
            font-size: 0.9rem;
            box-shadow: 0 -1px 4px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h3>Sign in to IMS</h3>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
                <label for="username" class="form-label">
                    <strong>Username or Email</strong>
                </label>
                <input type="text" class="form-control" id="username" name="username"
                    placeholder="Enter your username or email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">
                    <strong>Password</strong>
                </label>
                <input type="password" class="form-control" id="password" name="password"
                    placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
            <div class="form-text text-center mt-3">
                Forgot your password? <a href="#">Reset here</a>
            </div>
            <a href="{{ url('auth/google') }}" class="google-login-btn">
                <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google logo">
                Continue with Google
            </a>
        </form>
    </div>

    <footer>
        &copy; 2025 IMS (Inventory Management System)
    </footer>

    <audio id="successAudio" src="/sounds/login.mp3" preload="auto"></audio>
    <audio id="errorAudio" src="/sounds/error2.mp3" preload="auto"></audio>

    <script>
        const successAudio = document.getElementById('successAudio');
        const errorAudio = document.getElementById('errorAudio');

        @if(session('success'))
            successAudio.play().catch(error => console.error('Audio playback failed:', error));
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: "{{ session('success') }}",
            }).then(() => {
                window.location.href = "{{ url('/dashboard') }}";
            });
        @endif

        @if(session('error'))
            errorAudio.play().catch(error => console.error('Audio playback failed:', error));
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: "{{ session('error') }}",
            });
        @endif

        @if($errors->any())
            errorAudio.play().catch(error => console.error('Audio playback failed:', error));
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: "{{ $errors->first() }}",
            });
        @endif
    </script>
</body>
</html>