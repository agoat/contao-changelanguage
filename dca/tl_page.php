<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight webCMS
 * Copyright (C) 2005 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at http://www.gnu.org/licenses/.
 *
 * PHP version 5
 * @copyright  Felix Pfeiffer : Neue Medien 2008 / Andreas Schempp 2009
 * @author 	   Felix Pfeiffer <info@felixpfeiffer.com>, Andreas Schempp <andreas@schempp.ch>
 * @license	   LGPL
 */


/**
 * Config
 */
$GLOBALS['TL_DCA']['tl_page']['config']['onload_callback'][] = array('tl_page_changelanguage','showSelectbox');
$GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'][] = array('tl_page_changelanguage','resetFallback');
$GLOBALS['TL_DCA']['tl_page']['list']['label']['label_callback'] = array('tl_page_changelanguage', 'addFallbackNotice');


/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_page']['fields']['fallback']['eval']['submitOnChange'] = true;

$GLOBALS['TL_DCA']['tl_page']['fields']['languageMain'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_page']['languageMain'],
	'exclude'                 => true,
	'inputType'               => 'select',
	'options_callback'        => array('tl_page_changelanguage', 'getFallbackPages'),
	'eval'                    => array('tl_class'=>'w50'),
);

$GLOBALS['TL_DCA']['tl_page']['fields']['languageRoot'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_page']['languageRoot'],
	'exclude'                 => true,
	'inputType'               => 'select',
	'options_callback'        => array('tl_page_changelanguage', 'getRootPages'),
	'eval'                    => array('includeBlankOption'=>true, 'blankOptionLabel'=>&$GLOBALS['TL_LANG']['tl_page']['no_rootpage'], 'tl_class'=>'w50'),
);


/**
 * Provides miscellaneous functions for backend data container.
 * 
 * @extends Backend
 */
class tl_page_changelanguage extends Backend 
{
	
