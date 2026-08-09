<?php

declare(strict_types=1);

namespace Majpanel\MajpanelBundle\Tests\Fixtures\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804110000 extends AbstractMigration
{
    private const PRODUCT_MARKER = '<p>MajPanel sample fixture 20260804</p>';
    private const CATEGORY_MARKER = 'MajPanel sample fixture 20260804';

    public function getDescription(): string
    {
        return 'Create the complete MajPanel test schema, default admin, and sample catalog';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, price NUMERIC(10, 0) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, product_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, active TINYINT NOT NULL, INDEX IDX_64C19C14584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C14584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX uniq_app_user_username (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql(
            'INSERT INTO app_user (username, roles, password) VALUES (?, ?, ?)',
            ['admin', '["ROLE_ADMIN"]', '$2y$12$FVeQc63Ct4HyU5ehciAD1.shNrj/yfVgAUrtl2GH8P7oByhs9pJ.O'],
        );

        $this->addSql(
            <<<'SQL'
INSERT INTO product (name, description, price, created_at) VALUES
('Sample Laptop', '<p>MajPanel sample fixture 20260804</p>', 1299, NOW()),
('Sample Smartphone', '<p>MajPanel sample fixture 20260804</p>', 899, NOW()),
('Sample Headphones', '<p>MajPanel sample fixture 20260804</p>', 199, NOW()),
('Sample Camera', '<p>MajPanel sample fixture 20260804</p>', 749, NOW()),
('Sample Smart Watch', '<p>MajPanel sample fixture 20260804</p>', 299, NOW()),
('Sample Office Chair', '<p>MajPanel sample fixture 20260804</p>', 349, NOW()),
('Sample Coffee Maker', '<p>MajPanel sample fixture 20260804</p>', 149, NOW()),
('Sample Mechanical Keyboard', '<p>MajPanel sample fixture 20260804</p>', 129, NOW()),
('Sample Gaming Mouse', '<p>MajPanel sample fixture 20260804</p>', 79, NOW()),
('Sample External Monitor', '<p>MajPanel sample fixture 20260804</p>', 459, NOW())
SQL,
        );

        $this->insertCategory('Computers', 'Sample Laptop');
        $this->insertCategory('Business Equipment', 'Sample Laptop');
        $this->insertCategory('Mobile Devices', 'Sample Smartphone');
        $this->insertCategory('Communication', 'Sample Smartphone');
        $this->insertCategory('Audio', 'Sample Headphones');
        $this->insertCategory('Accessories', 'Sample Headphones');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C14584665A');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE messenger_messages');
    }

    private function insertCategory(string $categoryName, string $productName): void
    {
        $this->addSql(
            <<<'SQL'
INSERT INTO category (product_id, name, description, active)
SELECT id, ?, ?, 1
FROM product
WHERE name = ? AND description = ?
ORDER BY id DESC
LIMIT 1
SQL,
            [$categoryName, self::CATEGORY_MARKER, $productName, self::PRODUCT_MARKER],
        );
    }
}
