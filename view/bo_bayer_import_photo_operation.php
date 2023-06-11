<?php

use StimData\App\Groupement\Entities\Groupement;
use StimData\App\Groupement\Entities\GroupementOperation;
use StimData\App\Import\Repositories\Import;
use StimData\App\Core\Fichier;
use StimData\App\Operation\Entities\Operation;
use StimData\App\Utilisateur\Services\UtilisateurService;

chdir("../../");
include("./conf/loader.php");
$user = UtilisateurService::checkConnectAdmin();

include("./admin/ctrl_bayer_check_is_admin.php");
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <?php include("./Templates/head.php"); ?>
</head>

<body class="admin">
<?php include("./Templates/bo_menu.php"); ?>

<?php include("./Templates/flashbag.php"); ?>


<div class="container container-bo">

    <div class="row">

        <div class="col-xs-offset-0 col-xs-12 col-md-offset-3 col-md-6">

            <h3 class="underline"><span class="icon ico_upload"></span> Import photos</h3>

            <form class="form-group form-horizontal " action="ctrl_bayer_import_process.php"
                  name="form_import_test" id="formImportTest" method="post" enctype="multipart/form-data">


            <div class="form-group">
                    <div class="col-sm-12">
                        <select name="operation_id" class="selectpicker" required id="operationId" data-json="true">
                            <option value="">Opération...</option>
                            <?php
                            $operations = Operation::getAll("", " actif is true AND visible_back=1 AND declaration=1 ","order by id desc");

                            foreach ($operations as $operation) {

                                echo '<option  value="' . $operation->getField('id') . '" >' . $operation->getField('id') . ' - ' . $operation->getField('nom') . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="upload-container">
                    <?php include('./Templates/bloc_upload.php'); ?>
                </div>

                <ul class="parsley-errors-list filled">
                    <li class="parsley-required"></li>
                </ul>

                <input type="hidden" name="action" value="import_process_test">
                <input type="hidden" name="type" value="<?php echo Import::TYPE_IMPORT_PHOTO_OPERATION; ?>">

                <div class="col-xs-12 text-center">
                    <button id="importBtn" style="min-width: 210px;" type="submit" class="btn btn-secondary btn-bleu-generique btn-check">
                        Envoyer le fichier
                    </button>
                </div>

            </form>


            <form class="form-group form-horizontal hidden" action="ctrl_bayer_import_process.php"
                  name="form_import_confirm" id="formImportConfirm" method="post" enctype="multipart/form-data">

                <p>Confirmez vous l'import de cette configuration ?</p>


                <input type="hidden" id="operation" name="operation" value="">
                <input type="hidden" name="action" value="import_process_confirm">
                <input type="hidden" name="fichier" id='fichierConfirm' value="">
                <input type="hidden" name="type" value="<?php echo Import::TYPE_IMPORT_PHOTO_OPERATION; ?>">

                <div class="form-group">
                    <div class="col-xs-6">
                        <a href="/admin/import/<?php echo pathinfo(__FILE__, PATHINFO_FILENAME); ?>.php"
                           class="btn btn-secondary btn-bleu-generique btn-cancel action-confirm-redirection"
                           data-message="Etes-vous sûr de vouloir d'abandonner l'import ?">
                            Annuler
                        </a>
                    </div>
                    <div class="col-xs-6">
                        <button class="btn btn-primary btn-vert-generique btn-valid">
                            Importer
                        </button>
                    </div>
                </div>

            <?php include("./Templates/bloc_import_error.php") ?>

        </div>
    </div>

    <?php include("./Templates/footer.php") ?>

</div>
<?php include("./js/stim_js.php"); ?>

<script>
    $(document).ready(function () {
        initImportProcess();
        initUploadFile('<?php echo Fichier::TYPE_ZIP_PHOTO_VENTES; ?>');

        $('select[name="groupement_id"]').change(function () {

            var grpOperation = $('select[name="groupement_id"] option:selected').data('grpid');
            var grpId = parseInt($(this).val());

            if (grpOperation && grpOperation.length > 0 && grpId > 0 && $.inArray(grpId, grpOperation) === -1) {
                alert('Attention ce groupement n\'est pas dans la liste des groupement de l\'operation');
            }
        });
    });
</script>
<script src="/js/auto_select.js"></script>

</body>
</html>
