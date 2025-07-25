<div class="tab-pane fade show active text-center" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
    <h3>Attendance / Clock-in & Clock-out</h3>

    <!-- Time, Day, and Date Display -->
    <div class="attendance-info-container d-flex flex-column justify-content-start align-items-stretch">
        <div class="date-container">
            <div id="current-time"></div>
            <div id="current-day"></div>
            <div id="current-date" style="display:none;"></div>
        </div>

        <!-- Hidden clock-in time for auto-clock-out -->
        <input type="hidden" id="last-record-timein"
            value="{{ $verylastRecord ? \Carbon\Carbon::parse($verylastRecord->TimeIn)->toIso8601String() : '' }}">

        <!-- Clock In/Out Buttons -->
        <div class="d-flex justify-content-center gap-3">
            <button type="button" id="clockin-button" onclick="confirmClockIn()"
                data-route="{{ route('attendance.clockin') }}"
                class="btn {{ !$lastRecord || ($lastRecord && $lastRecord->TimeIn && $lastRecord->TimeOut) ? 'btn-clockin' : 'btn-clockout' }}"
                {{ !$lastRecord || ($lastRecord && $lastRecord->TimeIn && $lastRecord->TimeOut) ? '' : 'disabled' }}>
                Clock In
            </button>

            <button type="button" id="clockout-button" onclick="confirmClockOut()"
                data-route="{{ route('attendance.clockout') }}"
                class="btn {{ $lastRecord && $lastRecord->TimeIn && !$lastRecord->TimeOut ? 'btn-clockin' : 'btn-clockout' }}"
                {{ $lastRecord && $lastRecord->TimeIn && !$lastRecord->TimeOut ? '' : 'disabled' }}>
                Clock Out
            </button>
        </div>

        <!-- Hours Summary -->
        <div class="p-3 bg-light border rounded">
            <p><strong>Today's Hours:</strong> <span id="today-hours">{{ $todayHoursFormatted ?? '0:00' }}</span></p>
            <p><strong>This Week's Hours:</strong> <span id="week-hours">{{ $weekHoursFormatted ?? '0:00' }}</span></p>
        </div>
    </div>

    <!-- Attendance Table (Desktop) -->
    <div class="attendance-table">
        <table class="table table-bordered table-hover desktop">
            <thead class="table-dark">
                <tr>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Computed Hours</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($employeeClocksThisweek as $clockwk)
                    <tr data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $clockwk->Notes }}">
                        <td class="text-center">
                            <span>{{ \Carbon\Carbon::parse($clockwk->TimeIn)->format('h:i A') }}</span><br>
                            <sup><b>{{ \Carbon\Carbon::parse($clockwk->TimeIn)->format('M d, Y') }}</b></sup>
                        </td>
                        <td class="text-center">
                            @if ($clockwk->TimeOut)
                                <span>{{ \Carbon\Carbon::parse($clockwk->TimeOut)->format('h:i A') }}</span><br>
                                <sup><b>{{ \Carbon\Carbon::parse($clockwk->TimeOut)->format('M d, Y') }}</b></sup>
                            @else
                                <span class="badge badge-danger">Not yet timed out</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div id="computed-hours-{{ $clockwk->ID }}">
                                <sup><b>Not yet calculated</b></sup>
                            </div>

                            @if ($clockwk->TimeIn && $clockwk->TimeOut)
                                <span class="update-computed-hours d-none" data-id="{{ $clockwk->ID }}"
                                    data-timein="{{ \Carbon\Carbon::parse($clockwk->TimeIn)->toIso8601String() }}"
                                    data-timeout="{{ \Carbon\Carbon::parse($clockwk->TimeOut)->toIso8601String() }}">
                                </span>
                            @endif
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-primary text-white" data-bs-toggle="modal"
                                data-bs-target="#editNotesModal"
                                onclick="populateNotesModal('{{ $clockwk->ID }}', '{{ $clockwk->Notes }}')">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Mobile View -->
    <div class="container mobile">
        @foreach ($employeeClocksThisweek as $index => $clockwk)
            <div class="mobile-card mb-3 shadow-sm {{ $index % 2 == 0 ? 'bg-light' : 'bg-white' }}" data-bs-toggle="tooltip"
                data-bs-placement="top" title="{{ $clockwk->Notes }}">
                <div class="card-body">
                    <p class="mb-1"><strong>Date:</strong> {{ \Carbon\Carbon::parse($clockwk->TimeIn)->format('M d, Y') }}
                    </p>
                    <p class="mb-1"><strong>Time In:</strong> {{ \Carbon\Carbon::parse($clockwk->TimeIn)->format('h:i A') }}
                    </p>
                    <p class="mb-1"><strong>Time Out:</strong>
                        @if ($clockwk->TimeOut)
                            {{ \Carbon\Carbon::parse($clockwk->TimeOut)->format('h:i A') }}
                        @else
                            <span class="badge bg-danger">Not yet timed out</span>
                        @endif
                    </p>
                    <p class="mb-1"><strong>Computed Hours:</strong></p>
                    <div id="computed-hours-{{ $clockwk->ID }}">
                        <small><strong>Not yet calculated</strong></small>
                    </div>
                    @if ($clockwk->TimeIn && $clockwk->TimeOut)
                        <span class="update-computed-hours d-none" data-id="{{ $clockwk->ID }}"
                            data-timein="{{ \Carbon\Carbon::parse($clockwk->TimeIn)->toIso8601String() }}"
                            data-timeout="{{ \Carbon\Carbon::parse($clockwk->TimeOut)->toIso8601String() }}">
                        </span>
                    @endif
                    <div class="notes-container mt-2">
                        <button class="btn btn-sm btn-primary text-white" data-bs-toggle="modal"
                            data-bs-target="#editNotesModal"
                            onclick="populateNotesModal('{{ $clockwk->ID }}', '{{ $clockwk->Notes }}')">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>