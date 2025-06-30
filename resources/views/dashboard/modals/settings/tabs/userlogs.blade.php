<div class="tab-pane fade" id="userlogs" role="tabpanel" aria-labelledby="userlogs-tab">
    <h3 class="text-center">User Logs</h3>

    <form id="userlogs" class="userLogs">
        @csrf
        <fieldset>
            <select class="form-select" id="selectUserDrop_logs" name="user_id" required>
                @foreach ($Allusers as $userOption)
                    <option value="{{ $userOption->id }}" {{ auth()->id() == $userOption->id ? 'selected' : '' }}>
                        {{ $userOption->username }}
                    </option>
                @endforeach
            </select>
        </fieldset>

        <fieldset class="input-container">
            <input type="date" class="form-control" id="start_date_logs" name="start_date_logs"
                placeholder="Start Date">
            <input type="date" class="form-control" id="end_date_logs" name="end_date_logs" placeholder="End Date">
        </fieldset>

        <button type="button" class="btn btn-primary" id="filter_logs">Filter</button>
    </form>

    <div class="table-responsive d-none d-md-block">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>User</th>
                    <th>Actions</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody id="userlogsData" class="tbody-notes">
                <tr id="noLogsMessage" class="text-center d-none">
                    <td colspan="3">No logs found</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="d-block d-md-none" id="userlogsCardView">
        <p id="noLogsMessageCard" class="text-center d-md-none d-none">No logs found</p>
    </div>
</div>

@auth
    <script>
        const CURRENT_USER_ID = {{ auth()->user()->id }};
    </script>
@endauth