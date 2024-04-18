<?php

namespace App\Enums;

enum StatusTaskEnum: string
{
    case CREATED = 'created';
    case IN_PROGRESS = 'in-progress';
    case SUCCESS = 'success';
    case FAILED = 'failed';
}
