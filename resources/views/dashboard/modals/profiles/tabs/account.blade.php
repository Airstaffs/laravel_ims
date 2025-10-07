<div class="tab-pane fade" id="userprofile" role="tabpanel" aria-labelledby="userprofile-tab">
    <ul class="nav list-unstyled" id="accountTab" role="tablist">
        <li role="presentation">
            <button class="btn btn-account active" id="accountdetails-tab" data-bs-toggle="tab"
            data-bs-target="#accountdetails" type="button" role="tab"
            aria-controls="accountdetails" aria-selected="false">
            Account Details
            </button>
        </li>
        <li role="presentation">
            <button class="btn btn-account" id="changepass-tab" data-bs-toggle="tab"
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
            <form id="timezoneForm" class="timezoneForm" action="{{ route('update-timezone') }}" method="POST">
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

<div class="tab-pane fade" id="accountdetails" role="tabpanel" aria-labelledby="accountdetails-tab">
  <form id="accountDetailsForm">
    @csrf

    <div class="row g-3">
      <!-- READ-ONLY -->
      <div class="col-md-4">
        <label class="form-label">Username</label>
        <input id="ad_username" type="text" class="form-control" disabled>
      </div>
      <div class="col-md-4">
        <label class="form-label">User Type</label>
        <input id="ad_usertype" type="text" class="form-control" disabled>
      </div>
      <div class="col-md-4">
        <label class="form-label">Account Type</label>
        <input id="ad_accounttype" type="text" class="form-control" disabled>
      </div>

      <hr class="my-2">

      <!-- Editable -->
      <div class="col-md-6">
        <label class="form-label">Full Name</label>
        <input id="full_name" name="full_name" type="text" class="form-control">
      </div>
      <div class="col-md-6">
        <label class="form-label">Work Email</label>
        <input id="work_email" name="work_email" type="email" class="form-control">
      </div>
      <div class="col-md-6">
        <label class="form-label">Contact Number</label>
        <input id="contact_phone" name="contact_phone" type="number" class="form-control">
      </div>
      <div class="col-md-6">
        <label class="form-label">Birthdate</label>
        <input id="birthdate" name="birthdate" type="date" class="form-control">
      </div>
      <div class="col-12">
        <label class="form-label">Address</label>
        <textarea id="address" name="address" class="form-control" rows="2"></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label">Emergency Contact Person</label>
        <input id="ice_name" name="ice_name" type="text" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Relationship</label>
        <input id="ice_relationship" name="ice_relationship" type="text" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Emergency Contact Number</label>
        <input id="ice_phone" name="ice_phone" type="number" class="form-control">
      </div>
    </div>

    <div class="mt-3 d-flex gap-2 align-items-center">
      <button type="submit" class="btn btn-primary btn-process text-white">Save Details</button>
      <div id="ad_flash" class="ms-2"></div>
    </div>
  </form>
</div>
    </div>
</div>

<!-- Laravel-generated variable injected to JS context -->
<script>
    const updateTimezoneUrl = @json(route('update-timezone'));
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tabBtn = document.getElementById('accountdetails-tab');
  const form   = document.getElementById('accountDetailsForm');
  const flash  = document.getElementById('ad_flash');

  // Fetch details once when tab is opened
  tabBtn.addEventListener('shown.bs.tab', () => {
    if (form.dataset.loaded) return;
    fetch("{{ route('account.details') }}")
      .then(r => r.json())
      .then(data => {
        document.getElementById('ad_username').value    = data.user.username;
        document.getElementById('ad_usertype').value    = data.user.office_role;
        document.getElementById('ad_accounttype').value = data.user.accounttype;

        document.getElementById('full_name').value      = data.profile.full_name;
        document.getElementById('work_email').value     = data.profile.work_email;
        document.getElementById('contact_phone').value  = data.profile.contact_phone;
        document.getElementById('birthdate').value      = data.profile.birthdate;
        document.getElementById('address').value        = data.profile.address;
        document.getElementById('ice_name').value       = data.profile.ice_name;
        document.getElementById('ice_relationship').value = data.profile.ice_relationship;
        document.getElementById('ice_phone').value      = data.profile.ice_phone;

        form.dataset.loaded = '1';
      })
      .catch(err => {
        flash.innerHTML = "<span class='text-danger'>Failed to load details</span>";
        console.error(err);
      });
  });

  // Handle form submit via AJAX
  form.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(form);
    fetch("{{ route('account.update-details') }}", {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': fd.get('_token') },
      body: fd
    })
    .then(r => r.json())
    .then(res => {
      if (res.ok) {
        flash.innerHTML = "<span class='text-success'>" + res.message + "</span>";
      } else {
        flash.innerHTML = "<span class='text-danger'>Validation failed</span>";
        console.log(res.errors);
      }
    })
    .catch(err => {
      flash.innerHTML = "<span class='text-danger'>Error saving</span>";
      console.error(err);
    });
  });
});
</script>

<!-- External JS that uses that variable -->
<script src="{{ asset('js/account-timezone.js') }}"></script>
