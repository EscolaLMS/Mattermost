<?php

namespace EscolaLms\Mattermost\Providers;

use EscolaLms\Auth\Events\AccountBlocked;
use EscolaLms\Auth\Events\AccountConfirmed;
use EscolaLms\Auth\Events\AccountDeleted;
use EscolaLms\Courses\Events\CourseAssigned;
use EscolaLms\Courses\Events\CourseTutorAssigned;
use EscolaLms\Courses\Events\CourseTutorUnassigned;
use EscolaLms\Courses\Events\CourseUnassigned;
use EscolaLms\Mattermost\Enum\MattermostRoleEnum;
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

        Event::listen(AccountConfirmed::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Auth\Events\AccountConfirmed(App\Models\User::find(2)));
             */
            app(MattermostServiceContract::class)->addUser($event->user);
        });

        Event::listen(CourseAssigned::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Courses\Events\CourseAssigned(App\Models\User::find(3), EscolaLms\Courses\Models\Course::find(1)));
             */
            $user = $event->getUser();
            $course = $event->getCourse();
            app(MattermostServiceContract::class)->addUserToChannel($user, $course->title);
        });

        Event::listen(CourseUnassigned::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Courses\Events\CourseUnassigned(App\Models\User::find(3), EscolaLms\Courses\Models\Course::find(1)));
             */
            $user = $event->getUser();
            $course = $event->getCourse();
            app(MattermostServiceContract::class)->removeUserFromChannel($user, $course->title);
        });

        Event::listen(AccountBlocked::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Auth\Events\AccountBlocked(App\Models\User::find(10)));
             */
            app(MattermostServiceContract::class)->blockUser($event->getUser());
        });

        Event::listen(AccountDeleted::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Auth\Events\AccountDeleted(App\Models\User::find(10)));
             */
            app(MattermostServiceContract::class)->deleteUser($event->getUser());
        });

        Event::listen(CourseTutorAssigned::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Courses\Events\CourseTutorAssigned(App\Models\User::find(9), EscolaLms\Courses\Models\Course::find(6)));
             */
            $user = $event->getUser();
            $course = $event->getCourse();
            app(MattermostServiceContract::class)->addUserToChannel($user, $course->title, 'Courses', MattermostRoleEnum::CHANNEL_ADMIN);
        });

        Event::listen(CourseTutorUnassigned::class, function ($event) {
            /**
             * >>> event(new EscolaLms\Courses\Events\CourseTutorUnassigned(App\Models\User::find(9), EscolaLms\Courses\Models\Course::find(6)));
             */
            $user = $event->getUser();
            $course = $event->getCourse();
            app(MattermostServiceContract::class)->removeUserFromChannel($user, $course->title);
        });
    }
}