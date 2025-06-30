<div class="tab-pane fade" id="usertimerecord" role="tabpanel" aria-labelledby="usertimerecord-tab">
    <h3 class="text-center">User Time Record</h3>

    <form id="usertimerecord" class="userTimeRecord">
        @csrf
        <fieldset>
            <select class="form-select" id="selectUserDrop" name="user_id" required>
                @if (count($Allusers) > 0)
                    @foreach ($Allusers as $userOption)
                        <option value="{{ $userOption->id }}" {{ auth()->id() == $userOption->id ? 'selected' : '' }}>
                            {{ $userOption->username }}
                        </option>
                    @endforeach
                @else
                    <option disabled selected>No users found</option>
                @endif
            </select>
        </fieldset>

        <fieldset class="input-container">
            <input type="date" class="form-control" id="start_date" name="start_date" placeholder="Start Date" required>
            <input type="date" class="form-control" id="end_date" name="end_date" placeholder="End Date" required>
        </fieldset>

        <div class="d-grid gap-2">
            <button type="button" class="btn btn-primary" id="filterRecords">Filter</button>
        </div>
    </form>

    <div class="mt-4 table-responsive d-none d-md-block">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Details</th>
                    <th>Total Hours</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody id="timeRecordsBody" class="tbody-notes">
                <tr class="tr-notes" id="userlogsEmptyRow">
                    <td colspan="3" class="td-notes text-center">No logs found</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="d-block d-md-none" id="timeRecordsMobile">
        <div class="alert alert-info text-center" role="alert" id="userlogsEmptyCard">
            No logs found
        </div>
    </div>
</div>

@auth
    <script>
        const CURRENT_USER_ID = {{ auth()->user()->id }};
    </script>
@endauth