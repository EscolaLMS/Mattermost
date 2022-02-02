<?php

namespace EscolaLms\Mattermost\Tests\API;

use EscolaLms\Auth\Database\Seeders\AuthPermissionSeeder;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Mattermost\Enum\PackageStatusEnum;
use EscolaLms\Mattermost\Providers\SettingsServiceProvider;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Mattermost\Tests\TestCase;
use EscolaLms\Settings\Database\Seeders\PermissionTableSeeder;
use EscolaLms\Settings\Facades\AdministrableConfig;
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

        if (!class_exists(\EscolaLms\Auth\EscolaLmsAuthServiceProvider::class)) {
            $this->markTestSkipped('Auth package not installed');
        }

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
        $this->seed(AuthPermissionSeeder::class);
        $this->seed(CoursesPermissionSeeder::class);
        Config::set('escola_settings.use_database', true);
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
        $this->mock->reset();
    }

    protected function tearDown(): void
    {
        \EscolaLms\Settings\Models\Config::truncate();
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
                        'value' => 'localhost',
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
                    'full_key' => "$configKey.package_status",
                    'key' => 'package_status',
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
                            'full_key' => "$configKey.servers.default.host",
                            'key' => 'servers.default.host',
                            'rules' => [
                                'required',
                                'string'
                            ],
                            'public' => true,
                            'value' => 'localhost',
                            'readonly' => false,
                        ],
                        'login' => [
                            'full_key' => "$configKey.servers.default.login",
                            'key' => 'servers.default.login',
                            'rules' => [
                                'required',
                                'string'
                            ],
                            'public' => false,
                            'value' => 'new_login',
                            'readonly' => false,
                        ],
                        'password' => [
                            'full_key' => "$configKey.servers.default.password",
                            'key' => 'servers.default.password',
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
                        'host' => 'localhost',
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
        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $student1 = $this->makeStudent([
            'email_verified_at' => null
        ]);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUser')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->patchJson('/api/admin/users/' . $student1->getKey(), [
            'email_verified' => true,
        ])->assertOk();

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $student2 = $this->makeStudent([
            'email_verified_at' => null
        ]);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUser')->once()->andReturn(true);
        });

        $this->response = $this->actingAs($this->user, 'api')->patchJson('/api/admin/users/' . $student2->getKey(), [
            'email_verified' => true,
        ])->assertOk();
    }

    public function testCourseAssignedTemplateEventListenerWithPackageStatusSetting(): void
    {
        $course = Course::factory()->create([
            'author_id' => $this->user->getKey(),
            'base_price' => 997,
            'status' => CourseStatusEnum::PUBLISHED,
        ]);

        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $student1 = $this->makeStudent();

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->post('/api/admin/courses/' . $course->getKey() . '/access/add/', [
            'users' => [$student1->getKey()]
        ])->assertOk();

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $student2 = $this->makeStudent();

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->once()->andReturn(true);
        });

        $this->response = $this->actingAs($this->user, 'api')->post('/api/admin/courses/' . $course->getKey() . '/access/add/', [
            'users' => [$student2->getKey()]
        ])->assertOk();
    }

    public function testAddTutorToChannelWhenPackageIsDisabledAndEnabled(): void
    {
        $course = Course::factory()->make()->toArray();

        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/courses', $course)
            ->assertStatus(201);

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $course = Course::factory()->make()->toArray();

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->once()->andReturn(true);
        });

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/courses', $course)
            ->assertStatus(201);
    }

    public function testRemoveTutorFromChannelWhenPackageIsDisabledAndEnabled(): void
    {
        $course = Course::factory()->create();
        $course->authors()->sync($this->makeInstructor()->getKey());;
        $editedCourse = $course->toArray();
        $editedCourse['authors'] = [];

        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->never();
            $mock->shouldReceive('removeUserFromChannel')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/courses/' . $course->getKey(),
            $editedCourse
        )->assertStatus(200);

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->andReturn(true);
            $mock->shouldReceive('removeUserFromChannel')->once()->andReturn(true);
        });

        $course->authors()->sync($this->makeInstructor()->getKey());;
        $editedCourse['authors'] = [];

        $this->response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/courses/' . $course->getKey(),
            $editedCourse
        )->assertStatus(200);
    }

    public function testRemoveUserFromChannelWhenPackageIsDisabledAndEnabled(): void
    {
        $student = $this->makeStudent();
        $course = Course::factory()->create();
        $course->users()->sync([$student->getKey()]);

        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->never();
            $mock->shouldReceive('removeUserFromChannel')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/courses/' . $course->getKey() . '/access/remove/', [
            'users' => [$student->getKey()]
        ])->assertOk();

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->andReturn(true);
            $mock->shouldReceive('removeUserFromChannel')->once()->andReturn(true);
        });

        $course->users()->sync([$student->getKey()]);

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/courses/' . $course->getKey() . '/access/remove/', [
            'users' => [$student->getKey()]
        ])->assertOk();
    }

    private function setPackageStatus($packageStatus): void
    {
        Config::set(SettingsServiceProvider::CONFIG_KEY . '.package_status', $packageStatus);
        Config::set('escola_settings.use_database', true);
        AdministrableConfig::storeConfig();
        $this->refreshApplication();
    }
}
