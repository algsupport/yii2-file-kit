<?php

namespace trntv\filekit\events;

use yii\base\Event;

class StorageEvent extends Event
{
    public $filesystem;
    public string $path;
}
