<?php
include_once(dirname(__FILE__) . "/../../svnPostCommitHook.util.php");

// configuration directive for setting the sqlite database location
define('CFG_PATH_TO_SQLITE_DATABASE', 'pathToSqliteDatabase');

/**
 * Schema class for automatic creation of Sqlite database
 */
class DatabaseConnection
{
    /**
     * global columns
     */
    const COLUMN_ID = 'id';
    const COLUMN_NAME = 'name';

    /**
     * referenced tables
     */
    const TABLE_REPOSITORY = 'repository';
    const TABLE_REPOSITORY_COLUMN_URL = 'url';
    const TABLE_USER = 'user';


    const TABLE_COMMIT = 'svn_commit';
    const TABLE_COMMIT_COLUMN_MESSAGE = 'message';
    const TABLE_COMMIT_COLUMN_ID_USER = 'id_user';
    const TABLE_COMMIT_COLUMN_ID_REPOSITORY = 'id_repository';
    const TABLE_COMMIT_COLUMN_COMMIT_DATE = 'commit_date';
    const TABLE_COMMIT_COLUMN_REVISION = 'revision';
    const TABLE_COMMIT_COLUMN_TIMESTAMP = 'commit_timestamp';

    const TABLE_FILE_IN_COMMIT = 'file_in_svn_commit';
    const TABLE_FILE_IN_COMMIT_COLUMN_ID_COMMIT = 'id_commit';
    const TABLE_FILE_IN_COMMIT_COLUMN_FILENAME = 'filename';
    const TABLE_FILE_IN_COMMIT_COLUMN_TYPE = 'type';

    private $_pdo = null;
	private $_configuration = array();

    public function __construct($aConfiguration)
    {
		$this->_configuration = $aConfiguration;
        $this->_pdo = new PDO($this->getConnectionString(), null, null, array(PDO::ATTR_PERSISTENT => true));
        $this->_pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
    }
    
    /**
     * Returns the PDO
     *
     * @return PDO
     */
    public function getPDO()
    {
        return $this->_pdo;
    }
    
    /**
     * Return SQL CREATE statement for automatic creation of SQLite database
     *
     * @return string
     */
    public function getSqlCreateStatement()
    {
        $sqlCreateTableStatement  .= " CREATE TABLE " . DatabaseConnection::TABLE_REPOSITORY . " (" . DatabaseConnection::COLUMN_ID . " INTEGER NOT NULL, " . DatabaseConnection::COLUMN_NAME ." CHAR(255), " . DatabaseConnection::TABLE_REPOSITORY_COLUMN_URL ." LONGTEXT, PRIMARY KEY(" . DatabaseConnection::COLUMN_ID . "));\n";
        $sqlCreateTableStatement  .= " CREATE TABLE " . DatabaseConnection::TABLE_USER ." (" . DatabaseConnection::COLUMN_ID . " INTEGER NOT NULL, " . DatabaseConnection::COLUMN_NAME ." CHAR(255),  PRIMARY KEY(" . DatabaseConnection::COLUMN_ID . "));\n";
        $sqlCreateTableStatement  .= " CREATE TABLE " . DatabaseConnection::TABLE_COMMIT ." (" . DatabaseConnection::COLUMN_ID . " INTEGER NOT NULL, " . DatabaseConnection::TABLE_COMMIT_COLUMN_MESSAGE ." LONGTEXT, " . DatabaseConnection::TABLE_COMMIT_COLUMN_ID_USER ." INTEGER NOT NULL, " . DatabaseConnection::TABLE_COMMIT_COLUMN_ID_REPOSITORY ." INTEGER NOT NULL, " . DatabaseConnection::TABLE_COMMIT_COLUMN_COMMIT_DATE ." CHAR(128), " . DatabaseConnection::TABLE_COMMIT_COLUMN_REVISION ." INTEGER,  " . DatabaseConnection::TABLE_COMMIT_COLUMN_TIMESTAMP . " INTEGER, PRIMARY KEY(" . DatabaseConnection::COLUMN_ID . "));\n";
        $sqlCreateTableStatement  .= " CREATE TABLE " . DatabaseConnection::TABLE_FILE_IN_COMMIT ." (" . DatabaseConnection::COLUMN_ID . " INTEGER NOT NULL, " . DatabaseConnection::TABLE_FILE_IN_COMMIT_COLUMN_ID_COMMIT . " INTEGER NOT NULL, " . DatabaseConnection::TABLE_FILE_IN_COMMIT_COLUMN_FILENAME ." LONGTEXT, " . DatabaseConnection::TABLE_FILE_IN_COMMIT_COLUMN_TYPE . " CHAR(1), PRIMARY KEY(" . DatabaseConnection::COLUMN_ID . "));\n";

        return $sqlCreateTableStatement;
    }

