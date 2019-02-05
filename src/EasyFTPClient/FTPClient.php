<?php declare(strict_types=1);
namespace laudirbispo\EasyFTP;
/**
 * Copyright (c) Laudir Bispo  (laudirbispo@outlook.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     (c) Laudir Bispo  (laudirbispo@outlook.com)
 * @version       1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @package       laudirbispo\FTP
 */

class FTPClient 
{
    const ASCII = FTP_ASCII;
	const TEXT = FTP_TEXT;
	const BINARY = FTP_BINARY;
	const IMAGE = FTP_IMAGE;
	const TIMEOUT_SEC = FTP_TIMEOUT_SEC;
	const AUTOSEEK = FTP_AUTOSEEK;
	const AUTORESUME = FTP_AUTORESUME;
	const FAILED = FTP_FAILED;
	const FINISHED = FTP_FINISHED;
	const MOREDATA = FTP_MOREDATA;
    
    const DEFAULT_PORT = 21;
    
    // Connection timeout in seconds
    const DEFAULT_TIMEOUT = 90;
    
    private $conn = null;
    
    private $host;
    
    private $port;
    
    private $user;
    
    private $password;
    
    private $timeout;
    
    private $ssl = false;
    
    private $reconnectionAttempts = 0;
    
    /**
     * Passive mode ON, OFF 
     *
     * @var bool
     */
    private $passiveMode = false;
    
    private $typeColors = [
        'notice' => '#8d9ea7',
        'warning' => '#ff9800',
        'info' => '#1e88e5',
        'error' => '#f44336',
        'success' => '#4caf50'
    ];
    
    private $log = [];
    
    public function __construct()
    {
        if (!extension_loaded('ftp')) 
            throw new Exceptions\FTPException("FTP Extension is not loaded on the server!");
        
    }
    
    public function connect(
        string $host, 
        int $port = self::DEFAULT_PORT, 
        int $timeout = self::DEFAULT_TIMEOUT, 
        bool $ssl = false
    ) : self {
        
        try {
            
            if ($ssl) {
                $conn = @ftp_ssl_connect($host, $port, $timeout); 
                $this->recordLog('success', "Server connected");
            } else {
                $conn = @ftp_connect($host, $port, $timeout);
                $this->recordLog('success', "Server connected by SSL");
            }    
            
        } catch(\Excption $e) {
            throw new Exceptions\FTPException($e->getMessage());
        }
        
        if (false !== $conn) {
            $this->conn = $conn;
            $this->host = $host;
            $this->port = $port;
            $this->timeout = $timeout;
            $this->ssl = $ssl;
            $this->reconnectionAttempts = 0;
        } else {
            throw new Exceptions\NoConnection("Could not connect to server");
        } 
        return $this;
    }
    
    public function sslConnection(
        string $host, 
        int $port = self::DEFAULT_PORT, 
        int $timeout = self::DEFAULT_TIMEOUT
    ) : bool  {
        
        $this->connect($host, $port, $timeout, true);
    }
    
    public function login(string $user, string $password) : self 
    {  
        if (!@ftp_login($this->conn, $user, $password)) {
            $this->recordLog('error', "Login failed"); 
            throw new Exceptions\NoConnection("Login failed"); 
        } 
        $this->user = $user;
        $this->password = $password;
        return $this;
    }
    
    public function close() 
    {
        if ($this->conn) 
            @ftp_close($this->conn);
     
        $this->conn = null;
        $this->recordLog('notice', "Connection closed");
    }
    
    public function reconnect() : bool 
    {
        // Check that you have not exceeded the limit of re-connection attempts
        if ($this->reconnectionAttempts > 3) {
            $this->recordLog('error', "Limit of re-connection attempts exceeded"); 
            return false;
        }
        // Force the end connection 
        $this->close();
        
        // New connection
        $this->connect($this->host, $this->port, $this->timeout, $this->ssl);
        // Login
        $this->login($this->user, $this->password);
        if ($this->hasConnection()) {
            $this->recordLog('success', "Reconnected"); 
            return true;
        } else {
            $this->recordLog('error', "Reconnecion failed");
            $this->reconnectionAttempts += 1;
        }
        
        
    }
    
