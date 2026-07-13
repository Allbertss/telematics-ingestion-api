<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260713185622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Document the coordinate reference system (WGS84) on latitude/longitude';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN telematics_record.latitude IS \'WGS84 (EPSG:4326), decimal degrees\'');
        $this->addSql('COMMENT ON COLUMN telematics_record.longitude IS \'WGS84 (EPSG:4326), decimal degrees\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('COMMENT ON COLUMN telematics_record.latitude IS \'\'');
        $this->addSql('COMMENT ON COLUMN telematics_record.longitude IS \'\'');
    }
}