    /**
     * Returns the database file
     *
     * @return string
     */
    public function getDatabaseFile()
    {
		$r = null;

		if (!isset($this->_configuration[CFG_PATH_TO_SQLITE_DATABASE])) {
			$r = dirname(__FILE__) . "/svn2db.sqlite";
		} else {
			$r = $this->_configuration[CFG_PATH_TO_SQLITE_DATABASE];
		}

		return $r;
    }

    /**
     * Returns connection string
     *
     * @return string
     */
    public function getConnectionString()
    {
        return "sqlite:" . $this->getDatabaseFile();
    }
}

/**
 * This script retrieves information from svn repositories and writes all data to a normalized SQLite database.
 * You have to set up your post-commit to execute this script.
 *
 * @author ckl <christopher[dot]klein[at]ecw[dot]de>
 * @url http://wap.ecw.de / http://www.ecw.de
 * @since 2008-07-13
 */

class BaseDAO
{
    /**
     * PDO instance
     * @private
     */
    protected $_pdo = null;

    /**
     * HashMap with prepred statements
     *
     * @var hashmap
     */
    protected $_hmPst = array();


    /**
     * executes the query with given parameters
     * @param string SQL query
     * @param array parameter
     * @return PDOStatement
     */
    protected function execute($aQuery, $aArrParameters)
    {
        if ($this->_hmPst[$aQuery] == null)
        {
            LogUtil::debug("Preparing query '$aQuery'", "svn2db:db");
            $this->_hmPst[$aQuery] = $this->_pdo->prepare($aQuery);
        }

        LogUtil::debug("Exetuing '" . $aQuery ."' parameters " . print_r($aArrParameters, true), "svn2db:db");
        $sqlStat = $this->_hmPst[$aQuery];

        if ($sqlStat)
        {
            $sqlStat->execute($aArrParameters);
        }
        else
        {
            LogUtil::error("Query failed!: " . $aQuery, "svn2db:db");
        }

        return $sqlStat;
    }


    /**
     * Does append SQL operations LIMIT, ORDER BY [DIRECTION]
     *
     * @param string $sqlQuery
     * @param string $aOrder
     * @param string $aDirection
     * @param int $aLimit
     * @return string
     */
    protected function appendOperations($sqlQuery, $aOrder = null, $aDirection = null, $aLimit = 10)
    {
        if ($aOrder != null)
        {
            $sqlQuery .= " ORDER BY " . $aOrder;

            if ($aDirection != null)
            {
                $sqlQuery .= " " . $aDirection;
            }
        }

        if ($aLimit != null)
        {
            $sqlQuery .= " LIMIT " . $aLimit;
        }

        return $sqlQuery;
    }

}

/**
 * Dynamic DAO.
 * Let you specify simple SQL statements on the fly
 *
 * @author ckl <christopher[dot]klein[at]ecw[dot]de>
 * @url http://wap.ecw.de / http://www.ecw.de
 * @since 2008-07-13
 */
class DynamicDAO extends BaseDAO
{
    /**
     * Constructor
     *
     * @param PDO $_pdo
     */
    public function __construct(PDO $_pdo)
    {
        $this->_pdo = $_pdo;
    }