    /**
     * Active passive mode
     */
    public function passiveModeOn() : self 
    {   
        if (@ftp_pasv($this->conn, true)) {
            $this->passiveMode = true;
            $this->recordLog('notice', "Passive mode activated");
        }  else {
            $this->passiveMode = false;
            $this->recordLog('notice', "Passive mode disabled");
        }
            
        return $this;
    }
    
    /**
     * Disables passive mode
     */
    public function passiveModeOff() : self 
    {
        if (@ftp_pasv($this->conn, false)) {
            $this->passiveMode = false;
            $this->recordLog('notice', "Passive mode disabled");
        } else {
            $this->passiveMode = true;
            $this->recordLog('notice', "Passive mode activated");
        }
            
        return $this;
    }
    
    public function getCurrentDir() : ?string 
    {
        $currentDir = @ftp_pwd($this->conn);
        if (!$currentDir) {
            $this->recordLog('error', "Could not get current directory");
            return null;
        } else {
            return $currentDir;
        }
    }
    
    /**
     * Create new Directory
     *
     * @return mixed - false or new directory name
     */
    public function createDir(string $dir, $mode = 0644) 
    { 
        if (substr($dir, 0, 1) != '/') {
            $dir = '/'.$dir;
        }
        $parts = explode('/', $dir);
        $path = '';
        
        while (!empty($parts)) {
            $path .= array_shift($parts);
            if (!empty($path)) {
                if ($this->isDir($path)) {
                    $this->recordLog('notice', "Directory \"{$path}\" already exists");
                    $this->doChmod($path, $mode);
                    $path .= '/';
                    continue;
                } elseif (@ftp_mkdir($this->conn, $path)) {
                    $this->recordLog('success', "New directory \"{$path}\", created");
                    $this->doChmod($path, $mode);
                    $path .= '/';
                } else {
                    if ($this->isDir($path)) {
                        $this->recordLog('info', "Diretory already exists \"{$path}\"");
                        $this->doChmod($path, $mode);
                    } else {
                        $this->recordLog('error', "Fail to create directory \"{$path}\", path invalid");
                    }
                    
                }
                
            }
        }
        return $path;
    }
    
    public function goToDir(string $dir) : bool 
    {
        if ($this->isDir($dir)) {
            if (@ftp_chdir($this->conn, $dir)) {
                $this->recordLog('notice', "Went to directory \"{$dir}\"");
                return true;
            } else {
                $this->recordLog('notice', "Attempting to go to \"{$dir}\" directory failed");
                return false;
            }
        }
        $this->recordLog('notice', "The \"{$dir}\" directory does not exist");
        return false;
    }
    
    public function backDir() : bool 
    {
        if (@ftp_cdup($this->conn)) {
            $this->recordLog('notice', "Return a directory above");
            return true;
        } else {
            $this->recordLog('notice', "Attempt to return directory failed");
            return false;
        }
    }
    
    public function upload(string $localFile, $remoteFile, $chmod = 0644, $mode = self::ASCII) : bool 
    {
        
        if (@ftp_put($this->conn, $remoteFile, $localFile, $mode)) {
            $this->recordLog('success', "Upload completed, new file \"{$remoteFile}\"");
            $this->doChmod($remoteFile, $chmod);
            return true;
        } else {
            $this->recordLog('error', "Upload failed \"{$localFile}\"");
            if (!$this->passiveMode) {
                $this->recordLog('info', "Upload failed. Try to use passive mode");
            } else {
               $this->recordLog('error', "Upload failed \"{$localFile}\""); 
            }
            return false;
        }
    }
    
