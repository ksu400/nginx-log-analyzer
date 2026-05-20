<?php

/** @var yii\web\View $this */
/** @var array $dailyStats */
/** @var array $chartDates */
/** @var array $chartCounts */
/** @var array $browserDatasets */
/** @var array $osOptions */
/** @var array $archOptions */
/** @var array $filters */
/** @var string $sort */

/** @var string $dir */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Nginx Log Analysis';

$dataUrl = Url::to(['/site/data']);
$indexUrl = Url::to(['/site/index']);
?>

<div class="container-fluid py-3">
    <h1 class="h3 mb-4">Nginx Log Analysis</h1>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filter-form" method="get" action="<?= Html::encode($indexUrl) ?>" class="row g-3 align-items-end">
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Date from</label>
                    <?= Html::input('date', 'date_from', $filters['dateFrom'], ['class' => 'form-control']) ?>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Date to</label>
                    <?= Html::input('date', 'date_to', $filters['dateTo'], ['class' => 'form-control']) ?>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">OS</label>
                    <?= Html::dropDownList(
                        'os',
                        $filters['os'],
                        array_merge(['' => '— All —'], array_combine($osOptions, $osOptions)),
                        ['class' => 'form-select']
                    ) ?>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Architecture</label>
                    <?= Html::dropDownList(
                        'architecture',
                        $filters['architecture'],
                        array_merge(['' => '— All —'], array_combine($archOptions, $archOptions)),
                        ['class' => 'form-select']
                    ) ?>
                </div>
                <div class="col-auto">
                    <button type="button" id="apply-btn" class="btn btn-primary">Apply</button>
                    <button type="button" id="reset-btn" class="btn btn-outline-secondary ms-1">Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4 mb-lg-0">
            <div class="card h-100">
                <div class="card-header fw-semibold">Requests per Day</div>
                <div class="card-body">
                    <canvas id="requestsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Top 3 Browsers — % of Daily Requests</div>
                <div class="card-body">
                    <canvas id="browsersChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header fw-semibold">Daily Statistics</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="stats-table" class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                    <tr>
                        <th class="sort-col" data-sort-col="date">Date <span class="sort-icon"></span></th>
                        <th class="sort-col" data-sort-col="count">Requests <span class="sort-icon"></span></th>
                        <th class="sort-col" data-sort-col="top_url">Top URL <span class="sort-icon"></span></th>
                        <th class="sort-col" data-sort-col="top_browser">Top Browser <span class="sort-icon"></span>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($dailyStats)): ?>
                        <tr>
                            <td colspan="4" class="p-3 text-muted">No data for the selected filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyStats as $row): ?>
                            <tr>
                                <td><?= Html::encode($row['date']) ?></td>
                                <td><?= number_format((int) $row['count']) ?></td>
                                <td>
                                        <span class="url-preview" title="<?= Html::encode($row['top_url']) ?>">
                                            <?= Html::encode(mb_strimwidth((string) $row['top_url'], 0, 80, '…')) ?>
                                        </span>
                                </td>
                                <td><?= Html::encode((string) $row['top_browser']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php

$this->registerJs(
    'window.dashboardConfig = ' . json_encode([
        'dataUrl' => $dataUrl,
        'indexUrl' => $indexUrl,
        'sort' => $sort,
        'dir' => $dir,
        'chartDates' => $chartDates,
        'chartCounts' => $chartCounts,
        'browserDatasets' => $browserDatasets,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';',
    \yii\web\View::POS_BEGIN
);

$this->registerJsFile(
    'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
    ['position' => \yii\web\View::POS_END]
);

$this->registerJsFile(
    '@web/js/dashboard.js',
    ['position' => \yii\web\View::POS_END]
);
?>
