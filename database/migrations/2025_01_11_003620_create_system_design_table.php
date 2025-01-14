<?php

public function update(Request $request)
{
    // Validate the form input
    $request->validate([
        'site_title' => 'required|string|max:255',
        'theme_color' => 'required|string|max:7|regex:/^#[a-fA-F0-9]{6}$/',
        'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
    ]);

    // Retrieve the existing system design record, or create a default one if none exists
    $systemDesign = SystemDesign::first();

    if (!$systemDesign) {
        // Create a default system design record if it doesn't exist
        $systemDesign = SystemDesign::create([
            'site_title' => 'Default Title',
            'theme_color' => '#007bff',
            'logo' => null,
        ]);
    }

    // Update fields
    $systemDesign->site_title = $request->site_title;
    $systemDesign->theme_color = $request->theme_color;

    // Handle logo upload
    if ($request->hasFile('logo')) {
        // Delete the old logo if it exists
        if ($systemDesign->logo) {
            Storage::delete($systemDesign->logo);
        }

        // Store the new logo
        $path = $request->file('logo')->store('logos', 'public');
        $systemDesign->logo = $path;
    }

    // Save the changes
    $systemDesign->save();

    return back()->with('success', 'System design updated successfully!');
}