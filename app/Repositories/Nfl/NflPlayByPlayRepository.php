<?php

namespace App\Repositories\Nfl;

use App\Models\NflGamePlaybyplayScores;

class NflPlayByPlayRepository
{

    public function __construct(
        private NflGamePlaybyplayScores $model
    ) {}

    public function getPlayByPlay($contestId)
    {
         $data = $this->model->where('contest_id', $contestId)->first();
         $response = $data->response ?? [];

         return [
            ...$response
         ];
    }
}