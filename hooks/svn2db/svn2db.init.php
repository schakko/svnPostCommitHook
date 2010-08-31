<?php
include_once(dirname(__FILE__) . "/svn2db.classes.php");

/**
 * Factory class for DAO/BO/database connection
 *
 */
class Svn2DbFactory
{
	public function Svn2DbFactory($aConfiguration)
	{
		$this->configuration = $aConfiguration;
	}
	
	/**
    * configuration of this hook
    * @var array
    */
	private $configuration = array();
	
    /**
     * database connection
     *
     * @var DatabaseConncetion
     */
    private $instanceConnection = null;
    
    /**
     * DAO
     *
     * @var Svn2DbDAO
     */
    private $instanceDAO = null;
    
    /**
     * BO
     *
     * @var Svn2DbBO
     */
    private $instanceBO = null;
    
    /**
     * Returns BO
     *
     * @return Svn2DbBO
     */
    public function getBO()
    {
        if ($this->instanceBO == null)
        {
            $this->instanceBO = new Svn2DbBO($this->getDAO());
        }
        
        return $this->instanceBO;
    }
    
    /**
     * Returns DAO
     * @return Svn2DbDAO
     */
    public function getDAO()
    {
        if ($this->instanceDAO == null)
        {
            $this->instanceDAO = new Svn2DbDAO($this->getConnection()->getPDO(), $this->getConnection()->getSqlCreateStatement());
        }
        
        return $this->instanceDAO;
    }
    
    /**
     * Returns database connection
     * @return DatabaseConnection
     */
    public function getConnection()
    {
        if ($this->instanceConnection == null)
        {
            $this->instanceConnection = new DatabaseConnection($this->configuration);
        }
        
        return $this->instanceConnection;
    }
}
?>