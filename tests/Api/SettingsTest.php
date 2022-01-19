<?php

namespace EscolaLms\Mattermost\Tests\API;

use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Mattermost\Enum\PackageStatusEnum;
use EscolaLms\Mattermost\Providers\SettingsServiceProvider;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Mattermost\Tests\TestCase;
use EscolaLms\Settings\Database\Seeders\PermissionTableSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;

class SettingsTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\Settings\EscolaLmsSettingsServiceProvider::class)) {
            $this->markTestSkipped('Settings package not installed');
        }

        if (!class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class)) {
            $this->markTestSkipped('Courses package not installed');
        }

        if (!class_exists(\EscolaLms\Scorm\EscolaLmsScormServiceProvider::class)) {
            $this->markTestSkipped('Scorm package not installed');
        }

        $this->seed(PermissionTableSeeder::class);

        Config::set('escola_settings.use_database', true);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
        $this->mock->reset();
    }

    public function testAdministrableConfigApi(): void
    {
        $configKey = SettingsServiceProvider::CONFIG_KEY;

        $this->response = $this->actingAs($this->user, 'api')->json(
            'POST',
            '/api/admin/config',
            [
                'config' => [
                    [
                        'key' => "{$configKey}.package_status",
                        'value' => PackageStatusEnum::DISABLED,
                    ],
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
            $configKey => [
                'package_status' => [
                    'rules' => [
                        'required',
                        'string',
                        'in:' . implode(',', PackageStatusEnum::getValues())
                    ],
                    'public' => false,
                    'value' => PackageStatusEnum::DISABLED,
                    'readonly' => false,
                ],
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
            'package_status' => PackageStatusEnum::DISABLED,
            'login' => 'new_login',
            'password' => 'strong_password',
        ]);
    }

    public function testAccountConfirmedTemplateEventListenerWithPackageStatusSetting(): void
    {
        $admin = $this->makeAdmin();

        Config::set(SettingsServiceProvider::CONFIG_KEY . '.package_status', PackageStatusEnum::ENABLED);

        $student1 = $this->makeStudent([
            'email_verified_at' => null
        ]);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUser')->once()->andReturn(true);
        });

        $this->response = $this->actingAs($admin, 'api')->json('PUT', '/api/admin/users/' . $student1->getKey(), [
            'first_name' => $student1->first_name,
            'last_name' => $student1->last_name,
            'email_verified' => true,
        ])->assertOk();

        Config::set(SettingsServiceProvider::CONFIG_KEY . '.package_status', PackageStatusEnum::DISABLED);

        $student2 = $this->makeStudent([
            'email_verified_at' => null
        ]);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUser')->never();
        });

        $this->response = $this->actingAs($admin, 'api')->json('PUT', '/api/admin/users/' . $student2->getKey(), [
            'first_name' => $student2->first_name,
            'last_name' => $student2->last_name,
            'email_verified' => true,
        ])->assertOk();
    }

    public function testCourseAssignedTemplateEventListenerWithPackageStatusSetting(): void
    {
        $admin = $this->makeAdmin();
        $course = Course::factory()->create([
            'author_id' => $admin->getKey(),
            'base_price' => 997,
            'active' => true,
        ]);

        Config::set(SettingsServiceProvider::CONFIG_KEY . '.package_status', PackageStatusEnum::ENABLED);

        $student1 = $this->makeStudent();

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->once()->andReturn(true);
        });

        $this->response = $this->actingAs($this->user, 'api')->post('/api/admin/courses/' . $course->getKey() . '/access/add/', [
            'users' => [$student1->getKey()]
        ])->assertOk();

        Config::set(SettingsServiceProvider::CONFIG_KEY . '.package_status', PackageStatusEnum::DISABLED);

        $student2 = $this->makeStudent();

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->post('/api/admin/courses/' . $course->getKey() . '/access/add/', [
            'users' => [$student2->getKey()]
        ])->assertOk();
    }
}
