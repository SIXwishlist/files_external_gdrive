<?php
/**
 * @author Samy NASTUZZI <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2018, Samy NASTUZZI (samy@nastuzzi.fr).
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_external_gdrive\Storage;

use League\Flysystem\Config;
use League\Flysystem\Util;

class Adapter extends \Hypweb\Flysystem\GoogleDrive\GoogleDriveAdapter {
	const FOLDER = 'application/vnd.google-apps.folder';
	const DOCUMENT = 'application/vnd.google-apps.document';
	const SPREADSHEET = 'application/vnd.google-apps.spreadsheet';
	const DRAWING = 'application/vnd.google-apps.drawing';
	const PRESENTATION = 'application/vnd.google-apps.presentation';
	const MAP = 'application/vnd.google-apps.map';

    /**
     * List contents of a directory.
     *
     * @param string $path
     * @param bool $recursive
     *
     * @return array
     */
    public function listContents($path = '', $recursive = false) {
        $contents = $this->getItems($path, $recursive);

		foreach ($contents as $key => $content) {
			$extension = isset($content['mimetype']) ? $this->getGoogleDocExtension($content['mimetype']) : '';

			$contents[$key]['basename'] = $content['filename'].($content['extension'] === '' ? '' : '.'.$content['extension']).($extension === '' ? '' : '.'.$extension);
		}

		return $contents;
    }

	/**
	* Generate file extension for a Google Doc, choosing Open Document formats for download
	* @param string $mimetype
	* @return string
	*/
	private function getGoogleDocExtension($mimetype) {
		switch ($mimetype) {
			case self::DOCUMENT:
				return 'odt';
			case self::SPREADSHEET:
				return 'ods';
			case self::DRAWING:
				return 'jpeg';
			case self::PRESENTATION:
				return 'odp';
			default:
				return '';
		}
	}

	public function getMimeType($mimetype) {
		// Convert Google Doc mimetypes, choosing Open Document formats for download
		switch ($mimetype) {
			case self::FOLDER:
				return 'httpd/unix-directory';
			case self::DOCUMENT:
				return 'application/vnd.oasis.opendocument.text';
			case self::SPREADSHEET:
				return 'application/x-vnd.oasis.opendocument.spreadsheet';
			case self::DRAWING:
				return 'image/jpeg';
			case self::PRESENTATION:
				return 'application/vnd.oasis.opendocument.presentation';
			default: // use extension-based detection, could be an encrypted file
				return $mimetype;
		}
	}

	/**
	 * Get download url
	 *
	 * @param Google_Service_Drive_DriveFile $file
	 *
	 * @return string|false
	 */
	protected function getDownloadUrl($file) {
		return 'https://www.googleapis.com/drive/v3/files/'.$file->getId().'/export?mimeType='.rawurlencode($this->getMimeType($file->mimeType));
	}
	
    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        list ($oldParent, $fileId) = $this->splitPath($path);
        list ($newParent, $newName) = $this->splitPath($newpath);

		$mimetype = $this->getGoogleDocExtension($this->getMimetype($fileId)['mimetype']);

		if ($mimetype !== '' && end(explode('.', $newName)) === $mimetype)
			$newName = substr($newName, 0, - strlen($mimetype) - 1);

        $file = new \Google_Service_Drive_DriveFile();
        $file->setName($newName);
        $opts = [
            'fields' => $this->fetchfieldsGet
        ];
        if ($newParent !== $oldParent) {
            $opts['addParents'] = $newParent;
            $opts['removeParents'] = $oldParent;
        }

        $updatedFile = $this->service->files->update($fileId, $file, $this->applyDefaultParams($opts, 'files.update'));

        if ($updatedFile) {
            $this->cacheFileObjects[$updatedFile->getId()] = $updatedFile;
            $this->cacheFileObjects[$newName] = $updatedFile;
            return true;
        }

        return false;
    }
}
