<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        $validator = validator(
            ['locale' => $locale],
            [
                'locale' => ['required', 'string', Rule::in(config('app.available_locales', ['en']))],
            ]
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $request->session()->put('locale', $validator->validated()['locale']);

        return back();
    }
}
