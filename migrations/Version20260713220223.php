<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260713220223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen speed_kmh to INT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE telematics_record ALTER speed_kmh TYPE INT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE telematics_record ALTER speed_kmh TYPE SMALLINT');
    }
}
