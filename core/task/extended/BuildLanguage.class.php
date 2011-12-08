<?php

/**
 * @category TwengaDeploy
 * @package Core
 * @author Geoffroy AUBRY <geoffroy.aubry@twenga.com>
 */
class Task_Extended_BuildLanguage extends Task
{

    /**
     * Retourne le nom du tag XML correspondant à cette tâche dans les config projet.
     *
     * @return string nom du tag XML correspondant à cette tâche dans les config projet.
     */
    public static function getTagName ()
    {
        return 'buildlanguage';
    }

    /**
     * Constructeur.
     *
     * @param SimpleXMLElement $oTask Contenu XML de la tâche.
     * @param Task_Base_Project $oProject Super tâche projet.
     * @param ServiceContainer $oServiceContainer Register de services prédéfinis (Shell_Interface, ...).
     */
    public function __construct (SimpleXMLElement $oTask, Task_Base_Project $oProject,
        ServiceContainer $oServiceContainer)
    {
        parent::__construct($oTask, $oProject, $oServiceContainer);
        $this->_aAttrProperties = array(
            'project' => AttributeProperties::REQUIRED,
            'destdir' => AttributeProperties::DIR | AttributeProperties::REQUIRED
                | AttributeProperties::ALLOW_PARAMETER
        );
    }

    /**
     * Phase de traitements centraux de l'exécution de la tâche.
     * Elle devrait systématiquement commencer par "parent::_centralExecute();".
     * Appelé par _execute().
     * @see execute()
     */
    protected function _centralExecute ()
    {
        parent::_centralExecute();
        $this->_oLogger->indent();

        $sLanguagesPath = tempnam(
            DEPLOYMENT_TMP_DIR,
            $this->_oProperties->getProperty('execution_id') . '_languages_'
        );
        $fh = fopen($sLanguagesPath, 'w');
        $sURL = 'https://admin.twenga.com/translation_tool/build_language_files.php?project='
              . $this->_aAttributes['project'];
        $this->_oLogger->log('Generate language archive from web service: ' . $sURL);
        $aCurlParameters = array(
            'url' => $sURL,
            'login' => DEPLOYMENT_LANGUAGE_WS_LOGIN,
            'password' => DEPLOYMENT_LANGUAGE_WS_PASSWORD,
            'user_agent' => Curl::$aUserAgents['FireFox3'],
            'referer' => 'http://aai.twenga.com',
            'file' => $fh,
            'timeout' => 120,
            'return_header' => 0,
        );
        $result = Curl::disguiseCurl($aCurlParameters);
        fclose($fh);

        if ( ! empty($result['curl_error'])) {
            // Selon les configuration serveur, il se peut que le retour de cURL soit mal interprété.
            // Du coup on vérifie si c'est vrai en testant l'archive :
            if (preg_match('/^transfer closed with \d+ bytes remaining to read$/i', $result['curl_error']) === 1) {
                $this->_oLogger->log('Test language archive');
                $this->_oLogger->indent();
                $this->_oShell->exec('tar -tf "' . $sLanguagesPath . '"');
                $this->_oLogger->unindent();
            } else {
                @unlink($sLanguagesPath);
                throw new RuntimeException($result['curl_error']);;
            }

        } else if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            @unlink($sLanguagesPath);
            throw new RuntimeException(
                'Return HTTP code: ' . $result['http_code']
                . '. Last URL: ' . $result['last_url']
                . '. Body: ' . $result['body']
            );
        }

        // Diffusion de l'archive :
        $this->_oLogger->log('Send language archive to all servers');
        $this->_oLogger->indent();
        $aDestDirs = $this->_processPath($this->_aAttributes['destdir']);
        foreach ($aDestDirs as $sDestDir) {
            $aResult = $this->_oShell->copy($sLanguagesPath, $sDestDir);
            $sResult = implode("\n", $aResult);
            if (trim($sResult) != '') {
                $this->_oLogger->log($sResult);
            }
        }
        $this->_oLogger->unindent();

        // Décompression des archives :
        $this->_oLogger->log('Extract language files from archive on each server');
        $this->_oLogger->indent();
        $sPatternCmd = 'cd %1$s && tar -xf %1$s/"' . basename($sLanguagesPath)
                     . '" && rm -f %1$s/"' . basename($sLanguagesPath) . '"';
        foreach ($aDestDirs as $sDestDir) {
            $aResult = $this->_oShell->execSSH($sPatternCmd, $sDestDir);
            $sResult = implode("\n", $aResult);
            if (trim($sResult) != '') {
                $this->_oLogger->log($sResult);
            }
        }
        $this->_oLogger->unindent();

        @unlink($sLanguagesPath);
        $this->_oLogger->unindent();
    }
}
