<?php

namespace EscolaLms\Mattermost\Services;

use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Auth\Models\User;
use Gnello\Mattermost\Laravel\Facades\Mattermost;
use Illuminate\Support\Str;
use Gnello\Mattermost\Driver;
use Gnello\Mattermost\Models\TeamModel;
use Psr\Http\Message\ResponseInterface;

class MattermostService implements MattermostServiceContract
{

    public Driver $driver;

    public function __construct()
    {
        $this->driver = Mattermost::server('default');
    }

    private function getUsername(User $user): string
    {
        return Str::slug($user->email);
    }

    private function getData(ResponseInterface $result): mixed
    {
        return json_decode($result->getBody());
    }

    private function logResponse(ResponseInterface $result): void
    {
        if ($result->getStatusCode() < 400) {
            echo "Everything is ok.";
            dd(json_decode($result->getBody()));
        } else {
            echo "HTTP ERROR " . $result->getStatusCode();
            dd(json_decode($result->getBody()));
        }
    }

    public function addUser(User $user): bool
    {
        $result = $this->getOrCreateUser($user);
        return $result->getStatusCode() < 400;
    }

    public function addUserToTeam(User $user, $teamDisplayName = "Courses"): bool
    {
        $team = $this->getData($this->getOrCreateTeam($teamDisplayName));
        $user = $this->getData($this->getOrCreateUser($user));


        if (isset($team->id) && isset($user->id)) {
            $teams = $this->driver->getTeamModel();
            $result = $teams->addUser($team->id, [
                'user_id' => $user->id,
                'team_id' => $team->id
            ]);
            return $result->getStatusCode() < 400;
        }

        return false;
    }

    public function addUserToChannel(User $user, $channelDisplayName, $teamDisplayName = "Courses"): bool
    {
        $channel = $this->getData($this->getOrCreateChannel($teamDisplayName, $channelDisplayName));
        $mmUser = $this->getData($this->getOrCreateUser($user));

        if (isset($channel->id) && isset($mmUser->id)) {
            $this->addUserToTeam($user, $teamDisplayName);
            $channels = $this->driver->getChannelModel();
            $result = $channels->addUser($channel->id, [
                'user_id' => $mmUser->id
            ]);
            return $result->getStatusCode() < 400;
        }

        return false;
    }

    public function getOrCreateTeam(string $displayName): ResponseInterface
    {
        $name = Str::slug($displayName);

        $teams = $this->driver->getTeamModel();
        $result  = $teams->getTeamByName($name);

        if ($result->getStatusCode() < 400) {
            return $result;
        }

        // team does not exists create

        $result = $teams->createTeam([
            'name' => $name,
            'display_name' => $name,
            'type' => 'I' // 'O' for open, 'I' for invite only
        ]);

        return $result;
    }

    public function getOrCreateChannel(string $teamDisplayName, string $channelDisplayName): ResponseInterface
    {
        $team = $this->getData($this->getOrCreateTeam($teamDisplayName));

        $channelName =  Str::slug($channelDisplayName);

        if (isset($team->id)) {
            $channels = $this->driver->getChannelModel();
            $result = $channels->getChannelByName($team->id, $channelName);

            if ($result->getStatusCode() < 400) {
                return $result;
            }

            $result = $channels->createChannel([
                'team_id' => $team->id,
                'name' => $channelName,
                'display_name' => $channelDisplayName,
                'type' => 'P' // 'O' for a public channel, 'P' for a private channel
            ]);

            return $result;
        }

        return $team;
    }

    public function getOrCreateUser(User $user): ResponseInterface
    {
        //Retrieve the User Model
        $userModel = $this->driver->getUserModel();

        $result = $userModel->getUserByEmail($user->email);

        if ($result->getStatusCode() < 400) {
            return $result;
        }

        $result = $userModel->createUser([
            'email' => $user->email,
            'username' => $this->getUsername($user),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            "auth_service" => "email",
            "password" => Str::random(16)
        ]);

        return $result;
    }

    public function sendMessage(string $markdown, $channelDisplayName, $teamDisplayName = "Courses"): bool
    {

        $channels = $this->driver->getChannelModel();

        $channel = $channels->getChannelByNameAndTeamName(Str::slug($teamDisplayName), Str::slug($channelDisplayName));

        $channelData = $this->getData($channel);

        if ($channelData->id) {
            $result = $this->driver->getPostModel()->createPost([
                'channel_id' => $channelData->id,
                'message' => $markdown,
            ]);

            return $result->getStatusCode() < 400;
        }

        return false;
    }

    public function generateUserCredentials(User $user): array
    {
        $mmUser = json_decode($this->getOrCreateUser($user)->getBody());

        $users = $this->driver->getUserModel();

        $newPassword = Str::random() . rand(0, 9) . "!";

        $result = $users->updateUserPassword($mmUser->id, [
            'new_password' => $newPassword
        ]);

        $results = json_decode($result->getBody());

        return [
            'status' => $results,
            'user' => $mmUser,
            'password' => $newPassword
        ];
    }

    public function getUserData(User $user): array
    {
        $server =  config('mattermost.servers.default.host');

        $users = $this->driver->getUserModel();

        $result = $users->getUserByEmail($user->email);

        if ($result->getStatusCode() >= 400) {
            return [];
        }

        $userData = json_decode($result->getBody());

        $teams = $this->driver->getTeamModel();

        $result = $teams->getUserTeams($userData->id);

        $userTeamsData = json_decode($result->getBody());

        $channels = $this->driver->getChannelModel();

        foreach ($userTeamsData as $userTeamData) {

            $result =  $channels->getChannelsForUser($userData->id, $userTeamData->id);
            $channelsData = json_decode($result->getBody());
            foreach ($channelsData as $channelData) {
                $channelData->url = 'https://' . $server . '/' . $userTeamData->name . '/' . $channelData->name;
            }
            $userTeamData->channels = $channelsData;
        }

        return [
            'server' => $server,
            'teams' => $userTeamsData
        ];
    }

    public function sendUserResetPassword($user): bool
    {
        $this->getOrCreateUser($user);

        $users = $this->driver->getUserModel();

        $users->sendPasswordResetEmail(['email' => $user->email]);

        return true;
    }
}
