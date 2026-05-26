<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260525175646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE aliment (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, code_api VARCHAR(100) DEFAULT NULL, calories100g NUMERIC(7, 2) NOT NULL, proteines100g NUMERIC(6, 2) NOT NULL, glucides100g NUMERIC(6, 2) NOT NULL, lipides100g NUMERIC(6, 2) NOT NULL, fibres100g NUMERIC(6, 2) DEFAULT NULL, UNIQUE INDEX UNIQ_70FF972B4525EFA5 (code_api), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE exercice (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, groupe_musculaire VARCHAR(100) DEFAULT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE journal_alimentaire (id INT AUTO_INCREMENT NOT NULL, id_utilisateur INT NOT NULL, date_journal DATE NOT NULL, calories_totales NUMERIC(8, 2) DEFAULT \'0\' NOT NULL, proteines_totales NUMERIC(7, 2) DEFAULT \'0\' NOT NULL, glucides_totaux NUMERIC(7, 2) DEFAULT \'0\' NOT NULL, lipides_totaux NUMERIC(7, 2) DEFAULT \'0\' NOT NULL, fibres_totales NUMERIC(7, 2) DEFAULT \'0\' NOT NULL, INDEX IDX_93008F1550EAE44 (id_utilisateur), UNIQUE INDEX uniq_journal_user_date (id_utilisateur, date_journal), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ligne_journal (id INT AUTO_INCREMENT NOT NULL, journal_id INT NOT NULL, id_aliment INT NOT NULL, quantite_g NUMERIC(7, 2) NOT NULL, type_repas VARCHAR(30) NOT NULL, calories NUMERIC(7, 2) NOT NULL, proteines NUMERIC(6, 2) NOT NULL, glucides NUMERIC(6, 2) NOT NULL, lipides NUMERIC(6, 2) NOT NULL, fibres NUMERIC(6, 2) DEFAULT \'0\' NOT NULL, INDEX IDX_5E3ED974478E8802 (journal_id), INDEX IDX_5E3ED974AE623E5B (id_aliment), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE objectif (id INT AUTO_INCREMENT NOT NULL, id_utilisateur INT NOT NULL, type VARCHAR(20) NOT NULL, calories_jour INT NOT NULL, proteines_g INT DEFAULT NULL, glucides_g INT DEFAULT NULL, lipides_g INT DEFAULT NULL, actif TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_E2F8685150EAE44 (id_utilisateur), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE poids_historique (id INT AUTO_INCREMENT NOT NULL, id_utilisateur INT NOT NULL, date_pesee DATE NOT NULL, poids_kg NUMERIC(5, 2) NOT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_7F0D79EF50EAE44 (id_utilisateur), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recovery_score (id INT AUTO_INCREMENT NOT NULL, id_utilisateur INT NOT NULL, date_calcul DATE NOT NULL, score INT NOT NULL, recommandation VARCHAR(50) NOT NULL, charge7j NUMERIC(10, 2) DEFAULT NULL, bilan_calorique NUMERIC(7, 2) DEFAULT NULL, heures_sommeil_moy NUMERIC(4, 2) DEFAULT NULL, calculated_at DATETIME NOT NULL, INDEX IDX_26C3F32B50EAE44 (id_utilisateur), UNIQUE INDEX uniq_recovery_user_date (id_utilisateur, date_calcul), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE seance (id INT AUTO_INCREMENT NOT NULL, id_utilisateur INT NOT NULL, nom VARCHAR(150) NOT NULL, date_seance DATE NOT NULL, statut VARCHAR(20) DEFAULT \'en_cours\' NOT NULL, tonnage_total NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_DF7DFD0E50EAE44 (id_utilisateur), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE serie_exercice (id INT AUTO_INCREMENT NOT NULL, seance_id INT NOT NULL, id_exercice INT NOT NULL, repetitions INT NOT NULL, charge_kg NUMERIC(6, 2) NOT NULL, tonnage NUMERIC(8, 2) NOT NULL, numero_serie INT NOT NULL, INDEX IDX_AC9E6875E3797A94 (seance_id), INDEX IDX_AC9E6875B4C32BD8 (id_exercice), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sommeil (id INT AUTO_INCREMENT NOT NULL, id_utilisateur INT NOT NULL, date_nuit DATE NOT NULL, heures_sommeil NUMERIC(4, 2) NOT NULL, qualite_sommeil INT NOT NULL, INDEX IDX_AF87C03350EAE44 (id_utilisateur), UNIQUE INDEX uniq_sommeil_user_date (id_utilisateur, date_nuit), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, date_naissance DATE DEFAULT NULL, sexe VARCHAR(10) DEFAULT NULL, taille INT DEFAULT NULL, poids_initial NUMERIC(5, 2) DEFAULT NULL, calories_objectif INT DEFAULT NULL, niveau_activite VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE journal_alimentaire ADD CONSTRAINT FK_93008F1550EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_journal ADD CONSTRAINT FK_5E3ED974478E8802 FOREIGN KEY (journal_id) REFERENCES journal_alimentaire (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_journal ADD CONSTRAINT FK_5E3ED974AE623E5B FOREIGN KEY (id_aliment) REFERENCES aliment (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE objectif ADD CONSTRAINT FK_E2F8685150EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poids_historique ADD CONSTRAINT FK_7F0D79EF50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recovery_score ADD CONSTRAINT FK_26C3F32B50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0E50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE serie_exercice ADD CONSTRAINT FK_AC9E6875E3797A94 FOREIGN KEY (seance_id) REFERENCES seance (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE serie_exercice ADD CONSTRAINT FK_AC9E6875B4C32BD8 FOREIGN KEY (id_exercice) REFERENCES exercice (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE sommeil ADD CONSTRAINT FK_AF87C03350EAE44 FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE journal_alimentaire DROP FOREIGN KEY FK_93008F1550EAE44');
        $this->addSql('ALTER TABLE ligne_journal DROP FOREIGN KEY FK_5E3ED974478E8802');
        $this->addSql('ALTER TABLE ligne_journal DROP FOREIGN KEY FK_5E3ED974AE623E5B');
        $this->addSql('ALTER TABLE objectif DROP FOREIGN KEY FK_E2F8685150EAE44');
        $this->addSql('ALTER TABLE poids_historique DROP FOREIGN KEY FK_7F0D79EF50EAE44');
        $this->addSql('ALTER TABLE recovery_score DROP FOREIGN KEY FK_26C3F32B50EAE44');
        $this->addSql('ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0E50EAE44');
        $this->addSql('ALTER TABLE serie_exercice DROP FOREIGN KEY FK_AC9E6875E3797A94');
        $this->addSql('ALTER TABLE serie_exercice DROP FOREIGN KEY FK_AC9E6875B4C32BD8');
        $this->addSql('ALTER TABLE sommeil DROP FOREIGN KEY FK_AF87C03350EAE44');
        $this->addSql('DROP TABLE aliment');
        $this->addSql('DROP TABLE exercice');
        $this->addSql('DROP TABLE journal_alimentaire');
        $this->addSql('DROP TABLE ligne_journal');
        $this->addSql('DROP TABLE objectif');
        $this->addSql('DROP TABLE poids_historique');
        $this->addSql('DROP TABLE recovery_score');
        $this->addSql('DROP TABLE seance');
        $this->addSql('DROP TABLE serie_exercice');
        $this->addSql('DROP TABLE sommeil');
        $this->addSql('DROP TABLE utilisateur');
    }
}
