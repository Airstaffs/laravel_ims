<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $systemDesign->site_title ?? 'IMS' }} - Login</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <div id="login-app">
        <login-component :system-design='@json($systemDesign ?? [])'></login-component>
    </div>

    @if(session('logout_success'))
        <script>
            // Pass logout message via URL param for Vue to handle
            if (!window.location.search.includes('logout_success')) {
                const url = new URL(window.location);
                url.searchParams.set('logout_success', '{{ session('logout_success') }}');
                window.history.replaceState({}, '', url);
            }
        </script>
    @endif

    @if(session('error'))
        <script>
            // Pass error message via URL param for Vue to handle
            if (!window.location.search.includes('error')) {
                const url = new URL(window.location);
                url.searchParams.set('error', '{{ session('error') }}');
                window.history.replaceState({}, '', url);
            }
        </script>
    @endif

    @if($errors->any())
        <script>
            // Pass validation errors via URL param for Vue to handle
            if (!window.location.search.includes('error')) {
                const url = new URL(window.location);
                url.searchParams.set('error', '{{ $errors->first() }}');
                window.history.replaceState({}, '', url);
            }
        </script>
    @endif
</body>

</html>