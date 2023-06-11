/**
 * Dans l'idée, on a 2 formulaires
 * - le 1er sert à tester le fichier, quand le test est valide on affiche le 2ème
 * - le 2ème va lancer un cron qui va importer le fichier en tache de fond
 * Périodiquement on récupère l'avancement de l'import pour l'afficher
 */
function initImportProcess() {
    $('#formImportTest').on('submit', function (e) {
        e.preventDefault();
        pasBesoinDeFichier = false;
        if ($('select[data-pas-besoin-de-fichier=true]').length > 0) {
            if (parseInt($('select[data-pas-besoin-de-fichier=true]').val()) > 0) {
                pasBesoinDeFichier = true;
            }
        }

        if (pasBesoinDeFichier === true || ($(".file-row input").length || $("input[name='liste_fichiers[]'").length)) {

            $('#importBtn').html('Test en cours...').attr('disabled', true);

            $.ajax({
                method: "POST",
                url: $(this).attr('action'),
                data: $(this).serialize()
            })
                .done(function (result) {

                    $('#importBtn').html('Tester le fichier').attr('disabled', false);

                    data = JSON.parse(result);

                    if ($(".file-row input").length || $("input[name='liste_fichiers[]'").length) {
                        flushImportMsg();
                        if (data['success'] === false) {

                            for (var i in data['log']) {
                                addImportMsg('import_error', data['log'][i]);
                            }

                            $('#formImportConfirm').attr('disabled', true);
                        }
                    }
                    $('#formImportTest').addClass('hidden');
                    $('#formImportConfirm').removeClass('hidden');
                    $('#fichierConfirm').val(data['fichier']);
                    $('#operation').val($('#operationId').val());
                    //dans le cas de l'import incsription uniquement
                    $('#sra').val($('#sra_1').val());
                    $('#mail').val($('#mail_1').val());
                    $('#force').val($('#force_1').val());
                    $('#all').val($('#all_1').val());
                    $('#groupement_id').val($('#groupement_id_1').val());
                    $('#operation_id').val($('#operation_id_1').val());
                    $('#operation_signed').val($('#operation_signed_1').val());
                });
        } else if (pasBesoinDeFichier === true) {
            $('#formImportConfirm').submit();
        } else {
            $('.parsley-errors-list li').html('Vous devez fournir un fichier.')
        }
    });

    $('#formImportConfirm').on('submit', function (e) {
        e.preventDefault();
        var actionurl = $(this).attr('action');
        $.ajax({
            url: actionurl,
            method: "POST",
            data: $(this).serialize()
        }).done(function (data) {
            var result = JSON.parse(data);

            getImportProgress('#formImportConfirm', result['processusId']);

            refreshIntervalId = setInterval(function () {
                getImportProgress('#formImportConfirm', result['processusId']);
            }, 5000);
        });
    });
}


function initUploadFile(type) {

    var Dropzone = require("enyo-dropzone");
    Dropzone.autoDiscover = false;

    // Get the template HTML and remove it from the document
    var previewNode = document.querySelector("#template");

    if (previewNode !== null) {
        previewNode.id = "";
        var previewTemplate = previewNode.parentNode.innerHTML;
        previewNode.parentNode.removeChild(previewNode);

        if (type === 'zip_photo_ventes') {
            var myDropzone = new Dropzone('div#bloc_upload1', {
                url: "/ctrl_bayer_upload.php?type=zip_photo_ventes",
                thumbnailWidth: 100,
                thumbnailHeight: 141,
                acceptedFiles: 'application/zip,application/x-zip-compressed',
                parallelUploads: 2,
                previewTemplate: previewTemplate,
                autoQueue: true,
                previewsContainer: "#previews",
                clickable: ".fileinput-button"
            });

            myDropzone.on("success", function (file, responseText) {
                addFile(responseText, 'zip_photo_ventes');
                $('.doc_chapitre').css('display', 'none');
            });

            myDropzone.on("addedfile", function (file) {
                if (!file.type.match(/image.*/)) {
                    myDropzone.emit("thumbnail", file, "/images/ico_fichier.svg");
                }
                // Hookup the start button
                file.previewElement.querySelector(".delete").onclick = function () {
                    document.querySelector("#total-progress").style.opacity = "0";
                };
            });
        }

        myDropzone.on("queuecomplete", function (progress) {
            document.querySelector("#total-progress").style.opacity = "0";
            $("div.partial-progress").css('opacity', '0');
            $("div.partial-progress").css('display', 'none');
            $('#transfert_ok').css('display', 'block');
        });
    }
}