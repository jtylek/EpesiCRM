<?php

namespace Epesi\Modules\Followup;

use Illuminate\Database\Eloquent\Model;

class FollowupScheduled
{
    public function __construct(public Model $source, public Model $followup) {}
}
