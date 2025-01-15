<div id="dynamic-content">
    <h3>Receiving Module</h3>
    <div class="custom-table">
        <table class="product-table">
            <thead>
                <tr>
                    <th>Product Image</th>
                    <th>ASIN</th>
                    <th>Product Name</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><img src="path/to/product-image.jpg" alt="Product 1" class="product-img"></td>
                    <td>B08N5M7S6K</td>
                    <td>Bose QuietComfort 35 II Headphones</td>
                    <td>$299.99</td>
                    <td>150</td>
                    <td>
                        <button class="action-btn">View</button>
                        <button class="action-btn">Edit</button>
                    </td>
                </tr>
                <tr>
                    <td><img src="path/to/product-image.jpg" alt="Product 2" class="product-img"></td>
                    <td>B08VJYZF58</td>
                    <td>Sony WH-1000XM4 Wireless Headphones</td>
                    <td>$348.00</td>
                    <td>200</td>
                    <td>
                        <button class="action-btn">View</button>
                        <button class="action-btn">Edit</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <!-- Mobile View - Card Layout -->
        <div class="mobile-view">
            <div class="custom-table-row">
                <div class="product-img-container">
                    <img src="path/to/product-image.jpg" alt="Product 1" class="product-img">
                </div>
                <div class="product-details">
                    <p><strong>ASIN:</strong> B08N5M7S6K</p>
                    <p><strong>Product Name:</strong> Bose QuietComfort 35 II Headphones</p>
                    <p><strong>Price:</strong> $299.99</p>
                    <p><strong>Stock:</strong> 150</p>
                    <div class="actions">
                        <button class="action-btn">View</button>
                        <button class="action-btn">Edit</button>
                    </div>
                </div>
            </div>
            <div class="custom-table-row">
                <div class="product-img-container">
                    <img src="path/to/product-image.jpg" alt="Product 2" class="product-img">
                </div>
                <div class="product-details">
                    <p><strong>ASIN:</strong> B08VJYZF58</p>
                    <p><strong>Product Name:</strong> Sony WH-1000XM4 Wireless Headphones</p>
                    <p><strong>Price:</strong> $348.00</p>
                    <p><strong>Stock:</strong> 200</p>
                    <div class="actions">
                        <button class="action-btn">View</button>
                        <button class="action-btn">Edit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#dynamic-content {
    font-family: Arial, sans-serif;
    padding: 0;
    width: 100%;
    margin-top: 20px;
}

.custom-table {
    width: 100%;
}

.product-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.product-table th,
.product-table td {
    text-align: left;
    padding: 10px;
    border: 1px solid #ddd;
}

.product-table th {
    background-color: #f4f4f4;
    font-weight: bold;
}

.product-table td img.product-img {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 5px;
}

.product-table td .action-btn {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
    border-radius: 3px;
    margin-right: 5px;
    font-size: 12px;
}

.product-table td .action-btn:hover {
    background-color: #0056b3;
}

.product-table tr:hover {
    background-color: #f1f1f1;
}

/* Mobile View - Card Layout */
.mobile-view {
    display: none; /* Hidden by default */
}

.custom-table-row {
    display: flex;
    flex-direction: row; /* Change to row layout for mobile */
    padding: 10px;
    border: 1px solid #ddd;
    margin-bottom: 15px;
    align-items: center;
}

.product-img-container {
    text-align: left;
    margin-right: 20px; /* Space between image and text */
}

.product-img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 5px;
}

.product-details {
    flex: 1;
}

.product-details p {
    margin: 5px 0;
    font-size: 14px;
}

.actions {
    margin-top: 10px;
}

.action-btn {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
    border-radius: 3px;
    font-size: 12px;
    margin-right: 5px;
}

.action-btn:hover {
    background-color: #0056b3;
}

@media (max-width: 768px) {
    .product-table {
        display: none; /* Hide table view on mobile */
    }
    
    .mobile-view {
        display: block; /* Show card layout on mobile */
    }

    .custom-table-row {
        flex-direction: row; /* Layout as row for mobile: image left, text right */
    }

    .product-img {
        width: 80px;
        height: 80px;
    }

    .product-details p {
        font-size: 12px;
    }

    .action-btn {
        font-size: 11px;
        padding: 4px 8px;
    }
}

@media (min-width: 769px) {
    .mobile-view {
        display: none; /* Hide mobile layout on desktop */
    }

    .product-table {
        display: table; /* Show table layout on desktop */
    }
}

</style>