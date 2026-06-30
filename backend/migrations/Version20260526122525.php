<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260526122525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE aliment ADD nom_api VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE journal_alimentaire CHANGE calories_totales calories_totales NUMERIC(8, 2) DEFAULT \'0\' NOT NULL, CHANGE proteines_totales proteines_totales NUMERIC(7, 2) DEFAULT \'0\' NOT NULL, CHANGE glucides_totaux glucides_totaux NUMERIC(7, 2) DEFAULT \'0\' NOT NULL, CHANGE lipides_totaux lipides_totaux NUMERIC(7, 2) DEFAULT \'0\' NOT NULL, CHANGE fibres_totales fibres_totales NUMERIC(7, 2) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE ligne_journal CHANGE fibres fibres NUMERIC(6, 2) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE seance CHANGE tonnage_total tonnage_total NUMERIC(10, 2) DEFAULT \'0\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE aliment DROP nom_api');
        $this->addSql('ALTER TABLE ligne_journal CHANGE fibres fibres NUMERIC(6, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE journal_alimentaire CHANGE calories_totales calories_totales NUMERIC(8, 2) DEFAULT \'0.00\' NOT NULL, CHANGE proteines_totales proteines_totales NUMERIC(7, 2) DEFAULT \'0.00\' NOT NULL, CHANGE glucides_totaux glucides_totaux NUMERIC(7, 2) DEFAULT \'0.00\' NOT NULL, CHANGE lipides_totaux lipides_totaux NUMERIC(7, 2) DEFAULT \'0.00\' NOT NULL, CHANGE fibres_totales fibres_totales NUMERIC(7, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE seance CHANGE tonnage_total tonnage_total NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL');
    }
}
