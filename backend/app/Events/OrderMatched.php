<?php

namespace App\Events;

use App\Models\Order;
use App\Models\Trade;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcast
{
   use Dispatchable, InteractsWithSockets, SerializesModels;


    public $userId;
    public $order;
    public $trade;

    /**
     * Create a new event instance.
     */
    public function __construct(int $userId, Order $order, Trade $trade)
    {
        $this->userId = $userId;
        $this->order = $order;
        $this->trade = $trade;
    }


    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.matched';
    }

    public function broadcastWith(): array
    {
        return [
            'order' => $this->order,
            'trade' => $this->trade,
        ];
    }
}
