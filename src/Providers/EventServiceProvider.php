<?php

namespace EscolaLms\Mattermost\Providers;

use EscolaLms\Auth\Events\EscolaLmsAccountConfirmedTemplateEvent;
use EscolaLms\Courses\Events\EscolaLmsCourseAssignedTemplateEvent;
use EscolaLms\Mattermost\Enum\PackageStatusEnum;
use EscolaLms\Mattermost\Services\Contracts\MattermostServiceContract;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (Config::get(SettingsServiceProvider::CONFIG_KEY . '.package_status', PackageStatusEnum::ENABLED) !== PackageStatusEnum::ENABLED) {
            return;
        }

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
