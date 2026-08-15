<div class="election-list-item__milestone election-list-item__milestone--{{ $milestone->tone }}">
    <span class="election-list-item__milestone-label">{{ $milestone->label }}</span>
    @if ($milestone->at !== null)
        <time datetime="{{ $milestone->machineAt() }}">{{ $milestone->formattedAt() }}</time>
    @endif
</div>
