<?php

namespace EscolaLms\Mattermost\Services\Contracts;

use EscolaLms\Auth\Models\User;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Support\Facades\Auth;


interface MattermostServiceContract
{
    public function addUser(User $user): bool;

    public function addUserToTeam(User $user, $teamDisplayName = "Courses"): bool;

    public function addUserToChannel(User $user, $channelDisplayName, $teamDisplayName = "Courses"): bool;

    public function getOrCreateTeam(string $displayName): ResponseInterface;

    public function getOrCreateChannel(string $teamDisplayName, string $channelDisplayName): ResponseInterface;

    public function getOrCreateUser(User $user): ResponseInterface;

    public function sendMessage(string $markdown, $channelDisplayName, $teamDisplayName = "Courses"): bool;

    public function getUserData(User $user): array;

    public function generateUserCredentials(User $user): array;

    public function sendUserResetPassword($user): bool;
}
