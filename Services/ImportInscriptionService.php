<?php

namespace StimData\App\Import\Services;

use StimData\App\Core\Fichier;

use StimData\App\Groupement\Entities\GroupementPharmacie;
use StimData\App\Import\Repositories\Import;
use StimData\App\Inscription\Entities\Inscription;
use StimData\App\Operation\Entities\Operation;
use StimData\App\Operation\Entities\OperationProduit;
use StimData\App\Pharmacie\Entities\Pharmacie;
use StimData\Core\Entities\Processus;
use StimData\Core\Services\DateTime;
use StimData\Core\StimDataApp;

class ImportInscriptionService
{
    private bool $testMode = false;

    public function __construct()
    {
    }

    public function run(Processus $processus, $args)
    {
        $bdd = StimDataApp::getFromContainer('connection');

        $fichier = null;

        $timeStart = DateTime::getDate('U');

        $operationId = $args->operation;
        $type = $args->type;
        $groupement = $args->groupement_id ?? '';
        $sra = $args == '1';

        $all = $args->all == '1';
        $mail = $args->mail == '1';
        $signed = $args->operation_signed;
        $force = (isset($args->force) && $args->force == 'on');

        $msg = "Options de traitement : \r\n";
        $msg .= 'Operation ID : ' . $operationId . "\r\n";
        $msg .= 'Force mode : ' . ($force ? 'OUI' : 'NON') . "\r\n";
        $msg .= 'Groupement : ' . ($groupement ?? 'aucun selectionné') . "\r\n";
        $msg .= 'Etat signé : ' . ($signed ?? '') . "\r\n";
        $msg .= 'Envoi de mail : ' . ($mail ? 'OUI' : 'NON') . "\r\n";
        $msg .= 'Inscription de la base complète : ' . ($all ? 'OUI' : 'NON') . "\r\n";

        if ($signed == 'signe') {
            $signed = Inscription::ETAT_SIGNED;
        } else {
            $signed = Inscription::ETAT_BYPASS;
        }

        $processus->pid = getmypid();
        $processus->setStatut(Processus::STATUT_PENDING);

        $operation = new Operation();
        $isLoaded = $operation->load($operationId);

        if (!$isLoaded) {
            $processus->setStatut(Processus::STATUT_ERROR, 'Opération inconnue id ' . $operationId);
            exit($processus->statut);
        }

        if ($processus->getField('fichier_id') > 0) {
            $fichier = new Fichier();
            $isLoaded = $fichier->load($processus->getField('fichier_id'));

            if (!$isLoaded) {
                $processus->setStatut(Processus::STATUT_ERROR, 'Fichier associé introuvable en BDD');
                exit($processus->log);
            }
        }

        $oks = 0;
        $successes = 0;
        $row = 0;
        $log = [];
        $loadOperationRegional= $operation->isAccRegional();
        $loadOperationMeaKa = $operation->isMeaKa();
        $loadOperationVisibilite = $operation->isVisibilite();
        if (!in_array($operation->getField('type'), [Operation::TYPE_CADRE, Operation::TYPE_AUTONOME, Operation::TYPE_AUTONOME]) && !$loadOperationRegional  && !$loadOperationMeaKa) {
            $operationProduits = OperationProduit::getAll('', 'operation_id = ' . $operation->getField('id'));

            if (empty($operationProduits) && $operation->validation_ventes === true) {
                $processus->setStatut(Processus::STATUT_ERROR, 'Aucun produit associé à l\'opération ' . $operation->getField('nom'));
                exit($processus->log);
            }
        }

        if ($all && intval($groupement) == 0) {

            $etablissementRepo = StimDataApp::getRepository(Pharmacie::class);
            $groupements = $etablissementRepo->findBy([
                'actif' => 1,
            ]);

            $bdd->beginTransaction();
            foreach ($groupements as $pharmacie) {
                $result = $operation->inviter($pharmacie, null, null, $mail, $force, $mail, null, $sra, $signed);

//                print_r($result);
            }
            if (!$this->testMode)
                $bdd->commit();
            else
                $bdd->rollback();

        } elseif (intval($groupement) > 0) {

            $groupementRepo = StimDataApp::getRepository(GroupementPharmacie::class);
            $etablissements = $groupementRepo->getPharmacieDuGroupement($groupement, false);
            $row = 1;
            $bdd->beginTransaction();
            foreach ($etablissements as $etablissement) {
                $etablissementRepo = StimDataApp::getRepository(Pharmacie::class);
                $groupements = $etablissementRepo->findBy([
                    'actif' => 1,
                    'id' => $etablissement->id
                ]);
                foreach ($groupements as $pharmacie) {
                    $result = $operation->inviter($pharmacie, null, null, $mail, $force, $mail, null, $sra, $signed);

                    $successes++;
//                    print_r($result);
                }
                $row++;
                $oks++;
            }
            if (!$this->testMode)
                $bdd->commit();
            else
                $bdd->rollback();

        } elseif ($fichier && ($handle = fopen($fichier->getChemin(), "r")) !== false) {
            $bdd->beginTransaction();
            while (($data = fgetcsv($handle, 1000, ";")) !== false) {
                //On zappe la premiere ligne
                if ($row > 0) {
                    [$codeCip, $codeClient, $dateEngagement] = $data;
                    $load = true;
                    $updateDate = true;
                    if (empty($dateEngagement)) {
                        $dateEngagement = date("d/m/Y");
                        $updateDate = false;
                    }
                    $dateEngagement = DateTime::createFromFormat('d/m/Y', $dateEngagement);
                    $dateEngagement = $dateEngagement->format('Y-m-d');

                    $service = new Import();
                    $pharmacie = $service->getPharmacieAvecCodeCipEtCodeClient($codeClient, $codeCip);
                    if ($pharmacie) {

                        // Inscription

                        $result = $operation->inviter($pharmacie, null, null, $mail, $force, $mail, $dateEngagement, $sra, $signed);

//                        print_r($result);
                        /**
                         * @var Inscription $inscription
                         */

                        if ($result['success']) {
                            if ($updateDate) {
                                $inscription = $result['inscription'];
                                $inscription->setDateSignature($dateEngagement);
                            }

                            $successes++;
                        }

                        $oks++;
                    }
                }

                $row++;
            }
            if (!$this->testMode)
                $bdd->commit();
            else
                $bdd->rollback();
            fclose($handle);
        }

        $logs = empty($log) ? '' : "Etat du traitement : \n" . implode("\n", $log);

        $time = DateTime::getDate('U') - $timeStart;

        $testString = '';
        if ($this->testMode === true)
            $testString = ' MODE TEST' . "\n\r";

        $processus->setStatut(Processus::STATUT_DONE,
            'Import terminé : ' . ($row - 1) . " lignes\n\r" .
            ' Process ID : ' . $processus->id . "\n\r" .
            $testString .
            ' Pharmacies trouvées ' . $successes . ' / ' . $oks . "\n\r" .
            ' Inscriptions réalisées : ' . $successes . "\n\r" .
            ' Temps de traitement : ' . $time . "s \n\r" . $msg . $logs);


        print_r($processus->log);
    }

    public function setTestMode(bool $test = true)
    {
        $this->testMode = $test;

        return $this;
    }
}
