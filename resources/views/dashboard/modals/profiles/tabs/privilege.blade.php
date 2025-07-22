<div class="tab-pane fade show" id="myprivileges" role="tabpanel" aria-labelledby="myprivileges-tab">
    <h5 style="font-weight: bold; color: #333;">Account Privileges</h5>

    <div class="d-flex justify-content-start align-items-center gap-2" style="flex-wrap: wrap;">
        @php
            $privileges = [
                ['key' => 'order', 'label' => 'Order'],
                ['key' => 'unreceived', 'label' => 'Unreceived'],
                ['key' => 'receiving', 'label' => 'Receiving'],
                ['key' => 'labeling', 'label' => 'Labeling'],
                ['key' => 'testing', 'label' => 'Testing'],
                ['key' => 'cleaning', 'label' => 'Cleaning'],
                ['key' => 'packing', 'label' => 'Packing'],
                ['key' => 'stockroom', 'label' => 'Stockroom'],
                ['key' => 'validation', 'label' => 'Validation'],
                ['key' => 'fnsku', 'label' => 'FNSKU'],
                ['key' => 'asinlist', 'label' => 'ASIN List'],
                ['key' => 'productionarea', 'label' => 'Production Area'],
                ['key' => 'returnscanner', 'label' => 'Return Scanner'],
                ['key' => 'fbmorder', 'label' => 'FBM Order'],
                ['key' => 'notfound', 'label' => 'Not Found'],
                ['key' => 'asinoption', 'label' => 'Asin Option'],
                ['key' => 'houseage', 'label' => 'Houseage'],
                ['key' => 'printer', 'label' => 'Printer'],
            ];
        @endphp

        @foreach($privileges as $privilege)
            <div class="privileges__container">
                <input type="checkbox" id="{{ $privilege['key'] }}" name="{{ $privilege['key'] }}" value="1" disabled>
                <label for="{{ $privilege['key'] }}" style="font-size: 16px; font-weight: 500; color: #000;">
                    {{ $privilege['label'] }}
                </label>
            </div>
        @endforeach
    </div>
</div>

<script>
    const privilegesRoute = "{{ route('myprivileges') }}";
</script>