    /**
     * Converts the given name in Camel-Case order to database order:
     * FileInSvnCommit => file_in_svn_commit, SvnCommit => svn_commit and so on.
     *
     * @param string $aName
     */
    public function toDatabaseName($aName)
    {
        $lastWord = '';
        $r = '';

        $words = array();

        for ($i = 0, $m = strlen($aName); $i < $m; $i++ )
        {
            $letter = $aName[$i];
            $asciiCode = ord($letter);

            // upper case
            if ($asciiCode >= 65 && $asciiCode <= 90)
            {
                if (strlen($lastWord) > 0)
                {
                    array_push($words, $lastWord);
                    $lastWord = '';
                }
            }

            $lastWord .= strtolower($letter);
        }

        array_push($words, $lastWord);
        $r = implode("_", $words);


        LogUtil::debug("Converted silbing '" . $aName ."' to '" . $r . "'", 'svn2db:dynamicdao');

        return $r;
    }

    /**
     * Converts all Parameters to an array. The words are split between the keyword AND.
     * IdUserAndIdCommit => array('IdUser', 'IdCommit');
     *
     * @param string $aParameters
     * @return array
     */
    public function toParameters($aParameters)
    {
        $arrParameters = explode("And", $aParameters);

        if (sizeof($arrParameters) == 0)
        {
            $arrParameters = array($aParameters);
        }

        LogUtil::debug("Converted '" . $aParameters . "' to " . print_r($arrParameters, true), 'svn2db:dynamicdao');

        return $arrParameters;
    }

    /**
     * delegates method call to assigend find-routine
     *
     * @param string $aMethodName
     * @param array $aArrArguments
     * @return array
     */
    public function delegate($aMethodName, $aArrArguments)
    {
        if (preg_match("/^findAll(.*)/", $aMethodName, $arrRet))
        {
            return $this->_findAll($arrRet[1], $aArrArguments[0], $aArrArguments[1], $aArrArguments[2]);
        }
        elseif (preg_match("/^find(.*)By(.*)/", $aMethodName, $arrRet))
        {
            return $this->_findBy($arrRet[1], $arrRet[2], $aArrArguments[0], $aArrArguments[1], $aArrArguments[2], $aArrArguments[3]);
        }
        elseif (preg_match("/^find(.*)Of(.*)/", $aMethodName, $arrRet))
        {
            return $this->_findOf($arrRet[1], $arrRet[2], $aArrArguments[0]);
        }
    }

    /**
     * Returns exactly one column
     *
     * @param string $aParameter Column name
     * @param unknown_type $aSilbing Table name
     * @param unknown_type $aArrArgument (Where id)
     * @return unknown
     */
    private function _findOf($aParameter, $aSilbing, $aArgument)
    {
        $sqlQuery = "SELECT " . $this->toDatabaseName($aParameter) ." FROM " . $this->toDatabaseName($aSilbing) . " WHERE " . DatabaseConnection::COLUMN_ID ." = ?";

        LogUtil::debug("_findOf: '$sqlQuery'", 'svn2db:dynamicdao');
        $sqlStat = $this->execute($sqlQuery, array($aArgument));
        return $sqlStat->fetchColumn(0);
    }

    /**
     * Find all elements in table
     *
     * @param string $aSilbing table name as silbing
     * @param string $aOrder order
     * @param string $aDirection direction (ASC or DESC)
     * @param int $aLimit limit elements
     * @return array
     */
    private function _findAll($aSilbing, $aOrder = null, $aDirection = null, $aLimit = 10)
    {
        $sqlQuery = "SELECT * FROM " . $this->toDatabaseName($aSilbing);
        $sqlQuery = $this->appendOperations($sqlQuery, $aOrder, $aDirection, $aLimit);

        LogUtil::debug("_findAll: '$sqlQuery'", 'svn2db:dynamicdao');
        $sqlStat = $this->execute($sqlQuery, null);


        $r = $sqlStat->fetchAll();

        return $r;
    }

