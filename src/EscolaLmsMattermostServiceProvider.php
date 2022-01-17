<?php

namespace EscolaLms\Mattermost;

use Illuminate\Support\ServiceProvider;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Mattermost\Services\MattermostService;
use EscolaLms\Auth\Events\EscolaLmsAccountConfirmedTemplateEvent;
use Illuminate\Support\Facades\Event;
use EscolaLms\Settings\Facades\AdministrableConfig;
use EscolaLms\Courses\Events\EscolaLmsCourseAssignedTemplateEvent;

/**
 * SWAGGER_VERSION
 */

class EscolaLmsMattermostServiceProvider extends ServiceProvider
{

    const CONFIG_KEY = 'mattermost';

    public $singletons = [
        MattermostServiceContract::class => MattermostService::class,
    ];

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        AdministrableConfig::registerConfig(self::CONFIG_KEY . '.servers.default.host', ['required', 'string'], true);
        AdministrableConfig::registerConfig(self::CONFIG_KEY . '.servers.default.login', ['required', 'string'], false);
        AdministrableConfig::registerConfig(self::CONFIG_KEY . '.servers.default.password', ['required', 'string'], false);
    }


    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mattermost.php',
            'mattermost'
        );

        Event::listen(EscolaLmsAccountConfirmedTemplateEvent::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Auth\Events\EscolaLmsAccountConfirmedTemplateEvent(App\Models\User::find(2)));
             */
            app(MattermostServiceContract::class)->addUser($event->user);
        });

        Event::listen(EscolaLmsCourseAssignedTemplateEvent::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Courses\Events\EscolaLmsCourseAssignedTemplateEvent(App\Models\User::find(3), EscolaLms\Courses\Models\Course::find(1)));
             */
            $user = $event->getUser();
            $course = $event->getCourse();
            app(MattermostServiceContract::class)->addUserToChannel($user, $course->title);
        });
    }
}
