<?php
//� 2019 Martin Madsen
namespace MTM\FS\Factories;

class Directories extends Base
{
	//USE: $dirObj	= \MTM\FS\Factories::getDirectories()->getDirectory("/tmp/");
	
	protected $_sessTmp=null;
	
	public function __construct()
	{
		$this->_s["tmpDirs"]		= array();
		register_shutdown_function(array($this, '__destruct'));
	}
	public function __destruct()
	{
		if (count($this->_s["tmpDirs"]) > 0) {
			$dirObjs					= $this->_s["tmpDirs"];
			$this->_s["tmpDirs"]	= array();
			foreach ($dirObjs as $id => $dirObj) {
				$dirObj->delete();
				unset($this->_s["tmpDirs"][$id]);
			}
		}
	}	
	public function getDirectory($path=null)
	{
		$dirObj	= new \MTM\FS\Models\Directory();
		$dirObj->setTool($this->getLocalDirectoriesTool());
		
		if ($path != "") {
			$pDir	= $dirObj;
			$dirs	= $this->getSplitPath($path);
			foreach ($dirs as $dir) {
				$dirObj	= new \MTM\FS\Models\Directory();
				$dirObj->setTool($this->getLocalDirectoriesTool());
				
				$dirObj->setName($dir);
				if ($pDir !== null) {
					$dirObj->setParent($pDir);
					$pDir->addChild($dirObj);
				}
				$pDir	= $dirObj;
			}
		}
		return $dirObj;
	}
	public function getDirectoryFromFilePath($filePath)
	{
		if (is_string($filePath) === true) {
			
			$parts		= $this->getSplitPath($filePath);
			//last element must be the filename
			array_pop($parts);
			
			if (DIRECTORY_SEPARATOR == "/") {
				//linux paths start with /
				if (count($parts) > 0) {
					$dirPath	= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) . DIRECTORY_SEPARATOR;
				} else {
					$dirPath	= DIRECTORY_SEPARATOR;
				}
				
			} else {
				//windows, the first element is the drive letter + ":"
				$dirPath	= implode(DIRECTORY_SEPARATOR, $dirs) . DIRECTORY_SEPARATOR;
			}
			
			return $this->getDirectory($dirPath);
			
		} else {
			throw new \Exception("Invalid Input");
		}
	}
	public function cloneDirectory($dirObj)
	{
		$rDirObj	= $this->getDirectory($dirObj->getPathAsString());
		$rDirObj->setTool($dirObj->getTool());
		return $rDirObj;
	}
	public function getLocalDirectoriesTool()
	{
		if (array_key_exists(__FUNCTION__, $this->_s) === false) {
			$this->_s[__FUNCTION__]	= new  \MTM\FS\Tools\Directories\Local();
		}
		return $this->_s[__FUNCTION__];
	}
	public function getTempDirectory($baseDir=null)
	{
		//this directory and all content is deleted when php terminates
		if ($baseDir === null) {
			$baseDir	= $this->getSessionTempDir();
		} elseif (is_string($baseDir) === true) {
			$baseDir	= $this->getDirectory($baseDir);
		}

		$sPath	= $baseDir->getPathAsString();
		while (true) {
			$tmpPath		= $sPath . DIRECTORY_SEPARATOR . uniqid();
			$tmpDir			= $this->getDirectory($tmpPath);
			if ($tmpDir->getExists() === false) {
				$tmpDir->create();
				$this->setAsTempDir($tmpDir);
				return $tmpDir;
			}
		}
	}
	public function setAsTempDir($dirObj)
	{
		$this->_s["tmpDirs"][]   = $dirObj;
		return $this;
	}
	public function getNonTempDirectory($baseDir=null)
	{
		//this directory will not be deleted at the end of the session
		//however it will be removed on reboot.
		//we simply find an available name and create it
		if ($baseDir === null) {
			$baseDir	= $this->getDirectory(MTM_FS_TEMP_PATH);
		} elseif (is_string($baseDir) === true) {
			$baseDir	= $this->getDirectory($baseDir);
		}
		$sPath	= $baseDir->getPathAsString();
		while (true) {
			$newPath		= $sPath . DIRECTORY_SEPARATOR . uniqid();
			$newDir			= $this->getDirectory($newPath);
			if ($newDir->getExists() === false) {
				$newDir->create();
				return $newDir;
			}
		}
	}
	private function getSessionTempDir()
	{
		if ($this->_sessTmp === null) {
			$path					= MTM_FS_TEMP_PATH.uniqid("MTM-Temp-");
			$tmpDir					= $this->getDirectory($path);
			$this->setAsTempDir($tmpDir);
			if (DIRECTORY_SEPARATOR == "/") {
				//we need a guard process that can clean up the temp dir on hard exit, just like MTM-Shells with a bash creation
				//start a while loop. When process is no longer, we clean up if needed
				if (substr_count($tmpDir->getPathAsString(), DIRECTORY_SEPARATOR) > 1) {
					//only allow the delete process if we are at least 2x levels into the file system tree
					//a minimal guard against wiping the entire file system
					//monitor on linux if MTM_FS_TEMP_PATH=/dev/shm/:    watch -n1 'ls /dev/shm/ ; ps ax | grep -i "_MTM" | grep -v "watch" ;'

					$procPid	= getmypid();
					$loopSleep	= 3;
					$strCmd		= "(";
					$strCmd		.= " nohup sh -c '";
					$strCmd		.= " _MTM_WHAT_IS_THIS=\"".__METHOD__."\";"; //Give admins an idea what this weird process is
					$strCmd		.= " while";
					$strCmd		.= " [ -n \"".$procPid."\" -a -e /proc/" . $procPid . " ];";
					$strCmd		.= " do";
					$strCmd		.= " sleep ".$loopSleep."s;";
					$strCmd		.= " done;";
					$strCmd		.= " sleep ".$loopSleep."s ;";
					$strCmd		.= " rm -rf \"".$tmpDir->getPathAsString()."\"; ";
					$strCmd		.= " ' & ) > /dev/null 2>&1;";
				
					@exec($strCmd, $rData, $status);
					if ($status != 0) {
						throw new \Exception("Failed to excute shell setup: ".$status, 1111);
					}
				}
			}
			$this->_sessTmp		= $tmpDir;
		}
		return $this->_sessTmp;
	}
}