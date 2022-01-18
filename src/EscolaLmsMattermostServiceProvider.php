<?php

namespace EscolaLms\Mattermost;

use EscolaLms\Mattermost\Providers\SettingsServiceProvider;
use Illuminate\Support\ServiceProvider;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use EscolaLms\Mattermost\Services\MattermostService;
use EscolaLms\Auth\Events\EscolaLmsAccountConfirmedTemplateEvent;
use Illuminate\Support\Facades\Event;
use EscolaLms\Courses\Events\EscolaLmsCourseAssignedTemplateEvent;

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
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mattermost.php',
            'mattermost'
        );

        $this->app->register(SettingsServiceProvider::class);

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
