<?=$this->userName; ?> did a change in repository <?=$this->repositoryName; ?> on <?=$this->commit_date; ?>.

Repository is now in revision <?=$this->revision; ?>.
Commit message was "<?=html_entity_decode($this->message, null, "UTF-8"); ?>". <?=sizeof($this->file_in_svn_commit); ?> file(s) changed: