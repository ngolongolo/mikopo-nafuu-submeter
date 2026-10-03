@extends('layout')

@section('content')
<h1>Audit trail</h1>
<p class="subtitle">Review important activity across applications, loans, payments, and administration.</p>

<div class="grid">
    @foreach($stats as $label => $value)
        <article class="card"><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong><small>events</small></article>
    @endforeach
</div>

<form class="panel filters" method="GET" action="/audit">
    <input name="q" value="{{ request('q') }}" placeholder="Search event, description or user">
    <button type="submit">Filter</button>
    <a class="button secondary" href="/audit">Clear</a>
</form>

<div class="panel table-wrap">
    <table>
        <thead><tr><th>Date</th><th>User</th><th>Event</th><th>Description</th><th>IP address</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td>{{ $log->created_at?->format('d M Y, H:i') }}</td>
                <td>{{ $log->user?->name ?? 'System' }}</td>
                <td><span class="badge">{{ $log->event }}</span></td>
                <td>{{ $log->description }}</td>
                <td>{{ $log->ip_address ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No audit events match the selected filters.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $logs->links() }}
@endsection
