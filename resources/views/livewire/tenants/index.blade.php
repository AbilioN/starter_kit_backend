<div>
    <h1>Tenants</h1>

    <p><a href="{{ url('/god/tenants/create') }}">+ New Tenant</a></p>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Subdomain</th>
                <th>Status</th>
                <th>Created Via</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tenants as $tenant)
                <tr>
                    <td>{{ $tenant->name }}</td>
                    <td>{{ $tenant->subdomain }}</td>
                    <td>{{ $tenant->status }}</td>
                    <td>{{ $tenant->createdVia }}</td>
                    <td><a href="{{ url('/god/tenants/'.$tenant->id) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5">No tenants yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
