<div class="tab-pane fade show active" id="design" role="tabpanel" aria-labelledby="design-tab">
    <h3 class="text-center">Title & Design Settings</h3>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('update.system.design') }}" method="POST" class="tblnDsgnForm" enctype="multipart/form-data">
        @csrf
        @method('POST')

        <!-- Site Title -->
        <fieldset>
            <label for="siteTitle" class="form-label">Site Title</label>
            <input type="text" class="form-control" id="siteTitle" name="site_title" placeholder="Enter site title"
                required>
        </fieldset>

        <hr class="dashed m-0">

        <!-- Theme Color -->
        <fieldset>
            <label for="themeColor" class="form-label">Theme Color</label>
            <input type="color" class="form-control" id="themeColor" name="theme_color" value="#007bff" required>
        </fieldset>

        <hr class="dashed m-0">

        <!-- Logo Upload -->
        <fieldset>
            <label for="logoUpload" class="form-label">Upload Logo</label>
            <input type="file" class="form-control" id="logoUpload" name="logo">
            <div id="currentLogo" class="mt-2"></div>
        </fieldset>

        <button type="submit" class="btn btn-process">Save Changes</button>
    </form>
</div>