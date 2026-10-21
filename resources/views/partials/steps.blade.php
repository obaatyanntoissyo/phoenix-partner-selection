@php($steps = ['conditions' => '見積条件', 'estimate' => '入力', 'confirm' => '確認', 'result' => '結果', 'booking' => '面談案内'])
<nav aria-label="手続きの進み具合">
    <ol class="steps">
        @php($currentIndex = array_search($step, array_keys($steps), true))
        @foreach (array_keys($steps) as $i => $key)
            <li @class(['current' => $key === $step, 'done' => $i < $currentIndex]) @if ($key === $step) aria-current="step" @endif>{{ $steps[$key] }}</li>
        @endforeach
    </ol>
</nav>
