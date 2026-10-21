@error($name)
    <p class="field-error" id="e-{{ str_replace('.', '-', $name) }}">{{ $message }}</p>
@enderror
