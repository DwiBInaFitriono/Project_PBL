@props(['name', 'label', 'type' => 'text', 'hint' => null, 'errorBag' => 'default'])

@php
    $id = $attributes->get('id', $name);
    $isPassword = $type === 'password';
    $error = $errors->getBag($errorBag)->first($name);
    $describedBy = implode(' ', array_filter([
        $hint ? $id.'-hint' : null,
        $error ? $id.'-error' : null,
    ]));
@endphp

<div class="field">
    <label for="{{ $id }}">{{ $label }}</label>
    @if ($isPassword)
        <div class="password-field">
    @endif
    <input
        {{ $attributes->merge(['id' => $id, 'name' => $name, 'type' => $type]) }}
        @unless ($isPassword) value="{{ is_string(old($name)) ? old($name) : '' }}" @endunless
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
    >
    @if ($isPassword)
            <button
                type="button"
                class="password-toggle"
                data-password-toggle="{{ $id }}"
                aria-label="Tampilkan {{ mb_strtolower($label) }}"
                aria-pressed="false"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </button>
        </div>
    @endif
    @if ($hint)
        <p class="field-hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="field-error" role="alert">{{ $error }}</p>
    @endif
</div>
