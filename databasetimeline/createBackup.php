<?php

/**
 * Backup nur bei Unterschiede, standard aller 14 Tage
 *
 */

require_once dirname(__FILE__) . "/../bootstrap.php";
//include_once dirname(__FILE__) ."lib/Medoo.php";
//include_once dirname(__FILE__) ."config/baseconfig.php";

/**
 * Class d3BackUpDatebase
 */
class CreateBackup  extends oxSuperCfg
{
    /**
     * sModId
     *
     * @var string
     */
    protected $sSubPath = "backup/database/";

    public function init()
    {
        echo __METHOD__ . " " . __LINE__ . "<br>" . PHP_EOL;
        $this->getDataBaseContents();
        echo __METHOD__ . " " . __LINE__ . "<br>" . PHP_EOL;
    }

    public function getDataBaseContents()
    {
        $oDb = oxDb::getDb(oxDb::FETCH_MODE_ASSOC);

        $sDataBase = $this->getConfig()->getConfigParam('dbName');
        //$sDataBase = $this->dbName;

        $sQuery =
        <<<MYSQL
SHOW TABLE STATUS FROM {$sDataBase} 
WHERE Engine IS NOT null 
MYSQL;

        //echo $sQuery;
        $sRes = $oDb->getAll($sQuery);
        //dumpvar($sRes);

        foreach ($sRes as $aTable)
        {
            $aTmpTable = array();

            $sQueryTable =
            <<<MYSQL
            SHOW CREATE TABLE {$aTable[0]};
MYSQL;

            $sResTable = $oDb->getAll($sQueryTable);
            $aTmpTable['TABLENAME'] = $sResTable[0][0];
            $aTmpTable['ENGINE'] = $aTable[1];
            $aTmpTable['ROW_FORMAT'] = $aTable[3];
            $aTmpTable['COLLAGE'] = $aTable[14];
            $aTmpTable['COMMENT'] = $aTable[17];
            $aTmpTable['CREATE_SQL'] = $sResTable[0][1];

            //$aTables[$sResTable[0][0]] = $aTmpTable;

            $sData = $this->_JsonEncode($aTmpTable);
            $this->_writDataToFile($sResTable[0][0],$sData);
        }
        //dumpvar($aTables);
        echo "Anzahl Tabellen: ".(count($sRes));
    }

    /**
     * @param $sTable
     * @param $sContent
     */
    protected function _writDataToFile($sTable, $sContent)
    {
        $this->_checkExportFolder();
        $sFile = $this->_getPathToFiles().$this->_getFileNameForCurrentContent($sTable);
        //echo "<br>".$sFile;

        $fp = fopen($sFile, 'w');
        if(is_array($sContent))
        {
            fwrite($fp, print_r($sContent,true));
        }
        else
        {
            fwrite($fp, $sContent);
        }

        fclose($fp);
    }

    /**
     *
     * @return string
     */
    protected function _getFileNameForCurrentContent($sTable)
    {
        return "/".$sTable.".txt";
    }

    /**
     * @return string
     */
    protected function _getPathToFiles()
    {
        return oxRegistry::getConfig()->getConfigParam('sShopDir').$this->sSubPath.$this->_getDatePath();
    }

    /**
     * @todo Einstellung für Datum als Unistimestamp oder 2018032718:23:00
     *       Fallback: 2018032718:23:00
     *
     * @return int
     */
    protected function _getDatePath()
    {
        return date('YmdHi00');
        //return date('Y-m-d_Hi');
    }

    protected function _checkExportFolder()
    {
        if(file_exists ( $this->_getPathToFiles()) == false)
        {
            //echo __METHOD__ . " " . __LINE__ . "<br>" . PHP_EOL;
            mkdir($this->_getPathToFiles());
        }
    }

    /**
     * @param $sString
     *
     * @return array
     */
    protected function _JsonDecode($sString)
    {
        #return $sString;
        return (array) json_decode($sString);
    }

    /**
     * @param $aArray
     *
     * @return string
     */
    protected function _JsonEncode($aArray)
    {
        #return $aArray;
        return json_encode($aArray);
    }
}

$oBackup = new CreateBackup;
$oBackup->init();
