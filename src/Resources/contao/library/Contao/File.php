<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use Contao\CoreBundle\Exception\ResponseException;
use Contao\Image\Image as ContaoImage;
use Contao\Image\ImageDimensions;
use Patchwork\Utf8;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


/**
 * Creates, reads, writes and deletes files
 *
 * Usage:
 *
 *     $file = new File('test.txt');
 *     $file->write('This is a test');
 *     $file->close();
 *
 *     $file->delete();
 *
 *     File::putContent('test.txt', 'This is a test');
 *
 * @property integer  $size          The file size
 * @property integer  $filesize      Alias of $size
 * @property string   $name          The file name and extension
 * @property string   $basename      Alias of $name
 * @property string   $dirname       The path of the parent folder
 * @property string   $extension     The lowercase file extension
 * @property string   $origext       The original file extension
 * @property string   $filename      The file name without extension
 * @property string   $tmpname       The name of the temporary file
 * @property string   $path          The file path
 * @property string   $value         Alias of $path
 * @property string   $mime          The mime type
 * @property string   $hash          The MD5 checksum
 * @property string   $ctime         The ctime
 * @property string   $mtime         The mtime
 * @property string   $atime         The atime
 * @property string   $icon          The mime icon name
 * @property string   $dataUri       The data URI
 * @property array    $imageSize     The file dimensions (images only)
 * @property integer  $width         The file width (images only)
 * @property integer  $height        The file height (images only)
 * @property array    $imageViewSize The viewbox dimensions
 * @property integer  $viewWidth     The viewbox width
 * @property integer  $viewHeight    The viewbox height
 * @property boolean  $isImage       True if the file is an image
 * @property boolean  $isGdImage     True if the file can be handled by the GDlib
 * @property boolean  $isSvgImage    True if the file is an SVG image
 * @property integer  $channels      The number of channels (images only)
 * @property integer  $bits          The number of bits for each color (images only)
 * @property boolean  $isRgbImage    True if the file is an RGB image
 * @property boolean  $isCmykImage   True if the file is a CMYK image
 * @property resource $handle        The file handle (returned by fopen())
 * @property string   $title         The file title
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class File extends \System
{

	/**
	 * File handle
	 * @var resource
	 */
	protected $resFile;

	/**
	 * File name
	 * @var string
	 */
	protected $strFile;

	/**
	 * Temp name
	 * @var string
	 */
	protected $strTmp;

	/**
	 * Files model
	 * @var FilesModel
	 */
	protected $objModel;

	/**
	 * Pathinfo
	 * @var array
	 */
	protected $arrPathinfo = array();

	/**
	 * Image size
	 * @var array
	 */
	protected $arrImageSize = array();

	/**
	 * Image size runtime cache
	 * @var array
	 */
	protected static $arrImageSizeCache = array();

	/**
	 * Image view size
	 * @var array
	 */
	protected $arrImageViewSize = array();


	/**
	 * Instantiate a new file object
	 *
	 * @param string $strFile The file path
	 *
	 * @throws \Exception If $strFile is a directory
	 */
	public function __construct($strFile)
	{
		// No parent::__construct() here

		// Handle open_basedir restrictions
		if ($strFile == '.')
		{
			$strFile = '';
		}

		// Make sure we are not pointing to a directory
		if (is_dir(TL_ROOT . '/' . $strFile))
		{
			throw new \Exception(sprintf('Directory "%s" is not a file', $strFile));
		}

		$this->import('Files');

		$this->strFile = $strFile;
	}


	/**
	 * Close the file handle if it has not been done yet
	 */
	public function __destruct()
	{
		if (\is_resource($this->resFile))
		{
			$this->Files->fclose($this->resFile);
		}
	}


	/**
	 * Return an object property
	 *
	 * @param string $strKey The property name
	 *
	 * @return mixed The property value
	 */
	public function __get($strKey)
	{
		switch ($strKey)
		{
			case 'size':
			case 'filesize':
				return filesize(TL_ROOT . '/' . $this->strFile);
				break;

			case 'name':
			case 'basename':
				if (!isset($this->arrPathinfo[$strKey]))
				{
					$this->arrPathinfo = $this->getPathinfo();
				}

				return $this->arrPathinfo['basename'];
				break;

			case 'dirname':
			case 'filename':
				if (!isset($this->arrPathinfo[$strKey]))
				{
					$this->arrPathinfo = $this->getPathinfo();
				}

				return $this->arrPathinfo[$strKey];

			case 'extension':
				if (!isset($this->arrPathinfo['extension']))
				{
					$this->arrPathinfo = $this->getPathinfo();
				}

				return strtolower($this->arrPathinfo['extension']);
				break;

			case 'origext':
				if (!isset($this->arrPathinfo['extension']))
				{
					$this->arrPathinfo = $this->getPathinfo();
				}

				return $this->arrPathinfo['extension'];
				break;

			case 'tmpname':
				return basename($this->strTmp);
				break;

			case 'path':
			case 'value':
				return $this->strFile;
				break;

			case 'mime':
				return $this->getMimeType();
				break;

			case 'hash':
				return $this->getHash();
				break;

			case 'ctime':
				return filectime(TL_ROOT . '/' . $this->strFile);
				break;

			case 'mtime':
				return filemtime(TL_ROOT . '/' . $this->strFile);
				break;

			case 'atime':
				return fileatime(TL_ROOT . '/' . $this->strFile);
				break;

			case 'icon':
				return $this->getIcon();
				break;

			case 'dataUri':
				if ($this->extension == 'svgz')
				{
					return 'data:' . $this->mime . ';base64,' . base64_encode(gzdecode($this->getContent()));
				}
				else
				{
					return 'data:' . $this->mime . ';base64,' . base64_encode($this->getContent());
				}
				break;

			case 'imageSize':
				if (empty($this->arrImageSize))
				{
					$strCacheKey = $this->strFile . '|' . $this->mtime;

					if (isset(static::$arrImageSizeCache[$strCacheKey]))
					{
						$this->arrImageSize = static::$arrImageSizeCache[$strCacheKey];
					}
					elseif ($this->isGdImage)
					{
						$this->arrImageSize = @getimagesize(TL_ROOT . '/' . $this->strFile);
					}
					elseif ($this->isSvgImage)
					{
						try
						{
							$dimensions = (new ContaoImage(TL_ROOT . '/' . $this->strFile, System::getContainer()->get('contao.image.imagine_svg')))->getDimensions();

							if (!$dimensions->isRelative() && !$dimensions->isUndefined())
							{
								$this->arrImageSize = array
								(
									$dimensions->getSize()->getWidth(),
									$dimensions->getSize()->getHeight(),
									0, // replace this with IMAGETYPE_SVG when it becomes available
									'width="' . $dimensions->getSize()->getWidth() . '" height="' . $dimensions->getSize()->getHeight() . '"',
									'bits' => 8,
									'channels' => 3,
									'mime' => $this->getMimeType()
								);
							}
							else
							{
								$this->arrImageSize = false;
							}
						}
						catch(\Exception $e)
						{
							$this->arrImageSize = false;
						}
					}

					if (!isset(static::$arrImageSizeCache[$strCacheKey]))
					{
						static::$arrImageSizeCache[$strCacheKey] = $this->arrImageSize;
					}
				}

				return $this->arrImageSize;
				break;

			case 'width':
				return $this->imageSize[0];
				break;

			case 'height':
				return $this->imageSize[1];
				break;

			case 'imageViewSize':
				if (empty($this->arrImageViewSize))
				{
					if ($this->imageSize)
					{
						$this->arrImageViewSize = array
						(
							$this->imageSize[0],
							$this->imageSize[1]
						);
					}
					elseif ($this->isSvgImage)
					{
						try
						{
							$dimensions = new ImageDimensions(
								System::getContainer()
									->get('contao.image.imagine_svg')
									->open(TL_ROOT . '/' . $this->strFile)
									->getSize()
							);

							$this->arrImageViewSize = array
							(
								(int) $dimensions->getSize()->getWidth(),
								(int) $dimensions->getSize()->getHeight()
							);

							if (!$this->arrImageViewSize[0] || !$this->arrImageViewSize[1])
							{
								$this->arrImageViewSize = false;
							}
						}
						catch(\Exception $e)
						{
							$this->arrImageViewSize = false;
						}
					}
				}

				return $this->arrImageViewSize;
				break;

			case 'viewWidth':
				return $this->imageViewSize[0];
				break;

			case 'viewHeight':
				return $this->imageViewSize[1];
				break;

			case 'isImage':
				return $this->isGdImage || $this->isSvgImage;
				break;

			case 'isGdImage':
				return \in_array($this->extension, array('gif', 'jpg', 'jpeg', 'png'));
				break;

			case 'isSvgImage':
				return \in_array($this->extension, array('svg', 'svgz'));
				break;

			case 'channels':
				return $this->imageSize['channels'];
				break;

			case 'bits':
				return $this->imageSize['bits'];
				break;

			case 'isRgbImage':
				return $this->channels == 3;
				break;

			case 'isCmykImage':
				return $this->channels == 4;
				break;

			case 'handle':
				if (!\is_resource($this->resFile))
				{
					$this->resFile = fopen(TL_ROOT . '/' . $this->strFile, 'rb');
				}

				return $this->resFile;
				break;

			default:
				return parent::__get($strKey);
				break;
		}
	}


	/**
	 * Create the file if it does not yet exist
	 *
	 * @throws \Exception If the file cannot be written
	 */
	protected function createIfNotExists()
	{
		// The file exists
		if (file_exists(TL_ROOT . '/' . $this->strFile))
		{
			return;
		}

		// Handle open_basedir restrictions
		if (($strFolder = \dirname($this->strFile)) == '.')
		{
			$strFolder = '';
		}

		// Create the folder
		if (!is_dir(TL_ROOT . '/' . $strFolder))
		{
			new \Folder($strFolder);
		}

		// Open the file
		if (!$this->resFile = $this->Files->fopen($this->strFile, 'wb'))
		{
			throw new \Exception(sprintf('Cannot create file "%s"', $this->strFile));
		}
	}


	/**
	 * Check whether the file exists
	 *
	 * @return boolean True if the file exists
	 */
	public function exists()
	{
		return file_exists(TL_ROOT . '/' . $this->strFile);
	}


	/**
	 * Truncate the file and reset the file pointer
	 *
	 * @return boolean True if the operation was successful
	 */
	public function truncate()
	{
		if (\is_resource($this->resFile))
		{
			ftruncate($this->resFile, 0);
			rewind($this->resFile);
		}

		return $this->write('');
	}


	/**
	 * Write data to the file
	 *
	 * @param mixed $varData The data to be written
	 *
	 * @return boolean True if the operation was successful
	 */
	public function write($varData)
	{
		return $this->fputs($varData, 'wb');
	}


	/**
	 * Append data to the file
	 *
	 * @param mixed  $varData The data to be appended
	 * @param string $strLine The line ending (defaults to LF)
	 *
	 * @return boolean True if the operation was successful
	 */
	public function append($varData, $strLine="\n")
	{
		return $this->fputs($varData . $strLine, 'ab');
	}


	/**
	 * Prepend data to the file
	 *
	 * @param mixed  $varData The data to be prepended
	 * @param string $strLine The line ending (defaults to LF)
	 *
	 * @return boolean True if the operation was successful
	 */
	public function prepend($varData, $strLine="\n")
	{
		return $this->fputs($varData . $strLine . $this->getContent(), 'wb');
	}


	/**
	 * Delete the file
	 *
	 * @return boolean True if the operation was successful
	 */
	public function delete()
	{
		$return = $this->Files->delete($this->strFile);

		// Update the database
		if (\Dbafs::shouldBeSynchronized($this->strFile))
		{
			\Dbafs::deleteResource($this->strFile);
		}

		return $return;
	}


	/**
	 * Set the file permissions
	 *
	 * @param integer $intChmod The CHMOD settings
	 *
	 * @return boolean True if the operation was successful
	 */
	public function chmod($intChmod)
	{
		return $this->Files->chmod($this->strFile, $intChmod);
	}


	/**
	 * Close the file handle
	 *
	 * @return boolean True if the operation was successful
	 */
	public function close()
	{
		if (\is_resource($this->resFile))
		{
			$this->Files->fclose($this->resFile);
		}

		// Create the file path
		if (!file_exists(TL_ROOT . '/' . $this->strFile))
		{
			// Handle open_basedir restrictions
			if (($strFolder = \dirname($this->strFile)) == '.')
			{
				$strFolder = '';
			}

			// Create the parent folder
			if (!is_dir(TL_ROOT . '/' . $strFolder))
			{
				new \Folder($strFolder);
			}
		}

		// Move the temporary file to its destination
		$return = $this->Files->rename($this->strTmp, $this->strFile);
		$this->strTmp = null;

		// Update the database
		if (\Dbafs::shouldBeSynchronized($this->strFile))
		{
			$this->objModel = \Dbafs::addResource($this->strFile);
		}

		return $return;
	}


	/**
	 * Return the files model
	 *
	 * @return FilesModel The files model
	 */
	public function getModel()
	{
		if ($this->objModel === null && \Dbafs::shouldBeSynchronized($this->strFile))
		{
			$this->objModel = \FilesModel::findByPath($this->strFile);
		}

		return $this->objModel;
	}


	/**
	 * Return the file content as string
	 *
	 * @return string The file content without BOM
	 */
	public function getContent()
	{
		$strContent = file_get_contents(TL_ROOT . '/' . ($this->strTmp ?: $this->strFile));

		// Remove BOMs (see #4469)
		if (strncmp($strContent, "\xEF\xBB\xBF", 3) === 0)
		{
			$strContent = substr($strContent, 3);
		}
		elseif (strncmp($strContent, "\xFF\xFE", 2) === 0)
		{
			$strContent = substr($strContent, 2);
		}
		elseif (strncmp($strContent, "\xFE\xFF", 2) === 0)
		{
			$strContent = substr($strContent, 2);
		}

		return $strContent;
	}


	/**
	 * Write to a file
	 *
	 * @param string $strFile    Relative file name
	 * @param string $strContent Content to be written
	 */
	public static function putContent($strFile, $strContent)
	{
		$objFile = new static($strFile);
		$objFile->write($strContent);
		$objFile->close();
	}


	/**
	 * Return the file content as array
	 *
	 * @return array The file content as array
	 */
	public function getContentAsArray()
	{
		return array_map('rtrim', file(TL_ROOT . '/' . $this->strFile));
	}


	/**
	 * Rename the file
	 *
	 * @param string $strNewName The new path
	 *
	 * @return boolean True if the operation was successful
	 */
	public function renameTo($strNewName)
	{
		$strParent = \dirname($strNewName);

		// Create the parent folder if it does not exist
		if (!is_dir(TL_ROOT . '/' . $strParent))
		{
			new \Folder($strParent);
		}

		$return = $this->Files->rename($this->strFile, $strNewName);

		// Update the database AFTER the file has been renamed
		$syncSource = \Dbafs::shouldBeSynchronized($this->strFile);
		$syncTarget = \Dbafs::shouldBeSynchronized($strNewName);

		// Synchronize the database
		if ($syncSource && $syncTarget)
		{
			$this->objModel = \Dbafs::moveResource($this->strFile, $strNewName);
		}
		elseif ($syncSource)
		{
			$this->objModel = \Dbafs::deleteResource($this->strFile);
		}
		elseif ($syncTarget)
		{
			$this->objModel = \Dbafs::addResource($strNewName);
		}

		// Reset the object AFTER the database has been updated
		if ($return != false)
		{
			$this->strFile = $strNewName;
			$this->arrImageSize = array();
			$this->arrPathinfo = array();
		}

		return $return;
	}


	/**
	 * Copy the file
	 *
	 * @param string $strNewName The target path
	 *
	 * @return boolean True if the operation was successful
	 */
	public function copyTo($strNewName)
	{
		$strParent = \dirname($strNewName);

		// Create the parent folder if it does not exist
		if (!is_dir(TL_ROOT . '/' . $strParent))
		{
			new \Folder($strParent);
		}

		$this->Files->copy($this->strFile, $strNewName);

		// Update the database AFTER the file has been renamed
		$syncSource = \Dbafs::shouldBeSynchronized($this->strFile);
		$syncTarget = \Dbafs::shouldBeSynchronized($strNewName);

		// Synchronize the database
		if ($syncSource && $syncTarget)
		{
			\Dbafs::copyResource($this->strFile, $strNewName);
		}
		elseif ($syncTarget)
		{
			\Dbafs::addResource($strNewName);
		}

		return true;
	}


	/**
	 * Resize the file if it is an image
	 *
	 * @param integer $width  The target width
	 * @param integer $height The target height
	 * @param string  $mode   The resize mode
	 *
	 * @return boolean True if the image could be resized successfully
	 */
	public function resizeTo($width, $height, $mode='')
	{
		if (!$this->isImage)
		{
			return false;
		}

		$return = \System::getContainer()
			->get('contao.image.image_factory')
			->create(TL_ROOT . '/' . $this->strFile, array($width, $height, $mode), TL_ROOT . '/' . $this->strFile)
			->getUrl(TL_ROOT)
		;

		if ($return)
		{
			$this->arrPathinfo = array();
			$this->arrImageSize = array();
		}

		return $return;
	}


	/**
	 * Send the file to the browser
	 *
	 * @param string $filename An optional filename
	 *
	 * @throws ResponseException
	 */
	public function sendToBrowser($filename='')
	{
		$response = new BinaryFileResponse(TL_ROOT . '/' . $this->strFile);

		$response->setContentDisposition
		(
			ResponseHeaderBag::DISPOSITION_ATTACHMENT,
			$filename,
			Utf8::toAscii($this->basename)
		);

		$response->headers->addCacheControlDirective('must-revalidate');
		$response->headers->addCacheControlDirective('post-check', 0);
		$response->headers->addCacheControlDirective('pre-check', 0);

		$response->headers->set('Connection', 'close');

		throw new ResponseException($response);
	}


	/**
	 * Write data to a file
	 *
	 * @param mixed  $varData The data to be written
	 * @param string $strMode The operation mode
	 *
	 * @return boolean True if the operation was successful
	 */
	protected function fputs($varData, $strMode)
	{
		if (!\is_resource($this->resFile))
		{
			$this->strTmp = 'system/tmp/' . md5(uniqid(mt_rand(), true));

			// Copy the contents of the original file to append data
			if (strncmp($strMode, 'a', 1) === 0 && file_exists(TL_ROOT . '/' . $this->strFile))
			{
				$this->Files->copy($this->strFile, $this->strTmp);
			}

			// Open the temporary file
			if (!$this->resFile = $this->Files->fopen($this->strTmp, $strMode))
			{
				return false;
			}
		}

		fwrite($this->resFile, $varData);

		return true;
	}


	/**
	 * Return the mime type and icon of the file based on its extension
	 *
	 * @return array An array with mime type and icon name
	 */
	protected function getMimeInfo()
	{
		if (isset($GLOBALS['TL_MIME'][$this->extension]))
		{
			return $GLOBALS['TL_MIME'][$this->extension];
		}

		return array('application/octet-stream', 'iconPLAIN.svg');
	}


	/**
	 * Get the mime type of the file based on its extension
	 *
	 * @return string The mime type
	 */
	protected function getMimeType()
	{
		$arrMime = $this->getMimeInfo();

		return $arrMime[0];
	}


	/**
	 * Return the file icon depending on the file type
	 *
	 * @return string The icon name
	 */
	protected function getIcon()
	{
		$arrMime = $this->getMimeInfo();

		return $arrMime[1];
	}


	/**
	 * Return the MD5 hash of the file
	 *
	 * @return string The MD5 hash
	 */
	protected function getHash()
	{
		// Do not try to hash if bigger than 2 GB
		if ($this->filesize >= 2147483648)
		{
			return '';
		}
		else
		{
			return md5_file(TL_ROOT . '/' . $this->strFile);
		}
	}


	/**
	 * Return the path info (binary-safe)
	 *
	 * @return array The path info
	 *
	 * @see https://github.com/PHPMailer/PHPMailer/blob/master/class.phpmailer.php#L3520
	 */
	protected function getPathinfo()
	{
		$matches = array();
		$return = array('dirname'=>'', 'basename'=>'', 'extension'=>'', 'filename'=>'');

		preg_match('%^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$%im', $this->strFile, $matches);

		if (isset($matches[1]))
		{
			$return['dirname'] = TL_ROOT . '/' . $matches[1]; // see #8325
		}

		if (isset($matches[2]))
		{
			$return['basename'] = $matches[2];
		}

		if (isset($matches[5]))
		{
			$return['extension'] = $matches[5];
		}

		if (isset($matches[3]))
		{
			$return['filename'] = $matches[3];
		}

		return $return;
	}
}
