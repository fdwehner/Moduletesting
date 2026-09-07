<?php

namespace App\Support;

final class UploadRules
{
    /**
     * Shared image upload rules (JPEG, PNG, WebP; 5 MB).
     *
     * @return list<string>
     */
    public static function image(): array
    {
        return ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
    }

    /**
     * Shared document upload rules (office/PDF/CSV/text; 10 MB).
     *
     * @return list<string>
     */
    public static function document(): array
    {
        return ['file', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt', 'max:10240'];
    }
}
