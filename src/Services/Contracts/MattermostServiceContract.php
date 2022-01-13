<?php

namespace EscolaLms\Mattermost\Services\Contracts;

use EscolaLms\Auth\Models\User;
use Psr\Http\Message\ResponseInterface;

interface MattermostServiceContract
{
    public function addUser(User $user): bool;
}
