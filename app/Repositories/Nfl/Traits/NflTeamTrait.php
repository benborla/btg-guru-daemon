<?php
namespace App\Repositories\Nfl\Traits;

use Illuminate\Support\Str;

trait NflTeamTrait
{
    private $teamAbrv = '[
            {
              "ID": 1696,
              "Name": "Arizona Cardinals",
              "Abbreviation": "ARI",
              "Conference": "NFC",
              "Division": "West"
            },
            {
              "ID": 1690,
              "Name": "Atlanta Falcons",
              "Abbreviation": "ATL",
              "Conference": "NFC",
              "Division": "South"
            },
            {
              "ID": 1683,
              "Name": "Baltimore Ravens",
              "Abbreviation": "BAL",
              "Conference": "AFC",
              "Division": "North"
            },
            {
              "ID": 1689,
              "Name": "Buffalo Bills",
              "Abbreviation": "BUF",
              "Conference": "AFC",
              "Division": "East"
            },
            {
              "ID": 1684,
              "Name": "Carolina Panthers",
              "Abbreviation": "CAR",
              "Conference": "NFC",
              "Division": "South"
            },
            {
              "ID": 1703,
              "Name": "Chicago Bears",
              "Abbreviation": "CHI",
              "Conference": "NFC",
              "Division": "North"
            },
            {
              "ID": 1679,
              "Name": "Cincinnati Bengals",
              "Abbreviation": "CIN",
              "Conference": "AFC",
              "Division": "North"
            },
            {
              "ID": 1699,
              "Name": "Cleveland Browns",
              "Abbreviation": "CLE",
              "Conference": "AFC",
              "Division": "North"
            },
            {
              "ID": 1680,
              "Name": "Dallas Cowboys",
              "Abbreviation": "DAL",
              "Conference": "NFC",
              "Division": "East"
            },
            {
              "ID": 1708,
              "Name": "Denver Broncos",
              "Abbreviation": "DEN",
              "Conference": "AFC",
              "Division": "West"
            },
            {
              "ID": 1695,
              "Name": "Detroit Lions",
              "Abbreviation": "DET",
              "Conference": "NFC",
              "Division": "North"
            },
            {
              "ID": 1698,
              "Name": "Green Bay Packers",
              "Abbreviation": "GB",
              "Conference": "NFC",
              "Division": "North"
            },
            {
              "ID": 1697,
              "Name": "Houston Texans",
              "Abbreviation": "HOU",
              "Conference": "AFC",
              "Division": "South"
            },
            {
              "ID": 1706,
              "Name": "Indianapolis Colts",
              "Abbreviation": "IND",
              "Conference": "AFC",
              "Division": "South"
            },
            {
              "ID": 1687,
              "Name": "Jacksonville Jaguars",
              "Abbreviation": "JAX",
              "Conference": "AFC",
              "Division": "South"
            },
            {
              "ID": 1691,
              "Name": "Kansas City Chiefs",
              "Abbreviation": "KC",
              "Conference": "AFC",
              "Division": "West"
            },
            {
              "ID": 1692,
              "Name": "Miami Dolphins",
              "Abbreviation": "MIA",
              "Conference": "AFC",
              "Division": "East"
            },
            {
              "ID": 1701,
              "Name": "Minnesota Vikings",
              "Abbreviation": "MIN",
              "Conference": "NFC",
              "Division": "North"
            },
            {
              "ID": 1681,
              "Name": "New England Patriots",
              "Abbreviation": "NE",
              "Conference": "AFC",
              "Division": "East"
            },
            {
              "ID": 1682,
              "Name": "New Orleans Saints",
              "Abbreviation": "NO",
              "Conference": "NFC",
              "Division": "South"
            },
            {
              "ID": 1710,
              "Name": "New York Giants",
              "Abbreviation": "NYG",
              "Conference": "NFC",
              "Division": "East"
            },
            {
              "ID": 1709,
              "Name": "New York Jets",
              "Abbreviation": "NYJ",
              "Conference": "AFC",
              "Division": "East"
            },
            {
              "ID": 5566,
              "Name": "Las Vegas Raiders",
              "Abbreviation": "LV",
              "Conference": "AFC",
              "Division": "West"
            },
            {
              "ID": 1686,
              "Name": "Philadelphia Eagles",
              "Abbreviation": "PHI",
              "Conference": "NFC",
              "Division": "East"
            },
            {
              "ID": 1694,
              "Name": "Pittsburgh Steelers",
              "Abbreviation": "PIT",
              "Conference": "AFC",
              "Division": "North"
            },
            {
              "ID": 1702,
              "Name": "Los Angeles Chargers",
              "Abbreviation": "LAC",
              "Conference": "AFC",
              "Division": "West"
            },
            {
              "ID": 1707,
              "Name": "San Francisco 49ers",
              "Abbreviation": "SF",
              "Conference": "NFC",
              "Division": "West"
            },
            {
              "ID": 1704,
              "Name": "Seattle Seahawks",
              "Abbreviation": "SEA",
              "Conference": "NFC",
              "Division": "West"
            },
            {
              "ID": 5117,
              "Name": "Los Angeles Rams",
              "Abbreviation": "LAR",
              "Conference": "NFC",
              "Division": "West"
            },
            {
              "ID": 1693,
              "Name": "Tampa Bay Buccaneers",
              "Abbreviation": "TB",
              "Conference": "NFC",
              "Division": "South"
            },
            {
              "ID": 1705,
              "Name": "Tennessee Titans",
              "Abbreviation": "TEN",
              "Conference": "AFC",
              "Division": "South"
            },
            {
              "ID": 5753,
              "Name": "Washington Commanders",
              "Abbreviation": "WAS",
              "Conference": "NFC",
              "Division": "East"
            }
        ]';

    public function getTeamAbrv($teamId)
    {
        $teams = collect(json_decode($this->teamAbrv, true));
        return $teams->where('ID', $teamId)->first();
    }

    public function getPuntReturns($scoreData)
    {
        $totalHPuntingReturns = 0;
        $totalAPuntingReturns = 0;
        $totalHPuntingReturnsYards = 0;
        $totalAPuntingReturnsYards = 0;

        if (isset($scoreData['punt_returns'])) {
            $puntingReturns = $scoreData['punt_returns'] ?? [];
            $hPuntingReturns = $puntingReturns['hometeam']['player'] ?? [];
            $aPuntingReturns = $puntingReturns['awayteam']['player'] ?? [];

            if (isset($hPuntingReturns['id'])) {
                $hPuntingReturns = [$hPuntingReturns];
            }

            if (isset($aPuntingReturns['id'])) {
                $aPuntingReturns = [$aPuntingReturns];
            }

            $hPuntingReturns = collect($hPuntingReturns);
            $aPuntingReturns = collect($aPuntingReturns);

            $totalHPuntingReturns = $hPuntingReturns->sum('total');
            $totalAPuntingReturns = $aPuntingReturns->sum('total');
            $totalHPuntingReturnsYards = $hPuntingReturns->sum('yards');
            $totalAPuntingReturnsYards = $aPuntingReturns->sum('yards');
        }

        return [
            'totalHPuntingReturns' => $totalHPuntingReturns,
            'totalAPuntingReturns' => $totalAPuntingReturns,
            'totalHPuntingReturnsYards' => $totalHPuntingReturnsYards,
            'totalAPuntingReturnsYards' => $totalAPuntingReturnsYards,
        ];
    }

    public function getPunting($scoreData)
    {
        $totalHPunting = 0;
        $totalAPunting = 0;
        $totalHPuntingYards = 0;
        $totalAPuntingYards = 0;

        if (isset($scoreData['punting'])) {
            $punting = $scoreData['punting'] ?? [];
            $hPunting = $punting['hometeam']['player'] ?? [];
            $aPunting = $punting['awayteam']['player'] ?? [];

            if (isset($hPunting['id'])) {
                $hPunting = [$hPunting];
            }

            if (isset($aPunting['id'])) {
                $aPunting = [$aPunting];
            }

            $hPunting = collect($hPunting);
            $aPunting = collect($aPunting);

            $totalHPunting = $hPunting->sum('total');
            $totalAPunting = $aPunting->sum('total');
            $totalHPuntingYards = $hPunting->sum('yards');
            $totalAPuntingYards = $aPunting->sum('yards');
        }

        return [
            'totalHPunting' => $totalHPunting,
            'totalAPunting' => $totalAPunting,
            'totalHPuntingYards' => $totalHPuntingYards,
            'totalAPuntingYards' => $totalAPuntingYards,
        ];
    }

    public function getKicking($scoreData)
    {

      $kicking = $scoreData['kicking'] ?? [];
      $hKicking = $kicking['hometeam']['player'] ?? [];
      $aKicking = $kicking['awayteam']['player'] ?? [];

      if (isset($hKicking['id'])) {
        $hKicking = [$hKicking];
      }

      if (isset($aKicking['id'])) {
        $aKicking = [$aKicking];
      }

      $hKicking = collect($hKicking);
      $aKicking = collect($aKicking);

      $a = $hKicking->map(function($item){
        $fg = $item['field_goals'] ?? 0;
        $xp = $item['extra_point'] ?? 0;

        return [
          ...$item,
          'fg' => $fg,
          'fg_numberator' => $item['field_goals'] ? (int)explode('/', $item['field_goals'])[0] : 0,
          'fg_denominator' => $item['field_goals'] ? (int)explode('/', $item['field_goals'])[1] : 0,
          'xp' => $xp,
          'xp_numberator' => $item['extra_point'] ? (int)explode('/', $item['extra_point'])[0] : 0,
          'xp_denominator' => $item['extra_point'] ? (int)explode('/', $item['extra_point'])[1] : 0,
          'name' => $item['name'],
        ]; 
      });
      $b = $aKicking->map(function($item){
        $fg = $item['field_goals'] ?? 0;
        $xp = $item['extra_point'] ?? 0;

        return [
          ...$item,
          'fg' => $fg,
          'fg_numberator' => $item['field_goals'] ? (int)explode('/', $item['field_goals'])[0] : 0,
          'fg_denominator' => $item['field_goals'] ? (int)explode('/', $item['field_goals'])[1] : 0,
          'xp' => $xp,
          'xp_numberator' => $item['extra_point'] ? (int)explode('/', $item['extra_point'])[0] : 0,
          'xp_denominator' => $item['extra_point'] ? (int)explode('/', $item['extra_point'])[1] : 0,
          'name' => $item['name'],
        ]; 
      });



      return [
        'totalHKicking' => $a,
        'totalAKicking' => $b,
      ];
    }
}