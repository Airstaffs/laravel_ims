<div class="modal-body">
    {{-- Tabs Navigation --}}
    @include('dashboard.modals.settings.settings-tab')

    {{-- Tabs Content --}}
    <div class="tab-content" id="settingsTabContent">
        @include('dashboard.modals.settings.tabs.design')
        @include('dashboard.modals.settings.tabs.user')
        @include('dashboard.modals.settings.tabs.store')
        @include('dashboard.modals.settings.tabs.privilege')
        @include('dashboard.modals.settings.tabs.timerecord')
        @include('dashboard.modals.settings.tabs.userlogs')
    </div>
</div>