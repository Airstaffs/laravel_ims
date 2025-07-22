<div class="tab-pane fade" id="userprofile" role="tabpanel" aria-labelledby="userprofile-tab">
    <ul class="nav list-unstyled" id="accountTab" role="tablist">
        <li role="presentation">
            <button class="btn btn-account active" id="changepass-tab" data-bs-toggle="tab"
                data-bs-target="#changepass" type="button" role="tab" aria-controls="changepass"
                aria-selected="true">
                Change Password
            </button>
        </li>
        <li role="presentation">
            <button class="btn btn-account" id="timezone-tab" data-bs-toggle="tab"
                data-bs-target="#timezone" type="button" role="tab" aria-controls="timezone"
                aria-selected="false">
                Timezone Settings
            </button>
        </li>
    </ul>

    <div class="tab-content" id="accountTabContent">
        <div class="tab-pane fade show active" id="changepass" role="tabpanel"
            aria-labelledby="changepass-tab">
            <form action="{{ route('update-password') }}" method="POST" class="changePwdForm">
                @csrf
                <fieldset>
                    <label for="password" class="form-label">New Password</label>
                    <div class="has-toggle">
                        <input type="password" class="form-control" id="newpassword"
                            name="password" placeholder="Enter password" required>
                        <i role="button" class="bi bi-eye toggle-password"
                            id="toggleNewPassword" data-target="#password"></i>
                    </div>
                </fieldset>

                <hr class="dashed m-0">

                <fieldset>
                    <label for="password_confirmation" class="form-label">Confirm
                        Password</label>
                    <div class="has-toggle">
                        <input type="password" class="form-control" id="confirmpassword"
                            name="password_confirmation" placeholder="Confirm password"
                            required>
                        <i role="button" class="bi bi-eye toggle-password"
                            id="toggleConfirmPassword" data-target="#password"></i>
                    </div>
                </fieldset>

                <button type="submit" class="btn btn-primary btn-process text-white">Change
                    Password</button>
            </form>
        </div>

        <div class="tab-pane fade" id="timezone" role="tabpanel" aria-labelledby="timezone-tab">
            <form id="timezoneForm" class="timezoneForm">
                @csrf
                @php
                    $allTimezones = collect(timezone_identifiers_list())
                        ->map(function ($tz) {
                            $dt = new DateTime('now', new DateTimeZone($tz));
                            $offset = $dt->getOffset();
                            $hours = intdiv($offset, 3600);
                            $minutes = abs($offset % 3600) / 60;
                            $sign = $offset >= 0 ? '+' : '-';
                            $formattedOffset = sprintf("UTC %s%02d:%02d", $sign, abs($hours), $minutes);
                            return [
                                'tz' => $tz,
                                'offset' => $offset,
                                'label' => "($formattedOffset) $tz"
                            ];
                        });

                    $grouped = $allTimezones->sortBy('offset')->groupBy('offset');

                    $limitedTimezones = $grouped->map(function ($group) {
                        return $group->take(2);
                    })->flatten(1);

                    if (!$limitedTimezones->pluck('tz')->contains('America/Los_Angeles')) {
                        $la = $allTimezones->firstWhere('tz', 'America/Los_Angeles');
                        $limitedTimezones->push($la);
                    }

                    $timezones = $limitedTimezones->sortBy('offset');
                @endphp

                <!-- Timezone Dropdown -->
                <fieldset>
                    <label for="usertimezone">Preferred Timezone</label>
                    <select class="form-select" id="usertimezone" name="usertimezone" required>
                        @foreach($timezones as $tz)
                            <option value="{{ $tz['tz'] }}" {{ ($timezone_setting['usertimezone'] ?? 'UTC') === $tz['tz'] ? 'selected' : '' }}>
                                {{ $tz['label'] }}
                            </option>
                        @endforeach
                    </select>

                    <div class="has-checkbox">
                        <input class="form-check-input" type="checkbox" id="auto_sync"
                            name="auto_sync" {{ $timezone_setting['auto_sync'] ?? false ? 'checked' : '' }}>
                        <label class="form-check-label" for="auto_sync">
                            Automatically Sync Timezone
                        </label>
                    </div>
                </fieldset>

                <button type="submit" class="btn btn-process">Update Timezone</button>
            </form>

            <!-- Flash success box -->
            <div id="timezoneSuccessBox"
                class="alert alert-success alert-dismissible fade show mt-3 d-none"
                role="alert">
                <span id="timezoneSuccessMsg"></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"
                    aria-label="Close"></button>
            </div>
        </div>
    </div>
</div>