<?php
/**
 * bPost Option class
 *
 * @author    Serge Jamasb <serge@stigmi.eu>
 * @version   3.1.0
 * @copyright Copyright (c), Eontech.net. All rights reserved.
 * @license   BSD License
 */

/** 
 * default class was totally empty
 * but bpost does now send empty
 * unspecified options
 * ok * only if overridden
 */
class EontechModBpostOrderBoxOption
{
	/**
	 * 
	 * Return the object as an array for usage in the XML
	 *
	 * @param  \DomDocument $document
	 * @param  string	   $prefix
	 * @return \DomElement
	 */
	public function toXML(\DOMDocument $document, $prefix = 'common')
	{
		$tag_name = 'unspecifiedOption';
		if ($prefix !== null)
			$tag_name = $prefix.':'.$tag_name;

		return $document->createElement($tag_name);
	}

	/**
	 * @param  \SimpleXMLElement $xml
	 * @return AutomaticSecondPresentation
	 */
	public static function createFromXML(\SimpleXMLElement $xml)
	{
		return new EontechModBpostOrderBoxOption();
	}
}
