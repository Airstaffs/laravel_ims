<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            @include('dashboard.modals.settings.settings-modal-header')
            @include('dashboard.modals.settings.settings-modal-body')
        </div>
    </div>
</div>

@include('dashboard.modals.settings.user-list')
@include('dashboard.modals.settings.delete-user')
@include('dashboard.modals.settings.edit-user')
@include('dashboard.modals.settings.add-store')
@include('dashboard.modals.settings.edit-store')