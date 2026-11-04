<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use App\Support\Theme;
use App\Support\Upload;
use Illuminate\Http\Request;

/** Admin -> Content -> Themes (v3.0): default mode, customer toggle, light palette / fonts / roundness / logo. */
class Themes extends Controller
{
    public function index()
    {
        title('Themes');

        return admin_page('themes');
    }

    public function save(Request $r)
    {
        if ($r->input('action') === 'reset') {
            Settings::setMany(['theme_mode' => 'auto', 'theme_toggle' => '1', 'theme_light_bg' => Theme::DEFAULTS['bg'], 'theme_light_surface' => Theme::DEFAULTS['surface'],
                'theme_light_text' => Theme::DEFAULTS['text'], 'theme_light_accent' => '', 'theme_light_font' => 'fraunces', 'theme_light_radius' => 'soft']);
            flash('success', 'Theme settings reset to defaults.');

            return redirect('/admin/themes');
        }
        $color = fn (string $k, string $d) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $r->input($k)) ? strtolower((string) $r->input($k)) : $d;
        $data = [
            'theme_mode' => array_key_exists((string) $r->input('theme_mode'), Theme::MODES) ? (string) $r->input('theme_mode') : 'auto',
            'theme_toggle' => $r->input('theme_toggle') === '1' ? '1' : '0',
            'theme_light_bg' => $color('theme_light_bg', Theme::DEFAULTS['bg']),
            'theme_light_surface' => $color('theme_light_surface', Theme::DEFAULTS['surface']),
            'theme_light_text' => $color('theme_light_text', Theme::DEFAULTS['text']),
            'theme_light_accent' => $r->input('theme_light_auto') === '1' ? '' : $color('theme_light_accent', ''),
            'theme_light_font' => array_key_exists((string) $r->input('theme_light_font'), Theme::FONTS) ? (string) $r->input('theme_light_font') : 'fraunces',
            'theme_light_radius' => array_key_exists((string) $r->input('theme_light_radius'), Theme::RADII) ? (string) $r->input('theme_light_radius') : 'soft',
        ];
        try {
            if ($logo = Upload::image($r->file('theme_light_logo'), 'site', 2)) {
                Upload::remove((string) setting('theme_light_logo'));
                $data['theme_light_logo'] = $logo;
            } elseif ($r->boolean('remove_light_logo')) {
                Upload::remove((string) setting('theme_light_logo'));
                $data['theme_light_logo'] = '';
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());

            return redirect('/admin/themes');
        }
        Settings::setMany($data);
        flash('success', 'Theme settings saved.');

        return redirect('/admin/themes');
    }
}
