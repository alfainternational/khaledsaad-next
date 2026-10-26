<?php

namespace App\Domain\Content\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $contentItemId, public int $version) {}
}
