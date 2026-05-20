<?php

declare(strict_types=1);

namespace app\repositories;

use app\models\LogFilter;
use yii\db\Connection;

class LogEntryRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function getDateRange(): array
    {
        return $this->db->createCommand(
            'SELECT MIN(DATE(requested_at)) AS min_date, MAX(DATE(requested_at)) AS max_date FROM logs WHERE deleted_at IS NULL'
        )->queryOne() ?: [];
    }

    /**
     * Returns daily statistics merged with top URL and top browser, sorted per filter.
     *
     * @return array<array{date: string, count: int, top_url: string, top_browser: string}>
     */
    public function getDailyStats(LogFilter $filter): array
    {
        [$whereSql, $params] = $filter->buildWhere();

        $dailyRows = $this->fetchDailyRows($whereSql, $params);
        $dateToCount = array_column($dailyRows, 'count', 'date');
        $dateToTopUrl = $this->fetchTopPerDay('url', 'top_url', $whereSql, $params);
        $dateToTopBrowser = $this->fetchTopPerDay('browser', 'top_browser', $whereSql, $params);

        $stats = [];
        foreach ($dateToCount as $date => $count) {
            $stats[] = [
                'date' => $date,
                'count' => (int) $count,
                'top_url' => $dateToTopUrl[$date] ?? '-',
                'top_browser' => $dateToTopBrowser[$date] ?? '-',
            ];
        }

        usort($stats, function (array $a, array $b) use ($filter): int {
            $cmp = match ($filter->sort) {
                'count' => $a['count'] <=> $b['count'],
                'top_url' => strcmp((string) $a['top_url'], (string) $b['top_url']),
                'top_browser' => strcmp((string) $a['top_browser'], (string) $b['top_browser']),
                default => strcmp($a['date'], $b['date']),
            };
            return $filter->dir === 'DESC' ? -$cmp : $cmp;
        });

        return $stats;
    }

    /**
     * Returns chart data for the "requests per day" chart.
     *
     * @return array{dates: string[], counts: int[], dateToCount: array<string, int>}
     */
    public function getChartData(LogFilter $filter): array
    {
        [$whereSql, $params] = $filter->buildWhere();
        $rows = $this->fetchDailyRows($whereSql, $params);

        usort($rows, fn($a, $b) => strcmp($a['date'], $b['date']));

        $dates = array_column($rows, 'date');
        $counts = array_map('intval', array_column($rows, 'count'));
        $dateToCount = array_combine($dates, $counts);

        return [
            'dates' => $dates,
            'counts' => $counts,
            'dateToCount' => $dateToCount,
        ];
    }

    /**
     * Returns Chart.js dataset configs for the top-3 browsers percentage chart.
     */
    public function getBrowserDatasets(LogFilter $filter, array $chartDates, array $dateToCount): array
    {
        [$whereSql, $params] = $filter->buildWhere();

        $top3Rows = $this->db->createCommand(
            "SELECT browser, COUNT(*) AS cnt
             FROM logs
             WHERE deleted_at IS NULL AND {$whereSql} AND browser IS NOT NULL
             GROUP BY browser
             ORDER BY cnt DESC
             LIMIT 3",
            $params
        )->queryAll();

        $top3Names = array_column($top3Rows, 'browser');
        if (!$top3Names) {
            return [];
        }

        $inParams = $params;
        $inParts = [];
        foreach ($top3Names as $i => $name) {
            $key = ":b{$i}";
            $inParts[] = $key;
            $inParams[$key] = $name;
        }

        $bRows = $this->db->createCommand(
            "SELECT DATE(requested_at) AS date, browser, COUNT(*) AS cnt
             FROM logs
             WHERE deleted_at IS NULL AND {$whereSql} AND browser IN (" . implode(',', $inParts) . ")
             GROUP BY DATE(requested_at), browser",
            $inParams
        )->queryAll();

        $byBrowser = [];
        foreach ($bRows as $row) {
            $byBrowser[$row['browser']][$row['date']] = (int) $row['cnt'];
        }

        $colors = ['rgb(54,162,235)', 'rgb(255,99,132)', 'rgb(75,192,192)'];
        $datasets = [];
        foreach ($top3Names as $idx => $browser) {
            $data = [];
            foreach ($chartDates as $date) {
                $total = $dateToCount[$date] ?: 1;
                $browserCnt = $byBrowser[$browser][$date] ?? 0;
                $data[] = round($browserCnt / $total * 100, 2);
            }
            $color = $colors[$idx] ?? 'rgb(153,102,255)';
            $datasets[] = [
                'label' => $browser,
                'data' => $data,
                'borderColor' => $color,
                'backgroundColor' => str_replace('rgb(', 'rgba(', rtrim($color, ')')) . ',0.1)',
                'tension' => 0.3,
                'fill' => true,
            ];
        }

        return $datasets;
    }

    /**
     * Returns distinct OS and architecture values for filter dropdowns.
     *
     * @return array{os: string[], arch: string[]}
     */
    public function getFilterOptions(): array
    {
        return [
            'os' => $this->db->createCommand(
                'SELECT DISTINCT os FROM logs WHERE deleted_at IS NULL AND os IS NOT NULL ORDER BY os'
            )->queryColumn(),
            'arch' => $this->db->createCommand(
                'SELECT DISTINCT architecture FROM logs WHERE deleted_at IS NULL AND architecture IS NOT NULL ORDER BY architecture'
            )->queryColumn(),
        ];
    }

    private function fetchDailyRows(string $whereSql, array $params): array
    {
        return $this->db->createCommand(
            "SELECT DATE(requested_at) AS date, COUNT(*) AS count
             FROM logs
             WHERE deleted_at IS NULL AND {$whereSql}
             GROUP BY DATE(requested_at)",
            $params
        )->queryAll();
    }

    private const ALLOWED_TOP_COLUMNS = ['url', 'browser'];

    /** Returns [date => value] map for the top value of $column per day. */
    private function fetchTopPerDay(string $column, string $alias, string $whereSql, array $params): array
    {
        if (!in_array($column, self::ALLOWED_TOP_COLUMNS, true)) {
            throw new \InvalidArgumentException("Column '{$column}' is not allowed in top-per-day query.");
        }

        $rows = $this->db->createCommand(
            "SELECT date, {$column} AS {$alias}
             FROM (
                 SELECT DATE(requested_at) AS date, {$column}, COUNT(*) AS cnt,
                        ROW_NUMBER() OVER (PARTITION BY DATE(requested_at) ORDER BY COUNT(*) DESC) AS rn
                 FROM logs
                 WHERE deleted_at IS NULL AND {$whereSql}
                 GROUP BY DATE(requested_at), {$column}
             ) t
             WHERE rn = 1",
            $params
        )->queryAll();

        return array_column($rows, $alias, 'date');
    }
}
