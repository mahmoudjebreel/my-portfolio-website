{{-- Details of one media item (Filament media page). Every value is escaped; no storage path is shown. --}}
@php
    $alt = $item['alt'] ?? [];
@endphp
<div style="display: grid; gap: 1rem;">
    @if ($item['preview_url'])
        <img
            src="{{ $item['preview_url'] }}"
            alt="{{ $alt[app()->getLocale()] ?? '' }}"
            style="max-height: 18rem; width: 100%; object-fit: contain; border-radius: 0.75rem;"
            loading="lazy"
        >
    @else
        <p class="fi-color-warning" style="font-size: 0.875rem;">{{ __('admin.media.missing_note') }}</p>
    @endif

    <dl style="display: grid; grid-template-columns: max-content 1fr; gap: 0.5rem 1rem; font-size: 0.875rem;">
        <dt style="opacity: .7">{{ __('admin.media.file_name') }}</dt>
        <dd dir="ltr" style="overflow-wrap: anywhere;">{{ $item['file_name'] ?? '—' }}</dd>
        <dt style="opacity: .7">{{ __('admin.media.kind') }}</dt>
        <dd>{{ __('admin.media.kinds.'.$item['kind']) }}</dd>
        <dt style="opacity: .7">{{ __('admin.media.type') }}</dt>
        <dd dir="ltr">{{ $item['mime_type'] ?? '—' }}</dd>
        <dt style="opacity: .7">{{ __('admin.media.size') }}</dt>
        <dd>{{ $size }}</dd>
        <dt style="opacity: .7">{{ __('admin.media.dimensions') }}</dt>
        <dd dir="ltr">{{ $item['width'] && $item['height'] ? $item['width'].' × '.$item['height'] : '—' }}</dd>
        <dt style="opacity: .7">{{ __('admin.media.project') }}</dt>
        <dd>
            <a href="{{ \App\Filament\Resources\Projects\ProjectResource::getUrl('edit', ['record' => $item['project']['id']]) }}" class="fi-link">{{ $projectTitle }}</a>
        </dd>
        @foreach (['en' => __('admin.common.english'), 'ar' => __('admin.common.arabic')] as $locale => $language)
            <dt style="opacity: .7">{{ __('admin.media.alt', ['language' => $language]) }}</dt>
            <dd lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">{{ $alt[$locale] ?? __('admin.media.no_alt') }}</dd>
        @endforeach
    </dl>

    <p style="font-size: 0.75rem; opacity: .7;">{{ __('admin.media.kept_note') }}</p>
</div>