    /**
     * Find elements in table, spcecified by WHERE clause
     *
     * @param string $aSilbing table name as silbing
     * @param string $aParameters List with parameters (IdCommitAndIdUser)
     * @param string $aArrArguments arguments
     * @param string $aOrder order
     * @param string $aDirection direction (ASC or DESC)
     * @param int $aLimit limit elements
     * @return array
     */
    private function _findBy($aSilbing, $aParameters, $aArrArguments, $aOrder = null, $aDirection = null, $aLimit = 10)
    {
        $sqlQuery = "SELECT * FROM " . $this->toDatabaseName($aSilbing);
        $arrParameters = $this->toParameters($aParameters);
        $sqlWhere = "";

        for ($i = 0, $m = sizeof($arrParameters); $i < $m; $i++)
        {
            $sqlWhere .= $this->toDatabaseName($arrParameters[$i]) . " = ?";

            if (($i + 1) != $m)
            {
                $sqlWhere .= " AND ";
            }
        }

        if (strlen($sqlWhere) > 0)
        {
            $sqlQuery .= " WHERE " . $sqlWhere;
        }

        $sqlQuery = $this->appendOperations($sqlQuery, $aOrder, $aDirection, $aLimit);
        LogUtil::debug("_findBy: '$sqlQuery', arguments: " . print_r($aArrArguments, true), 'svn2db:dynamicdao');

        $sqlStat = $this->execute($sqlQuery, $aArrArguments);
        $r = $sqlStat->fetchAll(PDO::FETCH_ASSOC);

        return $r;
    }

}

/**
 * Svn2DbDAO encapsulates all database access to svn2db
 * @author ckl <christopher[dot]klein[at]ecw[dot]de>
 * @url http://wap.ecw.de / http://www.ecw.de
 * @since 2008-07-13
 */
class Svn2DbDAO extends BaseDAO
{
    /**
     * DynamicDAO
     *
     * @var DynamicDAO
     */
    private $_dynamicDAO;

    /**
     * Constructor
     * @param PDO available PDO instance
     * @param Schema given Schema definition
     */
    public function __construct(PDO $aPdo, $aSchemaDefinition)
    {
        $this->_pdo = $aPdo;

        $this->setUp($aSchemaDefinition);
        $this->_dynamicDAO = new DynamicDAO($this->_pdo);
    }

    public function __call($aMethodName, $arrArguments)
    {
        if (!method_exists($this, $aMethodName))
        {
            return $this->_dynamicDAO->delegate($aMethodName, $arrArguments);
        }
    }

    /**
     * set up database schema
     * @param Schema schema definition
     */
    private function setUp($aDatabaseSchema)
    {
        $sqlQuery = "SELECT * FROM sqlite_master WHERE name = '" . DatabaseConnection::TABLE_COMMIT ."'";
        $sqlStat = $this->_pdo->query($sqlQuery);
        $r = $sqlStat->fetchAll();

        if (sizeof($r) == 0)
        {
            try
            {
                LogUtil::info("Creating database schema " . $aDatabaseSchema, "svn2db:db");
                $arrDefinition = explode("\n", $aDatabaseSchema);

                for ($i = 0, $m = sizeof($arrDefinition); $i < $m; $i++)
                {
                    $this->_pdo->query($arrDefinition[$i]);
                }
            }
            catch (PDOException $e)
            {
                LogUtil::error("Failed creating database schema: " . $e->getMessage(), "svn2db:db");
            }
        }
    }

    /**
     * Inserts a repository. If $aName does already exists, the method returns.
     * @param string name of repository
     * @param string url of repository
     * @return int id of repository
     */
    public function updateRepository($aName, $aUrl)
    {
        $sqlQuery = "SELECT " . DatabaseConnection::COLUMN_ID ." FROM " . DatabaseConnection::TABLE_REPOSITORY ." WHERE " . DatabaseConnection::COLUMN_NAME ." = ?";
        $sqlStat = $this->execute($sqlQuery, array($aName));
        $rId = $sqlStat->fetchColumn();

        if ($rId == null)
        {
            $sqlQuery = "INSERT INTO " . DatabaseConnection::TABLE_REPOSITORY ." (" . DatabaseConnection::COLUMN_NAME .", " . DatabaseConnection::TABLE_REPOSITORY_COLUMN_URL .") VALUES(?,?)";
            $this->execute($sqlQuery, array($aName, $aUrl));

            $rId = $this->_pdo->lastInsertId();
        }

        return $rId;
    }

