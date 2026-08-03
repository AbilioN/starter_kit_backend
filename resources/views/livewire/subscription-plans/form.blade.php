<div>
    <h1>{{ $planId ? 'Edit' : 'New' }} Subscription Plan</h1>

    <form wire:submit="save">
        <div>
            <label for="name">Name</label>
            <input type="text" id="name" wire:model="name" required>
            @error('name') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div>
            <label for="slug">Slug</label>
            <input type="text" id="slug" wire:model="slug" required {{ $planId ? 'readonly' : '' }}>
            @error('slug') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div>
            <label for="priceCents">Price (cents)</label>
            <input type="number" id="priceCents" wire:model="priceCents" min="0">
            @error('priceCents') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div>
            <label><input type="checkbox" wire:model="isActive"> Active</label>
        </div>

        <fieldset>
            <legend>Features</legend>
            <label><input type="checkbox" wire:model="featureChat"> Chat</label>
            <label><input type="checkbox" wire:model="featureFileUpload"> File Upload</label>
            <label><input type="checkbox" wire:model="featureNotifications"> Notifications</label>
            <label><input type="checkbox" wire:model="featureAiAgent"> AI Agent</label>
        </fieldset>

        <fieldset>
            <legend>Limits</legend>
            <label for="maxAdmins">Max Admins</label>
            <input type="number" id="maxAdmins" wire:model="maxAdmins" min="0">

            <label for="maxUsers">Max Users</label>
            <input type="number" id="maxUsers" wire:model="maxUsers" min="0">

            <label for="maxStorageMb">Max Storage (MB)</label>
            <input type="number" id="maxStorageMb" wire:model="maxStorageMb" min="0">
        </fieldset>

        <button type="submit">Save</button>
    </form>
</div>
