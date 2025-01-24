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
        <!-- Store Dropdown -->
        <label>
            Store:
            <select name="store" id="store-select" required>
                <option value="Renovar Tech">Renovar Tech</option>
                <option value="All Renewed">All Renewed</option>
            </select>
        </label>
        <br>

        <!-- Marketplace Dropdown (populated dynamically) -->
        <label>
            Marketplace:
            <select name="marketplace" id="marketplace-select" required>
                <option value="">Select a marketplace</option>
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

        const storeSelect = document.getElementById('store-select');
        const marketplaceSelect = document.getElementById('marketplace-select');

        // Fetch marketplaces when the store changes
        storeSelect.addEventListener('change', function () {
            const store = storeSelect.value;

            // Clear previous options
            marketplaceSelect.innerHTML = '<option value="">Loading...</option>';

            // Fetch marketplaces from the backend
            axios.get("{{ route('fetchMarketplacestblstores') }}", { params: { store } })
                .then(response => {
                    if (response.data.success) {
                        const marketplaces = response.data.marketplaces;

                        // Populate the marketplace dropdown
                        marketplaceSelect.innerHTML = '<option value="">Select a marketplace</option>';
                        for (const [marketplace, marketplaceId] of Object.entries(marketplaces)) {
                            const option = document.createElement('option');
                            option.value = marketplaceId; // Set value as marketplace ID
                            option.textContent = marketplace; // Display name
                            marketplaceSelect.appendChild(option);
                        }
                    } else {
                        marketplaceSelect.innerHTML = '<option value="">Failed to load marketplaces</option>';
                    }
                })
                .catch(error => {
                    console.error(error);
                    marketplaceSelect.innerHTML = '<option value="">Failed to load marketplaces</option>';
                });
        });

        // Trigger initial fetch for the default store
        storeSelect.dispatchEvent(new Event('change'));

        // Handle form submission
        document.getElementById('fetch-inventory').addEventListener('click', function () {
            const formData = new FormData(document.getElementById('inventory-form'));

            // Send store and marketplace to the backend
            axios.post("{{ route('aws.inventory.summary') }}", {
                store: formData.get('store'),
                marketplace: formData.get('marketplace')
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