    /**
     * Inserts an user. If $aName does already exists, the method returns.
     * @param string name of user
     * @return int id of user
     */
    public function updateUser($aName)
    {
        $sqlQuery = "SELECT " . DatabaseConnection::COLUMN_ID ." FROM " . DatabaseConnection::TABLE_USER ." WHERE " . DatabaseConnection::COLUMN_NAME ." = ?";
        $sqlStat = $this->execute($sqlQuery, array($aName));
        $rId = $sqlStat->fetchColumn();

        if ($rId == null)
        {
            $sqlQuery = "INSERT INTO " . DatabaseConnection::TABLE_USER ." (" . DatabaseConnection::COLUMN_NAME .") VALUES(?)";
            $this->execute($sqlQuery, array($aName));

            $rId = $this->_pdo->lastInsertId();
        }

        return $rId;
    }

    /**
     * inserts a commit to database
     * @param int revision
     * @param string commit message
     * @param int user id
     * @param string date
     * @param int repository id
     * @return id of commit
     * @throws Exception if revision of repository already exist
     */
    public function insertCommit($aRevision, $aMessage, $aIdUser, $aDate, $aIdRepository, $aTimestamp)
    {
        if ($this->isRevisionAvailable($aIdRepository, $aRevision))
        {
            throw new Exception("Failed adding commit, revision '$aRevision' in repository '$aIdRepository' already exists");
        }

        $sqlQuery = "INSERT INTO " . DatabaseConnection::TABLE_COMMIT . " (" . DatabaseConnection::TABLE_COMMIT_COLUMN_REVISION . ", " . DatabaseConnection::TABLE_COMMIT_COLUMN_MESSAGE . ", " . DatabaseConnection::TABLE_COMMIT_COLUMN_ID_USER . ", " . DatabaseConnection::TABLE_COMMIT_COLUMN_COMMIT_DATE . ", " . DatabaseConnection::TABLE_COMMIT_COLUMN_ID_REPOSITORY . ", " . DatabaseConnection::TABLE_COMMIT_COLUMN_TIMESTAMP .") VALUES(?,?,?,?,?,?)";
        $this->execute($sqlQuery, array($aRevision, $aMessage, $aIdUser, $aDate, $aIdRepository, $aTimestamp));
        $rId = $this->_pdo->lastInsertId();

        return $rId;
    }
     
    /**
     * Inserts a file of commit to database
     * @param int comit id
     * @param string filename
     * @param string type
     * @return id of file
     */
    public function insertFileInCommit($aIdCommit, $aFilename, $aType)
    {
        $sqlQuery = "INSERT INTO " . DatabaseConnection::TABLE_FILE_IN_COMMIT . " (" . DatabaseConnection::TABLE_FILE_IN_COMMIT_COLUMN_ID_COMMIT . ", " . DatabaseConnection::TABLE_FILE_IN_COMMIT_COLUMN_FILENAME . ", " . DatabaseConnection::TABLE_FILE_IN_COMMIT_COLUMN_TYPE . ") VALUES(?,?,?)";
        $this->execute($sqlQuery, array($aIdCommit, $aFilename, $aType));
        $rId = $this->_pdo->lastInsertId();

        return $rId;
    }

    /**
     * checks the availibility of a repository revision
     * @param int repository id
     * @param int revision
     * @return boolean
     */
    public function isRevisionAvailable($aIdRepository, $aRevision)
    {
        $sqlQuery = "SELECT * FROM " . DatabaseConnection::TABLE_COMMIT . " WHERE " . DatabaseConnection::TABLE_COMMIT_COLUMN_REVISION . " = ? AND " . DatabaseConnection::TABLE_COMMIT_COLUMN_ID_REPOSITORY . " = ?";
        $sqlStat = $this->execute($sqlQuery, array($aRevision, $aIdRepository));
        $r = $sqlStat->fetchAll();

        if (sizeof($r) > 0)
        {
            return true;
        }

        return false;
    }

