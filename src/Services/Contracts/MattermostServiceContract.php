<?php

namespace EscolaLms\Mattermost\Services\Contracts;

use EscolaLms\Core\Models\User;
use EscolaLms\Mattermost\Enum\MattermostRoleEnum;
use Psr\Http\Message\ResponseInterface;

interface MattermostServiceContract
{
    public function addUser(User $user): bool;

    public function addUserToTeam(User $user, $teamDisplayName = "Courses"): bool;

    public function addUserToChannel(User $user, $channelDisplayName, $teamDisplayName = "Courses", $channelRole = MattermostRoleEnum::MEMBER): bool;

    public function getOrCreateTeam(string $displayName): ResponseInterface;

    public function getOrCreateChannel(string $teamDisplayName, string $channelDisplayName): ResponseInterface;

    public function getOrCreateUser(User $user): ResponseInterface;

    public function sendMessage(string $markdown, $channelDisplayName, $teamDisplayName = "Courses"): bool;

    public function getUserData(User $user): array;

    public function generateUserCredentials(User $user): array;

    public function sendUserResetPassword($user): bool;

    public function blockUser(User $user): bool;

    public function deleteUser(User $user): bool;

    public function removeUserFromChannel(User $user, $channelDisplayName, $teamDisplayName = "Courses"): bool;
}
