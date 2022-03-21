<?php

namespace EscolaLms\Mattermost\Tests\API;

use EscolaLms\Auth\Database\Seeders\AuthPermissionSeeder;
use EscolaLms\Auth\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Mattermost\Enum\PackageStatusEnum;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Mattermost\Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;

class AuthApiTestTestCase extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\Auth\EscolaLmsAuthServiceProvider::class)) {
            $this->markTestSkipped('Auth package not installed');
        }

        $this->seed(AuthPermissionSeeder::class);
        Config::set('escola_settings.use_database', true);
        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
        $this->mock->reset();
    }

    protected function tearDown(): void
    {
        \EscolaLms\Settings\Models\Config::truncate();
        User::query()->delete();
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
}
