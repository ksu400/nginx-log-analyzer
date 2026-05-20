<?php

declare(strict_types=1);

namespace app\models;

use yii\web\Request;

class LogFilter
{
    private const VALID_SORT = ['date', 'count', 'top_url', 'top_browser'];

    public function __construct(
        public readonly string $dateFrom,
        public readonly string $dateTo,
        public readonly string $os,
        public readonly string $architecture,
        public readonly string $sort,
        public readonly string $dir,
    )
    {
    }

    public static function fromRequest(Request $request, array $dateRange): self
    {
        $defaultFrom = date('Y-m-d', strtotime('-30 days'));
        $defaultTo   = date('Y-m-d');

        if ($request->get('date_from') === null && $request->get('date_to') === null) {
            $dateFrom = $dateRange['min_date'] ?? $defaultFrom;
            $dateTo   = $dateRange['max_date'] ?? $defaultTo;
        } else {
            $dateFrom = $request->get('date_from', $defaultFrom);
            $dateTo   = $request->get('date_to', $defaultTo);
        }

        if (!self::isValidDate($dateFrom)) {
            $dateFrom = date('Y-m-d', strtotime('-30 days'));
        }
        if (!self::isValidDate($dateTo)) {
            $dateTo = date('Y-m-d');
        }
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $toObj = new \DateTime($dateTo);
        if ((new \DateTime($dateFrom))->diff($toObj)->days > 365) {
            $dateFrom = (clone $toObj)->modify('-365 days')->format('Y-m-d');
        }

        $sort = in_array($request->get('sort'), self::VALID_SORT, true)
            ? $request->get('sort')
            : 'date';
        $dir = strtoupper((string) $request->get('dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        return new self(
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            os: (string) $request->get('os', ''),
            architecture: (string) $request->get('architecture', ''),
            sort: $sort,
            dir: $dir,
        );
    }

    /** Returns [whereSql, params] for use in DB queries. */
    public function buildWhere(): array
    {
        $parts = ['DATE(requested_at) BETWEEN :date_from AND :date_to'];
        $params = [':date_from' => $this->dateFrom, ':date_to' => $this->dateTo];

        if ($this->os !== '') {
            $parts[] = 'os = :os';
            $params[':os'] = $this->os;
        }
        if ($this->architecture !== '') {
            $parts[] = 'architecture = :arch';
            $params[':arch'] = $this->architecture;
        }

        return [implode(' AND ', $parts), $params];
    }

    public function toArray(): array
    {
        return [
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'os' => $this->os,
            'architecture' => $this->architecture,
        ];
    }

    private static function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }
}
