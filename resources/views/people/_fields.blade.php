@php
    $fieldPrefix = $fieldPrefix ?? 'person';
    $person = $person ?? new \App\Models\Person(['type' => 'student', 'status' => 'active']);
    $accountRequired = $accountRequired ?? false;
    $programs = \App\Models\Person::PROGRAMS;
    $selectedProgram = old('program', $person->program);
    if (filled($selectedProgram) && ! in_array($selectedProgram, $programs, true)) {
        $programs[] = $selectedProgram;
    }
@endphp

<div class="attendpro-form-grid">
    <div class="kit-field"><label for="{{ $fieldPrefix }}-institution-id">Institution ID *</label><input id="{{ $fieldPrefix }}-institution-id" name="institution_id" value="{{ old('institution_id', $person->institution_id) }}" required></div>
    <input type="hidden" name="type" value="student">
    <div class="kit-field"><label>Role</label><input value="Student" readonly aria-readonly="true"></div>
    <div class="kit-field"><label for="{{ $fieldPrefix }}-first-name">First name *</label><input id="{{ $fieldPrefix }}-first-name" name="first_name" value="{{ old('first_name', $person->first_name) }}" required></div>
    <div class="kit-field"><label for="{{ $fieldPrefix }}-middle-name">Middle name</label><input id="{{ $fieldPrefix }}-middle-name" name="middle_name" value="{{ old('middle_name', $person->middle_name) }}"></div>
    <div class="kit-field"><label for="{{ $fieldPrefix }}-last-name">Last name *</label><input id="{{ $fieldPrefix }}-last-name" name="last_name" value="{{ old('last_name', $person->last_name) }}" required></div>
    <div class="kit-field"><label for="{{ $fieldPrefix }}-suffix">Suffix</label><input id="{{ $fieldPrefix }}-suffix" name="suffix" value="{{ old('suffix', $person->suffix) }}"></div>
    <div class="kit-field"><label for="{{ $fieldPrefix }}-email">Email{{ $accountRequired ? ' *' : '' }}</label><input id="{{ $fieldPrefix }}-email" name="email" type="email" value="{{ old('email', $person->email) }}" autocomplete="email" @required($accountRequired)></div>
    <div class="kit-field"><label for="{{ $fieldPrefix }}-phone">Phone</label><input id="{{ $fieldPrefix }}-phone" name="phone" value="{{ old('phone', $person->phone) }}"></div>
    <div class="kit-field"><label for="{{ $fieldPrefix }}-department">Department</label><select id="{{ $fieldPrefix }}-department" name="department_id"><option value="">Unassigned</option>@foreach ($departments as $department)<option value="{{ $department->id }}" @selected((string) old('department_id', $person->department_id) === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></div>
    <div class="kit-field"><label for="{{ $fieldPrefix }}-program">Program</label><select id="{{ $fieldPrefix }}-program" name="program"><option value="">Select program</option>@foreach ($programs as $program)<option value="{{ $program }}" @selected($selectedProgram === $program)>{{ $program }}</option>@endforeach</select></div>
    <div class="kit-field"><label for="{{ $fieldPrefix }}-year-level">Year level</label><input id="{{ $fieldPrefix }}-year-level" name="year_level" value="{{ old('year_level', $person->year_level) }}"></div>
    <div class="kit-field"><label for="{{ $fieldPrefix }}-position">Position</label><input id="{{ $fieldPrefix }}-position" name="position" value="{{ old('position', $person->position) }}"></div>
    <div class="kit-field"><label for="{{ $fieldPrefix }}-joined-on">Joined on</label><input id="{{ $fieldPrefix }}-joined-on" name="joined_on" type="date" value="{{ old('joined_on', $person->joined_on?->toDateString()) }}"></div>
    <div class="kit-field"><label for="{{ $fieldPrefix }}-status">Status</label><select id="{{ $fieldPrefix }}-status" name="status"><option value="active" @selected(old('status', $person->status) === 'active')>Active</option><option value="inactive" @selected(old('status', $person->status) === 'inactive')>Inactive</option></select></div>
</div>
