<?php

declare(strict_types=1);

use yii\db\Migration;

class m000000_000000_create_logs_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%logs}}', [
            'id' => $this->primaryKey(),
            'ip' => $this->string(45)->notNull(),
            'requested_at' => $this->dateTime()->notNull(),
            'url' => $this->string(2048)->notNull(),
            'user_agent' => $this->text()->notNull(),
            'os' => $this->string(100)->null(),
            'architecture' => $this->string(10)->null(),
            'browser' => $this->string(100)->null(),
            'extra_fields' => $this->json()->null(),
            'deleted_at' => $this->dateTime()->null()->defaultValue(null),
        ]);

        $this->createIndex('idx_log_requested_at', '{{%logs}}', 'requested_at');
        $this->createIndex('idx_log_os', '{{%logs}}', 'os');
        $this->createIndex('idx_log_architecture', '{{%logs}}', 'architecture');
        $this->createIndex('idx_log_browser', '{{%logs}}', 'browser');
        $this->createIndex('idx_log_deleted_at', '{{%logs}}', 'deleted_at');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%logs}}');
    }
}