    /**
     * counts the inserted revisions of a repository
     *
     * @param int $aIdRepository
     */
    public function countInsertedRevision($aIdRepository)
    {
        $sqlQuery = "SELECT COUNT(*) FROM " . DatabaseConnection::TABLE_COMMIT . " WHERE " . DatabaseConnection::TABLE_COMMIT_COLUMN_ID_REPOSITORY . " = ?";
        $sqlStat = $this->execute($sqlQuery, array($aIdRepository));
        return $sqlStat->fetchColumn(0);
    }

    /**
     * Find specified commits
     * @param string column name
     * @param string colum value
     * @param string order column, default is DatabaseConnection::TABLE_COMMIT_COLUMN_COMMIT_DATE
     * @param string order direction, default is ascending
     * @param int limit output
     * @return hash
     */
    private function _findCommits($aColumnName = null, $aColumnValue = null, $aOrder = null, $aDirection = 'DESC', $aLimit = "10")
    {
        if (null == $aOrder)
        {
            $aOrder = DatabaseConnection::TABLE_COMMIT_COLUMN_COMMIT_DATE;
        }

        $arrParams = array();

        $sqlQuery = "SELECT * FROM " . DatabaseConnection::TABLE_COMMIT;

        if ($aColumnName != null && $aColumnValue != null)
        {
            $sqlQuery .= " WHERE ? = ?";
            array_push($arrParams, $aColumnName);
            array_push($arrParams, $aColumnValue);
        }

        if ($aOrder != null)
        {
            $sqlQuery .= " ORDER BY $aOrder";
            $sqlQuery .= " $aDirection";
        }

        LogUtil::debug("$aOrder");

        if ($aLimit != null)
        {
            $sqlQuery .= " LIMIT ?";
            array_push($arrParams, $aLimit);
        }

        $sqlStat = $this->execute($sqlQuery, $arrParams);

        return $sqlStat->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find all commits in a repository
     * @param int repository id
     * @param string order column, default is DatabaseConnection::TABLE_COMMIT_COLUMN_COMMIT_DATE
     * @param string order direction, default is ascending
     * @param int limit output
     * @return hash
     */
    public function findCommitsByRepository($aIdRepository, $aOrder = null, $aDirection = 'ASC', $aLimit = null)
    {
        return $this->_findCommits(DatabaseConnection::TABLE_COMMIT_COLUMN_ID_REPOSITORY, $aIdRepository, $aOrder, $aDirection, $aLimit);
    }

    /**
     * Find all commits by user
     * @param int user id
     * @param string order column, default is DatabaseConnection::TABLE_COMMIT_COLUMN_COMMIT_DATE
     * @param string order direction, default is ascending
     * @param int limit output
     * @return hash
     */
    public function findCommitsByUser($aIdUser, $aOrder = null, $aDirection, $aLimit = null)
    {
        return $this->_findCommits(DatabaseConnection::TABLE_COMMIT_COLUMN_ID_USER, $aIdUser, $aOrder, $aDirection, $aLimit);
    }

    /**
     * Find all commits
     * @param string order column
     * @param string order direction
     * @param int limit
     * @return hash
     */
    public function findAllCommits($aOrder = null, $aDirection = 'ASC', $aLimit = null)
    {
        return $this->_findCommits(null, null, $aOrder, $aDirection, $aLimit);
    }

    /**
     * find all users of a repository
     *
     * @param int $aRepositoryId
     * @return array
     */
    public function findUsersByRepositoryId($aRepositoryId)
    {
        $sqlQuery  = "SELECT * FROM " . DatabaseConnection::TABLE_USER . " WHERE " . DatabaseConnection::COLUMN_ID . " IN (SELECT " . DatabaseConnection::TABLE_COMMIT_COLUMN_ID_USER . " FROM " . DatabaseConnection::TABLE_COMMIT . " WHERE " . DatabaseConnection::TABLE_COMMIT_COLUMN_ID_REPOSITORY . " = ?)";
        $sqlStat = $this->execute($sqlQuery, array($aRepositoryId));
        return $sqlStat->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a repository by name
     * @param string name
     * @return hash
     */
    public function findRepositoryByName($aName)
    {
        $sqlQuery = "SELECT * FROM " . DatabaseConnection::TABLE_REPOSITORY . " WHERE " . DatabaseConnection::COLUMN_NAME . " = ?";
        $sqlStat = $this->execute($sqlQuery, array($aName));

        return $sqlStat->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Returns all registered repositories
     * @return hash
     */
    public function findAllRepositories()
    {
        $sqlQuery = "SELECT * FROM " . DatabaseConnection::TABLE_REPOSITORY;
        $sqlStat = $this->execute($sqlQuery, array());

        return $sqlStat->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find all files of a commit
     * @param int commit id
     * @return hash
     */
    public function findAllFilesInCommit($aIdCommit)
    {
        $sqlQuery = "SELECT * FROM " . DatabaseConnection::TABLE_FILE_IN_COMMIT . " WHERE " . DatabaseConnection::TABLE_FILE_IN_COMMIT_COLUMN_ID_COMMIT ." = ?";
        $sqlStat = $this->execute($sqlQuery, array($aIdCommit));

        return $sqlStat->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Svn2DbBO encapsulates business logic for accessing the svn2db database
 * @author ckl <christopher[dot]klein[at]ecw[dot]de>
 * @url http://wap.ecw.de / http://www.ecw.de
 * @since 2008-07-13
 */
class Svn2DbBO
{
    /**
     * Svn2DbDAO
     * @type Svn2DbDAO
     */
    private $_svnDAO = null;

    /**
     * constructor
     * @param Svn2DbDAO DAO
     */
    public function __construct(Svn2DbDAO $aSvnDao)
    {
        $this->_svnDAO = $aSvnDao;
    }

    /**
     * Commits a SvnCommitTO to database
     * @param SvnCommitTO transfer object
     * @throws Exception If repository could not be added/updated
     * @throws Exception If user could not be added/updated
     * @throws Exception If commit could not be inserted (maybe revision in repository already exists)
     */
    public function commitToDatabase(SvnCommitTO $aSvnCommitTO)
    {
        if (!$idRepository = $this->_svnDAO->updateRepository($aSvnCommitTO->repository, $aSvnCommitTO->repositoryUrl))
        {
            throw new Exception("Failed updating repository '" . $aSvnCommitTO->repository . "'");
        }

        LogUtil::debug("database: repository updated", 'svn2db:bo');

        if (!$idUser = $this->_svnDAO->updateUser($aSvnCommitTO->user))
        {
            throw new Exception("Failed updating user '" . $aSvnCommitTO->user . "'");
        }

        LogUtil::debug("database: user updated", 'svn2db:bo');

        if (!$idCommit = $this->_svnDAO->insertCommit($aSvnCommitTO->revision, $aSvnCommitTO->message, $idUser, $aSvnCommitTO->date, $idRepository, $aSvnCommitTO->timestamp))
        {
            throw new Exception("Failed adding commit revision '" . $aSvnCommitTO->revision . "'");
        }

        $totalFiles = sizeof($aSvnCommitTO->svnFileInCommitTO);

        LogUtil::debug("database: revision updated", 'svn2db:bo');
        LogUtil::debug("database: files to store: '" . $totalFiles ."'", 'svn2db:bo');

        for ($i = 0; $i < $totalFiles; $i++)
        {
            $file = $aSvnCommitTO->svnFileInCommitTO[$i];

            $this->_svnDAO->insertFileInCommit($idCommit, $file->filename, $file->type);
        }
    }
}
?>
