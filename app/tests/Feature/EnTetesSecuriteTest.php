<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnTetesSecuriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present_on_html_responses(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'same-origin');
        $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
    }

    public function test_the_content_security_policy_forbids_inline_scripts(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        // C'est la directive qui neutralise les charges utiles XSS : elle ne
        // doit jamais autoriser l'exécution de JavaScript inline arbitraire.
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    public function test_the_nonce_changes_on_every_request(): void
    {
        $extraire = fn (string $csp) => Str($csp)->after("'nonce-")->before("'")->toString();

        $premier = $extraire($this->get('/login')->headers->get('Content-Security-Policy'));
        $second = $extraire($this->get('/login')->headers->get('Content-Security-Policy'));

        $this->assertNotSame($premier, $second, 'Un nonce réutilisé serait devinable et perdrait son intérêt.');
        $this->assertGreaterThanOrEqual(24, strlen($premier));
    }

    /**
     * Les gestionnaires en attribut (onclick="…", onchange="…") sont bloqués
     * par la CSP : ce test empêche leur réintroduction silencieuse dans une
     * vue, qui casserait la fonctionnalité concernée sans erreur visible.
     */
    public function test_no_view_reintroduces_an_inline_event_handler(): void
    {
        $vues = rtrim(shell_exec(
            'grep -rlE \'on(click|change|submit|load|error|focus|blur)="\' '
            .escapeshellarg(resource_path('views')).' 2>/dev/null'
        ) ?? '');

        $this->assertSame('', $vues, "Gestionnaires inline détectés (à convertir en data-*) :\n".$vues);
    }
}
