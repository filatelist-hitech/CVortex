<?php

namespace App\Models;

enum CareerFactType: string
{
    case SKILL = 'skill';
    case EXPERIENCE = 'experience';
    case ACHIEVEMENT = 'achievement';
    case EDUCATION = 'education';
    case LANGUAGE = 'language';
    case CERTIFICATION = 'certification';
    case EMPLOYMENT_PERIOD = 'employment_period';
    case SENIORITY = 'seniority';
    case LEADERSHIP = 'leadership';
    case RESPONSIBILITY = 'responsibility';
    case TECHNOLOGY_DEPTH = 'technology_depth';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
