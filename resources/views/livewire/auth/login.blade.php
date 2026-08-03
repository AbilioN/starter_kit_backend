<div class="card" style="max-width: 360px; margin: 4rem auto;">
    <h1>GodAdmin Login</h1>

    @if ($error)
        <p class="error">{{ $error }}</p>
    @endif

    <form wire:submit="login">
        <div>
            <label for="email">Email</label>
            <input type="email" id="email" wire:model="email" required>
            @error('email') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div>
            <label for="password">Password</label>
            <input type="password" id="password" wire:model="password" required>
            @error('password') <div class="error">{{ $message }}</div> @enderror
        </div>

        <button type="submit">Log in</button>
    </form>
</div>
