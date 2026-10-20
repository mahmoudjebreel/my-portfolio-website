<?php

namespace App\Enums;

enum TechnologyGroup: string
{
    case Frontend = 'frontend';
    case Backend = 'backend';
    case Database = 'database';
    case Tools = 'tools';
}
