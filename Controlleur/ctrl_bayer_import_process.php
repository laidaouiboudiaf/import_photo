<?php

use StimData\App\Core\Fichier;
use StimData\App\Import\Repositories\Import;
use StimData\App\Import\Services\ImportInscriptionService;
use StimData\App\Import\Services\ImportPhotoOperationService;
use StimData\App\Utilisateur\Entities\Utilisateur;
use StimData\Core\Entities\Processus;
use StimData\Core\Repositories\ProcessusRepository;
use StimData\Core\StimDataApp;

chdir('../../');
include('./conf/loader.php');
include('./admin/ctrl_connecte_bayer.php');
include('./admin/ctrl_bayer_check_is_admin.php');

$request = StimDataApp::getFromContainer('request');
$session = StimDataApp::getFromContainer('session');


$action = $request->get('action');
$listeFichiers = $request->get('liste_fichiers');

$bdd = StimDataApp::i()->getContainer()->get('connection');
$conf_php_dir = StimDataApp::i()->getContainer()->get('conf_php_dir');

switch ($action) {

    case 'import_process_test':
        $url = 'location: bo_bayer_import_facturable.php';
        $type = $request->get('type');

        if (!empty($listeFichiers)) {

            $fichierId = $listeFichiers[0];
            $f = new Fichier();
            $f->load($fichierId);

            if ($f === false) {
                echo json_encode(['success' => false, 'log' => ['Le fichier n\'a pas pu être chargé.']]);
                exit();
            }
            if ($type == Import::TYPE_IMPORT_PHOTO_OPERATION) {
                $result = ['success' => true,'fichier'=>$f->id ,'log' => 'Le fichier est pret à etre importé.'];

            } else {
                $result = Import::chekImport($f, $type);
            }
            echo json_encode($result);

        } else {
            echo json_encode(['success' => false, 'log' => ['Le fichier n\'a pas pu être chargé.']]);
        }

        break;


    case 'import_process_confirm':
        $phpBin = StimDataApp::getFromContainer('conf_php_bin');
        // Récupérer le type depuis le champ 'type' du processus

        $type = $request->get('type');
        $processusRepo = StimDataApp::getFromContainer(ProcessusRepository::class);
        $processuss = $processusRepo->findBy([
            'type' => $type,
            'fichier_id' => $request->get('fichier'),
            'statut' => Processus::STATUT_NEW
        ]);

        $fichierID = $request->get('fichier');
        if (empty($processuss)) {
            $processus = new Processus();
            $processus->type = $type;
            $processus->statut = Processus::STATUT_NEW;
            $processus->date_creation = $processus->date_modification = date('Y-m-d H:i:s');
            $processus->fichier_id = !empty($fichierID) ? $fichierID : 0;
            $processus->log = '0%';
            $processus->user_id = isset($user) && $user instanceof Utilisateur ? $user->getField('id') : '';
            $processus->save();
        } else {
            $processus = $processuss[0];
        }
        $rootDir = StimDataApp::getFromContainer('root_dir');
        $cmd = $phpBin . ' ' . $rootDir . '/cron/processus.php >/dev/null &';


        // Sélectionner le service et les arguments en fonction du type
        switch ($type) {
            case Import::TYPE_IMPORT_INSCRIPTION:
                $processus->cmd = ImportInscriptionService::class;
                $processus->args = json_encode($_POST);
                break;
            case Import::TYPE_IMPORT_PHOTO_OPERATION:
                $processus->cmd = ImportPhotoOperationService::class;
                $processus->args = json_encode($_POST);
                break;
            default:
                $cmd = $phpBin . ' ' . $rootDir . '/script/background_import/cron_import_process.php ' . $type . ' >/dev/null &';
                break;
        }


        $processus->save();

        exec($cmd);
        error_log($cmd);

        $data['processusId'] = $processus->getField('id');

        echo json_encode($data);
        break;

    case 'import_processus_get_progress':

        $processusId = $request->get('processusId');
        $processus = new Processus();
        $isLoaded = $processus->load($processusId);

        if ($isLoaded === false) {
            $data['log'] = 'Impossible de trouver le processus en base de données.';
            $data['statut'] = Processus::STATUT_ERROR;
        } else {
            $data['log'] = $processus->getField('log');
            $data['statut'] = $processus->getField('statut');
        }

        echo json_encode($data);
        break;

    default:
        $session->getFlashBag()->add('notice', "Un problème est survenu lors de l'import.");
        header('location: /admin/bo_bayer_index.php');
        break;
}

exit();
