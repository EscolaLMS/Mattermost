<?php

namespace EscolaLms\Mattermost\Tests\API;

use EscolaLms\Auth\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Mattermost\Enum\PackageStatusEnum;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Mattermost\Tests\TestCase;
use EscolaLms\Webinar\Database\Seeders\WebinarsPermissionSeeder;
use EscolaLms\Webinar\Models\Webinar;
use EscolaLms\Webinar\Tests\Mocks\YTLiveDtoMock;
use EscolaLms\Youtube\Services\Contracts\YoutubeServiceContract;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;

class WebinarApiTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\Webinar\EscolaLmsWebinarServiceProvider::class)) {
            $this->markTestSkipped('Webinar package not installed');
        }

        $this->seed(WebinarsPermissionSeeder::class);
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
        Webinar::query()->delete();
    }

    public function testAddWebinarAuthorToChannel(): void
    {
        $webinar = Webinar::factory()->make([
            'active_from' => now()->format('Y-m-d H:i'),
            'active_to' => now()->modify('+1 hour')->format('Y-m-d H:i'),
        ])->toArray();

        $author = $this->makeInstructor();

        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $this->mock(YoutubeServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateYTStream')->once()->andReturn(new YTLiveDtoMock());
        });

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/webinars',
            array_merge($webinar, ['authors' => [$author->getKey()]])
        )->assertStatus(201);

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $webinar = Webinar::factory()->make([
            'active_from' => now()->format('Y-m-d H:i'),
            'active_to' => now()->modify('+1 hour')->format('Y-m-d H:i'),
        ])->toArray();
        $author = $this->makeInstructor();

        $this->mock(YoutubeServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateYTStream')->once()->andReturn(new YTLiveDtoMock());
        });

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('addUserToChannel')->once()->andReturn(true);
        });

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/webinars',
            array_merge($webinar, ['authors' => [$author->getKey()]])
        )->assertStatus(201);
    }

    public function testRemoveAuthorFromWebinar(): void
    {
        $author = $this->makeStudent();
        $webinar = Webinar::factory()->create([
            'active_from' => now()->format('Y-m-d H:i'),
            'active_to' => now()->modify('+1 hour')->format('Y-m-d H:i'),
        ]);
        $webinar->authors()->sync([$author->getKey()]);

        $this->setPackageStatus(PackageStatusEnum::DISABLED);

        $this->mock(YoutubeServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('updateYTStream')->once()->andReturn(new YTLiveDtoMock());
        });

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('removeUserFromChannel')->never();
        });

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/webinars/' . $webinar->getKey(), [
            'authors' => []
        ])->assertOk();

        $this->setPackageStatus(PackageStatusEnum::ENABLED);

        $this->mock(YoutubeServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('updateYTStream')->once()->andReturn(new YTLiveDtoMock());
        });

        $this->mock(MattermostServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('removeUserFromChannel')->once()->andReturn(true);
        });

        $webinar->authors()->sync([$author->getKey()]);

        $this->response = $this->actingAs($this->user, 'api')->postJson('/api/admin/webinars/' . $webinar->getKey(), [
            'authors' => []
        ])->assertOk();
    }
}
