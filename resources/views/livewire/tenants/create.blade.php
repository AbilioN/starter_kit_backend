<div>
    <h1>New Tenant</h1>

    @if ($error)
        <p class="error">{{ $error }}</p>
    @endif

    <form wire:submit="save">
        <div>
            <label for="name">Name</label>
            <input type="text" id="name" wire:model="name" required>
            @error('name') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div>
            <label for="subdomain">Subdomain</label>
            <input type="text" id="subdomain" wire:model="subdomain" required>
            @error('subdomain') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div>
            <label for="planId">Subscription Plan</label>
            <select id="planId" wire:model="planId">
                <option value="">— none —</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="adminEmail">Owner Admin Email</label>
            <input type="email" id="adminEmail" wire:model="adminEmail" required>
            @error('adminEmail') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div>
            <label for="adminPassword">Owner Admin Password</label>
            <input type="password" id="adminPassword" wire:model="adminPassword" required>
            @error('adminPassword') <div class="error">{{ $message }}</div> @enderror
        </div>

        <button type="submit">Create Tenant</button>
    </form>
</div>
