<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .google-login-btn:hover {
            background-color: #f7f7f7;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            color: #555;
            text-decoration: none;
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

        .is-invalid {
            border-color: #dc3545;
        }

        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }

        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h3>Sign in to IMS</h3>
        <form method="POST" action="{{ route('login') }}" id="loginForm">
            @csrf
            <div class="mb-3">
                <label for="username" class="form-label">
                    <strong>Username or Email</strong>
                </label>
                <input type="text" class="form-control @error('username') is-invalid @enderror" id="username"
                    name="username" value="{{ old('username') }}" placeholder="Enter your username or email" required
                    autocomplete="username">
                @error('username')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">
                    <strong>Password</strong>
                </label>
                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                    name="password" placeholder="Enter your password" required autocomplete="current-password">
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">
                    Remember me
                </label>
            </div>
            
            <!-- Timezone Value -->
            <input type="hidden" name="timezone" id="timezone">

            <button type="submit" class="btn btn-primary w-100" id="loginButton">
                <span class="login-text">Login</span>
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            </button>

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

    <!-- AUDIO ELEMENTS - Only for logout and errors (login success handled on dashboard) -->
    <audio id="logoutSuccessAudio" preload="auto">
        <source src="/sounds/logout.mp3" type="audio/mpeg">
        <source src="/sounds/logout.wav" type="audio/wav">
        Your browser does not support the audio element.
    </audio>
    <audio id="errorAudio" preload="auto">
        <source src="/sounds/error2.mp3" type="audio/mpeg">
        <source src="/sounds/error.wav" type="audio/wav">
        Your browser does not support the audio element.
    </audio>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Login page loaded');

            const logoutSuccessAudio = document.getElementById('logoutSuccessAudio');
            const errorAudio = document.getElementById('errorAudio');
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            const loginText = loginButton.querySelector('.login-text');
            const spinner = loginButton.querySelector('.spinner-border');

            // Function to play audio with error handling
            function playAudio(audioElement, audioName) {
                if (audioElement) {
                    console.log(`Attempting to play ${audioName} audio`);

                    // Reset audio to beginning
                    audioElement.currentTime = 0;

                    const playPromise = audioElement.play();

                    if (playPromise !== undefined) {
                        playPromise
                            .then(() => {
                                console.log(`${audioName} audio played successfully`);
                            })
                            .catch(error => {
                                console.warn(`${audioName} audio playback failed:`, error);

                                // Try to play after user interaction
                                const playOnClick = () => {
                                    audioElement.play().catch(e => console.error(`${audioName} retry failed:`, e));
                                    document.removeEventListener('click', playOnClick);
                                };
                                document.addEventListener('click', playOnClick, { once: true });
                            });
                    }
                } else {
                    console.error(`${audioName} audio element not found`);
                }
            }

            // Handle form submission
            loginForm.addEventListener('submit', function (e) {
                console.log('Form submitted');

                // Set timezone value
                document.getElementById('timezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone;
                // Show loading state
                loginButton.disabled = true;
                loginText.textContent = 'Signing in...';
                spinner.classList.remove('d-none');
            });

            // Reset form state if there are errors
            @if($errors->any())
                console.log('Form has errors, resetting button state');
                loginButton.disabled = false;
                loginText.textContent = 'Login';
                spinner.classList.add('d-none');
            @endif

            // NOTE: Login success audio is now handled on the dashboard page

            // Handle LOGOUT success messages (play logout sound)
            @if(session('logout_success'))
                console.log('Logout success detected:', "{{ session('logout_success') }}");

                // Play logout audio
                //     playAudio(logoutSuccessAudio, 'Logout Success');

                Swal.fire({
                    icon: 'success',
                    title: 'Logged Out Successfully',
                    text: "{{ session('logout_success') }}",
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            @endif

            // Handle error messages
            @if(session('error'))
                console.log('Error message detected:', "{{ session('error') }}");

                playAudio(errorAudio, 'Error');

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: "{{ session('error') }}"
                });
            @endif

            // Handle validation errors
            @if($errors->any())
                console.log('Validation errors detected');

                playAudio(errorAudio, 'Validation Error');

                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    html: `
                            <ul style="text-align: left; list-style: none; padding: 0;">
                                @foreach($errors->all() as $error)
                                    <li style="margin-bottom: 5px;">• {{ $error }}</li>
                                @endforeach
                            </ul>
                        `
                });
            @endif

            // Auto-focus on username field
            document.getElementById('username').focus();

            // Handle Enter key submission
            document.addEventListener('keypress', function (e) {
                if (e.key === 'Enter' && !loginButton.disabled) {
                    loginForm.submit();
                }
            });

            // Clear previous errors when user starts typing
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('input', function () {
                    this.classList.remove('is-invalid');
                    const feedback = this.parentNode.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.style.display = 'none';
                    }
                });
            });

            // Test audio files on page load (optional - remove this in production)
            setTimeout(() => {
                console.log('Testing audio files...');

                // Test logout audio
                if (logoutSuccessAudio.canPlayType('audio/mpeg')) {
                    console.log('Logout audio: MP3 supported');
                } else if (logoutSuccessAudio.canPlayType('audio/wav')) {
                    console.log('Logout audio: WAV supported');
                } else {
                    console.warn('Logout audio: No supported format');
                }

                // Test error audio
                if (errorAudio.canPlayType('audio/mpeg')) {
                    console.log('Error audio: MP3 supported');
                } else if (errorAudio.canPlayType('audio/wav')) {
                    console.log('Error audio: WAV supported');
                } else {
                    console.warn('Error audio: No supported format');
                }
            }, 1000);
        });

        // Prevent double submission
        window.addEventListener('beforeunload', function () {
            const loginButton = document.getElementById('loginButton');
            if (loginButton) {
                loginButton.disabled = true;
            }
        });

        // Prevent back button after successful login
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                // Check if user is logged in
                fetch('/check-auth', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => {
                        if (response.ok) {
                            // User is authenticated, redirect to dashboard
                            window.location.replace("{{ route('dashboard.system') }}");
                        }
                    })
                    .catch(() => {
                        // Ignore errors, stay on login page
                    });
            }
        });
    </script>
</body>

</html>