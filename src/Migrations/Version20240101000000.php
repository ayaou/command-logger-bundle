<?php

declare(strict_types=1);

namespace Ayaou\CommandLoggerBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to create the command_log table for the Command Logger Bundle.
 */
final class Version20240101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create command_log table for command logging functionality';
    }

    public function up(Schema $schema): void
    {
        // Check if table already exists to avoid conflicts
        if ($schema->hasTable('command_log')) {
            return;
        }

        $table = $schema->createTable('command_log');
        
        // Primary key
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        
        // Command information
        $table->addColumn('command_name', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('arguments', 'json', ['notnull' => false]);
        
        // Timing information
        $table->addColumn('start_time', 'datetime_immutable', ['notnull' => true]);
        $table->addColumn('end_time', 'datetime_immutable', ['notnull' => false]);
        
        // Result information
        $table->addColumn('exit_code', 'integer', ['notnull' => false]);
        $table->addColumn('error_message', 'text', ['notnull' => false]);
        
        // Unique execution tracking
        $table->addColumn('execution_token', 'string', ['length' => 36, 'notnull' => true]);
        $table->addUniqueIndex(['execution_token'], 'UNIQ_execution_token');
        
        // Performance indexes
        $table->addIndex(['command_name'], 'IDX_command_name');
        $table->addIndex(['start_time'], 'IDX_start_time');
        $table->addIndex(['command_name', 'start_time'], 'IDX_command_name_start_time');
        $table->addIndex(['exit_code'], 'IDX_exit_code');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('command_log')) {
            $schema->dropTable('command_log');
        }
    }
}