<div class="modal fade" id="editStoreModal" tabindex="-1" aria-labelledby="editStoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStoreModalLabel">Edit Store</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form id="editStoreForm">
                    <input type='hidden' id="editStoreId">

                    <fieldset>
                        <label>Store Name</label>
                        <input type="text" class="form-control" id="editStoreName" required>
                    </fieldset>
                    <fieldset>
                        <label>Client ID</label>
                        <input type="text" class="form-control" id="editClientID">
                    </fieldset>
                    <fieldset>
                        <label>Client Secret</label>
                        <input type="text" class="form-control" id="editClientSecret">
                    </fieldset>
                    <fieldset>
                        <label>Refresh Token</label>
                        <input type="text" class="form-control" id="editRefreshToken">
                    </fieldset>
                    <fieldset>
                        <label>Merchant ID</label>
                        <input type="text" class="form-control" id="editMerchantID">
                    </fieldset>
                    <fieldset>
                        <label>Select Marketplace</label>
                        <select class="form-select" id="selectMarketplace" name="marketplaces[]" required>
                            <!-- Options will be populated dynamically -->
                        </select>
                    </fieldset>
                    <fieldset>
                        <label>Marketplace</label>
                        <input type="text" class="form-control" id="editMarketplace">
                    </fieldset>
                    <fieldset>
                        <label>Marketplace ID</label>
                        <input type="text" class="form-control" id="editMarketplaceID">
                    </fieldset>

                    <button type="submit" class="btn btn-primary justify-content-center fw-bold text-white">
                        Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>