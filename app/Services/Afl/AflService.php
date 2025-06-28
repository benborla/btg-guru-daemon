<?php

namespace App\Services\Afl;

use App\Services\ApiDriverHandler;
use App\Services\Facade\ApiInterface;
use App\Services\ApiDrivers\GoalServeApiDriver;
use App\Services\Afl\Utils\Analyzer;
use App\Models\AflApiResponse;

class AflService
{
    private $api;

    public function __construct(
        GoalServeApiDriver $driver,
        private Analyzer $analyzer
    ) {
        $this->api = new ApiDriverHandler($driver);
        $this->hydrate();
    }

    /**
     * Undocumented function
     *
     * @return array<string, string<json>>
     */
    public function getApiLiveData(): array
    {
        $uri = AflApiResponse::URI_LIVE;
        if (!$this->api instanceof ApiInterface) {
            return [];
        }

        $response = $this->api->get()->uri($uri)->send();

        return [
            'response_code' => $response->getResponse()->getStatusCode(),
            'response' => $response->getResponse()->json(),
            'uri' => $uri
        ];
    }

    public function getApiSchedules(): array
    {
        $uri = AflApiResponse::URI_SCHEDULE;

        if (!$this->api instanceof ApiInterface) {
            return [];
        }

        $response = $this->api->get()->uri($uri)->send();

        return [
            'response_code' => $response->getResponse()->getStatusCode(),
            'response' => $response->getResponse()->json(),
            'uri' => $uri
        ];
    }

    public function getApiStandings(): array
    {
        $uri = AflApiResponse::URI_STANDINGS;

        if (!$this->api instanceof ApiInterface) {
            return [];
        }

        $response = $this->api->get()->uri($uri)->send();

        return [
            'response_code' => $response->getResponse()->getStatusCode(),
            'response' => $response->getResponse()->json(),
            'uri' => $uri
        ];
    }

    private function hydrate()
    {
	$data = AflApiResponse::getLatestData();
	$response = [];

	if ($data->count()) {
		$response = $data->response;
	}

        $this->analyzer->hydrate($response);
    }


    public function getScoreboard()
    {
        if (!has_match_today()) {
            return $this->analyzer->getNextMatchSchedule();
        }

        return $this->analyzer->getTeamScores();
    }

    public function getHeadToHead()
    {
        return $this->analyzer->getallheadtoheadrecords();
    }

    public function getMatchSummary()
    {
        return $this->analyzer->getMatchSummary();
    }

    public function getTeamStandings()
    {
        return $this->analyzer->getTeamStandings();
    }

    public function getUpcomingSchedules()
    {
        return $this->analyzer->getNextMatchSchedule();
    }

    public function getCurrentMatchData(): array
    {
        return $this->analyzer->getCurrentMatchData();
    }

    public function getPreviousMatchData(): array
    {
        return $this->analyzer->getPreviousMatchData();
    }
}
