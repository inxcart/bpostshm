<?php
/**
* pdf file manager class Prestashop module helper
*  
* @author    Serge <serge@stigmi.eu>
* @version   0.5.0
* @copyright Copyright (c), Eontech.net. All rights reserved.
* @license   BSD License
*/

if (!defined('_PS_VERSION_'))
	exit;

class EontechPdfManager extends EontechBaseObject
{
	const ERR_INITIALIZE = 3;
	const ERR_ACCESS = 4;
	
	private $_links = array();

	public function __construct($module_name = '', $pdf_dir = 'pdf', $raise_exceptions = false)
	{
		parent::__construct(true, $raise_exceptions);

		$pdf_dir = $this->prependSeparator($pdf_dir);
		if (!is_dir(_PS_MODULE_DIR_.$module_name))
			$this->setError('Cannot locate module '.$module_name.' directory');
		elseif ($this->_active_dir = $this->getPath(_PS_MODULE_DIR_.$module_name.$pdf_dir))
			$this->_active_url = _PS_BASE_URL_._MODULE_DIR_.$module_name.$pdf_dir;
		else
			$this->setError('Cannot create pdf folder.');

	}

	public function setActiveFolder($sub_path)
	{
		$sub_path = $this->prependSeparator($sub_path);
		if ($this->_active_dir = $this->getPath($this->_active_dir, $sub_path))
			$this->_active_url = $this->_active_url.$sub_path;
		else
			$this->setError('Cannot create pdf folder '.$sub_path);

		$this->_links = array();
		$pdf_files = glob($this->_active_dir.'/*.pdf');
		foreach ($pdf_files as $file)
			$this->_links[] = $this->_active_url.$this->prependSeparator(basename($file));

	}

	public function writePdf($bytes)
	{
		if ($this->hasError() || !isset($bytes))
			return false;

		// filename is the next index
		$file_name = (string)($this->count() + 1).'.pdf';
		$file_path = $this->_active_dir.DIRECTORY_SEPARATOR.$file_name;

		if ($fp = fopen($file_path, 'w'))
		{
			fwrite($fp, $bytes);
			fclose($fp);
			$this->_links[] = $this->_active_url.DIRECTORY_SEPARATOR.$file_name;
		}
		else
			$this->setError('Error opening pdf file for writing', self::ERR_ACCESS);

	}

	protected function prependSeparator($dir)
	{
		return DIRECTORY_SEPARATOR == Tools::substr($dir, 0, 1) ? $dir : DIRECTORY_SEPARATOR.$dir;
	}

	protected function getPath($base_dir, $sub_dirs = '')
	{
		// ex. getPath('module/bpost/pdf', 'today/ref123')
		$path = empty($sub_dirs) ? $base_dir : $base_dir.$sub_dirs;
		if ($path = $this->validPath($path))
			return $path;

		$path = $base_dir;
		$sub_dirs = explode('/', $sub_dirs);
		foreach ($sub_dirs as $sub_dir)
		{
			$path .= empty($sub_dir) ? '' : DIRECTORY_SEPARATOR.$sub_dir;
			if (!is_writable($path))
				mkdir($path, 0755);

		}

		return $this->validPath($path, true);
	}

	protected function validPath($path = '', $strict = false)
	{
		if (!empty($path) && is_writable($path))
		{
			$index_file = $path.DIRECTORY_SEPARATOR.'index.php';
			if (!file_exists($index_file))
				@copy(dirname(realpath(__FILE__)).DIRECTORY_SEPARATOR.'index.php', $index_file);

			return $path;
		}
		else
		{
			if ($strict)
				$this->setError('Path: '.$path.' is not accessible.', self::ERR_ACCESS);

			return false;
		}
	}

