<details
    @class(['security-source-snippet', 'security-source-snippet--vulnerable' => ! $snippet['production']])
    @if ($open ?? false) open @endif
    @if (! empty($scope)) data-comparison-code-scope="{{ $scope }}" @endif
    @if (! empty($role)) data-comparison-code-role="{{ $role }}" @endif
    @if (($mode ?? 'secure') === 'secure') data-code-secure @else data-code-vulnerable hidden @endif
>
    <summary>
        <span>
            <strong>{{ $snippet['label'] }}</strong>
            <small>{{ $snippet['production'] ? 'Exact code used by the real VoteTrust application' : 'Demo-only version with the selected security controls removed' }}</small>
        </span>
        <span class="security-source-snippet__badge">{{ $snippet['production'] ? 'Exact secure source' : 'Security removed' }}</span>
    </summary>
    <p class="security-code__file">
        {{ $snippet['file'] }}@if ($snippet['startLine'] !== null):{{ $snippet['startLine'] }}&ndash;{{ $snippet['endLine'] }}@endif
    </p>
    <pre><code>{{ $snippet['code'] }}</code></pre>
</details>
