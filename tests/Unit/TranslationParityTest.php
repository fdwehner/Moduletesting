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
}
