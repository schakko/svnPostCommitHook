<?php
/**
 * A really simple logging class
 */
class LogUtil
{
    /**
     * file pointer to log file
     */
    private static $fp = null;

    /**
     * Loglevels enabled
     *
     * @var array
     */
    private static $arrLogLevel = array('info', 'error');

    /**
     * warn
     * @param string message
     * @param string module
     */
    public static function warn($msg, $module = null)
    {
        LogUtil::out($msg, "warn", $module);
    }

    /**
     * error
     * @param string message
     * @param string module
     *      */
    public static function error($msg, $module = null)
    {
        LogUtil::out($msg, "error", $module);
    }

    /**
     * info
     * @param string message
     * @param string module
     *      */
    public static function info($msg, $module = null)
    {
        LogUtil::out($msg, "info", $module);
    }

    /**
     * debug
     * @param string message
     * @param string module
     */
    public static function debug($msg, $module = null)
    {
        LogUtil::out($msg, "debug", $module);
    }

    /**
     * opens the given log file
     * @param string path to log file
     */
    public static function open($aLogFile)
    {
        self::$fp = fopen($aLogFile, "a+");

        LogUtil::info("-------------------------------------------");
        LogUtil::info("Log file " . $aLogFile . " opened for write");
    }

    /**
     * Set used log level
     *
     * @param array $aArrLogLevel
     */
    public static function setLogLevel($aArrLogLevel)
    {
        self::$arrLogLevel = $aArrLogLevel;
    }

    /**
     * closes opened log file
     */
    public static function close()
    {
        self::info("Closing log file");

        if (self::$fp != null)
        {
            fclose(self::$fp);
        }
    }

    /**
     * print the given message to log file
     * @param string message
     * @param string level
     * @param string module
     */
    private static function out($msg, $level, $module = null)
    {
        if (self::$fp != null)
        {
            if (in_array($level, self::$arrLogLevel))
            {
                if (null == $module)
                {
                    $module = "core";
                }

                fwrite(self::$fp, date("Y-m-d H:i:s") . " [" . $level . "] " . " [" . $module . "] " .$msg . "\r\n");
            }
        }
    }
}


/**
 * transfer object class. Every entry of "svn log" will result in exactly one SvnCommitTO
 *
 * @author ckl <christopher[dot]klein[at]ecw[dot]de>
 * @url http://wap.ecw.de / http://www.ecw.de
 * @since 2008-07-13
 *  */
class SvnCommitTO
{
    /**
     * message of commit. German umlauts are converted to htmlentities
     *
     * @var string
     */
    public $message;

    /**
     * Revision
     *
     * @var int
     */
    public $revision;

    /**
     * Date of commit.
     * Format is 2008-07-14 16:33:57 +0200 (Mo, 14 Jul 2008)
     *
     * @var string
     */
    public $date;

    /**
     * Timestamp
     *
     * @var int
     */
    public $timestamp;

    /**
     * username
     *
     * @var string
     */
    public $user;

    /**
     * zero, one or more SvnFileInCommitTOs
     * @type SvnFileInCommitTO
     */
    public $svnFileInCommitTO;

    /**
     * Name of repository
     *
     * @var string
     */
    public $repository;

    /**
     * URL to repository
     *
     * @var string
     */
    public $repositoryUrl;
}

/**
 * transfer object class. Every commited file results in exactly one SvnFileInCommitTO
 *
 * @author ckl <christopher[dot]klein[at]ecw[dot]de>
 * @url http://wap.ecw.de / http://www.ecw.de
 * @since 2008-07-13
 */
class SvnFileInCommitTO
{
    /**
     * Filename, german umlauts are converted to hmtlentities
     *
     * @var unknown_type
     */
    public $filename;

    /**
     * type of file (modified, changed, deleted) as single char in upper cases (M,C,D)
     *
     * @var char
     */
    public $type;
}

/**
 * Helper class for accessing "svn log"
 *
 * @author ckl <christopher[dot]klein[at]ecw[dot]de>
 * @url http://wap.ecw.de / http://www.ecw.de
 * @since 2008-07-13
 */
class SvnLogUtil
{
    /**
     * path to "svn log"
     * @type string
     */
    private $_cmdSvnLog;

    /**
     * full URL of repository
     *
     * @var string
     */
    private $_repositoryPath;

    /**
     * name of repository
     *
     * @var string
     */
    private $_repositoryName;

