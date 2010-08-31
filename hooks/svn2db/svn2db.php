<?php
include_once(dirname(__FILE__) . "/svn2db.init.php");
include_once(dirname(__FILE__) . "/../../svnPostCommitHook.interface.php");

/**
 * This post-commit hook stores every repository commit in a database.
 * 
 * @author ckl <christopher[dot]klein[at]ecw[dot]de>
 * @url http://wap.ecw.de / http://www.ecw.de
 * @since 2008-07-13
 */
class svn2db implements ISvnPostCommitHook
{
    /**
     * Implemented by ISvnPostCommitHook. Function of hook:
     * If the revision of the commit does not already exists in database, it will be stored.
     * If the count of axisting revision does not match with current (revision - 1), every revision of the repository will be retrieved by "svn log".
     * Existing revisions will ***not*** be overwritten.
     *
     * @param map array with hook configuration 
     * @param string $aRepositoryName
     * @param string $aRevision
     * @param string $aFullRepositoryPath
     * @param SvnHookRunner $aSvnHookRunner
     * @param SvnCommitTO $aCommitTO
     */
    public function runHook($aHookConfiguration, $aRepositoryName, $aRevision, $aFullRepositoryPath, SvnHookRunner $aSvnHookRunner, SvnCommitTO $aCommitTO)
    {
        try
        {
			$factory = new Svn2DbFactory($aHookConfiguration);
			
            $svnDAO = $factory->getDAO();
            $bo = $factory->getBO();

            $arrRepository = $svnDAO->findRepositoryByName($aRepositoryName);

            $objectsToDatabase = array();

            // repository is already entered in database => are all revisions available?
            if (null != $arrRepository)
            {
                $idRepository = $arrRepository[DatabaseConnection::COLUMN_ID];

                LogUtil::debug("Repository '$aRepositoryName' already exists in database (id: '$idRepository')", 'svn2db:hook');

                $iCntRevisions = $svnDAO->countInsertedRevision($idRepository);
                LogUtil::debug("Already inserted revisions for '$aRepositoryName': '$iCntRevisions'", "svn2db:hook");
                LogUtil::debug("Repository '$aRepositoryName' has '$iCntRevisions' stored in database", 'svn2db:hook');

                // all revisions are in database
                if ($iCntRevisions == $aRevision)
                {
                    LogUtil::info("Database is up-to-date, returning", "svn2db:hook");
                    return;
                }

                if ($iCntRevisions > $aRevision)
                {
                    LogUtil::error("Revision '$aRevision' was added long long time ago..., returning", 'svn2db:hook');
                    return;
                }

                if ($iCntRevisions == ($aRevision - 1))
                {
                    LogUtil::info("Adding latest revision ('$aRevision')", "svn2db:hook");
                    $objectsToDatabase[] = $aCommitTO;
                }
            }

            if (sizeof($objectsToDatabase) == 0)
            {
                LogUtil::info("Fetching ***all*** revisions for repository '$aRepositoryName'", 'svn2db:hook');
                $objectsToDatabase = $aSvnHookRunner->getSvnLogUtil()->retrieveAllCommits($aFullRepositoryPath, $aRepositoryName);
            }

            LogUtil::info("Storing '" . sizeof($objectsToDatabase) ."' revisions in database", 'svn2db:hook');

            for ($i = 0, $m  = sizeof($objectsToDatabase); $i < $m; $i++)
            {
                LogUtil::info("Storing revision '" . $objectsToDatabase[$i]->revision ."'...", 'svn2db:hook');
                
                try
                {
                    $bo->commitToDatabase($objectsToDatabase[$i]);
                }
                catch (Exception $e)
                {
                    LogUtil::error($e->getMessage(), "svn2db:hook");
                }
            }
        }
        catch (Exception $e)
        {
            LogUtil::error($e->getMessage(), "svn2db:hook");
        }
        catch (PDOException $e)
        {
            LogUtil::error("Connection to database '" . Svn2DbFactory::getConnection()->getConnectionString() . "' failed: " . $e->getMessage(), "svn2db:hook");
        }

    }
}

?>