<?php

use EscolaLms\Mattermost\Tests\TestCase;
use EscolaLms\Settings\Database\Seeders\PermissionTableSeeder;
use Illuminate\Support\Facades\Config;

class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\Settings\EscolaLmsSettingsServiceProvider::class)) {
            $this->markTestSkipped();
        }

        $this->seed(PermissionTableSeeder::class);

        Config::set('escola_settings.use_database', true);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
    }

    public function testAdministrableConfigApi()
    {
        $configKey = 'mattermost';

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/config',
            [
                'config' => [
                    [
                        'key' => "{$configKey}.servers.default.host",
                        'value' => 'mm-server.pl',
                    ],
                    [
                        'key' => "{$configKey}.servers.default.login",
                        'value' => 'new_login',
                    ],
                    [
                        'key' => "{$configKey}.servers.default.password",
                        'value' => 'strong_password',
                    ],
                ]
            ]
        );
        $this->response->assertOk();

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/admin/config'
        );
        $this->response->assertOk();
        $this->response->assertJsonFragment([
            'data' => [
                $configKey => [
                    'servers' => [
                        'default' => [
                            'host' => [
                                'rules' => [
                                    'required',
                                    'string'
                                ],
                                'public' => true,
                                'value' => 'mm-server.pl',
                                'readonly' => false,
                            ],
                            'login' => [
                                'rules' => [
                                    'required',
                                    'string'
                                ],
                                'public' => false,
                                'value' => 'new_login',
                                'readonly' => false,
                            ],
                            'password' => [
                                'rules' => [
                                    'required',
                                    'string'
                                ],
                                'public' => false,
                                'value' => 'strong_password',
                                'readonly' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->response = $this->json(
            'GET',
            '/api/config'
        );
        $this->response->assertJsonFragment([
            $configKey => [
                'servers' => [
                    'default' => [
                        'host' => 'mm-server.pl',
                    ],
                ],
            ]
        ]);

        $this->response->assertJsonMissing([
            'login' => 'new_login',
            'password' => 'strong_password',
        ]);
    }
}
