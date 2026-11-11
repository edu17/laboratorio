<?php

use App\Models\LabProfile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

it('serves the laboratory seal as the panel brand mark, falling back to the logo', function () {
    Storage::fake('local');
    $profile = LabProfile::query()->where('is_active', true)->firstOrFail();

    $this->get(route('lab.brand-mark'))->assertNotFound();

    $profile->update(['logo_path' => UploadedFile::fake()->image('logo.png', 120, 60)->store('lab-profile-logos', 'local')]);
    $this->get(route('lab.brand-mark'))->assertOk()->assertHeader('Content-Type', 'image/png');

    $profile->update(['institutional_seal_path' => UploadedFile::fake()->image('seal.png', 80, 80)->store('lab-report-assets', 'local')]);
    expect($this->get(route('lab.brand-mark'))->getContent())
        ->toBe(Storage::disk('local')->get($profile->institutional_seal_path));
});

it('shows the brand mark on the login page', function () {
    $this->get('/admin/login')->assertOk()->assertSee(route('lab.brand-mark'), false);
});
