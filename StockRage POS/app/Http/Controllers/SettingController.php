<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'businessName' => Setting::get('business_name', 'Takoyaki POS'),
            'defaultThreshold' => (int) Setting::get('default_low_stock_threshold', 10),
            'themes' => Theme::all(),
            'theme' => Theme::current(),
            'receiptFooter' => Setting::get('receipt_footer', 'Thank you! Come again.'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:60'],
            'default_low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'receipt_footer' => ['nullable', 'string', 'max:120'],
            'theme_color' => ['nullable', Rule::in(array_keys(Theme::all()))],
        ]);

        Setting::set('business_name', $data['business_name']);
        Setting::set('default_low_stock_threshold', $data['default_low_stock_threshold']);
        Setting::set('receipt_footer', $data['receipt_footer'] ?? '');
        Setting::set('theme_color', $data['theme_color'] ?? Theme::current());

        return back()->with('success', 'Settings saved.');
    }
}
