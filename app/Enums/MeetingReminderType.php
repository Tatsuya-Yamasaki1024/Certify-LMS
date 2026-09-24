<?php

declare(strict_types=1);

namespace App\Enums;

enum MeetingReminderType: string
{
    case Eve = 'eve';
    case OneHourBefore = 'one_hour_before';
}
