<?php

/**
 * http://www.infoom.se/compare-mysql-online/#results
 */

require_once dirname(__FILE__) . "/../bootstrap.php";
//include_once dirname(__FILE__) ."lib/Medoo.php";
//include_once dirname(__FILE__) ."config/baseconfig.php";

class createIncrementBackup  extends oxSuperCfg
{
    /**
     * sModId
     *
     * @todo    Als Konfigparmater hinterlegen, muss erweiterbar sein
     *
     * @var string
     */
    protected $sSubPath = "backup/database/";

    /***
     * @todo    Als Konfigparmater hinterlegen, muss erweiterbar sein
     * @var array
     */
    protected $aCleanSql = array(
        'AUTO_INCREMENT='
    );

    protected $_sBackUpFileTyp = 'txt';

    protected $_sDropTableSuffix = '_drop';


    public function init()
    {
        $this->getDataBaseContents();
        //$this->compareTables();
    }

    /**
     * @todo:    test auf gelöschte Tabellen
     * @todo:    E-Mail versenden
     *
     * @throws oxConnectionException
     */
    public function getDataBaseContents()
    {
        $aTmp = $this->_checkForLatestBackupFiles();
        $aBackUpFiles = $aTmp['FileBackups'];
        $aDeletedTables = $aTmp['DeletedTables'];
        //dumpvar($aBackUpFiles);

        $oDb = oxDb::getDb(oxDb::FETCH_MODE_ASSOC);
        $aTables = $this->_getAllTables();
        //dumpvar($aTables);
        foreach ($aTables as $aTable)
        {
            //dumpvar($aTable);
            $aTmpTable = array();
            $sQueryTable =
            <<<MYSQL
            SHOW CREATE TABLE {$aTable['Name']};
MYSQL;

            $sResTable = $oDb->getAll($sQueryTable);
            //dumpvar($sResTable);
            $aTmpTable['TABLENAME'] = $sResTable[0]['Table'];
            //$aTmpTable['ENGINE'] = $aTable['1'];
            $aTmpTable['ENGINE'] = $aTable['Engine'];
            //$aTmpTable['ROW_FORMAT'] = $aTable[3];
            //$aTmpTable['COLLAGE'] = $aTable[14];
            //$aTmpTable['COMMENT'] = $aTable[17];

            $aTmpTable['ROW_FORMAT'] = $aTable['Row_format'];
            $aTmpTable['COLLAGE'] = $aTable['Collation'];
            $aTmpTable['COMMENT'] = $aTable['Comment'];
            $aTmpTable['CREATE_SQL'] = $sResTable[0]['Create Table'];
            $this->compareContents($aTable['Name'],$aTmpTable, $aBackUpFiles);
        }

        //gelöschte Tabelle
        foreach($aDeletedTables as $sKey =>  $aTable)
        {
            $this->writeFileForDeletedTable($sKey,$aDeletedTables, false);
            $this->writeFileForDeletedTable($sKey,$aDeletedTables, true);
        }
    }


    /**
     * @return array
     * @throws oxConnectionException
     */
    protected function _getAllTables()
    {
        $oDb = oxDb::getDb(oxDb::FETCH_MODE_ASSOC);

        $sDataBase = $this->getConfig()->getConfigParam('dbName');
        //$sDataBase = $this->dbName;

        $sQuery =
        <<<MYSQL
SHOW TABLE STATUS FROM {$sDataBase} 
WHERE Engine IS NOT null 
MYSQL;
        return $oDb->getAll($sQuery);
    }

    /**
     * @return array
     * @throws oxConnectionException
     */
    protected function _getTableNames()
    {
        $aTablesFull = $this->_getAllTables();
        $aTables = array();
        //dumpvar($aTablesFull);
        foreach($aTablesFull as $aTable)
        {
            $aTables[$aTable['Name']] = $aTable['Name'];
        }

        //asort($aTables);
        return $aTables;
    }


