<?php

namespace StimData\App\Groupement\Tests\phpunit;

use StimData\App\Core\Fichier;
use StimData\App\Declaratif\Entities\Declaratif;
use StimData\App\Declaratif\Entities\DeclaratifFichier;
use StimData\App\Import\Services\ImportPhotoOperationService;
use StimData\App\Inscription\Entities\Inscription;
use StimData\App\Operation\Entities\Operation;
use StimData\App\Operation\Entities\OperationProduit;
use StimData\App\Pharmacie\Entities\Pharmacie;
use StimData\App\Produit\Entities\Produit;
use StimData\Core\Entities\Processus;
use StimData\Core\StimDataApp;
use StimData\Core\StimDataTestCase;

class ImportPhotoTest extends StimDataTestCase
{

    public function testBasique()
    {
        $this->assertClassExists(ImportPhotoOperationService::class);
    }

    public function testImportZipValideAvecUnePharmacieOperationAvecDeclaration()
    {
        $phamarcie = new Pharmacie();

        $phamarcie->code_cip = '2189782';

        $phamarcie->save();

        $processus = $this->initTest();

        $operationRepo = StimDataApp::getRepository(Operation::class);
        $operation = $operationRepo->findOneById(1);
        $operation->declaration = true;
        $operation->save();

        $produit = new Produit();
        $produit->nom = 'test';
        $produit->gamme = 'test';
        $produit->vente_precedente = 12;

        $produit->save();

        $produitOperation = new OperationProduit();
        $produitOperation->operation_id = $operation->id;
        $produitOperation->produit_id = $produit->id;
        $produitOperation->save();

        $service = StimDataApp::getFromContainer(ImportPhotoOperationService::class);

        $args = json_decode($processus->args);
        $service->run($processus, $args);

        $fichierRepo = StimDataApp::getRepository(Fichier::class);
        $this->assertCount(2, $fichierRepo->findAll());

        $inscriptionRepo = StimDataApp::getRepository(Inscription::class);
        $this->assertCount(1, $inscriptionRepo->findAll());

        $repo = StimDataApp::getRepository(DeclaratifFichier::class);
        $data = $repo->findAll();
        $this->assertCount(1, $data);

        $this->assertSame('2', $data[0]->fichier_id);
        $this->assertSame('1', $data[0]->declaratif_id);
        $this->assertSame(DeclaratifFichier::TYPE_PHOTO, $data[0]->type);

        $repo = StimDataApp::getRepository(Declaratif::class);
        $this->assertCount(1, $repo->findAll());

        $this->assertStringContainsString('Import terminé : 1 photo(s) importées', $processus->log);
        $this->assertStringContainsString('Aucune pharmacie trouvé pour ce code cip [2190122]', $processus->log);
        $this->assertStringContainsString('Aucune pharmacie trouvé pour ce code cip [2003735]', $processus->log);
    }

    private function initTest()
    {
        $fichier = __DIR__ . '/../fixtures/photos.zip';

        copy($fichier, '/tmp/temp.zip');

        $fichierZip = new Fichier();
        $fichierZip->setMode(Fichier::INPUT_MODE)
            ->setType(Fichier::TYPE_ZIP_PHOTO_VENTES)
            ->setContentDepuisFichier('/tmp/temp.zip');

        $fichierZip->save();

        $operation = new Operation();

        $operation->nom = 'TEST';
        $operation->libelle = 'TEST';
        $operation->preambule = 'TEST';
        $operation->visible_front = true;
        $operation->visible_back = true;
        $operation->categorie_menu = 'TEST';
        $operation->rang = 1;
        $operation->millesime = 2023;
        $operation->mode_declaration = 'total';
        $operation->actif = true;
        $operation->facturable = true;
        $operation->inscription_date_debut = '2023-01-01 12:12:30';
        $operation->inscription_date_fin = '2023-01-31 12:12:30';
        $operation->prestation_date_debut = date('Y-m-d') . ' 00:00:00';
        $operation->prestation_date_fin = date('Y-m-d') . ' 23:00:00';
        $operation->signable = true;
        $operation->libelle_rang = 'TEST';

        $operation->save();

        $processus = new Processus();
        $processus->cmd = ImportPhotoOperationService::class;
        $processus->fichier_id = $fichierZip->id;
        $processus->type = 'TEST';
        $processus->statut = 'TEST';
        $processus->args = json_encode(['operation' => 1]);

        $processus->save();

        return $processus;
    }

    public function testImportZipValideAvecUnePharmacieOperationSansDeclaration()
    {

        $phamarcie = new Pharmacie();

        $phamarcie->code_cip = '2189782';

        $phamarcie->save();

        $processus = $this->initTest();

        $service = StimDataApp::getFromContainer(ImportPhotoOperationService::class);

        $args = json_decode($processus->args);
        $service->run($processus, $args);

        $fichierRepo = StimDataApp::getRepository(Fichier::class);

        $this->assertCount(1, $fichierRepo->findAll());

        $inscriptionRepo = StimDataApp::getRepository(Inscription::class);

        $this->assertCount(1, $inscriptionRepo->findAll());

        $this->assertStringContainsString('Pas de déclaration demandé pour l\'opération, on passe à la suivante', $processus->log);
        $this->assertStringContainsString('Aucune pharmacie trouvé pour ce code cip [2190122]', $processus->log);
        $this->assertStringContainsString('Aucune pharmacie trouvé pour ce code cip [2003735]', $processus->log);
    }

    public function testImportZipValideMaisPasDePharmacie()
    {
        $processus = $this->initTest();

        $service = StimDataApp::getFromContainer(ImportPhotoOperationService::class);

        $args = json_decode($processus->args);
        $service->run($processus, $args);

        $fichierRepo = StimDataApp::getRepository(Fichier::class);

        $this->assertCount(1, $fichierRepo->findAll());

        $inscriptionRepo = StimDataApp::getRepository(Inscription::class);

        $this->assertCount(0, $inscriptionRepo->findAll());

        $this->assertStringContainsString('Aucune pharmacie trouvé pour ce code cip [2189782]', $processus->log);
        $this->assertStringContainsString('Aucune pharmacie trouvé pour ce code cip [2190122]', $processus->log);
        $this->assertStringContainsString('Aucune pharmacie trouvé pour ce code cip [2003735]', $processus->log);
    }

}

