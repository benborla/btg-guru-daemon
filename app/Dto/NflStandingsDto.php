<?php


namespace App\Dto;

use Illuminate\Support\Collection;

class NflStandingsDto
{

    public function __construct(public Collection $data){

    }

    public function getTeamStandings($season, $teamId)
    {
        if (empty($this->data)) return [];

        return $this->data->map(function ($item) use($teamId) {
            $filteredDivisions = collect($item['division'])->map(function ($division) use($teamId) {
                $filteredTeams = collect($division['team'])->where('id', $teamId);

                if ($filteredTeams->isNotEmpty()) {
                    return [
                        'name' => $division['name'],
                        'team' => $filteredTeams->first()
                    ];
                }
                return null;
            })->filter(); // Remove null divisions

            if ($filteredDivisions->isNotEmpty()) {
                return [
                    'division' => $filteredDivisions->first()
                ];
            }
            return null;
        })->filter()->first(); // Remove null items
    }


}
