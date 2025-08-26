<?php

namespace App\Repositories\Interfaces;

use Illuminate\Support\Collection;

interface NflScoresRepositoryInterface
{
    /* public function getScores(?string $week = null): Collection; */
    /* public function getGameById(string $gameId): ?NflScoreData; */
    /* public function refreshScores(): Collection; */

    public function getTeamsInfo(string $seasonTypeId):Collection;
    public function getTeamInfo(string $teamId) : Collection;
    public function getTeamStandings(string $season,string $teamId) : array;
    public function getScores(string $date);
}
