<span class="lang-switch">
    @foreach (config('app.available_locales') as $code => $label)
        <form method="POST" action="{{ route('locale.update', $code) }}" style="display:inline">
            @csrf
            @method('PUT')
            <button type="submit" class="{{ app()->getLocale() === $code ? 'active' : '' }}" style="background:none;border:none;color:inherit;cursor:pointer;padding:0 .3rem;font:inherit;">
                {{ strtoupper($code) }}
            </button>
        </form>
    @endforeach
</span>