    public function download(string $remoteFile, string $saveAs, $mode = self::BINARY) : bool 
    {
        if ($this->isDir($remoteFile)) {
            $this->recordLog('warning', "Can not download a directory");
            return false;
        }
        
        if (!$this->isFile($remoteFile)) {
            $this->recordLog('error', "\"{$remoteFile}\", not a valid file or does not exist on the server");
            return false;
        }
        
        if (@ftp_get($this->conn, $saveAs, $remoteFile, $mode)) {
            $this->recordLog('success', "\"{$remoteFile}\", file downloaded, saved as, \"{$saveAs}\"");
            return true;
        } else {
            $this->recordLog('error', "\"{$remoteFile}\", file not downloaded");
            return false;
        }
        
    }
    
    public function rename(string $oldName, string $newName) : bool 
    {
        if (@ftp_rename($this->conn, $oldName, $newName)) {
            $this->recordLog('success', "\"{$oldName}\", renamed to \"{$newName}\"");
            return true;
        } else {
            $this->recordLog('error', "Could not rename the file \"{$oldName}\"");
            return false;
        }
    }
    
    public function move(string $oldName, string $newName) : bool 
    {
        if (@ftp_rename($this->conn, $oldName, $newName)) {
            $this->recordLog('success', "\"{$oldName}\", moved to \"{$newName}\"");
            return true;
        } else {
            $this->recordLog('error', "Could not move the file \"{$oldName}\"");
            return false;
        }
    }
    
    public function delete($path, bool $recursive = false) 
    {
        if (is_array($path)) {
            foreach ($path as $key => $value) {
                $this->delete($value, $recursive);
                // Delete directorys
                if ($this->isDir($key)) {
                    $this->doDelete($key);  
                }
            }
        } else {
            if ($this->isDir($path) && $recursive) {
                $list = $this->listDir($path, true);
                $this->delete($list, $recursive);
            } 
            // Delete first dir
            $this->doDelete($path);
            
        }
        
    }
    
    private function doDelete(string $file) : bool 
    {
        if ($this->isDir($file)) {
            $del = @ftp_rmdir($this->conn, $file);
        } else {
            $del = @ftp_delete($this->conn, $file);
        }
        if ($del) {
            $this->recordLog('success', "\"{$file}\" deleted successfully");
            return true;
        } else {
            $this->recordLog('error', "Could not delete \"{$file}\"");
            return false;
        }
    }
    
    public function getFileSize(?string $file) : int
    {
        $size = @ftp_size($this->conn, $file);
        if ($size === -1) {
            $this->recordLog('notice', "\"{$file}\", is not a file");
            return 0;
        } else {
            return $size;
        }
    }
    
    public function getLastModificationFile(string $file) : ?int
    {
        if ($this->isDir($file)) {
            $this->recordLog('warning', "Can not get directory modification time");
            return null;
        }
        
        $lastModification = ftp_mdtm($this->conn, $file);
        if ($lastModification === -1) 
            return null;
        else 
            return $lastModification;
        
    }
    
    public function listDir(?string $dir = null, bool $recursive = false, int $loop = 0) : array 
    {
        if (null === $dir) 
            $dir = $this->getCurrentDir();
        
        $items = []; 
        
        if (!$this->isDir($dir)) {
            $this->recordLog('warning', "\"{$dir}\" is not a directory or is not readable");
            return $items;
        }

        $list = @ftp_nlist($this->conn, $dir);
        
        if (!$list || count($list) <= 0) {
            return $items;
        } 
        foreach ($list as $item) {
            if ($this->isDir($item) && $recursive) {
                $items[$item] = $this->listDir($item, true, 1);
            } else {
                $items[] = $item; 
            }
               
        }
        if ($loop === 0)
            $this->recordLog('notice', "Linsting \"{$dir}\"");
        
        return $items;
    }
    
