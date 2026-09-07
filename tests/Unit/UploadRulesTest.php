<?php

namespace Tests\Unit;

use App\Support\UploadRules;
use Tests\TestCase;

class UploadRulesTest extends TestCase
{
    public function test_image_rules_are_centralized(): void
    {
        $this->assertSame(
            ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            UploadRules::image()
        );
    }

    public function test_document_rules_are_centralized(): void
    {
        $this->assertSame(
            ['file', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt', 'max:10240'],
            UploadRules::document()
        );
    }
}