    /**
     * constructor
     * @param string path to "svn log"
     */
    public function __construct($aCmdSvnLog, $aRepositoryPath, $aRepositoryName)
    {
        $this->_cmdSvnLog = $aCmdSvnLog;
        $this->_repositoryName = $aRepositoryName;
        $this->_repositoryPath = $aRepositoryPath;

        if (!file_exists($this->_cmdSvnLog))
        {
            $msg = "Could not find svn executable: '" . $this->_cmdSvnLog ."'";
            LogUtil::error($msg);
            throw new Exception($msg);
        }

        LogUtil::debug("New SvnToUtil created:");
        LogUtil::debug(" * path to svn is '" . $this->_cmdSvnLog."'");
        LogUtil::debug(" * repository url is '" . $this->_repositoryPath . "'");
        LogUtil::debug(" * repository name is '" . $this->_repositoryName . "'");
    }

    /**
     * retrieves all commits
     * @return array of SvnCommitTo. The first item (idx 0) is the oldest revision (e.g. 1). The latest item (idx > 0) is the newest revision (e.g. 2)
     */
    public function retrieveAllCommits()
    {
        LogUtil::info("Retrieving all commits from repository '" . $this->_repositoryPath ."'...");

        $subCommand = "log -v " .$this->_repositoryPath;
        $buffer = $this->getSvnLogResult($subCommand);

        $svnCommitEntry = '';
        $bForceParsingOnNextMatch = false;

        $rSvn = array();
        $arrBuffer = array();

        for ($i = 0, $m  = sizeof($buffer); $i < $m; $i++)
        {
            $line  = $buffer[$i];

            if ((strstr($line, "------") !== FALSE) )
            {
                if ($bForceParsingOnNextMatch == true)
                {
                    array_push($arrBuffer, $line);
                    array_push($rSvn, $this->svnCommitEntryToTO($arrBuffer));
                    $arrBuffer = array();
                }
                else
                {
                    $bForceParsingOnNextMatch = true;
                }
            }

            array_push($arrBuffer, $line);
        }

        return array_reverse($rSvn, false);
    }

    /**
     * retrieves a single revision
     *
     * @param int revision
     * @return array of SvnCommitTO
     */
    public function retrieveCommitByRevision($aRevision)
    {
        $subCommand = "log -r $aRevision -v " . $this->_repositoryPath;
        $buffer = $this->getSvnLogResult($subCommand);

        return $this->svnCommitEntryToTO($buffer);
    }

    /**
     * executes the "svn log" command with given parameters and returns the output as array
     *
     * @param string Subcommand of "svn log"
     * @return array each entry contains one line of output
     */
    private function getSvnLogResult($aSubCommand)
    {
        $cmdSvnLog = $this->_cmdSvnLog ." " . $aSubCommand;
        LogUtil::info("Executing '" . $cmdSvnLog ."' ...");

        $fp = popen($cmdSvnLog, "r");
        $rBuffer = '';

        if ($fp != null)
        {
            $buffer = '';

            while(!feof($fp))
            {
                $buffer .= fgets($fp, 4096);
            }

            pclose($fp);

            $rBuffer = explode("\n", trim($buffer));
        }
        else
        {
            LogUtil::error("Could not open pipe to svn");
        }

        LogUtil::info("Buffer has '" . sizeof($rBuffer) . "' lines");

        return $rBuffer;
    }

