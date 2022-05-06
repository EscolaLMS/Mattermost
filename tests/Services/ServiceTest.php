<?php

namespace EscolaLms\Mattermost\Tests\Services;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Mattermost\Enum\MattermostRoleEnum;
use EscolaLms\Mattermost\Tests\TestCase;
use EscolaLms\Webinar\Tests\Mocks\YTLiveDtoMock;
use EscolaLms\Youtube\Services\Contracts\YoutubeServiceContract;
use GuzzleHttp\Psr7\Response;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;

class ServiceTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    private MattermostServiceContract $service;
    private $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = $this->makeStudent();
        $this->service = $this->app->make(MattermostServiceContract::class);
        $this->mock->reset();
    }

    public function testAddUser()
    {
        if (class_exists(\EscolaLms\Webinar\EscolaLmsWebinarServiceProvider::class)) {
            $this->mock(YoutubeServiceContract::class, function (MockInterface $mock) {
                $mock->shouldReceive('getYtLiveStream')->zeroOrMoreTimes()->andReturn(collect([1]));
            });
            $this->mock(YoutubeServiceContract::class, function (MockInterface $mock) {
                $mock->shouldReceive('generateYTStream')->once()->andReturn(new YTLiveDtoMock());
            });
        }
        $this->mock->append(new Response(200, ['Token' => 'Token'], 'Hello, World'));
        $this->assertTrue($this->service->addUser($this->user));
    }

    public function testAddUserToTeam()
    {
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));

        $this->assertTrue($this->service->addUserToTeam($this->user, "Courses"));
    }

    public function testAddUserToChannel()
    {
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));

        $this->assertTrue($this->service->addUserToChannel($this->user, "Courses"));
    }

    public function testGetOrCreateTeam()
    {
        $response = new Response(200, ['Token' => 'Token'], json_encode(["id" => 123]));
        $this->mock->append($response);

        $this->assertEquals($this->service->getOrCreateTeam("Team name"),  $response);
    }

    public function testGetOrCreateChannel()
    {
        $response = new Response(200, ['Token' => 'Token'], json_encode(["id" => 123]));
        $this->mock->append($response);
        $this->mock->append($response);

        $this->assertEquals($this->service->getOrCreateChannel("Team name", "channel name"),  $response);
    }

    public function testGetOrCreateUser()
    {
        $response = new Response(200, ['Token' => 'Token'], json_encode(["id" => 123]));
        $this->mock->append($response);

        $this->assertEquals($this->service->getOrCreateUser($this->user),  $response);
    }

    public function testSendMessage()
    {
        $response = new Response(200, ['Token' => 'Token'], json_encode(["id" => 123]));
        $this->mock->append($response);
        $this->mock->append($response);

        $this->assertTrue($this->service->sendMessage("hello world", "Town Square"));
    }

    public function testGenerateUserCredentials()
    {
        $object = ["id" => 123];
        $response = new Response(200, ['Token' => 'Token'], json_encode($object));
        $this->mock->append($response);
        $this->mock->append($response);

        $response = $this->service->generateUserCredentials($this->user);

        $this->assertEquals((array) $response['status'], $object);
        $this->assertEquals((array) $response['user'], $object);
        $this->assertNotNull($response['password']);
    }

    public function testBlockUser()
    {
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["status" => 'ok'])));
        $this->assertTrue($this->service->blockUser($this->user));

        $this->mock->append(new Response(404, ['Token' => 'Token']));
        $this->assertFalse($this->service->blockUser($this->user));
    }

    public function testDeleteUser()
    {
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["status" => 'ok'])));
        $this->assertTrue($this->service->deleteUser($this->user));

        $this->mock->append(new Response(404, ['Token' => 'Token']));
        $this->assertFalse($this->service->blockUser($this->user));
    }

    public function testAddTutorToChanel(): void
    {
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));
        $this->mock->append(new Response(200, ['Token' => 'Token'], ""));

        $this->assertTrue($this->service->addUserToChannel($this->user, 'Channel name', 'Courses', MattermostRoleEnum::CHANNEL_ADMIN));
    }

    public function testRemoveUserFromChannel(): void
    {
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["id" => 123])));
        $this->mock->append(new Response(200, ['Token' => 'Token'], json_encode(["status" => 'ok'])));

        $this->assertTrue($this->service->removeUserFromChannel($this->user, 'Channel name'));
    }
}