    public function compareContents($sTable, $aLiveTableStructure ,$aBackUpFiles)
    {
        $aBackupFile = $this->loadFile($aBackUpFiles[$sTable], $sTable);

        echo "<hr>";
        echo 'Table: '.$sTable.' - BackupFile: '.$aBackUpFiles[$sTable].'/'.$sTable.'.'.$this->_sBackUpFileTyp;

        $aBackupFile = $this->_RemoveTagsFromSql($aBackupFile);
        $aLiveTableStructure = $this->_RemoveTagsFromSql($aLiveTableStructure);

        $sFile1 = print_r($aBackupFile,true);
        $sFile2 = print_r($aLiveTableStructure,true);

        if(md5($sFile1) != md5($sFile2))
        {
            var_dump(array_diff ((array)$aBackupFile, (array)$aLiveTableStructure));
            $sData = $this->_JsonEncode($aLiveTableStructure);
            $this->_writDataToFile($sTable,$sData);
        }
    }

    /**
     * @param      $sTable
     * @param      $aBackUpFiles
     * @param bool $blDrop
     */
    public function writeFileForDeletedTable($sTable,$aBackUpFiles, $blDrop = false)
    {
        $sData = '';
        if($blDrop == false)
        {
            $sData = 'Table '.$sTable. 'droped';
        }

        echo "<hr>";
        echo "Tabelle entfernt: ".$sTable.' - '.$aBackUpFiles[$sTable].'/'.$sTable.'.'.$this->_sBackUpFileTyp;

        $this->_writDataToFile($sTable,$sData,$blDrop);
    }

    /**
     * @param $sString
     *
     * @return null|string|string[]
     */
    protected function _RemoveTagsFromSql($sString)
    {
        $sReplace = '';

        foreach($this->aCleanSql as $sSearch)
        {
            //echo "<br>:".$sSearch;
            $sString = preg_replace("/".$sSearch."[0-9]*/", $sReplace, $sString);
        }

        return $sString;
    }

    /**
     * @param $sFolder
     * @param $sTable
     *
     * @return array
     */
    public function loadFile($sFolder, $sTable)
    {
        $sFile = $this->_getPathToBackUpFolders().$sFolder.'/'.$sTable.'.'.$this->_sBackUpFileTyp;
        //echo "<br>". $sFile;

        $aCurrentFileContent = file_get_contents($sFile, "r");
        return $this->_JsonDecode($aCurrentFileContent);
    }


    /**
     * @return array
     * @throws oxConnectionException
     */
    protected function _checkForLatestBackupFiles()
    {
        $aFileBackups = array();
        $aDeletedTables = array();
        $aDropTableFiles = array();
        $aFolders  = $this->_getAllBackUpFolders();
        $aTables = $this->_getTableNames();
        //echo "Tables:";
        //dumpvar($aTables);

        foreach($aFolders as $sFolder)
        {
            //echo "<hr>Folder:".$sFolder;
            $aFiles = $this->_getFilesFromFolder($sFolder);
            foreach ($aFiles as $sKey => $sFile)
            {
                //echo "<br>File:".$sFile;
                //$sTable = rtrim($sFile,'txt');
                $sTable = rtrim($sFile,$this->_sBackUpFileTyp);
                $sTable = rtrim($sTable,'.');

                //$sTableDrop = $sFile.'_drop';

                if(in_array($sTable, $aTables) == true)
                {
                    $aFileBackups[$sTable] = $sFolder;
                }
                else
                {
                    $aDeletedTables[$sTable] = $sFolder;
                }


                if(substr_count ($sTable, $this->_sDropTableSuffix) > 0)
                {
                    //echo "<br>Table:".$sTable;
                    $sTmpTable = $sTable;
                    //$sTmpTable = rtrim($sTmpTable,'_drop');
                    //$sTmpTable = rtrim($sTmpTable,'_drop');

                    $sTmpTable = $this->removeDrop($sTmpTable);
                    //echo "<br>Table-ohne: ".$sTmpTable;
                    $aDropTableFiles[$sTmpTable] = $sFolder;
                }

                if(count($aTables) ==0)
                {
                    break;
                }
            }
            if(count($aTables) ==0)
            {
                break;
            }
        }

        //echo '<hr>';
        dumpvar($aDeletedTables);
        dumpvar($aDropTableFiles);
        if(count($aDropTableFiles) > 0)
        {
            $aDeletedTables = array_diff ($aDropTableFiles,$aDeletedTables);
        }
        else{
            $aDeletedTables = array_diff ($aDeletedTables,$aDropTableFiles);
        }

        //dumpvar($aDeletedTables);
        //dumpvar(array_diff ($aDropTableFiles,$aDeletedTables));
        //dumpvar(array_diff ($aDeletedTables,$aDropTableFiles));
        //$aDeletedTables = array_diff ($aDropTableFiles,$aDeletedTables);
        //die();
        //dumpvar($aDeletedTables);
        //dumpvar($aFileBackups);

        //geloeschte Tabellen filtern
        $aDeletedTables = array_diff ($aDeletedTables,$aFileBackups);
        //dumpvar($aDeletedTables);
        $aTmp['FileBackups'] = $aFileBackups;
        $aTmp['DeletedTables'] = $aDeletedTables;
        return $aTmp;
    }