	public static function mergedLinksCache($links, $outfile = '')
	{
		require_once('Xpdf/fpdf/fpdf.php');
		require_once('Xpdf/fpdi/fpdi.php');

		$return = array();
		$errors = array();
		$_BASE_PATH_ = $_SERVER['DOCUMENT_ROOT'];

		if (isset($links['error']))
		{
			$errors = $links['error'];
			unset($links['error']);
		}
		$cache_dir = defined('_PS_CACHE_DIR_') ? _PS_CACHE_DIR_ : _PS_ROOT_DIR_.'/cache/';
		$fpdi = new FPDI;

		try {
			foreach ($links as $index => $link)
			{
				//$filepath = str_replace(_PS_BASE_URL_._MODULE_DIR_, _PS_ROOT_DIR_.'/modules/', $link);
				$filepath = str_replace(_PS_BASE_URL_, $_BASE_PATH_, $link);
				$page_count = $fpdi->setSourceFile($filepath);
				for ($p = 1; $p <= $page_count; $p++)
				{
					$tpl = $fpdi->importPage($p);
					$size = $fpdi->getTemplateSize($tpl);
					$orient = $size['h'] > $size['w'] ? 'P' : 'L';
					$fpdi->AddPage($orient, array($size['w'], $size['h']));
					$fpdi->useTemplate($tpl);
				}
			}

			if (empty($outfile))
				$outfile = $cache_dir.'bpost_labels.pdf';
			if ('' == $fpdi->Output($outfile, 'F'))
				$return[] = str_replace($_BASE_PATH_, _PS_BASE_URL_, $outfile);
			else
				$errors[] = 'failed to merge pdf labels';

		} catch (Exception $e) {
			$errors[] = 'unable to merge pdf labels';

		}		
		
		if (!empty($errors))
			$return['error'] = $errors;

		return $return;
	}

	public static function mergedLinks($links, $outfile = '')
	{
		$return = array();
		$errors = array();
		
		if (isset($links['error']))
		{
			$errors = $links['error'];
			unset($links['error']);
		}
		
		if (!empty($links))
		{
			require_once('Xpdf/fpdf/fpdf.php');
			require_once('Xpdf/fpdi/fpdi.php');

			$modules_url = _PS_BASE_URL_._MODULE_DIR_;
			$module_pdf_dir = dirname(dirname($links[0]));
			$module_pdf_dir = str_replace($modules_url, '', $module_pdf_dir).DIRECTORY_SEPARATOR;
			if (empty($outfile))
			{
				$pdf_dir = _PS_MODULE_DIR_.$module_pdf_dir;
				array_map('unlink', glob($pdf_dir."*.pdf"));
				$outfile = $pdf_dir.Tools::passwdGen(12, 'NO_NUMERIC').'.pdf';
			}

			$fpdi = new FPDI;
			try {
				foreach ($links as $index => $link)
				{
					$filepath = str_replace($modules_url, _PS_MODULE_DIR_, $link);
					$page_count = $fpdi->setSourceFile($filepath);
					for ($p = 1; $p <= $page_count; $p++)
					{
						$tpl = $fpdi->importPage($p);
						$size = $fpdi->getTemplateSize($tpl);
						$orient = $size['h'] > $size['w'] ? 'P' : 'L';
						$fpdi->AddPage($orient, array($size['w'], $size['h']));
						$fpdi->useTemplate($tpl);
					}
				}

				if ('' == $fpdi->Output($outfile, 'F'))
					$return[] = str_replace(_PS_MODULE_DIR_, $modules_url, $outfile);
				else
					$errors[] = 'failed to merge pdf labels';

			} catch (Exception $e) {
				$errors[] = 'unable to merge pdf labels';

			}
		}
		
		if (!empty($errors))
			$return['error'] = $errors;

		return $return;
	}

	public function count()
	{
		return count($this->_links);
	}

	public function hasPrints()
	{
		return (bool)$this->count();
	}

	public function getLinks()
	{
		return $this->_links;
	}

	protected function setError($msg, $severity = self::ERR_INITIALIZE)
	{
		parent::setError($msg, $severity);
	}
}