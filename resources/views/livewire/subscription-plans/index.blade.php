<div>
    <h1>Subscription Plans</h1>

    <p><a href="{{ url('/god/subscription-plans/create') }}">+ New Plan</a></p>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Price</th>
                <th>Active</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($plans as $plan)
                <tr>
                    <td>{{ $plan->name }}</td>
                    <td>{{ $plan->slug }}</td>
                    <td>{{ $plan->priceCents !== null ? number_format($plan->priceCents / 100, 2) : '—' }}</td>
                    <td>{{ $plan->isActive ? 'Yes' : 'No' }}</td>
                    <td><a href="{{ url('/god/subscription-plans/'.$plan->id.'/edit') }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="5">No subscription plans yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
