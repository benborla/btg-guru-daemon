<?php

namespace App\Events;

use App\Models\AflApiResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Services\Afl\AflService;

class AflGetLiveMatch implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The AFL data that should be broadcast.
     *
     * @var \App\Models\AflApiResponse
     */
    public $aflData;

    public AflService $aflService;

    /**
     * Fresh response data to bypass any caching issues.
     *
     * @var array|null
     */
    public ?array $freshResponse = null;

    /**
     * Create a new event instance.
     */
    public function __construct(AflApiResponse $aflData, AflService $aflService)
    {
        $this->aflData = $aflData;
        $this->aflService = $aflService;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('sports.live.afl.match'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'afl.update';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array|null
    {
        $matchData = [];
        // If we have fresh response data, use it to hydrate the analyzer
        if ($this->freshResponse) {
            // Create a temporary analyzer with the fresh data
            $analyzer = app(\App\Services\Afl\Utils\Analyzer::class);
            $analyzer->hydrate($this->freshResponse);
            $matchData = $analyzer->getCurrentMatchData();
        } else {
            // Fall back to the service if no fresh data is available
            $matchData = $this->aflService->getCurrentMatchData();
        }

        return $matchData;
    }
}