    /**
     * Converts exactly one log entry into a transfer object.
     * Field 'message' and 'files' are converted to htmlentities.
     *
     * @param array each item must contain exactly one line of result. The first and last line must be  "--------"!
     * @return SvnCommitTO or null if commit entry is invalid
     */
    public function svnCommitEntryToTO($aLogEntry)
    {
        $rTO = new SvnCommitTO();

        $totalLines = sizeof($aLogEntry);

        if ($totalLines == 1)
        {
            LogUtil::error("Log entry seems to be invalid. Did you specify a valid revision and repository url? Return a NULL SvnCommitTO");
            return null;
        }

        LogUtil::info("Log entry has '" . $totalLines . "' lines");
        $revisionInfo = explode("|", $aLogEntry[1]);
        $rTO->revision = str_replace("r", "", trim($revisionInfo[0]));
        $rTO->user = trim($revisionInfo[1]);
        $rTO->date = trim($revisionInfo[2]);
        $rTO->repository = $this->_repositoryName;
        $rTO->repositoryUrl = $this->_repositoryPath;
        $rTO->timestamp = self::svnDateToTimestamp($rTO->date);

        preg_match("/(\d*)(\w*)/", trim($revisionInfo[3]), $arrLinesAdded);
        $totalMessageLines = $arrLinesAdded[1];

        $message = '';

        // parse messages
        for ($i = ($totalLines - $totalMessageLines), $m = $totalLines; $i < $m; $i++) {
            $wantedLineNumber = $i;
            $currentLine = $aLogEntry[$wantedLineNumber - 1];

            if (strlen(trim($currentLine)) > 0)
            {
                $message .= $currentLine . "\n";
            }
        }

        $rTO->message = self::cp850toGermanHtmlEntities(trim($message));

        $arrFiles = array();

        for ($i = 3, $m = (sizeof($aLogEntry) - 2 - $totalMessageLines); $i < $m; $i++) {
            $logFileLine = trim($aLogEntry[$i]);

            if (strlen($logFileLine) > 0)
            {
                $file = new SvnFileInCommitTO();
                $file->type = $logFileLine{0};
                $file->filename = self::cp850toGermanHtmlEntities(substr($logFileLine, 2));

                array_push($arrFiles, $file);
            }
        }

        $rTO->svnFileInCommitTO = $arrFiles;

        return $rTO;
    }

    /**
     * Converts codepage 850 items to HTML entities
     *
     * @param string $aText
     * @return string
     */
    public static function cp850toGermanHtmlEntities($aText)
    {
        $arrBads  = array("\x84", "\x81", "\x94", "\x8e", "\x9a", "\x99", "\xe1");
        $arrGoods = array('&auml;', '&uuml;', '&ouml;', '&Auml;', '&Uuml;', '&Ouml;', '&szlig;');

        return str_replace($arrBads, $arrGoods, $aText);
    }


    /**
     * Converts a SVN date to timestamp
     *
     * @param string $aSvnDate
     * @return int
     */
    public static function svnDateToTimestamp($aSvnDate)
    {
        $data = self::svnDateToArray($aSvnDate);
        return mktime($data['hour'], $data['minute'], $data['second'], $data['month'], $data['day'], $data['year']);
    }

    /**
     * Converts a svn date to an array
     *
     * @param hash $aSvnDate
     */
    public static function svnDateToArray($aSvnDate)
    {
        if (preg_match("/^(\d\d\d\d)\-(\d\d)\-(\d\d)\s(\d\d):(\d\d):(\d\d)\s([\+|\-])(\d\d\d\d)\s\((\w*)\,\s(\d*)\s(\w*)\s(\d*)\)/", $aSvnDate, $arrRet))
        {
            $r['year'] = $arrRet[1];
            $r['month'] = $arrRet[2];
            $r['day'] = $arrRet[3];
            $r['hour'] = $arrRet[4];
            $r['minute'] = $arrRet[5];
            $r['second'] = $arrRet[6];
            $r['addition'] = $arrRet[7];
            $r['gmt'] = $arrRet[8];
            $r['dayName'] = $arrRet[9];
            $r['monthName'] = $arrRet[11];

            return $r;
        }
    }
}

/**
 * Executes all hooks.
 *
 * @author ckl <christopher[dot]klein[at]ecw[dot]de>
 * @url http://wap.ecw.de / http://www.ecw.de
 * @since 2008-07-13
 */
class SvnHookRunner
{
    /**
     * @var string
     */
    private $_repositoryName;

    /**
     * @var int
     */
    private $_revision;

    /**
     * @var hash
     */
    private $_confEnvironment;

    /**
     * @var hash
     */
    private $_confRepositories;

    /**
     * @var hash
     */
    private $_confCurrentRepository;

    /**
     * @var array
     */
    private $_executeHooks;
	
	/**
    * @var array
    */
	private $_configHooks;
	

    /**
     * @var SvnLogUtil
     */
    private $_svnLogUtil;

    /**
     * @var string
     */
    private $_repositoryPath;

    /**
     * Constructor
     *
     * @param string name of repository
     * @param string revision
     * @param array $aConfEnvironment
     * @param array $aConfRepositories
     */

    public function __construct($aRepositoryName, $aRevision, $aConfEnvironment, $aConfRepositories)
    {
        $this->_repositoryName = $aRepositoryName;
        $this->_revision = $aRevision;
        $this->_confEnvironment = $aConfEnvironment;
        $this->_confRepositories = $aConfRepositories;
		$this->_configHooks = array();

        $this->setUp();
    }

