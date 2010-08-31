<?php
include_once("Zend/Feed.php");
include_once("Zend/Feed/Builder.php");
include_once("Zend/View.php");

/**
 * Converts URL parameters to parameters for retrieving SVN data
 *
 * @author ckl <christopher[dot]klein[at]ecw[dot]de>
 * @url http://wap.ecw.de / http://www.ecw.de
 * @since 2008-07-13
 */
class ParameterToSvnDataBO
{
    /**
     * @var Svn2DbDAO
     */
    private $_dao;

    /**
     * @var int
     */
    private $_repositoryId;

    /**
     * @var string
     */
    private $_repositoryName;

    /**
     * @var int
     */
    private $_userId;

    /**
     * @var string
     */
    private $_userName;

    /**
     * @var int
     */
    private $_limit = 10;

    /**
     * @var string
     */
    private $_orderBy = DatabaseConnection::TABLE_COMMIT_COLUMN_COMMIT_DATE;

    /**
     * @var string
     */
    private $_orderDirection = "DESC";

    /**
     * Default constructor
     *
     * @param Svn2DbDAO $aDao
     */
    public function __construct(Svn2DbDAO $aDao)
    {
        $this->_dao = $aDao;
    }

    /**
     * Sets the repository name. If repository name could not be found in database, no repository will be set
     *
     * @param string $aName
     */
    public function setRepositoryName($aName)
    {
        if (!$aName)
        {
            return;
        }

        $repository = $this->_dao->findRepositoryByName($aName);

        if ($repository[0] != null)
        {
            $this->setRepositoryId($repository[0][DatabaseConnection::COLUMN_ID]);
            $this->_repositoryName = $repository[0][DatabaseConnection::COLUMN_NAME];
        }
    }

    /**
     * Sets repository id
     *
     * @param int $aId
     */
    public function setRepositoryId($aId)
    {
        $this->_repositoryId = (int)$aId;
        $this->_repositoryName = $this->_dao->findNameOfRepository($aId);

        LogUtil::debug("repository id set to '" . $this->getRepositoryId() ."'", 'db2rss:parser');
    }

    /**
     * returns repository id
     *
     * @return int
     */
    public function getRepositoryId()
    {
        return $this->_repositoryId;
    }

    /**
     * Sets the user name. If user name could not be found in database, no user will be set
     *
     * @param string $aName
     */
    public function setUserName($aName)
    {
        if (!$aName)
        {
            return;
        }

        $user = $this->_dao->findUserByName(array($aName));

        if ($user[0] != null)
        {
            $this->setUserId($user[0][DatabaseConnection::COLUMN_ID]);
            $this->_userName = $user[0][DatabaseConnection::COLUMN_NAME];
        }
    }

    /**
     * set user id
     * @param int $aId
     */
    public function setUserId($aId)
    {
        $this->_userId = (int)$aId;
        $this->_userName = $this->_dao->findNameOfUser($aId);

        LogUtil::debug("user id set to '" . $this->getUserId() ."'", 'db2rss:parser');
    }

    /**
     * get user id
     * @return int
     */
    public function getUserId()
    {
        return $this->_userId;
    }

    /**
     * set limit
     *
     * @param int $aLimit
     */
    public function setLimit($aLimit)
    {
        if ($aLimit > 0)
        {
            $this->_limit = (int)$aLimit;
        }
    }

    /**
     * get limit
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->_limit;
    }

    /**
     * get result from database
     *
     * @return array
     */
    public function getCommits()
    {
        $arrParameters = array();

        $findMethod = "findSvnCommitBy";

        if ($this->_userId)
        {
            array_push($arrParameters, $this->_userId);
            $findMethod .= "IdUser";
        }

        if ($this->_repositoryId)
        {
            if (sizeof($arrParameters) > 0)
            {
                $findMethod .= "And";
            }

            array_push($arrParameters, $this->_repositoryId);

            $findMethod .= "IdRepository";
        }

        if (sizeof($arrParameters) > 0)
        {
            return $this->_dao->$findMethod($arrParameters, $this->_orderBy, $this->_orderDirection, $this->_limit);
        }

        return $this->_dao->findAllCommits($this->_orderBy, $this->_orderDirection, $this->_limit);
    }

    /**
     * Returns the result with commited/changed files
     *
     * @return array
     */
    public function getResult()
    {
        $arrCommits = $this->getCommits();

        for ($i = 0, $m = sizeof($arrCommits); $i < $m; $i++)
        {
            $arrCommits[$i][DatabaseConnection::TABLE_FILE_IN_COMMIT] = $this->_dao->findAllFilesInCommit($arrCommits[$i][DatabaseConnection::COLUMN_ID]);
        }

        return $arrCommits;
    }

