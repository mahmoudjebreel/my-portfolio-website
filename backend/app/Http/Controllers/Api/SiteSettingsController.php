<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LocaleRequest;
use App\Support\SiteSettingsRegistry;
use Illuminate\Http\JsonResponse;

class SiteSettingsController extends Controller
{
    /**
     * Public site settings for one locale. Only allow-listed public keys;
     * missing values are null (no fallback to the other language).
     */
    public function __invoke(LocaleRequest $request): JsonResponse
    {
        $locale = $request->contentLocale();
        $values = SiteSettingsRegistry::publicValues($locale);

        return response()->json([
            'data' => [
                'locale' => $locale->value,
                'display_name' => $values['display_name'],
                'professional_title' => $values['professional_title'],
                'hero_eyebrow' => $values['hero_eyebrow'],
                'hero_headline' => $values['hero_headline'],
                'hero_description' => $values['hero_description'],
                'about_text' => $values['about_text'],
                'experience_text' => $values['experience_text'],
                'footer_text' => $values['footer_text'],
                'seo' => [
                    'title' => $values['seo_default_title'],
                    'description' => $values['seo_default_description'],
                    'image_url' => $values['og_default_image_path'],
                ],
                'contact' => [
                    'email' => $values['contact_email'],
                    'github_url' => $values['github_url'],
                    'linkedin_url' => $values['linkedin_url'],
                ],
                'profile_image' => $values['profile_image_path'] === null ? null : [
                    'url' => $values['profile_image_path'],
                    'alt' => $values['display_name'],
                ],
            ],
        ]);
    }
}