    /**
     * delegate method to sub routines
     * @throws Exception if setup failed
     */
    private function setUp()
    {
        if ($this->_confEnvironment['logging']['enable'] == true)
        {
            LogUtil::open($this->_confEnvironment['logging']['file']);
            LogUtil::setLogLevel($this->_confEnvironment['logging']['level']);
        }

        $this->loadCurrentRepositoryConfiguration();
        $this->loadHooks();

        $this->_svnLogUtil = new SvnLogUtil($this->_confEnvironment['svn']['executable'], $this->_repositoryPath, $this->_repositoryName);
    }

    /**
     * Loads the configuration. If no own configuration is assigned for repository, the configuration __DEFAULT__ is used.
     * @throws Exception If configuration could not be set.
     */
    private function loadCurrentRepositoryConfiguration()
    {
        if (isset($this->_confRepositories[strtolower($this->_repositoryName)]))
        {
            $this->_confCurrentRepository = $this->_confRepositories[strtolower($this->_repositoryName)];
            LogUtil::info("Own configuration loaded for repository " . $this->_repositoryName);
        }
        else
        {
            $this->_confCurrentRepository = $this->_confRepositories['__DEFAULT__'];
            LogUtil::info("Default configuration loaded for repository " . $this->_repositoryName);
        }

        if (!$this->_confCurrentRepository)
        {
            throw new Exception("No configuration loaded for repository '" . $this->_repositoryName ."'");
        }

        $this->_repositoryPath = $this->_confCurrentRepository['repositoryUrl'] . '/' . $this->_repositoryName;

        LogUtil::debug("Configuration values: " . print_r($this->_confCurrentRepository, true));
    }

    /**
     * Loads all desired hooks - our own class loader.
     * This method does ***not*** throw any error if loading failed.
     */
    private function loadHooks()
    {
        $arrHooks = $this->_confCurrentRepository['arrHooks'];

        LogUtil::debug("'" . sizeof($arrHooks) ."' hooks assigned for this repository");
		$thisDir = dirname(__FILE__);
		
		while(list($hook, $arrHookConfig) = each($arrHooks))
		{
			if ($hook == '' && !is_array($arrHookConfig)) {
				$hook = $arrHookConfig;
				$arrHookConfig = array();
			}

            $path = $thisDir . "/hooks/" . $hook . "/" . $hook . ".php";

            if (file_exists($path))
            {
                include_once($path);
                LogUtil::debug("Hook file '$hook'' loaded");

                if (class_exists($hook))
                {
                    $clazz = new $hook();

                    if ($clazz instanceof ISvnPostCommitHook )
                    {
                        $this->_executeHooks[] = $clazz;
						$this->_configHooks[] = $arrHookConfig;
						
                        LogUtil::info("Hook '$hook' ready for use");
                    }
                    else
                    {
                        LogUtil::error("Hook '$hook' does not implement ISvnPostCommitHook");
                    }
                }
                else
                {
                    LogUtil::error("Clazz '$hook' does not exists in file '" . $path . "'");
                }
            }
            else
            {
                LogUtil::error("Could not load hook '"  . $hook . "': file '" . $path . "' not found");
            }
        }
    }

    /**
     * run all available scripts for current repository
     */
    public function run()
    {
        $commitTO = $this->_svnLogUtil->retrieveCommitByRevision($this->_revision);

        if (null == $commitTO)
        {
            LogUtil::info("Will not execute any hooks. SvnCommitTO is NULL");
            return;
        }

        for ($i = 0, $m = sizeof($this->_executeHooks); $i < $m; $i++)
        {
            $clazz = $this->_executeHooks[$i];

            $clazz->runHook($this->_configHooks[$i], $this->_repositoryName, $this->_revision, $this->_repositoryPath, $this, $commitTO);
        }
    }

    /**
     * returns the configuration for current repository
     *
     * @return hash
     */
    public function getCurrentRepositoryConfiguration()
    {
        return $this->_confCurrentRepository;
    }

    /**
     * returns the svn log utility
     *
     * @return SvnLogUtil
     */
    public function getSvnLogUtil()
    {
        return $this->_svnLogUtil;
    }

    /**
     * destructor - only used for closing log file
     *
     */
    public function __destruct()
    {
        LogUtil::close();
    }
}
?>