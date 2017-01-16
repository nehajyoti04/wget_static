<?php
namespace Drupal\wget_static;

/**
 * FTP Client Class.
 */
class WgetStaticFTPClient {
  private $connectionid;
  private $loginok = FALSE;
  private $messagearray = array();

  /**
   * Constructor.
   */
  public function __construct() {}

  /**
   * Deconstructor.
   */
  public function __destruct() {
    if ($this->connectionid) {
      ftp_close($this->connectionid);
    }
  }

  /**
   * Message logger.
   */
  private function logmessage($message) {
    $this->messagearray[] = $message;
  }

  /**
   * Returns Logged messages.
   */
  public function getmessages() {
    return $this->messagearray;
  }

  /**
   * For establishing connection.
   */
  public function connect($server, $ftpuser, $ftppassword, $ispassive = FALSE) {
    // *** Set up basic connection.
    $this->connectionid = ftp_connect($server);
    // *** Login with username and password.
    $loginresult = ftp_login($this->connectionid, $ftpuser, $ftppassword);
    // *** Sets passive mode on/off (default off).
    ftp_pasv($this->connectionid, $ispassive);
    // *** Check connection.
    if ((!$this->connectionid) || (!$loginresult)) {
      $this->logmessage('FTP connection has failed!');
      $this->logmessage('Attempted to connect to ' . $server . ' for user ' . $ftpuser, TRUE);
      return FALSE;
    }
    else {
      $this->logmessage('Connected to ' . $server . ', for user ' . $ftpuser);
      $this->loginok = TRUE;
      return TRUE;
    }
  }

  /**
   * For creating fresh directory.
   */
  public function makedir($directory) {
    // Removing existing directory.
    if (_wget_static_ftp_directory_exists($this->connectionid, $directory)) {
      if (!_wget_static_ftp_rmdirr($this->connectionid, $directory)) {
        $this->logmessage('Directory "' . $directory . '"could not be deleted on remote server.');
        return FALSE;
      }
    }

    // *** If creating a directory is successful.
    if (ftp_mkdir($this->connectionid, $directory)) {
      $this->logmessage('Directory "' . $directory . '" created successfully');
      return TRUE;
    }
    else {
      // *** ...Else, FAIL.
      $this->logmessage('Failed creating directory "' . $directory . '"');
      return FALSE;
    }

  }

  /**
   * To upload single file.
   */
  public function uploadfile($filefrom, $fileto) {
    // *** Set the transfer mode.
    $asciiarray = array('txt', 'csv', 'html', 'css', 'zip');
    $temp_array = explode('.', $filefrom);
    $extension = end($temp_array);
    if (in_array($extension, $asciiarray)) {
      $mode = FTP_ASCII;
    }
    else {
      $mode = FTP_BINARY;
    }

    // Turn passive mode on.
    ftp_pasv($this->connectionid, TRUE);

    // *** Upload the file.
    $upload = ftp_put($this->connectionid, $fileto, $filefrom, $mode);

    // *** Check upload status.
    if (!$upload) {
      $this->logmessage('FTP upload has failed!');
      return FALSE;
    }
    else {
      $this->logmessage('Uploaded "' . $filefrom . '" as "' . $fileto);
      return TRUE;
    }
  }

  /**
   * Uploads whole directory to remote server.
   */
  public function ftpputall($src_dir, $dst_dir) {
    $conn_id = $this->connectionid;
    $d = dir($src_dir);
    while ($file = $d->read()) {
      // Do this for each file in the directory.
      if ($file != "." && $file != "..") {
        // To prevent an infinite loop.
        if (is_dir($src_dir . "/" . $file)) {
          // Do the following if it is a directory.
          if (!@ftp_chdir($conn_id, $dst_dir . "/" . $file)) {
            // Create directories that do not yet exist.
            ftp_mkdir($conn_id, $dst_dir . "/" . $file);
          }
          // Recursive part.
          ftpputall($conn_id, $src_dir . "/" . $file, $dst_dir . "/" . $file);
        }
        else {
          // Put the files.
          ftp_put($this->connectionid, $dst_dir . "/" . $file, $src_dir . "/" . $file, FTP_BINARY);
        }
      }
    }
    $d->close();
    return TRUE;
  }

}
