@extends('layout')

@section('content')
<h1>Team & access</h1>
<p class="subtitle">Customers self-register. Administrators create staff, supplier, and financier accounts.</p>

<div class="grid">
    @foreach($stats as $label => $value)
        <article class="card"><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong><small>users</small></article>
    @endforeach
</div>

<form class="panel filters" method="GET" action="/users">
    <input name="q" value="{{ request('q') }}" placeholder="Search name or email">
    <select name="role">
        <option value="">All roles</option>
        @foreach(['customer', 'supplier', 'officer', 'financier', 'admin'] as $role)
            <option value="{{ $role }}" @selected(request('role') === $role)>{{ ucfirst($role) }}</option>
        @endforeach
    </select>
    <button type="submit">Filter</button>
    <a class="button secondary" href="/users">Clear</a>
</form>

<div class="panel table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Financier</th><th>Created</th></tr></thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td><span class="badge">{{ ucfirst($user->role) }}</span></td>
                <td>{{ $user->financier?->name ?? '—' }}</td>
                <td>{{ $user->created_at?->format('d M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No users match the selected filters.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $users->links() }}

<section class="panel">
    <h2>Create staff account</h2>
    <form method="POST" action="/users" class="form-grid">
        @csrf
        <label>Name<input name="name" value="{{ old('name') }}" required></label>
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
        <div class="notice">A secure temporary password will be generated and emailed to the user.</div>
        <label>Role
            <select name="role" required>
                @foreach(['officer', 'supplier', 'financier', 'admin'] as $role)
                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst($role) }}</option>
                @endforeach
            </select>
        </label>
        <label>Financier (financier role only)
            <select name="financier_id">
                <option value="">Not assigned</option>
                @foreach($financiers as $financier)
                    <option value="{{ $financier->id }}" @selected((string) old('financier_id') === (string) $financier->id)>{{ $financier->name }}</option>
                @endforeach
            </select>
        </label>
        <div><button type="submit">Create account & email credentials</button></div>
    </form>
</section>
@endsection
