<?php

namespace Tests\Feature;

use App\Support\Settings;
use App\Support\Theme;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    public function test_contrast_and_mix(): void
    {
        $this->assertEqualsWithDelta(21.0, Theme::contrast('#000000', '#ffffff'), 0.01);
        $this->assertSame('#808080', Theme::mix('#000000', '#ffffff', 0.5));
    }

    public function test_default_light_accent_is_readable_on_cream(): void
    {
        $a = Theme::lightAccent();
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $a);
        $this->assertGreaterThanOrEqual(4.5, Theme::contrast($a, Theme::DEFAULTS['bg']));
    }

    public function test_css_contains_palette_fonts_and_radius(): void
    {
        $css = Theme::css();
        $this->assertStringContainsString('html[data-theme=light]{', $css);
        $this->assertStringContainsString('--bg:#faf7f2', $css);
        $this->assertStringContainsString("'Fraunces'", $css);
        $this->assertStringContainsString('--rb:10px', $css);
    }

    public function test_invalid_settings_fall_back(): void
    {
        Settings::reset();
        $this->assertContains(Theme::mode(), array_keys(Theme::MODES));
        $this->assertContains(Theme::font(), array_keys(Theme::FONTS));
        $this->assertContains(Theme::radius(), array_keys(Theme::RADII));
        $this->assertStringContainsString('data-theme', Theme::bootScript());
    }

    public function test_storefront_renders_toggle_and_light_css(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="theme-toggle"', $html);
        $this->assertStringContainsString('css/theme-light.css', $html);
        $this->assertStringContainsString('KIOSK_THEME', $html);
        Settings::set('theme_toggle', '0');
        $this->assertStringNotContainsString('id="theme-toggle"', $this->get('/')->getContent());
    }

    public function test_admin_can_save_theme_settings_and_customers_cannot(): void
    {
        $cust = \App\Models\User::create(['name' => 'C', 'email' => 'c@example.com', 'password' => 'secret123', 'role' => 'customer', 'status' => 'active']);
        $this->actingAs($cust)->get('/admin/themes')->assertRedirect();
        $admin = \App\Models\User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret123', 'role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get('/admin/themes')->assertOk()->assertSee('Light theme colours');
        $this->actingAs($admin)->post('/admin/themes', ['theme_mode' => 'light', 'theme_toggle' => '1', 'theme_light_bg' => '#fff8e7', 'theme_light_surface' => '#ffffff', 'theme_light_text' => '#222222', 'theme_light_auto' => '1', 'theme_light_font' => 'playfair', 'theme_light_radius' => 'pill'])->assertRedirect('/admin/themes');
        Settings::reset();
        $this->assertSame('light', Theme::mode());
        $this->assertSame('playfair', Theme::font());
        $this->assertSame('pill', Theme::radius());
        $this->assertStringContainsString('--bg:#fff8e7', Theme::css());
        $this->actingAs($admin)->post('/admin/themes', ['theme_mode' => 'evil', 'theme_light_bg' => 'red', 'theme_light_font' => 'x', 'theme_light_radius' => 'x'])->assertRedirect();
        Settings::reset();
        $this->assertSame('auto', Theme::mode());
        $this->assertSame('#faf7f2', Theme::hex('theme_light_bg', '#faf7f2'));
    }
}
