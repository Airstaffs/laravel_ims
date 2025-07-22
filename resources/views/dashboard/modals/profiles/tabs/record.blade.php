<div class="tab-pane fade show text-center" id="timerecord" role="tabpanel" aria-labelledby="timerecord-tab">

    <!-- Date Range Filter -->
    <form id="filter-form" class="filterForm" data-route="{{ route('attendance.filter.ajax') }}">
        <!-- Start Date -->
        <div class="form-group">
            <label for="start-date" class="form-label visually-hidden">Start Date:</label>
            <input type="date" class="form-control" id="start-date" name="start_date" placeholder="Start Date">
        </div>

        <!-- End Date -->
        <div class="form-group">
            <label for="end-date" class="form-label visually-hidden">End Date:</label>
            <input type="date" class="form-control" id="end-date" name="end_date" placeholder="End Date">
        </div>

        <!-- Filter Button -->
        <button type="button" id="filter-button" class="btn btn-primary">Filter</button>
    </form>

    <!-- Computations -->
    <strong>
        <p>Total Hours: <span id="total-hours">0:00</span></p>
    </strong>

    <!-- Attendance Table -->
    <div class="table-responsive d-none d-md-block">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Computed Hours</th>
                </tr>
            </thead>
            <tbody id="attendance-table-body">
                <!-- Default Rows Will Be Loaded Dynamically -->
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="container d-block d-md-none" id="attendance-card-container">
        <!-- Cards will be injected dynamically -->
    </div>
</div>