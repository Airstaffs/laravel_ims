<div class="tab-pane fade show active" id="design" role="tabpanel" aria-labelledby="design-tab">
    <h3 class="text-center">Title & Design Settings</h3>

    <form action="{{ route('update.system.design') }}" method="POST" class="tblnDsgnForm" enctype="multipart/form-data">
        @csrf
        @method('POST')
        <!-- Site Title -->
        <fieldset>
            <label for="siteTitle" class="form-label">Site Title</label>
            <input type="text" class="form-control" id="siteTitle" name="site_title" placeholder="Enter site title"
                value="{{ $systemDesign->site_title ?? '' }}" required>
        </fieldset>

        <hr class="dashed m-0">

        <!-- Theme Color -->
        <fieldset>
            <label for="themeColor" class="form-label">Theme Color</label>
            <input type="color" class="form-control" id="themeColor" name="theme_color"
                value="{{ $systemDesign->theme_color ?? '#007bff' }}" required>
        </fieldset>

        <hr class="dashed m-0">

        <!-- Logo Upload -->
        <fieldset>
            <label for="logoUpload" class="form-label">Upload Logo</label>
            <input type="file" class="form-control" id="logoUpload" name="logo">
            @if (!empty($systemDesign->logo))
                <p>Current Logo: <img src="{{ asset('storage/' . $systemDesign->logo) }}" alt="Logo" width="100"></p>
            @endif
        </fieldset>
        <button type="submit" class="btn btn-process">Save Changes</button>
    </form>
</div>