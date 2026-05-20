<?php

declare(strict_types=1);

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\services\UserAgentParser;
use yii\db\Exception;

/**
 * Парсит nginx access-лог и сохраняет записи в базу данных.
 */
class ParseLogController extends Controller
{
    private const LOG_PATTERN = '/^(?P<ip>\S+) \S+ \S+ \[(?P<datetime>[^\]]+)\] "\S+ (?P<url>\S+)[^"]*" \d+ \d+ "[^"]*" "(?P<ua>[^"]*)"$/';

    private const DATETIME_FORMAT = 'd/M/Y:H:i:s O';

    private const COLUMNS = ['ip', 'requested_at', 'url', 'user_agent', 'os', 'architecture', 'browser', 'extra_fields'];

    public int $batchSize = 500;

    public function __construct(
        $id,
        $module,
        private readonly UserAgentParser $parser,
        $config = [],
    )
    {
        parent::__construct($id, $module, $config);
    }

    /** @inheritdoc */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['batchSize']);
    }

    /**
     * Парсит лог-файл и сохраняет записи в таблицу logs.
     *
     * @param string $filePath абсолютный путь к nginx access-логу
     * @return int код завершения (ExitCode::OK или ExitCode::UNSPECIFIED_ERROR)
     * @throws Exception
     */
    public function actionIndex(string $filePath): int
    {
        if (!file_exists($filePath)) {
            $this->stderr("File not found: {$filePath}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->stderr("Cannot open file: {$filePath}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $batch = [];
        $total = 0;
        $skipped = 0;

        while (($line = fgets($handle)) !== false) {
            $row = $this->parseLine(rtrim($line));

            if ($row === null) {
                $skipped++;
                continue;
            }

            $batch[] = $row;

            if (count($batch) >= $this->batchSize) {
                $this->flushBatch($batch);
                $total += count($batch);
                $batch = [];
                $this->stdout("Добавлено: {$total}\r");
            }
        }

        if ($batch !== []) {
            $this->flushBatch($batch);
            $total += count($batch);
        }

        fclose($handle);

        $this->stdout("\nДобавлено: {$total}, пропущено: {$skipped}\n");
        return ExitCode::OK;
    }

    /**
     * Разбирает одну строку лога.
     * Возвращает массив значений для batchInsert или null если строка не соответствует формату.
     *
     * @return list<string|int|null>|null
     */
    private function parseLine(string $line): ?array
    {
        if (!preg_match(self::LOG_PATTERN, $line, $m)) {
            return null;
        }

        $dt = \DateTime::createFromFormat(self::DATETIME_FORMAT, $m['datetime']);
        if ($dt === false) {
            return null;
        }

        $ua = $m['ua'];
        $parsed = $this->parser->parse($ua);

        return [
            $m['ip'],
            $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            mb_substr($m['url'], 0, 2048),
            mb_substr($ua, 0, 65535),
            $parsed->os,
            $parsed->architecture,
            $parsed->browser,
            json_encode(['raw' => $line], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Сохраняет батч строк в базу данных одним INSERT-запросом.
     *
     * @param list<list<string|int|null>> $batch
     * @throws Exception
     */
    private function flushBatch(array $batch): void
    {
        Yii::$app->db->createCommand()
            ->batchInsert('{{%logs}}', self::COLUMNS, $batch)
            ->execute();
    }
}
