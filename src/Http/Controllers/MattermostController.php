<?php

namespace EscolaLms\Mattermost\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Mattermost\Http\Controllers\Swagger\MattermostSwagger;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Support\Facades\Auth;

class MattermostController extends EscolaLmsBaseController /* implements LrsSwagger */
{

    private MattermostServiceContract $service;

    public function __construct(MattermostServiceContract $service)
    {
        $this->service = $service;
    }

    public function me(Request $request): JsonResponse
    {
        return $this->sendResponse($this->service->getUserData(Auth::user()));
    }

    public function generateCredentials(Request $request): JsonResponse
    {
        return $this->sendResponse($this->service->generateUserCredentials(Auth::user()));
    }

    public function resetPassword(Request $request): JsonResponse
    {
        return $this->sendResponse($this->service->sendUserResetPassword(Auth::user()));
    }
}
