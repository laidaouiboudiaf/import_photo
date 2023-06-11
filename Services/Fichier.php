<?php


namespace StimData\App\Core;



use Exception;
use ZipArchive;

class Fichier extends \StimData\Core\Entities\Fichier
{

/** Envoyer un tableau contenant les noms des fichiers présents dans le répertoire, sans le fichier '.gitkeep'.*
 * @param $chemin
 * @return array
 */
public static function obtenirFichiersRepertoire($chemin): array
{
    $files = [];
    $ouvrirDossier = opendir($chemin);
    //readdir —  lire le contenu du répertoire
    while (($file = readdir($ouvrirDossier)) !== false) {
        if ($file !== '.' && $file !== '..' && $file !== '.gitkeep') {
            $files[] = $file;
        }
    }

    closedir($ouvrirDossier);

    return $files;
}


    public function unzipFichier(string $repertoireDestination)
    {
        $zip = new ZipArchive();
        //ouvrir le fichier zip
        $res = $zip->open($this->getChemin());

        if ($res === true) {
            if(!is_dir($repertoireDestination)) {
                throw new Exception('le répertoire [' . $repertoireDestination . '] est introuvable');
            }
            // extraire le contenu du fichier zip vers le répertoire de destination
            $extractResultat = $zip->extractTo($repertoireDestination);
            // onferme le fichier zip
            $zip->close();

            if (!$extractResultat) {
                throw new Exception('Erreur lors de l\'extraction
                 
                 du contenu du zip [' . $this->getChemin() . '] vers le répertoire [' . $repertoireDestination . ']');
            }
        } else {
            throw new Exception('Erreur lors de l\'ouverture du zip [' . $this->getChemin() . ']');
        }
    }
}