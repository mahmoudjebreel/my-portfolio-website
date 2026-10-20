{{-- Topbar button: back to the public website, in the panel's current language. --}}
<x-filament::button
    tag="a"
    :href="rtrim(config('app.frontend_url'), '/').'/'.app()->getLocale()"
    color="gray"
    outlined
    size="sm"
    icon="heroicon-o-globe-alt"
    labeled-from="sm"
    :tooltip="__('admin.back_to_site')"
    class="fi-admin-back-to-site"
>
    {{ __('admin.back_to_site') }}
</x-filament::button>
