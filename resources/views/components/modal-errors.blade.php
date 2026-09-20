@props(['modal'])

@if (old('_modal') === $modal && $errors->any())
    <div class="attendpro-modal-errors" role="alert">
        <i class="ph ph-warning-circle" aria-hidden="true"></i>
        <div>
            <strong>Please review the information below.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
