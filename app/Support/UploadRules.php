<?php

namespace App\Support;

final class UploadRules
{
    public const MAX_KB_LARGE = 10240;

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
        return ['file', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt', 'max:'.self::MAX_KB_LARGE];
    }

    /**
     * Org designer Excel baseline import.
     *
     * @return list<string>
     */
    public static function excelWorkbook(): array
    {
        return ['required', 'file', 'extensions:xlsx,xlsm,xls', 'max:'.self::MAX_KB_LARGE];
    }

    public static function excelWorkbookRuleString(): string
    {
        return implode('|', self::excelWorkbook());
    }
}
