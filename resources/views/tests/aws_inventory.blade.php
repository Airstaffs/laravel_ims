<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AWS Inventory Summary</title>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body>
    <h1>Fetch AWS Inventory Summary</h1>
    <form id="inventory-form">
        <!-- Removed the fields for granularityType, granularityId, and marketplaceIds -->
        <label>
            Store:
            <select name="store" required>
                <option value="Renovar Tech">RT</option>
                <option value="AR">AR</option>
            </select>
        </label>
        <br>
        <button type="button" id="fetch-inventory">Fetch Inventory</button>
    </form>

    <h2>Result:</h2>
    <pre id="inventory-result"></pre>

    <script>
        // Ensure CSRF Token is included
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Handle button click
        document.getElementById('fetch-inventory').addEventListener('click', function () {
            const formData = new FormData(document.getElementById('inventory-form'));

            // Send only the store value to the backend
            axios.post("{{ route('aws.inventory.summary') }}", {
                store: formData.get('store')
            })
            .then(response => {
                const resultElement = document.getElementById('inventory-result');
                if (response.data.success) {
                    resultElement.textContent = JSON.stringify(response.data.data, null, 2);
                } else {
                    resultElement.textContent = `Error: ${response.data.message}`;
                }
            })
            .catch(error => {
                console.error(error);
                document.getElementById('inventory-result').textContent = "An error occurred while fetching data.";
            });
        });
    </script>
</body>
</html>
