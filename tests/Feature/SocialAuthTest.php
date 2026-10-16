<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fingirProvedor(?string $email, string $id = 'g-123'): void
    {
        config([
            'services.google.client_id' => 'x',
            'services.google.client_secret' => 'y',
            'app.frontend_url' => 'http://front.test',
        ]);

        $perfil = (new SocialUser)->map(['id' => $id, 'name' => 'Maria Social', 'email' => $email]);

        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($perfil);

        $fabrica = Mockery::mock(Factory::class);
        $fabrica->shouldReceive('driver')->with('google')->andReturn($driver);
        $this->app->instance(Factory::class, $fabrica);
    }

    public function test_callback_cria_usuario_pessoa_e_troca_codigo_por_token(): void
    {
        $this->fingirProvedor('maria@exemplo.com');

        $resposta = $this->get('/api/auth/google/callback');

        $resposta->assertRedirect();
        $this->assertStringStartsWith('http://front.test/login/social?codigo=', $resposta->headers->get('Location'));

        $user = User::where('email', 'maria@exemplo.com')->firstOrFail();
        $this->assertSame('pessoa', $user->perfil);
        $this->assertNotNull($user->email_verified_at);

        parse_str(parse_url($resposta->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->postJson('/api/auth/social/trocar', ['codigo' => $query['codigo']])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        // Código de uso único.
        $this->postJson('/api/auth/social/trocar', ['codigo' => $query['codigo']])->assertStatus(422);
    }

    public function test_callback_vincula_a_conta_existente_pelo_email(): void
    {
        $existente = User::factory()->create(['email' => 'joao@exemplo.com', 'email_verified_at' => null]);
        $this->fingirProvedor('joao@exemplo.com');

        $this->get('/api/auth/google/callback')->assertRedirect();

        $this->assertSame(1, User::where('email', 'joao@exemplo.com')->count());
        $this->assertNotNull($existente->fresh()->email_verified_at);
        $this->assertSame(1, $existente->contasSociais()->count());
    }

    public function test_callback_sem_email_volta_com_erro(): void
    {
        $this->fingirProvedor(null);

        $resposta = $this->get('/api/auth/google/callback');

        $this->assertStringContainsString('/login/social?erro=', $resposta->headers->get('Location'));
        $this->assertSame(0, User::count());
    }

    public function test_codigo_invalido_e_recusado(): void
    {
        Cache::flush();

        $this->postJson('/api/auth/social/trocar', ['codigo' => 'inexistente'])->assertStatus(422);
    }
}
