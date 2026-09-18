<?php

declare(strict_types=1);

namespace OCA\Memories\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version900001Date20260918120000 extends SimpleMigrationStep
{
    #[\Override]
    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        $schema = $schemaClosure();
        if (!$schema->hasTable('memories_album_order')) {
            $table = $schema->createTable('memories_album_order');
            $table->addColumn('album_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('items', Types::TEXT, ['notnull' => true]);
            $table->addColumn('revision', Types::STRING, ['notnull' => true, 'length' => 32]);
            $table->addColumn('manual', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
            $table->setPrimaryKey(['album_id']);
        }

        return $schema;
    }
}
