<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260811100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the retired Majpanel demo blog table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('majpanel_blog')) {
            $schema->dropTable('majpanel_blog');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('majpanel_blog')) {
            return;
        }

        $blogs = $schema->createTable('majpanel_blog');
        $blogs->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $blogs->addColumn('title', Types::STRING, ['length' => 255]);
        $blogs->addColumn('content', Types::TEXT);
        $blogs->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $blogs->setPrimaryKey(['id']);
    }
}
