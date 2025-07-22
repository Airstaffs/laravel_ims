<div class="modal-body">
    {{-- Tabs Navigation --}}
    @include('dashboard.modals.profiles.profiles-tab')

    {{-- Tabs Content --}}
    <div class="tab-content" id="profileTabContent">
        @include('dashboard.modals.profiles.tabs.attendance')
        @include('dashboard.modals.profiles.tabs.account')
        @include('dashboard.modals.profiles.tabs.record')
        @include('dashboard.modals.profiles.tabs.privilege')
    </div>
</div>