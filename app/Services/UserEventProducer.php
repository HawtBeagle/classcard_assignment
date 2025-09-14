<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class UserEventProducer
{
    protected $streamPrefix = 'user_stream:';

    /**
     * Push event to Redis stream for a specific user.
     */
    public function pushEvent(int $userId, array $data)
    {
        $streamKey = $this->streamPrefix . $userId;

        // XADD command adds a message to the stream
        Redis::xadd($streamKey, '*', $data);
    }
}
