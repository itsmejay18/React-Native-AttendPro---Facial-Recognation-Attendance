<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\FacialProfile;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class PersonController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(Person::DIRECTORY_TYPES)],
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'face' => ['nullable', Rule::in(['enrolled', 'not_enrolled'])],
            'view' => ['nullable', Rule::in(['list', 'grid'])],
        ]);

        $people = Person::query()->with(['department', 'activeFacialProfiles'])
            ->whereIn('type', Person::DIRECTORY_TYPES)
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['face'] ?? null) === 'enrolled', fn ($query) => $query->whereHas('activeFacialProfiles'))
            ->when(($filters['face'] ?? null) === 'not_enrolled', fn ($query) => $query->whereDoesntHave('activeFacialProfiles'))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('institution_id', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')->orderBy('first_name')->paginate(20)->withQueryString();

        return view('people.index', [
            'people' => $people,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedType' => $filters['type'] ?? null,
            'enrollmentShots' => $this->enrollmentShots($people),
            'view' => $filters['view'] ?? 'grid',
        ]);
    }

    /**
     * Lists restored enrollment images on the private disk for the given
     * people (up to 15 per person, oldest session first). These files exist
     * even before facial profiles are rebuilt via re-extract.
     *
     * @param  iterable<\App\Models\Person>  $people
     * @return array<int, array<int, array{url: string, label: string}>>
     */
    private function enrollmentShots(iterable $people): array
    {
        $shots = [];
        foreach ($people as $person) {
            $files = glob(storage_path("app/private/facial-enrollments/{$person->id}/*/*.jpg")) ?: [];
            sort($files);
            $shots[$person->id] = collect(array_slice($files, 0, 15))->map(function ($path) use ($person): array {
                $file = basename($path);
                $session = basename(dirname($path));

                return [
                    'url' => route('people.enrollment-shots.show', [
                        'person' => $person,
                        'session' => $session,
                        'file' => $file,
                    ]),
                    'label' => str_replace(['sample-', '.jpg'], '', $file),
                ];
            })->all();
        }

        return $shots;
    }

    public function create(Request $request): RedirectResponse
    {
        $type = in_array($request->query('type'), Person::DIRECTORY_TYPES, true) ? $request->query('type') : 'student';

        return redirect()->route('people.index', ['type' => $type])
            ->with('open_modal', 'register-person-modal');
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $this->validated($request);
        $password = $validated['password'];
        unset($validated['password']);

        $person = DB::transaction(function () use ($validated, $password): Person {
            $person = Person::query()->create($validated);
            $role = Role::findOrCreate($person->type, 'web');

            $user = User::query()->create([
                'person_id' => $person->id,
                'name' => $person->full_name,
                'email' => $person->email,
                'password' => $password,
                // The account becomes active only after facial enrollment succeeds.
                'is_active' => false,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles($role);

            return $person->setRelation('user', $user);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Profile and login account created. Complete facial enrollment to activate the account.',
                'data' => [
                    'person' => [
                        'id' => $person->id,
                        'institution_id' => $person->institution_id,
                        'full_name' => $person->full_name,
                        'type' => $person->type,
                        'account_email' => $person->user->email,
                        'account_active' => false,
                    ],
                    'redirect_url' => route('people.index', ['type' => $person->type]),
                ],
            ], 201);
        }

        if ($request->boolean('enroll_face')) {
            return redirect(route('recognition', ['enroll' => $person->institution_id]).'#face-enrollment')
                ->with('success', 'Profile created. Capture the person’s face to finish enrollment.');
        }

        return redirect()->route('people.index', ['type' => $person->type])->with('success', 'Profile created successfully.');
    }

    public function edit(Person $person): View
    {
        return view('people.form', [
            'person' => $person->load(['activeFacialProfiles', 'user']),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function facialSample(Person $person, FacialProfile $facialProfile)
    {
        abort_unless($facialProfile->person_id === $person->id, 404);
        abort_unless($facialProfile->sample_image_path, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($facialProfile->sample_image_path), 404);

        return response()->file($disk->path($facialProfile->sample_image_path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Serves one restored enrollment image from the private disk. The route
     * constraints already limit the session to a UUID and the file to the
     * sample-NN.jpg pattern, so no traversal is possible.
     */
    public function enrollmentShot(Person $person, string $session, string $file)
    {
        $path = "facial-enrollments/{$person->id}/{$session}/{$file}";
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $person->loadMissing('user');
        $validated = $this->validated($request, $person);
        unset($validated['password']);

        DB::transaction(function () use ($person, $validated): void {
            $person->update($validated);

            if ($person->user) {
                $person->user->update([
                    'name' => $person->full_name,
                    'email' => $person->email,
                    'is_active' => $person->status === 'active' && $person->activeFacialProfiles()->exists(),
                ]);
                $person->user->syncRoles(Role::findOrCreate($person->type, 'web'));
            }
        });

        return redirect()->route('people.index', ['type' => $person->type])->with('success', 'Profile updated successfully.');
    }

    public function destroy(Person $person): RedirectResponse
    {
        $type = $person->type;
        $person->user?->update(['is_active' => false]);
        $person->update(['status' => 'inactive']);
        $person->delete();

        return redirect()->route('people.index', ['type' => $type])->with('success', 'Profile archived.');
    }

    private function validated(Request $request, ?Person $person = null): array
    {
        $person?->loadMissing('user');
        $emailRequired = $person === null || $person->user !== null;

        return $request->validate([
            'institution_id' => ['required', 'string', 'max:60', Rule::unique('people')->ignore($person)],
            'type' => ['required', Rule::in(Person::DIRECTORY_TYPES)],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => [
                $emailRequired ? 'required' : 'nullable',
                'email',
                'max:255',
                Rule::unique('people')->ignore($person),
                Rule::unique('users')->ignore($person?->user),
            ],
            'password' => [$person ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:40'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'program' => ['nullable', 'string', 'max:255'],
            'year_level' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'joined_on' => ['nullable', 'date'],
        ]);
    }
}
