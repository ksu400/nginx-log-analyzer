<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\LogFilter;
use app\repositories\LogEntryRepository;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\Response;

class SiteController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly LogEntryRepository $logRepository,
        $config = [],
    )
    {
        parent::__construct($id, $module, $config);
    }

    public function actions(): array
    {
        return [
            'error' => ['class' => ErrorAction::class],
        ];
    }

    public function actionIndex(): string
    {
        $filter = $this->buildFilter();
        $filterOptions = $this->logRepository->getFilterOptions();

        return $this->render('index', array_merge(
            $this->buildPageData($filter),
            [
                'osOptions'  => $filterOptions['os'],
                'archOptions' => $filterOptions['arch'],
                'filters'    => $filter->toArray(),
                'sort'       => $filter->sort,
                'dir'        => $filter->dir,
            ]
        ));
    }

    public function actionData(): Response
    {
        return $this->asJson($this->buildPageData($this->buildFilter()));
    }

    private function buildPageData(LogFilter $filter): array
    {
        $dailyStats  = $this->logRepository->getDailyStats($filter);
        $chartData   = $this->logRepository->getChartData($filter);
        $browserData = $this->logRepository->getBrowserDatasets($filter, $chartData['dates'], $chartData['dateToCount']);

        return [
            'dailyStats'      => $dailyStats,
            'chartDates'      => $chartData['dates'],
            'chartCounts'     => $chartData['counts'],
            'browserDatasets' => $browserData,
        ];
    }

    private function buildFilter(): LogFilter
    {
        return LogFilter::fromRequest($this->request, $this->logRepository->getDateRange());
    }
}
