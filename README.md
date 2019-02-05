
### A simple Easy FTPClient class

> I have always chosen to build simple classes and closer to the actual use. This class represents exactly that, simple and functional.

## Installation

Install the latest version with

```bash
$ composer require laudirbispo/easy-ftp
```

## Basic Usage

```php
<?php

  use laudirbispo\EasyFTP\FTPClient;
  use laudirbispo\EasyFTP\Exceptions\{FTPException, NoConnection};

    $FTP = new FTPClient();
    $FTP->connect(string $host, int $port, int $timeout, bool $ssl);
    // or
    $FTP->sslConnection(string $host, int $port, int $timeout);
    $FTP->login(string $user, string $pass);
    
    /**
     * Turns on or off passive mode. 
     * In passive mode, data connections are initiated by the client, rather than by the server.
     * @return bool
     */
    $FTP->passiveModeOn();
    $FTP->passiveModeOff();
    
    /**
     * Get current directory
     * @return string|null
     */
     $FTP->getCurrentDir();
     
    /**
     * Moves to the specified directory
     * @return bool
     */
     $FTP->goToDir($upUdir);
     
    /**
     * Back to the above directory
     * @return bool
     */
     $FTP->backDir();
     
    /**
     * Creates a new directory
     * @param $chmod octal - default is 0644
     * @return bool
     */
     $FTP->createDir(string $dir, 0755);
     
    /**
     * Move upload file
     * @param string $localFile
     * @param string $remoteFile
     * @param $chmod octal - default is 0644
     * @return bool
     */
     $FTP->upload(string $localFile, string $remoteFile, $chmod);
     
    /**
     * Download a file
     * @param string $remoteFile
     * @param string $localFile
     * @return bool
     */
     $FTP->download(string $remoteFile, string $localFile);
     
    /**
     * Returns the file size
     * @param string $localFile
     * @return int - 0 if it does not exist or is not a file
     */
     $FTP->getFileSize(string $remoteFile);
     
    /**
     * List directory
     * @param string $dir
     * @param bool $recursive
     * @return array
     */
     $FTP->listDir(string $dir, bool $recursive);
     
    /**
     * Rename or move file or rename folder
     * @param string $oldName
     * @param string $newName
     * @return bool
     */
     $FTP->rename(string $oldName, string $newName);
     
    /**
     * Move dir and files 
     * @param string $oldName
     * @param string $newName
     * @return bool
     */
     $FTP->move(string $oldLocal, string $newLocal);
     
    /**
     * Change chmod
     * @param string $path - file or dir
     * @param $chmod octal - default is 0644
     * @param boll $recursive - aplly to subdirectories
     * @return bool
     */
     $FTP->chmod(string $path, $chmod, bool $recursive);
     
     /**
     * Delete files and dirs
     * @param string $path - file or dir
     * @param boll $recursive - aplly to subdirectories
     * @return bool
     */
     $FTP->delete(string $path, bool $recursive);
     
    /**
     * Get the timestamp of the last modification of the file
     * @param string $file- file
     * @return int timestamp
     */
     $FTP->getLastModificationFile(string $file);
     
     /**
     * More
     * @return bool
     */
     $FTP->isDir(string $directory);
     $FTP->isFile(string $file);
     
    /**
     * Send an arbitrary command to an FTP server
     * @param string $command 
     * @return bool
     */
     $FTP->command(string $command);
     // Example
     $FTP->command("MSG Wello Word");
     
    /**
     * Send a message to server
     * @param string $message 
     * @return bool
     */
     $FTP->message(string $message);
     
    /**
     * Returns only the errors that occurred
     * @return array
     */
     $FTP->getErrors();
     
    /**
     * Checks for errors
     * @return bool
     */
     $FTP->hasErrors();
     
     /**
     * Returns a list formatted with everything that happened
     * @return html string formatted
     */
     $FTP->debug();
     
     // Debug example
    [02/05/2019 18:28:28] Status: Server connected by SSL
    [02/05/2019 18:28:28] Status: Passive mode activated
    [02/05/2019 18:28:28] Status: New directory "example", created
    [02/05/2019 18:28:28] Status: "example" chmod changed to "755"
    [02/05/2019 18:28:29] Status: "/example" deleted successfully
    [02/05/2019 18:28:29] Status: Command sent "MSG lorem ipsum dolor" executed
    [02/05/2019 18:28:29] Status: Message "Hello Word!" sent to server
    [02/05/2019 18:28:29] Status: Connection closed
    
    /**
     * Check if there is a connection
     * @return bool
     */
    $FTP->hasConnection();
    
    /**
     * End current connection
     */
    $FTP->close();
    
    /**
     * Recconect
     */
    $FTP->reconnect();



```

### Author

Laudir Bispo - <laudirbispo@outlook.com> - <https://twitter.com/laudir_bispo><br />

### License

Easy FTPClient is licensed under the MIT License - see the `LICENSE` file for details
