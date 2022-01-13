<?php

namespace EscolaLms\Mattermost;

use Illuminate\Support\ServiceProvider;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Mattermost\Services\MattermostService;
use EscolaLms\Auth\Events\EscolaLmsAccountConfirmedTemplateEvent;
use Illuminate\Support\Facades\Event;

/**
 * SWAGGER_VERSION
 */

class EscolaLmsMattermostServiceProvider extends ServiceProvider
{
    public $singletons = [
        MattermostServiceContract::class => MattermostService::class,
    ];


    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mattermost.php',
            'mattermost'
        );

        Event::listen(EscolaLmsAccountConfirmedTemplateEvent::class, function ($data) {
            /**
             * >>> event(new EscolaLms\Auth\Events\EscolaLmsAccountConfirmedTemplateEvent(App\Models\User::find(2)));
             */
            app(MattermostServiceContract::class)->addUser($data->user);
        });
    }
}
