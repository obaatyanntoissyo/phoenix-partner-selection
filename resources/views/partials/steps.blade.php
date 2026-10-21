@php($steps = ['conditions' => '見積条件', 'estimate' => '入力', 'confirm' => '確認', 'result' => '結果', 'booking' => '面談案内'])
<nav aria-label="手続きの進み具合">
    <ol class="steps">
        @foreach ($steps as $key => $label)
            <li @class(['current' => $key === $step]) @if ($key === $step) aria-current="step" @endif>{{ $label }}</li>
        @endforeach
    </ol>
</nav>
