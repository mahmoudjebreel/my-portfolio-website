{{-- Language switch below the sign-in form (the user menu is not available there). --}}
<div class="fi-admin-locale-switch" style="margin-top: 1rem; text-align: center;">
    <a
        href="{{ route('admin-panel.locale', ['locale' => __('admin.switch_to_locale')]) }}"
        lang="{{ __('admin.switch_to_locale') }}"
        class="fi-link fi-size-sm"
    >
        {{ __('admin.switch_to') }}
    </a>
</div>