    /**
     * @param $sFolder
     *
     * @return array
     */
    protected function _getFilesFromFolder($sFolder)
    {
        //echo "<br>".$this->_getPathToBackUpFolders().$sFolder;
        $aFolders = scandir($this->_getPathToBackUpFolders().$sFolder);
        $aFiles = array();
        foreach($aFolders as $sFile)
        {
            if($sFile != '.' && $sFile != '..')
            {
                $aFiles[] = $sFile;
            }
        }
        asort($aFiles);

        return $aFiles;
    }

    /**
     * @return array
     */
    protected function _getAllBackUpFolders()
    {
        //$this->_checkExportFolder();
        $files = scandir($this->_getPathToBackUpFolders());
        $aFiles = array();
        foreach($files as $sFile)
        {
            if($sFile != '.' && $sFile != '..')
            {
                $aFiles[] = $sFile;
            }
        }
        arsort($aFiles);
        return $aFiles;
    }

    /**
     * @param      $sTable
     * @param      $sContent
     * @param bool $blDrop
     */
    protected function _writDataToFile($sTable, $sContent, $blDrop = false)
    {
        $this->_checkExportFolder();
        $sFile = $this->_getPathToFilesWithDate().$this->_getFileNameForCurrentContent($sTable, $blDrop);
        echo "<br>new File".$sFile;

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

    protected function _checkExportFolder()
    {
        if(file_exists ( $this->_getPathToFilesWithDate()) == false)
        {
            //echo __METHOD__ . " " . __LINE__ . "<br>" . PHP_EOL;
            mkdir($this->_getPathToFilesWithDate());
        }
    }

    /**
     * @param      $sTable
     * @param bool $blDrop
     *
     * @return string
     */
    protected function _getFileNameForCurrentContent($sTable, $blDrop = false)
    {
        if($blDrop == true)
        {
            $sTable.= $this->_sDropTableSuffix;
        }
        return "/".$sTable.".txt";
    }


    /**
     * @return string
     */
    protected function _getPathToFilesWithDate()
    {
        return oxRegistry::getConfig()->getConfigParam('sShopDir').$this->sSubPath.$this->_getDatePath();
    }

    /**
     * @return string
     */
    protected function _getPathToBackUpFolders()
    {
        return oxRegistry::getConfig()->getConfigParam('sShopDir').$this->sSubPath;
    }

    /**
     * @todo Einstellung für Datum als Unistimestamp oder 2018032718:23:00
     * @todo Timestamp Runden, z.B. die letzten 1-2 Ziffern, wenn die Ausführung etwas länger dauert, damit keine
     *       Dateien in neue Ordner geschrieben werden
     *       Fallback: 2018032718:23:00
     *
     * @return int
     */
    protected function _getDatePath()
    {

        return date('YmdHi00');
        //return date('Y-m-d_Hi');
    }

    /**
     * @param $sString
     *
     * @return string
     */
    protected function removeDrop($sString)
    {
        while(substr_count ($sString, $this->_sDropTableSuffix) > 0)
        {
            $sString = rtrim($sString,$this->_sDropTableSuffix);
        }

        return $sString;
    }

    /**
     * @param $sString
     *
     * @return array
     */
    protected function _JsonDecode($sString)
    {
        return (array) json_decode($sString);
    }

    /**
     * @param $aArray
     *
     * @return string
     */
    protected function _JsonEncode($aArray)
    {
        return json_encode($aArray);
    }
}


$oCompare = new createIncrementBackup();
$oCompare->init();
