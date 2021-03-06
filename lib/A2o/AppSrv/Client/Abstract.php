<?php
/**
 * a2o framework
 *
 * LICENSE
 *
 * This source file is subject to the GNU GPLv3 license that is
 * bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl.html
 *
 * @category   A2o
 * @package    A2o_AppSrv
 * @author     Bostjan Skufca <bostjan@a2o.si>
 * @copyright  Copyright (c) 2009 Bostjan Skufca (http://a2o.si)
 * @license    http://www.gnu.org/licenses/gpl.html     GNU GPLv3
 */


/**
 * @see A2o_AppSrv_Client_Exception
 */
require_once 'A2o/AppSrv/Client/Exception.php';


/**
 * Standalone preforking PHP application server framework
 *
 * @category   A2o
 * @package    A2o_AppSrv
 * @subpackage A2o_AppSrv_Client
 * @author     Bostjan Skufca <bostjan@a2o.si>
 * @copyright  Copyright (c) 2009 Bostjan Skufca (http://a2o.si)
 * @license    http://www.gnu.org/licenses/gpl.html     GNU GPLv3
 */
abstract class A2o_AppSrv_Client_Abstract
{
    /**
     * Parent instance object
     */
    protected $___parent = NULL;

    /**
     * Stream handle
     */
    public $stream = NULL;

    /**
     * Remote IP address
     */
    public $address = NULL;

    /**
     * Remote TCP port
     */
    public $port = NULL;

    /**
     * For readLine method
     *
     * Number of consecutive empty reads, max, timeout in seconds
     * FreeBSD workaround. Whole timeout is 5 seconds (Max * delay)
     */
    protected $__nrConsecutiveEmptyReadsMax     =   100;
    protected $__delayBetweenEmptyReads         = 50000;   // Micro seconds



    /**
     * Constructor
     *
     * @param    parent     Parent object
     * @param    resource   Stream handle
     * @param    string     Remote IP address
     * @param    integer    Remote TCP port
     * @return   void
     */
    public function __construct ($parent, $stream, $address, $port)
    {
    	// Parent instance object
    	$this->___parent = $parent;

    	// Client details
	$this->stream  = $stream;
	$this->address = $address;
	$this->port    = $port;
    }



    /**
     * Destructor
     *
     * @return   void
     */
    public function __destruct ()
    {
	if ($this->stream !== NULL) {
	    $this->closeConnection();
	}
    }



    /**
     * Reads given length from client stream handle
     *
     * Method is blocking until that many characters are received from client
     *
     * @param    integer   Number of bytes to read
     * @return   string
     */
    public function read ($bytesToRead)
    {
	$this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."($bytesToRead)", 9);

        $content = '';
        while (strlen($content) < $bytesToRead) {
	    $r = fread($this->stream, $bytesToRead - strlen($content));
	    if ($r === false) throw new A2o_AppSrv_Client_Exception('Unable to read from client');
            $content .= $r;
        }

	return $content;
    }



    /**
     * Reads single line from client stream handle
     *
     * Reads max 16384 characters, terminates reading on \n (and \r?)
     *
     * @return   string
     */
    public function readLine ()
    {
	$this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

        $nrConsecutiveEmptyReads = 0;
        $line = '';
        while ($nrConsecutiveEmptyReads < $this->__nrConsecutiveEmptyReadsMax) {
            $nrConsecutiveEmptyReads++;

            // Try to read	
	    $r = fgets($this->stream, 16384);
	    if ($r === false) {
                $this->_checkStreamForError();
                $r = '';
            } else {
	        $line .= $r;
            }

            // If there is newline present in received stuff, return whole line
            if (preg_match('/\n$/', $r)) {
                return $line;
            }

            // Now sleep for allocated time
            usleep($this->__delayBetweenEmptyReads);
        }

        // If empty content, bump the empty reads counter
        if ($line == '') {
            $this->__nrConsecutiveEmptyReads++;
        } else {
            $this->__nrConsecutiveEmptyReads = 0;
        }

        // If too many empty reads throw an exception
        // Basically FreeBSD workaround (Linux returns 'Connection reset by peer above')
        $this->_checkStreamForError();
        throw new A2o_AppSrv_Client_Exception('Timeout reading line from client');
    }



    /**
     * Write data to client
     *
     * @param    string   Data to send to client
     * @return   void
     */
    public function write ($data)
    {
	$this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

	$r = fwrite($this->stream, $data, strlen($data));
	if ($r === false) throw new A2o_AppSrv_Client_Exception('Unable to send data to client');
    }



    /**
     * Close client connection
     *
     * Closes the client connection and resets the client data
     *
     * @return   void
     */
    public function closeConnection ()
    {
	$this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

	// Close the connection
	if (is_resource($this->stream)) {
            fclose($this->stream);
            $this->stream = NULL;
	}
    }



    /**
     * Analyse stream error
     *
     * Throws appropriate exception
     *
     * @return   void
     */
    public function _checkStreamForError ()
    {
        $metaData = stream_get_meta_data($this->stream);
        if ($metaData['timed_out'] == 1) throw new A2o_AppSrv_Client_Exception('Connection timeout');
        if ($metaData['eof']       == 1) throw new A2o_AppSrv_Client_Exception('Connection reset by peer');
    }



    /**
     * Reads the request from client
     *
     * @return   void
     */
    abstract public function readRequest ();



    /**
     * Writes the response to client
     *
     * @return   void
     */
    abstract public function writeResponse ($response);



    /**
     * Writes error to the client
     *
     * @return   void
     */
    abstract public function writeError ($errorMessage);



    /**
     * Parent method wrapper for debug messages
     */
    protected function _debug ($message, $importanceLevel=5)
    {
    	$this->___parent->__debug($message, $importanceLevel);
    }



    /**
     * Parent method wrapper for debug messages
     */
    protected function _debug_r ($var, $importanceLevel=5)
    {
    	$this->___parent->__debug_r($var, $importanceLevel);
    }


    /**
     * Parent method wrapper for log messages
     */
    protected function _log ($message)
    {
    	$this->___parent->__log($message);
    }



    /**
     * Parent method wrapper for warning messages
     */
    protected function _warning ($message)
    {
    	$this->___parent->__warning($message);
    }



    /**
     * Parent method wrapper for error messages
     */
    protected function _error ($message)
    {
    	$this->___parent->__error($message);
    }
}
