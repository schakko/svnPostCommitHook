<?php
include_once("Zend/Config/Ini.php");

//
try
{
    $config = new Zend_Config_Ini('config.ini', $_SERVER['SERVER_NAME']);
    
    if (!$config)
    {
        throw new Exception("Configuration file config.ini could not be parsed");
    }
    
    if (!is_dir($config->svnPostCommitHook->dir)) 
    {
       throw new Exception("svnPostCommitHook directory '" . $config->svnPostCommitHook->dir . "' not found"); 
    }
    
    include_once($config->svnPostCommitHook->dir . "/svnPostCommitHook.util.php");
    include_once($config->svnPostCommitHook->dir . "/hooks/svn2db/svn2db.init.php");
    include_once(dirname(__FILE__) . "/db2rss.classes.php");
	
    $factory = new Svn2DbFactory(array('pathToSqliteDatabase' => $config->pathToSqliteDatabase));
	
    $svnDAO = $factory->getDAO();
    
    if (isset($_GET['feed']) && (($_GET['feed'] == 'rss') || ($_GET['feed'] == 'atom')))
    {
		$limit = null;
		if (isset($_GET['limit'])) {
			$limit = $_GET['limit'];
		}
		
		$repositoryId = null;
		if (isset($_GET['repository_id'])) {
			$repositoryId = $_GET['repository_id'];
		}
		$repositoryName = null;
		if (isset($_GET['repository'])) {
			$repositoryName = $_GET['repository'];
		}
		$userId = null;
		if (isset($_GET['user_id'])) {
			$userId = $_GET['user_id'];
		}

		$userName = null;
		if (isset($_GET['user'])) {
			$userName = $_GET['user'];
		}
		
        $dataLoader = new ParameterToSvnDataBO($svnDAO);
        $dataLoader->setLimit($limit);
        $dataLoader->setRepositoryId($repositoryId);
        $dataLoader->setRepositoryName($repositoryName);
        $dataLoader->setUserId($userId);
        $dataLoader->setUserName($userName);
        $result = $dataLoader->getResult();

        $feedCreator = new SvnFeedCreator($_GET['feed'], $svnDAO, $config);
        $feedCreator->setTitle($dataLoader->toString());
        $feedCreator->setSvnData($result);
        header("Content-Type: application/" . $_GET['feed'] . "+xml; charset: utf-8");
        print $feedCreator->generate();
        exit;
    }
    else
    {
		function createUrl($suburl)
		{
            return "?$suburl&amp;feed";
		}
		
		function atomRssRelLink($suburl, $title) {
			$url = createUrl($suburl);
			$r  = "    <link rel=\"alternate\" type=\"application/rss+xml\" title=\"$title (RSS)\" href=\"$url=rss\" />\n";
			$r .= "    <link rel=\"alternate\" type=\"application/atom+xml\" title=\"$title (Atom)\" href=\"$url=atom\" />\n";
			return $r;
		}
		
        function atomRssLink($suburl)
        {
			$url = createUrl($suburl);
            return "<a href='$url=rss'>RSS</a> | <a href='$url=atom'>Atom</a>";
        }
        
		$arrRepositories = $svnDAO->findAllRepositories();
        $arrUsers = $svnDAO->findAllUser();
		
		$repoList = "";
		$userList = "";
		$atomRssRelList = "";

        for ($i = 0, $m = sizeof($arrRepositories); $i < $m; $i++)
        {
            $queryLink = 'repository_id=' . $arrRepositories[$i][DatabaseConnection::COLUMN_ID];

            $linkRepo = atomRssLink($queryLink);
			$name = $arrRepositories[$i][DatabaseConnection::COLUMN_NAME];
			
			$atomRssRelList .= atomRssRelLink($queryLink, $name);
			
			$repoList .= "           <li> ". $name . " " . atomRssLink($queryLink) . "\n";
            $repoList .= "             <ul>";

            $arrUsersInRepository = $svnDAO->findUsersByRepositoryId($arrRepositories[$i][DatabaseConnection::COLUMN_ID]);

            for ($j = 0, $n = sizeof($arrUsersInRepository); $j < $n; $j++)
            {
				$user = $arrUsersInRepository[$j][DatabaseConnection::COLUMN_NAME];
				$nameUserInRepo = $name . " " . $user;
				$queryLinkUserInRepo = $queryLink . "&amp;user_id=" . $arrUsersInRepository[$j][DatabaseConnection::COLUMN_ID];
				
				$linkUserInRepo = atomRssLink($queryLinkUserInRepo);
				
				$atomRssRelList .= atomRssRelLink($queryLinkUserInRepo, $nameUserInRepo);
                $repoList .= "               <li>" . $user . " " . $linkUserInRepo . "</li>\n";
            }

            $repoList .= "             </ul>\n";
            $repoList .= "           </li>\n";
        }
		
		
        for ($i = 0, $m = sizeof($arrUsers); $i < $m; $i++)
        {
            $queryLink = 'user_id=' . $arrUsers[$i][DatabaseConnection::COLUMN_ID];
			$name = $arrUsers[$i][DatabaseConnection::COLUMN_NAME];
			$atomRssRelList .= atomRssRelLink($queryLink, $name);
			
            $userList .= "           <li>" .  $name . " " . atomRssLink($queryLink) . "</li>\n";
        }



        print "<html>\n";
		print "  <head>\n";
		print "    <title>feed overview for SVN repositories</title>";
		print atomRssRelLink('all', 'All repositories');
		print $atomRssRelList;
		print "  </head>\n";
        print "  <body>\n";
        print "    <ul>\n";
        print "      <li>all repositories/users " . atomRssLink('all') ."</li>\n";
        print "      <li>repositories:\n";
        print "         <ul>\n";
		print $repoList;
        print "         </ul>\n";
        print "      <li>users:\n";
        print "         <ul>\n";
		print $userList;
        print "      </li>\n";
        print "    <ul>\n";
        print "  </body>\n";
        print "</html>\n";
    }
}
catch (Exception $e)
{
    die($e->getMessage());

}

?>