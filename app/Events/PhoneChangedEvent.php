<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Users\Models\User;

class PhoneChangedEvent implements OtpEventInterface
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public string $type = 'change_phone';
    public string $phone;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, string $phone)
    {
        $this->user = $user;
        $this->phone = $phone;
    }
}
