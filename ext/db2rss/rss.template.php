<strong><?=$this->userName; ?></strong> did a change in repository <strong><?=$this->repositoryName; ?></strong> on <strong><?=$this->commit_date; ?></strong>. 
<br />
Repository is now in revision <strong><?=$this->revision; ?></strong>.<br />
Commit message was <pre>
<?=$this->message; ?>
</pre><br />

Changed files were:

<table border='0'>
<?php foreach ($this->file_in_svn_commit as $file) { ?>
  <tr><td><?= $file['type'];?></td><td><?=$file['filename'];?></td></tr>
<?php } ?>
</table>
