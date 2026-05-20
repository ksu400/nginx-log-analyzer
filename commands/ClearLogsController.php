<?php

declare(strict_types=1);

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Мягко удаляет все записи из таблицы logs (soft delete).
 *
 * Использование: php yii clear-logs
 */
class ClearLogsController extends Controller
{
    /**
     * Помечает все активные записи как удалённые, выставляя deleted_at = NOW().
     * Данные физически остаются в базе, но больше не отображаются в приложении.
     */
    public function actionIndex(): int
    {
        $count = Yii::$app->db->createCommand(
            'UPDATE {{%logs}} SET deleted_at = NOW() WHERE deleted_at IS NULL'
        )->execute();

        $this->stdout("Помечено как удалённые: {$count} записей\n");

        return ExitCode::OK;
    }
}
