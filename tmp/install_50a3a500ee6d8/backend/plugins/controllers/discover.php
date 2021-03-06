<?php
/**
 * @package AkeebaBackup
 * @copyright Copyright (c)2009-2012 Nicholas K. Dionysopoulos
 * @license GNU General Public License version 3, or later
 * @since 3.2
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * Archive discovery view - Controller
 */
class AkeebaControllerDiscover extends FOFController
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
	
	public function execute($task)
	{
		if(!in_array($task, array('discover','import'))) {
			$task = 'browse';
		}
		parent::execute($task);
	}
	
	/**
	 * Discovers JPA, JPS and ZIP files in the selected profile's directory and
	 * lets you select them for inclusion in the import process.
	 */
	public function discover()
	{
		// CSRF prevention
		if(!$this->csrfProtection) {
			$this->_csrfProtection();
		}
		
		$directory = FOFInput::getString('directory', '', $this->input);

		if(empty($directory)) {
			$url = 'index.php?option=com_akeeba&view=discover';
			$msg = JText::_('DISCOVER_ERROR_NODIRECTORY');
			$this->setRedirect($url, $msg, 'error');
			return;
		}
		
		$model = $this->getThisModel();
		$model->setState('directory', $directory);
		
		parent::display();
	}
	
	/**
	 * Performs the actual import
	 */
	public function import()
	{
		// CSRF prevention
		if(!$this->csrfProtection) {
			$this->_csrfProtection();
		}
		
		$directory = FOFInput::getString('directory', '', $this->input);
		$files = FOFInput::getArray('files', array(), $this->input);
		
		if(empty($files)) {
			$url = 'index.php?option=com_akeeba&view=discover';
			$msg = JText::_('DISCOVER_ERROR_NOFILESSELECTED');
			$this->setRedirect($url, $msg, 'error');
			return;
		}
		
		$model = $this->getThisModel();
		$model->setState('directory', $directory);
		foreach($files as $file)
		{
			$model->import($file);
		}
		$url = 'index.php?option=com_akeeba';
		$msg = JText::_('DISCOVER_LABEL_IMPORTDONE');
		$this->setRedirect($url, $msg);
	}
}