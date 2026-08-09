<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260809100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Majpanel administrator and demo blog tables';
    }

    public function up(Schema $schema): void
    {
        $users = $schema->createTable('majpanel_admin_user');
        $users->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $users->addColumn('username', Types::STRING, ['length' => 180]);
        $users->addColumn('roles', Types::JSON);
        $users->addColumn('password', Types::STRING, ['length' => 255]);
        $users->setPrimaryKey(['id']);
        $users->addUniqueIndex(['username'], 'uniq_majpanel_admin_username');

        $blogs = $schema->createTable('majpanel_blog');
        $blogs->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $blogs->addColumn('title', Types::STRING, ['length' => 255]);
        $blogs->addColumn('content', Types::TEXT);
        $blogs->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $blogs->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('majpanel_blog');
        $schema->dropTable('majpanel_admin_user');
    }
}
