@extends('layout')

@section('content')
<div class="section-head"><h1>Team & access</h1><a class="button" href="/suppliers">Onboard suppliers →</a></div>
<p class="subtitle">Administrators create internal and financier accounts here. Supplier users are managed from the dedicated Suppliers page.</p>

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
        <thead><tr><th>Name</th><th>Email</th><th>ID details</th><th>Role</th><th>Financier</th><th>Created</th></tr></thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ $user->id_type ? ucwords(str_replace('_', ' ', $user->id_type)) : 'Missing' }}<br><small>{{ $user->id_number ?? '—' }}</small></td>
                <td><span class="badge">{{ ucfirst($user->role) }}</span></td>
                <td>{{ $user->financier?->name ?? '—' }}</td>
                <td>{{ $user->created_at?->format('d M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No users match the selected filters.</td></tr>
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
        <label>ID type
            <select name="id_type" required>
                <option value="">Select ID type</option>
                @foreach(['national_id' => 'National ID', 'driving_license' => 'Driving License', 'passport' => 'Passport', 'voters_id' => 'Voters ID'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('id_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>ID number<input name="id_number" value="{{ old('id_number') }}" required></label>
        <div class="notice">A secure temporary password will be generated and emailed to the user.</div>
        <label>Role
            <select name="role" required>
                @foreach(['officer', 'financier', 'admin'] as $role)
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