    public function chmod($path, $mode, bool $recursive = false) 
    {
        if (is_array($path) && $recursive) {
            foreach ($path as $key => $value) {
                if ($this->isDir($key)) {
                    $this->doChmod($key, $mode);
                    $ls = $this->listDir($key, false);
                    $this->chmod($ls, $mode, $recursive);
                } else {
                  $this->chmod($value, $mode, true);  
                }  
            }
            
        } else {
            if ($this->isDir($path) && $recursive) {
                // Change this directory first
                $this->doChmod($path, $mode);
                $list = $this->listDir($path, false);
                $this->chmod($list, $mode, true);
            } else {
              // Change first dir
                $this->doChmod($path, $mode);  
            }
            
        }
    }
    
    /**
     * Sets the permissions on the specified remote file
     *
     * @param string $path - The remote file or dir
     * @param int $mode - The new permissions, given as an octal value.
     */
    private function doChmod(string $path, $mode) : bool 
    {
        
        if (@ftp_chmod($this->conn, $mode, $path)) {
            $octal = decoct($mode);
            $this->recordLog('success', "\"{$path}\" chmod changed to \"{$octal}\"");
            return true;
        } else {
            $this->recordLog('error', "\"{$path}\" chmod unchaged");
            return false;
        }
    }
    
    public function isDir($dir) : bool 
    {
        if (!is_string($dir)) 
            return false;
        $currentDir = $this->getCurrentDir();
        
        if (@ftp_chdir($this->conn, $dir)) {
            @ftp_chdir($this->conn, $currentDir);
            return true;
        }
        return false;
    } 
    
    public function isFile($file) 
    {
        if (!is_string($file)) 
            return false;
        return (@ftp_size($this->conn, $file) === -1) ? false : true;
    }
    
    public function hasConnection() : bool 
    {
        return is_array(@ftp_nlist($this->conn, '.'));
    }
    
    private function checkConnection() 
    {
        if (!$this->hasConnection()) {
            $this->reconnect();
        }
    }
    
    public function command(string $command) : bool 
    {
        if (ftp_raw($this->conn, $command)) {
            $this->recordLog('info', "Command sent \"{$command}\" executed");
            return true;
        } else {
            $this->recordLog('error', "Command \"{$command}\" not executed");
            return false;
        }
    }
    
    public function message(string $text) : bool 
    {
        if (ftp_raw($this->conn, "MSG $text")) {
            $this->recordLog('info', "Message \"{$text}\" sent to server");
            return true;
        } else {
            $this->recordLog('error', "Message  \"{$text}\" not sent");
            return false;
        }
    }
    
    public function hasErrors() : bool 
    {
        foreach ($this->log as $key => $value) {
            if ($value['type'] === 'error')
                return true;
            else
                continue;
        }
        return false;
    }
    
    public function getErrors() : ?array
    {
        $errors = [];
        foreach ($this->log as $key => $value) {
            if ($value['type'] === 'error') {
                $errors[] = $value['message'];
            }
        }
        return $errors;
    }
    
    private function recordLog(string $type = 'notice', string $message) : void 
    {
        $this->log[] = [
            'type' => $type,
            'time' => time(),
            'message' => $message
        ];
    }
    
    public function getLog() : array 
    {
        return $this->log;
    }
     
    public function debug() : string 
    {
        $format = "<li style='color:%s'>[%s] Status: %s</li>";
        $html = '<ul style="list-style:none">';
        foreach ($this->log as $key => $log) {
            $html .= sprintf(
                $format, 
                $this->typeColors[$log['type']], 
                date( "m/d/Y H:i:s", $log['time']),
                $log['message']
            );
        }
        $html .= '</ul>';
        return $html;
    }
    
    public static function explodePathName($path) : ?string 
    {
        if (is_string($path)) {
            $name = explode('/', $path);
            return end($name);
        } else {
            return null;
        }
    }
    
    public function __call($func, $a)
    {
        if (strstr($func, 'ftp_') !== false && function_exists($func)) {
            array_unshift($a,$this->conn);
            return call_user_func_array($func,$a);
        } else {
            // replace with your own error handler.
            die("\"$func\" is a not valid FTP function");
        }
    } 
    
    public function __destruct() 
    {
        $this->close();
    }
    
}