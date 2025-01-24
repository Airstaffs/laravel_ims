<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>UPS Tracking</title>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>

<body>
    <h1>UPS Tracking</h1>
    <form id="tracking-form">
        <!-- Tracking Number Input -->
        <label>
            Tracking Number:
            <input type="text" name="trackingnumber" id="trackingnumber" required>
        </label>
        <br>
        <button type="button" id="fetch-tracking">Submit</button>
    </form>

    <h2>Result:</h2>
    <pre id="tracking-result"></pre>

    <script>
        // Ensure CSRF Token is included in Axios
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Handle form submission
        document.getElementById('fetch-tracking').addEventListener('click', function () {
            const trackingNumber = document.getElementById('trackingnumber').value;

            // Validate the input
            if (!trackingNumber) {
                alert("Please enter a tracking number.");
                return;
            }

            // Make a POST request to the UPS tracking endpoint
            axios.post("{{ route('UPS.trackingnumber') }}", { trackingnumber: trackingNumber })
                .then(response => {
                    const resultElement = document.getElementById('tracking-result');
                    resultElement.textContent = JSON.stringify(response.data, null, 2);
                })
                .catch(error => {
                    console.error(error);
                    document.getElementById('tracking-result').textContent =
                        error.response?.data?.error || "An error occurred while fetching tracking details.";
                });
        });
    </script>
</body>

</html>
