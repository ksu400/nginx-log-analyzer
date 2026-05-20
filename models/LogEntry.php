<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

class LogEntry extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%logs}}';
    }

    public function rules(): array
    {
        return [
            [['ip', 'requested_at', 'url', 'user_agent'], 'required'],
            ['ip', 'string', 'max' => 45],
            ['url', 'string', 'max' => 2048],
            ['user_agent', 'string'],
            ['os', 'string', 'max' => 100],
            ['architecture', 'string', 'max' => 10],
            ['browser', 'string', 'max' => 100],
        ];
    }
}
