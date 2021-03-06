<?php
/**
 * Origin filesystem driver
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\Filesystem\Driver;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DriverInterface;

class File implements DriverInterface
{
    /**
     * @var string
     */
    protected $scheme = '';

    /**
     * Returns last warning message string
     *
     * @return string
     */
    protected function getWarningMessage()
    {
        $warning = error_get_last();
        if ($warning && $warning['type'] == E_WARNING) {
            return 'Warning!' . $warning['message'];
        }
        return null;
    }

    /**
     * Is file or directory exist in file system
     *
     * @param string $path
     * @return bool
     * @throws FileSystemException
     */
    public function isExists($path)
    {
        clearstatcache();
        $result = @file_exists($this->getScheme() . $path);
        if ($result === null) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('Error occurred during execution %1', [$this->getWarningMessage()])
            );
        }
        return $result;
    }

    /**
     * Gathers the statistics of the given path
     *
     * @param string $path
     * @return array
     * @throws FileSystemException
     */
    public function stat($path)
    {
        clearstatcache();
        $result = @stat($this->getScheme() . $path);
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('Cannot gather stats! %1', [$this->getWarningMessage()])
            );
        }
        return $result;
    }

    /**
     * Check permissions for reading file or directory
     *
     * @param string $path
     * @return bool
     * @throws FileSystemException
     */
    public function isReadable($path)
    {
        clearstatcache();
        $result = @is_readable($this->getScheme() . $path);
        if ($result === null) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('Error occurred during execution %1', [$this->getWarningMessage()])
            );
        }
        return $result;
    }

    /**
     * Tells whether the filename is a regular file
     *
     * @param string $path
     * @return bool
     * @throws FileSystemException
     */
    public function isFile($path)
    {
        clearstatcache();
        $result = @is_file($this->getScheme() . $path);
        if ($result === null) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('Error occurred during execution %1', [$this->getWarningMessage()])
            );
        }
        return $result;
    }

    /**
     * Tells whether the filename is a regular directory
     *
     * @param string $path
     * @return bool
     * @throws FileSystemException
     */
    public function isDirectory($path)
    {
        clearstatcache();
        $result = @is_dir($this->getScheme() . $path);
        if ($result === null) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('Error occurred during execution %1', [$this->getWarningMessage()])
            );
        }
        return $result;
    }

    /**
     * Retrieve file contents from given path
     *
     * @param string $path
     * @param string|null $flag
     * @param resource|null $context
     * @return string
     * @throws FileSystemException
     */
    public function fileGetContents($path, $flag = null, $context = null)
    {
        clearstatcache();
        $result = @file_get_contents($this->getScheme() . $path, $flag, $context);
        if (false === $result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'Cannot read contents from file "%1" %2',
                    [$path, $this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Check if given path is writable
     *
     * @param string $path
     * @return bool
     * @throws FileSystemException
     */
    public function isWritable($path)
    {
        clearstatcache();
        $result = @is_writable($this->getScheme() . $path);
        if ($result === null) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('Error occurred during execution %1', [$this->getWarningMessage()])
            );
        }
        return $result;
    }

    /**
     * Returns parent directory's path
     *
     * @param string $path
     * @return string
     */
    public function getParentDirectory($path)
    {
        return dirname($this->getScheme() . $path);
    }

    /**
     * Create directory
     *
     * @param string $path
     * @param int $permissions
     * @return bool
     * @throws FileSystemException
     */
    public function createDirectory($path, $permissions)
    {
        $result = @mkdir($this->getScheme() . $path, $permissions, true);
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'Directory "%1" cannot be created %2',
                    [$path, $this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Read directory
     *
     * @param string $path
     * @return string[]
     * @throws FileSystemException
     */
    public function readDirectory($path)
    {
        try {
            $flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;
            $iterator = new \FilesystemIterator($path, $flags);
            $result = [];
            /** @var \FilesystemIterator $file */
            foreach ($iterator as $file) {
                $result[] = $file->getPathname();
            }
            sort($result);
            return $result;
        } catch (\Exception $e) {
            throw new FileSystemException(new \Magento\Framework\Phrase($e->getMessage()), $e);
        }
    }

    /**
     * Search paths by given regex
     *
     * @param string $pattern
     * @param string $path
     * @return string[]
     * @throws FileSystemException
     */
    public function search($pattern, $path)
    {
        clearstatcache();
        $globPattern = rtrim($path, '/') . '/' . ltrim($pattern, '/');
        $result = @glob($globPattern, GLOB_BRACE);
        return is_array($result) ? $result : [];
    }

    /**
     * Renames a file or directory
     *
     * @param string $oldPath
     * @param string $newPath
     * @param DriverInterface|null $targetDriver
     * @return bool
     * @throws FileSystemException
     */
    public function rename($oldPath, $newPath, DriverInterface $targetDriver = null)
    {
        $result = false;
        $targetDriver = $targetDriver ?: $this;
        if (get_class($targetDriver) == get_class($this)) {
            $result = @rename($this->getScheme() . $oldPath, $newPath);
        } else {
            $content = $this->fileGetContents($oldPath);
            if (false !== $targetDriver->filePutContents($newPath, $content)) {
                $result = $this->deleteFile($newPath);
            }
        }
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'The "%1" path cannot be renamed into "%2" %3',
                    [$oldPath, $newPath, $this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Copy source into destination
     *
     * @param string $source
     * @param string $destination
     * @param DriverInterface|null $targetDriver
     * @return bool
     * @throws FileSystemException
     */
    public function copy($source, $destination, DriverInterface $targetDriver = null)
    {
        $targetDriver = $targetDriver ?: $this;
        if (get_class($targetDriver) == get_class($this)) {
            $result = @copy($this->getScheme() . $source, $destination);
        } else {
            $content = $this->fileGetContents($source);
            $result = $targetDriver->filePutContents($destination, $content);
        }
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'The file or directory "%1" cannot be copied to "%2" %3',
                    [
                        $source,
                        $destination,
                        $this->getWarningMessage(),
                    ]
                )
            );
        }
        return $result;
    }

    /**
     * Create symlink on source and place it into destination
     *
     * @param string $source
     * @param string $destination
     * @param DriverInterface|null $targetDriver
     * @return bool
     * @throws FileSystemException
     */
    public function symlink($source, $destination, DriverInterface $targetDriver = null)
    {
        $result = false;
        if (get_class($targetDriver) == get_class($this)) {
            $result = @symlink($this->getScheme() . $source, $destination);
        }
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'Cannot create a symlink for "%1" and place it to "%2" %3',
                    [
                        $source,
                        $destination,
                        $this->getWarningMessage(),
                    ]
                )
            );
        }
        return $result;
    }

    /**
     * Delete file
     *
     * @param string $path
     * @return bool
     * @throws FileSystemException
     */
    public function deleteFile($path)
    {
        $result = @unlink($this->getScheme() . $path);
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('The file "%1" cannot be deleted %2', [$path, $this->getWarningMessage()])
            );
        }
        return $result;
    }

    /**
     * Recursive delete directory
     *
     * @param string $path
     * @return bool
     * @throws FileSystemException
     */
    public function deleteDirectory($path)
    {
        $flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;
        $iterator = new \FilesystemIterator($path, $flags);
        /** @var \FilesystemIterator $entity */
        foreach ($iterator as $entity) {
            if ($entity->isDir()) {
                $this->deleteDirectory($entity->getPathname());
            } else {
                $this->deleteFile($entity->getPathname());
            }
        }
        $result = @rmdir($this->getScheme() . $path);
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'The directory "%1" cannot be deleted %2',
                    [$path, $this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Change permissions of given path
     *
     * @param string $path
     * @param int $permissions
     * @return bool
     * @throws FileSystemException
     */
    public function changePermissions($path, $permissions)
    {
        $result = @chmod($this->getScheme() . $path, $permissions);
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'Cannot change permissions for path "%1" %2',
                    [$path, $this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Sets access and modification time of file.
     *
     * @param string $path
     * @param int|null $modificationTime
     * @return bool
     * @throws FileSystemException
     */
    public function touch($path, $modificationTime = null)
    {
        if (!$modificationTime) {
            $result = @touch($this->getScheme() . $path);
        } else {
            $result = @touch($this->getScheme() . $path, $modificationTime);
        }
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'The file or directory "%1" cannot be touched %2',
                    [$path, $this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Write contents to file in given path
     *
     * @param string $path
     * @param string $content
     * @param string|null $mode
     * @return int The number of bytes that were written.
     * @throws FileSystemException
     */
    public function filePutContents($path, $content, $mode = null)
    {
        $result = @file_put_contents($this->getScheme() . $path, $content, $mode);
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'The specified "%1" file could not be written %2',
                    [$path, $this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Open file
     *
     * @param string $path
     * @param string $mode
     * @return resource file
     * @throws FileSystemException
     */
    public function fileOpen($path, $mode)
    {
        $result = @fopen($this->getScheme() . $path, $mode);
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('File "%1" cannot be opened %2', [$path, $this->getWarningMessage()])
            );
        }
        return $result;
    }

    /**
     * Reads the line content from file pointer (with specified number of bytes from the current position).
     *
     * @param resource $resource
     * @param int $length
     * @param string $ending [optional]
     * @return string
     * @throws FileSystemException
     */
    public function fileReadLine($resource, $length, $ending = null)
    {
        $result = @stream_get_line($resource, $length, $ending);
        if (false === $result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('File cannot be read %1', [$this->getWarningMessage()])
            );
        }
        return $result;
    }

    /**
     * Reads the specified number of bytes from the current position.
     *
     * @param resource $resource
     * @param int $length
     * @return string
     * @throws FileSystemException
     */
    public function fileRead($resource, $length)
    {
        $result = @fread($resource, $length);
        if ($result === false) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('File cannot be read %1', [$this->getWarningMessage()])
            );
        }
        return $result;
    }

    /**
     * Reads one CSV row from the file
     *
     * @param resource $resource
     * @param int $length [optional]
     * @param string $delimiter [optional]
     * @param string $enclosure [optional]
     * @param string $escape [optional]
     * @return array|bool|null
     * @throws FileSystemException
     */
    public function fileGetCsv($resource, $length = 0, $delimiter = ',', $enclosure = '"', $escape = '\\')
    {
        $result = @fgetcsv($resource, $length, $delimiter, $enclosure, $escape);
        if ($result === null) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('Wrong CSV handle %1', [$this->getWarningMessage()])
            );
        }
        return $result;
    }

    /**
     * Returns position of read/write pointer
     *
     * @param resource $resource
     * @return int
     * @throws FileSystemException
     */
    public function fileTell($resource)
    {
        $result = @ftell($resource);
        if ($result === null) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase('Error occurred during execution %1', [$this->getWarningMessage()])
            );
        }
        return $result;
    }

    /**
     * Seeks to the specified offset
     *
     * @param resource $resource
     * @param int $offset
     * @param int $whence
     * @return int
     * @throws FileSystemException
     */
    public function fileSeek($resource, $offset, $whence = SEEK_SET)
    {
        $result = @fseek($resource, $offset, $whence);
        if ($result === -1) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'Error occurred during execution of fileSeek %1',
                    [$this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Returns true if pointer at the end of file or in case of exception
     *
     * @param resource $resource
     * @return boolean
     */
    public function endOfFile($resource)
    {
        return feof($resource);
    }

    /**
     * Close file
     *
     * @param resource $resource
     * @return boolean
     * @throws FileSystemException
     */
    public function fileClose($resource)
    {
        $result = @fclose($resource);
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'Error occurred during execution of fileClose %1',
                    [$this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Writes data to file
     *
     * @param resource $resource
     * @param string $data
     * @return int
     * @throws FileSystemException
     */
    public function fileWrite($resource, $data)
    {
        $result = @fwrite($resource, $data);
        if (false === $result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'Error occurred during execution of fileWrite %1',
                    [$this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Writes one CSV row to the file.
     *
     * @param resource $resource
     * @param array $data
     * @param string $delimiter
     * @param string $enclosure
     * @return int
     * @throws FileSystemException
     */
    public function filePutCsv($resource, array $data, $delimiter = ',', $enclosure = '"')
    {
        /**
         * Security enhancement for CSV data processing by Excel-like applications.
         * @see https://bugzilla.mozilla.org/show_bug.cgi?id=1054702
         *
         * @var $value string|\Magento\Framework\Phrase
         */
        foreach ($data as $key => $value) {
            if (!is_string($value)) {
                $value = (string)$value;
            }
            if (isset($value[0]) && $value[0] === '=') {
                $data[$key] = ' ' . $value;
            }
        }

        $result = @fputcsv($resource, $data, $delimiter, $enclosure);
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'Error occurred during execution of filePutCsv %1',
                    [$this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Flushes the output
     *
     * @param resource $resource
     * @return bool
     * @throws FileSystemException
     */
    public function fileFlush($resource)
    {
        $result = @fflush($resource);
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'Error occurred during execution of fileFlush %1',
                    [$this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Lock file in selected mode
     *
     * @param resource $resource
     * @param int $lockMode
     * @return bool
     * @throws FileSystemException
     */
    public function fileLock($resource, $lockMode = LOCK_EX)
    {
        $result = @flock($resource, $lockMode);
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'Error occurred during execution of fileLock %1',
                    [$this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * Unlock file
     *
     * @param resource $resource
     * @return bool
     * @throws FileSystemException
     */
    public function fileUnlock($resource)
    {
        $result = @flock($resource, LOCK_UN);
        if (!$result) {
            throw new FileSystemException(
                new \Magento\Framework\Phrase(
                    'Error occurred during execution of fileUnlock %1',
                    [$this->getWarningMessage()]
                )
            );
        }
        return $result;
    }

    /**
     * @param string $basePath
     * @param string $path
     * @param string|null $scheme
     * @return string
     */
    public function getAbsolutePath($basePath, $path, $scheme = null)
    {
        return $this->getScheme($scheme) . $basePath . ltrim($this->fixSeparator($path), '/');
    }

    /**
     * Retrieves relative path
     *
     * @param string $basePath
     * @param string $path
     * @return string
     */
    public function getRelativePath($basePath, $path = null)
    {
        $path = $this->fixSeparator($path);
        if (strpos($path, $basePath) === 0 || $basePath == $path . '/') {
            $result = substr($path, strlen($basePath));
        } else {
            $result = $path;
        }
        return $result;
    }

    /**
     * Fixes path separator
     * Utility method.
     *
     * @param string $path
     * @return string
     */
    protected function fixSeparator($path)
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Return path with scheme
     *
     * @param null|string $scheme
     * @return string
     */
    protected function getScheme($scheme = null)
    {
        return $scheme ? $scheme . '://' : '';
    }

    /**
     * Read directory recursively
     *
     * @param string $path
     * @return string[]
     * @throws FileSystemException
     */
    public function readDirectoryRecursively($path = null)
    {
        $result = [];
        $flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, $flags),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            /** @var \FilesystemIterator $file */
            foreach ($iterator as $file) {
                $result[] = $file->getPathname();
            }
        } catch (\Exception $e) {
            throw new FileSystemException(new \Magento\Framework\Phrase($e->getMessage()), $e);
        }
        return $result;
    }

    /**
     * Get real path
     *
     * @param string $path
     *
     * @return string|bool
     */
    public function getRealPath($path)
    {
        return realpath($path);
    }

    /**
     * Return correct path for link
     *
     * @param string $path
     * @return mixed
     */
    public function getRealPathSafety($path)
    {
        if (strpos($path, DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR) === false) {
            return $path;
        }
        $pathParts = explode(DIRECTORY_SEPARATOR, $path);
        $realPath = [];
        foreach ($pathParts as $pathPart) {
            if ($pathPart == '.') {
                continue;
            }
            if ($pathPart == '..') {
                array_pop($realPath);
                continue;
            }
            $realPath[] = $pathPart;
        }
        return implode(DIRECTORY_SEPARATOR, $realPath);
    }
}