    /**
     * Converts the requested operation to string
     * @return string
     */
    public function toString()
    {
		$r = "";
		
        if ($this->_repositoryName)
        {
            $r .= $this->_repositoryName;
        }

        if ($this->_userName)
        {
            if ($this->_repositoryName)
            {
                $r .= "/";
            }

            $r .= $this->_userName;
        }

        if ($r == "")
        {
            $r = "All repositories";
        }

        return $r;
    }
}
/**
 * Conerts data from database to RSS/Atom feed. For generation of feed, Zend Framework is used.
 *
 * @author ckl <christopher[dot]klein[at]ecw[dot]de>
 * @url http://wap.ecw.de / http://www.ecw.de
 * @since 2008-07-13
 */
class SvnFeedCreator
{
    /**
     * Feed type (rss or atom
     *
     * @var string
     */
    private $_feedType = 'rss';

    /**
     * Data
     *
     * @var array
     */
    private $_svnData = null;

    /**
     * @var Svn2DbDAO
     */
    private $_dao = null;

    /**
     * Title of feed
     *
     * @var string
     */
    private $_title = 'Title not set';

	/**
	 * Configuration
	 * @var Zend_Config_Ini
	 */
	private $_config = null;
	
    /**
     * Constructor
     *
     * @param string $aFeedType
     * @param string $aDao
     */
    public function __construct($aFeedType, Svn2DbDAO $aDao, Zend_Config_Ini $aConfig = null)
    {
        $this->setFeedType($aFeedType);
        $this->_dao = $aDao;
		$this->_config = $aConfig;
    }

    /**
     * Feed type, can be rss or atom, Default is rss
     *
     * @param unknown_type $aFeedType
     */
    public function setFeedType($aFeedType)
    {
        if ($aFeedType == 'rss' || $aFeedType == 'atom')
        {
            $this->_feedType = $aFeedType;
        }
    }

    /**
     * Data
     *
     * @param array $aData
     */
    public function setSvnData($aData)
    {
        $this->_svnData = $aData;
    }

    /**
     * Set title of feed
     *
     * @param string $aTitle
     */
    public function setTitle($aTitle)
    {
        $this->_title = $aTitle;
    }

    /**
     * Execute generation
     *
     * @return string (xml)
     */
    public function generate()
    {
        $arrEntries = array();

        LogUtil::info("Items to return: " . sizeof($this->_svnData), 'db2rss:svnfeedcreator');

        for ($i = 0, $m = sizeof($this->_svnData); $i < $m; $i++)
        {
            $current = $this->_svnData[$i];

            $view = new Zend_View();
            $view->addScriptPath(dirname(__FILE__));
            $view->timestamp = SvnLogUtil::svnDateToTimestamp($current[DatabaseConnection::TABLE_COMMIT_COLUMN_COMMIT_DATE]);
            $view->userName = $this->_dao->findNameOfUser($current[DatabaseConnection::TABLE_COMMIT_COLUMN_ID_USER]);
			$repoName = $this->_dao->findNameOfRepository($current[DatabaseConnection::TABLE_COMMIT_COLUMN_ID_REPOSITORY]);
            $view->repositoryName = $repoName;
            $view->repositoryUrl = str_replace('%repositoryName%', $repoName, $this->_config->repositoryUrl);
			
            while (list($key, $val) = each($current))
            {
                $view->$key = $val;
            }

            reset($current);

            $arrEntry['title'] = 'Repository ' .  $view->repositoryName . ' updated to revision ' . $view->revision;
            $arrEntry['lastUpdate'] = $view->timestamp;
            $arrEntry['link'] = $view->repositoryUrl;
            $arrEntry['content'] = $view->render($this->_feedType . ".template.php");
            $arrEntry['description'] = $arrEntry['content'];

            array_push($arrEntries, $arrEntry);
        }

        $arrFeed = array(
        					'title' => 'SVN: ' . $this->_title,
                            'link' => urlencode($_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']),
                            'charset' => 'UTF-8',
                            'SVN commits of ' . $this->_title,
                            'generator' => 'svn2db => db2rss',
                            'author' => 'EDV Consulting Wohlers GmbH',

                            'entries' => $arrEntries
        );

        $r = Zend_Feed::importBuilder(new Zend_Feed_Builder($arrFeed), $this->_feedType);

        return $r->saveXml();
    }
}
?>