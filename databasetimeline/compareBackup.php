<?php

/**
 *
 * Other libraries
 * http://www.infoom.se/compare-mysql-online/#results
  *https://medoo.in/api/new
 */

require_once dirname(__FILE__) . "/../bootstrap.php";
//include_once dirname(__FILE__) ."lib/Medoo.php";
//include_once dirname(__FILE__) ."config/baseconfig.php";

class CompareWithLastBackup  extends oxSuperCfg
{
    /**
     * sModId
     *
     * @var string
     */
    protected $sSubPath = "backup/database/";
    protected $aCleanSql = array(
        'AUTO_INCREMENT='
    );

    protected $_sBackUpFileTyp = 'txt';

    public function init()
    {
        $this->getDataBaseContents();
        //$this->compareTables();

    }

    public function getDataBaseContents()
    {
        $aBackUpFiles = $this->_checkForLatestBackupFiles();

        $oDb = oxDb::getDb(oxDb::FETCH_MODE_ASSOC);
        $aTables = $this->_getAllTables();
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

            $aTmpTable['ROW_FORMAT'] = $aTable['Row_format'];
            $aTmpTable['COLLAGE'] = $aTable['Collation'];
            $aTmpTable['COMMENT'] = $aTable['Comment'];
            $aTmpTable['CREATE_SQL'] = $sResTable[0]['Create Table'];
            $this->compareContents($aTable['Name'],$aTmpTable,$aBackUpFiles);
        }
    }


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
        //dumpvar($sTable);
        //Finde Tabelle+
        $sFolder = $aBackUpFiles[$sTable];
        //dumpvar($sFolder);
        //die();
        $aBackupFile = $this->loadFile($aBackUpFiles[$sTable], $sTable);

        echo "<hr>";
        echo "Tabelle: ".$sTable.' - '.$aBackUpFiles[$sTable].'/'.$sTable.'.'.$this->_sBackUpFileTyp;
        //dumpvar($aBackupFile);
        //dumpvar($aContent);

        $aBackupFile = $this->_cleanSql($aBackupFile);
        $aLiveTableStructure = $this->_cleanSql($aLiveTableStructure);

        var_dump(array_diff ((array)$aBackupFile, (array)$aLiveTableStructure));

        $sFile1 = print_r($aBackupFile,true);
        $sFile2 = print_r($aLiveTableStructure,true);

        echo "<br>MD5-Backup:". md5($sFile1);
        echo "<br>MD5-CurrentDb:". md5($sFile2);

        $aResultCompare = Diff::compare($sFile2,$sFile1);
        //dumpvar($aResultCompare);
        $sContent =  Diff::toTable($aResultCompare,'','<br>',array('DB','File'));
        var_dump($sContent);

        //todo: send Mail

        //bei Unterschied Sicherung anlegen
        //$this->_writDataToFile($sTable, $aContent);
    }

    /**
     * @param $sString
     *
     * @return null|string|string[]
     */
    protected function _cleanSql($sString)
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
     * @param $sTable
     *
     * @return string
     */
    public function getLastBackupFileFromTable($sTable)
    {
        $this->_checkExportFolder();
        $files = scandir($this->_getPathToFiles());
        foreach($files as $sFile)
        {
            if($sFile != '.' && $sFile != '..')
            {
                $aFiles[] = $sFile;
            }
        }

        end($aFiles);
        return $this->_getPathToFiles().current($aFiles);
    }

    /**
     * @return array
     */
    protected function _checkForLatestBackupFiles()
    {
        $aFileBackups = array();
        $aFolders  = $this->_getAllBackUpFolders();
        $aTables = $this->_getTableNames();
        //echo "Tables:";
        //dumpvar($aTables);

        foreach($aFolders as $sFolder)
        {
            //echo "<hr>Folder:".$sFolder;
            $aFiles = $this->_getFilesFromFolder($sFolder);
            //echo "<br>Files";
            //dumpvar($aFiles);
            foreach ($aFiles as $sKey => $sFile)
            {
                //$sTable = rtrim($sFile,'txt');
                $sTable = rtrim($sFile,$this->_sBackUpFileTyp);
                $sTable = rtrim($sTable,'.');
                //echo "<br>Table:".$sTable.' - '.$sFile;
                if(in_array($sTable, $aTables) == true)
                {
                    //echo "<br>gefunden:".$sTable;
                    $aFileBackups[$sTable] = $sFolder;
                    unset($aTables[$sTable]);
                    //echo "<br>".count($aTables);
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
        //echo "<hr>";
        //dumpvar($aTables);
        //dumpvar($aFileBackups);

        return $aFileBackups;
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
     * @param $sTable
     * @param $sContent
     */
    protected function _writDataToFile($sTable, $sContent)
    {
        //$this->_checkExportFolder();
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

    protected function _checkExportFolder()
    {
        if(file_exists ( $this->_getPathToFiles()) == false)
        {
            //echo __METHOD__ . " " . __LINE__ . "<br>" . PHP_EOL;
            mkdir($this->_getPathToFiles());
        }
    }

    /**
     * @param $sTable
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

    protected function _getPathToBackUpFolders()
    {
        return oxRegistry::getConfig()->getConfigParam('sShopDir').$this->sSubPath;
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


$oCompare = new CompareWithLastBackup();
$oCompare->init();
