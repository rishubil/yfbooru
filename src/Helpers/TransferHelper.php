<?php
class TransferHelper
{
	public static function download($srcUrl, $dstPath, $maxBytes = null)
	{
		set_time_limit(0);
		$srcHandle = fopen($srcUrl, 'rb');
		if (!$srcHandle)
			throw new SimpleException('Cannot open URL for reading');

		$dstHandle = fopen($dstPath, 'w+b');
		if (!$dstHandle)
		{
			fclose($srcHandle);
			throw new SimpleException('Cannot open file for writing');
		}

		try
		{
			while (!feof($srcHandle))
			{
				$buffer = fread($srcHandle, 4 * 1024);
				if (fwrite($dstHandle, $buffer) === false)
					throw new SimpleException('Cannot write into file');
				fflush($dstHandle);
				if ($maxBytes !== null and ftell($dstHandle) > $maxBytes)
				{
					fclose($srcHandle);
					fclose($dstHandle);
					throw new SimpleException(
						'File is too big (maximum size: %s)',
						TextHelper::useBytesUnits($maxBytes));
				}
			}
		}
		finally
		{
			fclose($srcHandle);
			fclose($dstHandle);

			chmod($dstPath, 0644);
		}
	}

	public static function moveUpload($srcPath, $dstPath)
	{
		if (is_uploaded_file($srcPath))
		{
			move_uploaded_file($srcPath, $dstPath);
		}
		else
		{
			//problems with permissions on some systems?
			#rename($srcPath, $dstPath);
			copy($srcPath, $dstPath);
			unlink($srcPath);
		}
	}

	public static function handleUploadErrors($file)
	{
		switch ($file['error'])
		{
			case UPLOAD_ERR_OK:
				break;

			case UPLOAD_ERR_INI_SIZE:
				throw new SimpleException('File is too big (maximum size: %s)', ini_get('upload_max_filesize'));

			case UPLOAD_ERR_FORM_SIZE:
				throw new SimpleException('File is too big than it was allowed in HTML form');

			case UPLOAD_ERR_PARTIAL:
				throw new SimpleException('File transfer was interrupted');

			case UPLOAD_ERR_NO_FILE:
				throw new SimpleException('No file was uploaded');

			case UPLOAD_ERR_NO_TMP_DIR:
				throw new SimpleException('Server misconfiguration error: missing temporary folder');

			case UPLOAD_ERR_CANT_WRITE:
				throw new SimpleException('Server misconfiguration error: cannot write to disk');

			case UPLOAD_ERR_EXTENSION:
				throw new SimpleException('Server misconfiguration error: upload was canceled by an extension');

			default:
				throw new SimpleException('Generic file upload error (id: ' . $file['error'] . ')');
		}
		if (!is_uploaded_file($file['tmp_name']))
			throw new SimpleException('Generic file upload error');
	}
}