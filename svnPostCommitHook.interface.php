<?php
/**
 * Interface for running a specific hook
 * @author ckl
 */
interface ISvnPostCommitHook
{
    /**
     * executes the given hook
     * @param map array with hook configuration 
     * @param string name of repository in lower cases
     * @param int revision
     * @param string full URL to repository path
     * @param SvnHookRunner reference to caller class
     * @param SvnCommitTO $aCommitTO
     * @throws Exception if hook execution failed
     */
    public function runHook($aHookConfiguration, $aRepositoryName, $aRevision, $aFullRepositoryPath, SvnHookRunner $aSvnHookRunner, SvnCommitTO $aCommitTO);
}

?>