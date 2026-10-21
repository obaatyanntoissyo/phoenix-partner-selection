@if ($errors->any())
    <div class="error-summary" role="alert" tabindex="-1" id="error-summary">
        <p><strong>入力内容を確認してください（{{ $errors->count() }}件）</strong></p>
        <ul>
            @foreach ($errors->messages() as $field => $messages)
                <li><a href="#f-{{ str_replace('.', '-', $field) }}">{{ $messages[0] }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
