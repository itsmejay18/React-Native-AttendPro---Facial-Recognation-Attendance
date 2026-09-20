@extends('layouts.dashboard')

@php($editing = $person->exists)
@section('title', ($editing ? 'Edit' : 'Add').' Profile | AttendPro')
@section('page-title', $editing ? 'Edit Profile' : 'Add Profile')

@section('content')
    <div class="kit-dashboard-content attendpro-admin-page">
        <section class="kit-dashboard-section-head">
            <div><h2>{{ $editing ? 'Update '.$person->full_name : 'Register a person' }}</h2><p>Create the Laravel profile used for facial enrollment and attendance matching.</p></div>
            <a class="kit-button secondary" href="{{ route('people.index', ['type' => old('type', $person->type)]) }}">Back to directory</a>
        </section>

        <form class="kit-dashboard-panel attendpro-record-form" method="POST" action="{{ $editing ? route('people.update', $person) : route('people.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif
            @include('people._fields', ['fieldPrefix' => 'person', 'accountRequired' => (bool) $person->user])
            @unless ($editing)
                <label class="attendpro-check">
                    <input type="checkbox" name="enroll_face" value="1" @checked(old('enroll_face', true))>
                    Continue to camera-based face enrollment after creating this profile.
                </label>
            @endunless
            <div class="attendpro-form-actions"><button class="kit-button primary" type="submit">{{ $editing ? 'Save changes' : 'Create profile and continue' }}</button></div>
        </form>

        @if ($editing)
            @php($sampleProfiles = $person->activeFacialProfiles->filter(fn ($profile) => filled($profile->sample_image_path)))
            <section class="kit-dashboard-panel attendpro-facial-sample-gallery">
                <div class="kit-dashboard-panel-head"><div><h3>Private enrollment samples</h3><span>Visible only to authorized administrators</span></div><span>{{ $sampleProfiles->count() }} saved</span></div>
                @if ($sampleProfiles->isNotEmpty())
                    <div class="attendpro-enrollment-sample-grid attendpro-saved-enrollment-grid">
                        @foreach ($sampleProfiles as $index => $profile)
                            <img src="{{ route('people.facial-samples.show', [$person, $profile]) }}" alt="Private enrollment sample {{ $index + 1 }} for {{ $person->full_name }}">
                        @endforeach
                    </div>
                    <p class="kit-muted">These consented samples are stored on the private Laravel disk, never in the public web directory.</p>
                @else
                    <p class="kit-muted">No saved sample photos are available for this legacy profile. Select Replace face to capture and save a new private sample set.</p>
                @endif
            </section>

            <section class="kit-dashboard-panel attendpro-danger-zone">
                <div>
                    <h3>{{ $person->is_face_enrolled ? 'Replace facial profile' : 'Enroll facial profile' }}</h3>
                    <p class="kit-muted">Open the local camera scanner with this person’s institutional ID already selected.</p>
                </div>
                <a class="kit-button secondary" href="{{ route('recognition', ['enroll' => $person->institution_id]) }}#face-enrollment">
                    <i class="ph ph-user-focus" aria-hidden="true"></i>{{ $person->is_face_enrolled ? 'Replace face' : 'Enroll face' }}
                </a>
            </section>

            <section class="kit-dashboard-panel attendpro-danger-zone">
                <div><h3>Archive profile</h3><p class="kit-muted">Archived people no longer participate in recognition or attendance processing.</p></div>
                <form method="POST" action="{{ route('people.destroy', $person) }}" onsubmit="return confirm('Archive this profile?')">@csrf @method('DELETE')<button class="kit-button danger" type="submit">Archive</button></form>
            </section>
        @endif
    </div>
@endsection
