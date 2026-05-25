<?php

namespace App\Tests\Service;

use App\Entity\Aliment;
use App\Entity\LigneJournal;
use PHPUnit\Framework\TestCase;

class NutritionServiceTest extends TestCase
{
    public function testCalculerCaloriesLigne(): void
    {
        $aliment = new Aliment();
        $aliment->setNom('Poulet grillé');
        $aliment->setCalories100g('165');
        $aliment->setProteines100g('31');
        $aliment->setGlucides100g('0');
        $aliment->setLipides100g('3.6');

        $ligne = new LigneJournal();
        $ligne->setAliment($aliment);
        $ligne->setQuantiteG('150');
        $ligne->setTypeRepas('dejeuner');
        $ligne->calculerCalories();

        $this->assertEquals('247.50', $ligne->getCalories());
        $this->assertEquals('46.50', $ligne->getProteines());
        $this->assertEquals('0.00', $ligne->getGlucides());
        $this->assertEquals('5.40', $ligne->getLipides());
    }

    public function testCaloriesArrondiDeuxDecimales(): void
    {
        $aliment = new Aliment();
        $aliment->setCalories100g('89');
        $aliment->setProteines100g('1.09');
        $aliment->setGlucides100g('22.84');
        $aliment->setLipides100g('0.33');

        $ligne = new LigneJournal();
        $ligne->setAliment($aliment);
        $ligne->setQuantiteG('120');
        $ligne->setTypeRepas('encas');
        $ligne->calculerCalories();

        $this->assertEquals('106.80', $ligne->getCalories());
    }
}
