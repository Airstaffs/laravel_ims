<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Complete Your Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center" style="min-height:80vh;">
  <div class="card shadow" style="max-width:720px;width:100%;">
    <div class="card-body p-4">
      <h4 class="mb-3">Complete Your Account Profile</h4>
      <p class="text-muted">All fields are required.</p>

      <form id="acctForm" class="row g-3">
        @csrf
        <div class="col-md-6">
          <label class="form-label">Full Name</label>
          <input required name="full_name" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label">Work Email</label>
          <input required type="email" name="work_email" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label">Contact Phone</label>
          <input required name="contact_phone" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label">Birthdate</label>
          <input required type="date" name="birthdate" class="form-control">
        </div>
        <div class="col-12">
          <label class="form-label">Address</label>
          <textarea required name="address" rows="2" class="form-control"></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Emergency Contact Name</label>
          <input required name="ice_name" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label">Emergency Contact Relationship</label>
          <input required name="ice_relationship" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label">Emergency Contact Phone</label>
          <input required name="ice_phone" class="form-control">
        </div>

        <div class="col-12">
          <button class="btn btn-primary w-100" type="submit">
            Save & Continue
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(async function () {
  // 1) Prefill
  const res = await fetch("{{ route('account.details') }}");
  const data = await res.json();
  const f = document.getElementById('acctForm');

  const set = (name, val) => f.elements[name] && (f.elements[name].value = val || '');
  set('full_name', data?.profile?.full_name ?? data?.user?.username ?? '');
  set('work_email', data?.profile?.work_email ?? data?.user?.email ?? '');
  set('contact_phone', data?.profile?.contact_phone ?? '');
  set('birthdate', data?.profile?.birthdate ?? '');
  set('address', data?.profile?.address ?? '');
  set('ice_name', data?.profile?.ice_name ?? '');
  set('ice_relationship', data?.profile?.ice_relationship ?? '');
  set('ice_phone', data?.profile?.ice_phone ?? '');

  // 2) Submit
  f.addEventListener('submit', async (e) => {
    e.preventDefault();

    const fd = new FormData(f);
    const body = new URLSearchParams(fd);

    const r = await fetch("{{ route('account.update-details') }}", {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      body
    });

    if (r.status === 422) {
      const j = await r.json();
      alert('Please complete all required fields.');
      return;
    }

    const j = await r.json();
    if (j.ok) {
      // Server flips first_login=0 inside controller when it was first login
      window.location.href = "{{ route('dashboard.system') }}";
    } else {
      alert(j.message || 'Update failed.');
    }
  });
})();
</script>
</body>
</html>