	public $arrPages = array();
	
	
	/**
	 * Return all fallback pages for the current page (used as options_callback).
	 * 
	 * @access public
	 * @return array
	 */
	public function getFallbackPages($dc)
	{
		$objPage = $this->getPageDetails($dc->id);
		
		$objRootPage = $this->Database->prepare("SELECT * FROM tl_page WHERE id=?")->limit(1)->execute($objPage->rootId);
				
		$objFallback = $this->Database->prepare("SELECT * FROM tl_page WHERE type='root' AND fallback=1 AND id!=? AND (dns=? OR id=?)")->limit(1)->execute($objRootPage->id, $objPage->domain, $objRootPage->languageRoot, $objRootPage->languageRoot);
		
		if ($objFallback->languageRoot)
		{
			$objFallback = $this->Database->prepare("SELECT * FROM tl_page WHERE id=?")->limit(1)->execute($objFallback->languageRoot);
		}

		$this->arrPages[0] = $GLOBALS['TL_LANG']['tl_page']['no_subpage'];
		
		if ($objFallback->numRows)
		{
			$this->createPageList($objFallback->id, 0);			
		}
		
		return $this->arrPages;
	}
	
	
	public function getRootPages($dc)
	{
		$arrPages = array();
		$objPages = $this->Database->prepare("SELECT * FROM tl_page WHERE type='root' AND fallback='1' AND languageRoot=0 AND language!=(SELECT language FROM tl_page WHERE id=?) AND id!=?")->execute($dc->id, $dc->id);
		
		while( $objPages->next() )
		{
			$arrPages[$objPages->id] = $objPages->title . (strlen($objPages->dns) ? (' (' . $objPages->dns . ')') : '') . ' [' . $objPages->language . ']';
		}
		
		return $arrPages;
	}
	
	
	/**
	 * Generates a list of all subpages and fill into $this->arrPages.
	 * 
	 * @access public
	 * @param int $intId. (default: 0)
	 * @param int $level. (default: -1)
	 * @return void
	 */
	public function createPageList($intId=0, $level=-1)
	{
		// Add child pages
		$objPages = $this->Database->prepare("SELECT id, title FROM tl_page WHERE pid=? AND ( type = 'regular' OR type = 'redirect' OR type = 'forward') ORDER BY sorting")
								   ->execute($intId);
								   
		if ($objPages->numRows < 1)
		{
			return;
		}

		++$level;
		$strOptions = '';

		while ($objPages->next())
		{
			$fallbackID = $objPages->id;
			$fallbackTitle = str_repeat("&nbsp;", (3 * $level)) . $objPages->title;
			$this->arrPages[$fallbackID] = $fallbackTitle;
			
			$this->createPageList($objPages->id, $level);
		}
	}
	
	
	/**
	 * Inject fields if appropriate.
	 * 
	 * @access public
	 * @return void
	 */
	public function showSelectbox($dc)
	{
		if($this->Input->get('act') == "edit")
		{
			$objPage = $this->getPageDetails($dc->id);
			
			if ($objPage->type == 'root' && $objPage->fallback)
			{
				$GLOBALS['TL_DCA']['tl_page']['fields']['fallback']['eval']['tl_class'] = 'm12 w50';
				$GLOBALS['TL_DCA']['tl_page']['fields']['dns']['eval']['tl_class'] = 'clr w50';
				$GLOBALS['TL_DCA']['tl_page']['palettes']['root'] = preg_replace('@([,|;]fallback)([,|;])@','$1,languageRoot$2', $GLOBALS['TL_DCA']['tl_page']['palettes']['root']);
			}
			elseif ($objPage->type == 'regular')
			{
				$objRootPage = $this->Database->prepare("SELECT * FROM tl_page WHERE id=?")->limit(1)->execute($objPage->rootId);
				
				$objFallback = $this->Database->prepare("SELECT id FROM tl_page WHERE type='root' AND fallback='1' AND id!=? AND (dns=? OR id=?)")->limit(1)->execute($objRootPage->id, $objPage->domain, ($objRootPage->fallback ? $objRootPage->languageRoot : 0));
	
				if($objFallback->numRows)
				{
					$GLOBALS['TL_DCA']['tl_page']['fields']['title']['eval']['tl_class'] = 'w50';
					$GLOBALS['TL_DCA']['tl_page']['fields']['alias']['eval']['tl_class'] = 'clr w50';
					$GLOBALS['TL_DCA']['tl_page']['palettes']['regular'] = preg_replace('@([,|;]title)([,|;])@','$1,languageMain$2', $GLOBALS['TL_DCA']['tl_page']['palettes']['regular']);
				}
			}
		}
		else if($this->Input->get('act') == "editAll")
		{
			$GLOBALS['TL_DCA']['tl_page']['fields']['title']['eval']['tl_class'] = 'w50';
			$GLOBALS['TL_DCA']['tl_page']['fields']['alias']['eval']['tl_class'] = 'clr w50';
			$GLOBALS['TL_DCA']['tl_page']['palettes']['regular'] = preg_replace('@([,|;]title)([,|;])@','$1,languageMain$2', $GLOBALS['TL_DCA']['tl_page']['palettes']['regular']);
		}
	}
	
	
	/**
	 * Make sure languageMain is "0" on fallback tree. Otherwise unknown behaviour could occure.
	 * 
	 * @access public
	 * @param mixed $dc
	 * @return void
	 */
	public function resetFallback($dc)
	{
		if ($dc->id > 0)
		{
			$objPage = $this->Database->prepare("SELECT * FROM tl_page WHERE id=?")->limit(1)->execute($dc->id);
			
			if ($objPage->numRows && $objPage->type == 'root' && $objPage->fallback && !$objPage->languageRoot)
			{
				$arrIds = $this->getChildRecords($objPage->id, 'tl_page');
				$arrIds[] = $objPage->id;
				
				$this->Database->execute("UPDATE tl_page SET languageMain=0 WHERE id IN (" . implode(',', $arrIds) . ")");
			}
			elseif ($objPage->numRows && $objPage->type == 'root' && !$objPage->fallback && $objPage->languageRoot)
			{
				$this->Database->prepare("UPDATE tl_page SET languageRoot=0 WHERE id=?")->execute($objPage->id);
			}
		}
	}
	
	
	/**
	 * Show notice if no fallback page is set
	 */
	public function addFallbackNotice($row, $label, $param3, $param4, $blnReturnImage=false)
	{
		// Parameter sorting has been changed in 2.8: http://dev.typolight.org/issues/show/1488
		if (version_compare(VERSION.BUILD, '2.7.6', '>'))
		{
			$dc = $param3;
			$imageAttribute = $param4;
		}
		else
		{
			$dc = $prama4;
			$imageAttribute = $param3;
		}
		
		if (in_array('cacheicon', $this->Config->getActiveModules()))
		{
			$objPage = new tl_page_cacheicon();
			$label = $objPage->addImage($row, $label, $imageAttribute, $dc);
		}
		else
		{
			$objPage = new tl_page();
			$label = version_compare(VERSION.BUILD, '2.7.6', '>') ? $objPage->addIcon($row, $label, $imageAttribute, $dc, $blnReturnImage) : $objPage->addImage($row, $label, $imageAttribute, $dc);
		}
		
		if (!$row['languageMain'])
		{
			$objPage = $this->getPageDetails($row['id']);
				
			// Save resources if we are not a regular page
			if ($objPage->type !== 'regular')
				return $label;
				
			$objRootPage = $this->Database->prepare("SELECT * FROM tl_page WHERE id=?")->limit(1)->execute($objPage->rootId);
				
			$objFallback = $this->Database->prepare("SELECT id FROM tl_page WHERE type='root' AND fallback='1' AND id!=? AND (dns=? OR id=?)")->limit(1)->execute($objRootPage->id, $objPage->domain, ($objRootPage->fallback ? $objRootPage->languageRoot : 0));
	
			if($objFallback->numRows)
			{
				$label .= '<span style="color:#b3b3b3; padding-left:3px;">[' . $GLOBALS['TL_LANG']['MSC']['noMainLanguage'] . ']</span>';
			}
		}
		
		return $label;
	}
}

