<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Training</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/training.js'])
</head>
<body class="antialiased">
    <div id="ai-app"></div>
</body>
</html>

<!-- Install -> npm install vue-chartjs chart.js -->
