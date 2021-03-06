<?php
/**
 * @package AkeebaBackup
 * @copyright Copyright (c)2009-2012 Nicholas K. Dionysopoulos
 * @license GNU General Public License version 3, or later
 *
 * @since 3.2
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * The controller to handle actions against remote files
 * @author nicholas
 */
class AkeebaControllerRemotefiles extends FOFController
{
	public function  __construct($config = array()) {
		parent::__construct($config);
		// Access check, Joomla! 1.6 style.
		$user = JFactory::getUser();
		if (!$user->authorise('akeeba.download', 'com_akeeba')) {
			$this->setRedirect('index.php?option=com_akeeba');
			return JError::raiseWarning(403, JText::_('JERROR_ALERTNOAUTHOR'));
			$this->redirect();
		}
		$base_path = JPATH_COMPONENT_ADMINISTRATOR.'/plugins';
		$model_path = $base_path.'/models';
		$view_path = $base_path.'/views';
		$this->addModelPath($model_path);
		$this->addViewPath($view_path);
	}
	
	/**
	 * This controller does not support a default task, thank you.
	 */
	public function display($cachable = false, $urlparams = false)
	{
		JError::raiseError(500, 'Invalid task');
		return false;
	}
	
	/**
	 * Lists the available remote storage actions for a specific backup entry
	 */
	public function listactions($cachable = false, $urlparams = false)
	{
		// List available actions
		$id = $this->getAndCheckId();
		$model = $this->getThisModel();
		$model->setState('id', $id);
		
		if($id === false) {
			JError::raiseError(500, 'Invalid ID');
			return false;
		}
		
		parent::display(false, false);
	}
	
	
	/**
	 * Fetches a complete backup set from a remote storage location to the local (server)
	 * storage so that the user can download or restore it.
	 */
	public function dltoserver()
	{
		// Get the parameters
		$id = $this->getAndCheckId();
		$part = FOFInput::getInt('part', -1, $this->input);
		$frag = FOFInput::getInt('frag', -1, $this->input);
		
		// Check the ID
		if($id === false) {
			$url = 'index.php?option=com_akeeba&view=remotefiles&tmpl=component&task=listactions&id='.$id;
			$this->setRedirect($url, JText::_('REMOTEFILES_ERR_INVALIDID'), 'error');
			return;
		}

		$model = $this->getThisModel();
		$model->setState('id',		$id);
		$model->setState('part',	$part);
		$model->setState('frag',	$frag);
		
		$result = $model->downloadToServer();
		
		if($result['finished']) {
			$url = 'index.php?option=com_akeeba&view=remotefiles&tmpl=component&task=listactions&id='.$id;
			$this->setRedirect($url, JText::_('REMOTEFILES_LBL_JUSTFINISHED'));
			return;
		} elseif($result['error']) {
			$url = 'index.php?option=com_akeeba&view=remotefiles&tmpl=component&task=listactions&id='.$id;
			$this->setRedirect($url, $result['error'], 'error');
			return;
		} else {
			parent::display(false, false);
		}
	}
	
	/**
	 * Downloads a file from the remote storage to the user's browsers
	 */
	public function dlfromremote()
	{
		$id = $this->getAndCheckId();
		$part = FOFInput::getInt('part', 0, $this->input);
		
		if($id === false) {
			$url = 'index.php?option=com_akeeba&view=remotefiles&tmpl=component&task=listactions&id='.$id;
			$this->setRedirect($url, JText::_('REMOTEFILES_ERR_INVALIDID'), 'error');
			return;
		}
		
		$stat = AEPlatform::getInstance()->get_statistics($id);
		$remoteFilename = $stat['remote_filename'];
		$rfparts = explode('://', $remoteFilename);
		$engine = AEFactory::getPostprocEngine($rfparts[0]);
		$remote_filename = $rfparts[1];
		
		$basename = basename($remote_filename);
		$extension = strtolower(str_replace(".", "", strrchr($basename, ".")));
		
		if($part > 0) {
			$new_extension = substr($extension,0,1) . sprintf('%02u', $part); 
		} else {
			$new_extension = $extension;
		}
		
		$filename = $basename.'.'.$new_extension;
		$remote_filename = substr($remote_filename, 0, -strlen($extension)).$new_extension;
		
		if($engine->downloads_to_browser_inline)
		{
			@ob_end_clean();
			@clearstatcache();
			// Send MIME headers
			header('MIME-Version: 1.0');
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			header('Content-Transfer-Encoding: binary');
			switch($extension)
			{
				case 'zip':
					// ZIP MIME type
					header('Content-Type: application/zip');
					break;
	
				default:
					// Generic binary data MIME type
					header('Content-Type: application/octet-stream');
					break;
			}
			// Disable caching
			header('Expires: Mon, 20 Dec 1998 01:00:00 GMT');
			header('Cache-Control: no-cache, must-revalidate');
			header('Pragma: no-cache');
		}
		
		AEPlatform::getInstance()->load_configuration($stat['profile_id']);
		$result = $engine->downloadToBrowser($remote_filename);
		
		if(is_string($result) && ($result !== true) && $result !== false)
		{
			// We have to redirect
			$result = str_replace('://%2F','://', $result);
			@ob_end_clean();
			header('Location: '.$result);
			flush();
			JFactory::getApplication()->close();
		} elseif($result === false ) {
			// Failed to download
			$url = 'index.php?option=com_akeeba&view=remotefiles&tmpl=component&task=listactions&id='.$id;
			$this->setRedirect($url, $engine->getWarning(), 'error');
		}
		
		return;
	}
	
	
	/**
	 * Deletes a file from the remote storage
	 */
	public function delete()
	{
		// Get the parameters
		$id = $this->getAndCheckId();
		$part = FOFInput::getInt('part', -1, $this->input);
		
		// Check the ID
		if($id === false) {
			$url = 'index.php?option=com_akeeba&view=remotefiles&tmpl=component&task=listactions&id='.$id;
			$this->setRedirect($url, JText::_('REMOTEFILES_ERR_INVALIDID'), 'error');
			return;
		}
		
		$model = $this->getThisModel();
		$model->setState('id',		$id);
		$model->setState('part',	$part);
		
		$result = $model->deleteRemoteFiles();
		
		if($result['finished']) {
			$url = 'index.php?option=com_akeeba&view=remotefiles&tmpl=component&task=listactions&id='.$id;
			$this->setRedirect($url, JText::_('REMOTEFILES_LBL_JUSTFINISHEDELETING'));
		} elseif($result['error']) {
			$url = 'index.php?option=com_akeeba&view=remotefiles&tmpl=component&task=listactions&id='.$id;
			$this->setRedirect($url, $result['error'], 'error');
		} else {
			$url = 'index.php?option=com_akeeba&view=remotefiles&tmpl=component&task=delete&id='.$result['id'].'&part='.$result['part'];
			$this->setRedirect($url);
		}

		return;
	}
	
	/**
	 * Gets the stats record ID from the request and checks that it does exist
	 * 
	 * @return bool|int False if an invalid ID is found, the numeric ID if it's valid
	 */
	private function getAndCheckId()
	{
		$id = FOFInput::getInt('id', 0, $this->input);
		
		if($id <= 0) return false;

		$statObject = AEPlatform::getInstance()->get_statistics($id);
		if(empty($statObject) || !is_array($statObject)) return false;

		return $id;
	}
}