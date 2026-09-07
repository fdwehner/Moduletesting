<?php

namespace Tests\Unit;

use Tests\TestCase;

class TranslationParityTest extends TestCase
{
    public function test_common_translation_files_exist_for_english_and_german(): void
    {
        $this->assertFileExists(lang_path('en/common.php'));
        $this->assertFileExists(lang_path('de/common.php'));
    }

    public function test_shared_action_keys_exist_in_both_locales(): void
    {
        foreach (['save', 'cancel', 'delete', 'edit', 'search', 'filter', 'loading'] as $action) {
            $this->assertNotSame('common.actions.'.$action, __('common.actions.'.$action, locale: 'en'));
            $this->assertNotSame('common.actions.'.$action, __('common.actions.'.$action, locale: 'de'));
        }
    }

    public function test_welcome_and_dashboard_keys_exist_in_both_locales(): void
    {
        foreach (['app.welcome.title', 'app.welcome.subtitle', 'app.dashboard.title', 'app.dashboard.body'] as $key) {
            $this->assertNotSame($key, __($key, locale: 'en'));
            $this->assertNotSame($key, __($key, locale: 'de'));
        }
    }

    public function test_org_designer_keys_exist_in_both_locales(): void
    {
        foreach (['org_designer.title', 'org_designer.welcome_body', 'org_designer.validation.role_required', 'org_designer.js.need_area'] as $key) {
            $this->assertNotSame($key, __($key, locale: 'en'));
            $this->assertNotSame($key, __($key, locale: 'de'));
        }
    }

    public function test_org_designer_english_and_german_key_trees_match(): void
    {
        $english = include lang_path('en/org_designer.php');
        $german = include lang_path('de/org_designer.php');

        $this->assertSame(
            $this->translationKeys($english),
            $this->translationKeys($german)
        );
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return list<string>
     */
    private function translationKeys(array $tree, string $prefix = ''): array
    {
        $keys = [];
        foreach ($tree as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $keys[] = $full;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->translationKeys($value, $full));
            }
        }

        sort($keys);

        return $keys;
    }
}
