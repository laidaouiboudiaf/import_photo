<?php

namespace StimData\App\Import\Services;

use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\DBAL\Driver\Exception;
use StimData\App\Core\Fichier;
use StimData\App\Declaratif\Entities\Declaratif;
use StimData\App\Declaratif\Entities\DeclaratifFichier;
use StimData\App\Inscription\Entities\Inscription;
use StimData\App\Operation\Entities\Operation;
use StimData\App\Pharmacie\Repositories\PharmacieRepository;
use StimData\App\Pharmacie\Services\PharmacieService;
use StimData\Core\Entities\Processus;
use StimData\Core\Exceptions\StimDataException;
use StimData\Core\Services\DateTime;
use StimData\Core\StimDataApp;

class ImportPhotoOperationService
{
    private bool $testMode = false;

    public function __construct()
    {
    }

    /**
     * @throws Exception
     * @throws DependencyException
     * @throws NotFoundException
     * @throws StimDataException
     */
    public function run(Processus $processus, $args)
    {
        $fichier_id = $processus->fichier_id;
        $pathPhotoSource = StimDataApp::getFromContainer('tmp_dir') . '/imports_zip_photos/' . $fichier_id . '/';

        $fichierZip = new Fichier();
        $fichierZip->load($fichier_id);

        try {
            if (!is_dir($pathPhotoSource)) {
                mkdir($pathPhotoSource, 0777, true);
            }

            //@TODO prévoir un script de purge des ZIP lorsque traitement OK
            $fichierZip->unzipFichier($pathPhotoSource);
        } catch (\Exception $e) {
            $processus->setStatut(Processus::STATUT_ERROR, $e->getMessage());
            exit();
        }

        $rowInscription = 0;
        $log = [];

        $photoTransferer = 0;
        $timeStart = DateTime::getDate('U');
        $operationId = $args->operation;
        $msg = "Options de traitement : \r\n";
        $msg .= 'Operation ID : ' . $operationId . "\r\n";

        $operation = new Operation();
        $isLoaded = $operation->load($operationId);

        if (!$isLoaded) {
            $processus->setStatut(Processus::STATUT_ERROR, 'Opération inconnue id ' . $operationId);
            exit();
        }

        $files = Fichier::obtenirFichiersRepertoire($pathPhotoSource);
        $nbFiles = count($files);

        /* Vérifie si des fichiers ont été trouvés dans le répertoire. Sinon, affiche un message d'erreur et termine le script. */
        if ($nbFiles === 0) {
            $msg .= "Aucun fichier trouvé dans le répertoire : " . $pathPhotoSource . ", import fini <br/>";
            echo $msg;
            $processus->setStatut(Processus::STATUT_ERROR, $msg);
            exit();
        }

        $codeCipNonValid = [];
        $fileError = [];

        foreach ($files as $file) {
            /* Extrait les 7 premiers caractères du nom de fichier pour obtenir le code CIP. */
            $codeCip = intval(substr($file, 0, 7));
            $pharmacieService = StimDataApp::getFromContainer(PharmacieService::class);

            try {
                $pharmacie = $pharmacieService->trouverLaPharmacieDepuisUnCodeCIP($codeCip);

                /* Si l'inscription n'existe pas, une invitation est créée pour la pharmacie. */
                $inscriptionRepo = StimDataApp::getRepository(Inscription::class);
                $inscriptions = $inscriptionRepo->findBy([
                    'pharmacie_id' => $pharmacie->id,
                    'operation_id' => $operationId,
                    'etat' => [Inscription::ETAT_SIGNED, Inscription::ETAT_BYPASS]
                ]);

                if (count($inscriptions) > 1) {
                    $msg .= 'Erreur code client ' . $codeCip . ', impossible de charger l\'inscription correspondante ' . $operationId . "<br/>";
                }

                if (empty($inscriptions)) {
                    $result = $operation->inviter($pharmacie, null, null, false, false, false, null, false, true);
                    if ($result['success']) {
                        $inscriptions = [$result['inscription']];
                        $rowInscription++;
                    } else {
                        throw new \Exception('Erreur lors de la création de l\'inscription', 'ERREUR_INSCRIPTION');
                    }
                }

                $inscription = $inscriptions[0];

                if ($inscription->declaratif_id === null) {
                    throw new \Exception('Pas de déclaration demandée pour l\'opération, on passe à la suivante', 'ERREUR_DECLARATION');
                } else {
                    $declaratifCheck = new Declaratif();
                    $declaratifCheck->load($inscription->declaratif_id, 'id');
                    $etatDeclaratif = $declaratifCheck->statut_photo;
                    if ($etatDeclaratif !== Declaratif::STATUT_NEW && $etatDeclaratif !== Declaratif::STATUT_VALIDATION_REFUSE) {
                        throw new \Exception('La déclaration demandée est déjà traitée pour l\'opération et le Cip  : ' . $codeCip, 'ERREUR_PHOTO_EXISTE');
                    }
                }

                $newFile = new Fichier();
                $newFile->setMode(Fichier::INPUT_MODE)
                    ->setIdComplementaire($inscription->declaratif_id)
                    ->setIdComplementaire($inscription->id, Fichier::ID_TYPE_INSCRIPTION)
                    ->setIdComplementaire($inscription->operation_id, Fichier::ID_TYPE_OPERATION)
                    ->setType(Fichier::TYPE_DECLARATIF_PHOTO)
                    ->setContentDepuisFichier($pathPhotoSource . $file);
                $newFile->save();

                $declaratifFichier = new DeclaratifFichier();
                $declaratifFichier->fichier_id = $newFile->id;
                $declaratifFichier->declaratif_id = $inscription->declaratif_id;
                $declaratifFichier->type = DeclaratifFichier::TYPE_PHOTO;
                $declaratifFichier->date_creation = date('Y-m-d H:i:s');
                $declaratifFichier->save();

                $declaratif = new Declaratif();
                $declaratif->load($inscription->declaratif_id);
                if ($declaratif->statut_photo == Declaratif::STATUT_VALIDATION_REFUSE) {
                    $declaratif->statut_photo = Declaratif::STATUT_VALIDATION_PENDING;
                }
                $declaratif->date_creation_photo = $declaratif->date_etat_photo = date('Y-m-d H:i:s');
                $declaratif->save();

                $photoTransferer++;
                $processus->setStatut(Processus::STATUT_PENDING, $photoTransferer . ' photo(s) traitée(s) sur ' . $nbFiles);
            } catch (\Exception $e) {
                //@TODO prévoir un script de purge des photos dans tmp qui sont en erreur
                /*$codeCipNonValid[] = $e->getMessage();*/
                // $fileError[] = $file;
                /*$fileError[]=$file.' : '.$e->getMessage();*/

                $fileError[$e->getCode()][] = $file;
            }
        }

        $erreursMessage = [
            'ERREUR_INSCRIPTION' => 'Erreur lors de la création de l\'inscription',
            'ERREUR_PHOTO_EXIST' => 'Erreur lors du traitement de la photo',
            'ERREUR_FICHIER' => 'Erreur lors du traitement du fichier',
        ];

        foreach ($fileError as $codeErreur => $fichiers) {
            $log .= $erreursMessage[$codeErreur] . ':';
            $log .= '<ul>';
            foreach ($fichiers as $fichier) {
                $log .= '<li>' . $fichier . '</li>';
            }
            $log .= '</ul>';
        }
        $logs = empty($log) ? '' : "Etat du traitement :\n" . implode("\n", $log);
        $logs .= $time = DateTime::getDate('U') - $timeStart;
        $testString = '';

        if ($this->testMode === true) {
            $testString = ' MODE TEST' . "\n\r";
        }

        $processus->setStatut(Processus::STATUT_DONE,
            'Import terminé : ' . $photoTransferer . " photo(s) importée(s)\n\r" .
            ' Process ID : ' . $processus->id . "\n\r" .
            $testString .
            ' Inscriptions réalisées : ' . $rowInscription . "\n\r" .
            ' Temps de traitement : ' . $time . "s \n\r" . $msg . $logs);

        print_r($processus->log);

        $this->supprimerDossierTemporaire($pathPhotoSource);
    }

    function supprimerDossierTemporaire($chemin)
    {

        $files = array_diff(scandir($chemin), array('.','..'));

        foreach ($files as $file) {

            (is_dir("$chemin/$file")) ? $this->supprimerDossierTemporaire("$chemin/$file") : unlink("$chemin/$file");

        }

        return rmdir($chemin);

    }

    public function setTestMode(bool $test = true)
    {
        $this->testMode = $test;
        return $this;
    }
}

?>
