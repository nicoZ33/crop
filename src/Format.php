<?php

namespace FemtoPixel\ImageResizer;

use FemtoPixel\ImageResizer\Exception\FormatFullModeNotAvailable;

/**
 * Class Format
 * @package FemtoPixel\ImageResizer
 */
class Format implements \ArrayAccess
{
    const WIDTH = 'width';
    const HEIGHT = 'height';

    const FULL = 'full';
    const FULL_WIDTH = 'width';
    const FULL_HEIGHT = 'height';
    const FULL_CROPPED = 'cropped';
    const FULL_NONE = 'none';

    const DEFAULT_IMAGE = 'default';

    private $data = array();

    /**
     * @param float $width
     * @param float $height
     * @param string $full
     * @param null $defaultImage
     * @throws FormatFullModeNotAvailable
     */
    public function __construct($width, $height, $full = self::FULL_NONE, $defaultImage = null)
    {
        $this->setFullMode($full)
            ->setHeight($height)
            ->setWidth($width);
        if ($defaultImage) {
            $this->setDefaultImage($defaultImage);
        }
    }

    /**
     * @param float $width
     * @return $this
     */
    public function setWidth($width)
    {
        $this->data[self::WIDTH] = (float)$width;
        return $this;
    }

    /**
     * @param float $height
     * @return $this
     */
    public function setHeight($height)
    {
        $this->data[self::HEIGHT] = (float)$height;
        return $this;
    }

    /**
     * @param string $fullMode
     * @return $this
     * @throws FormatFullModeNotAvailable
     */
    public function setFullMode($fullMode = self::FULL_NONE)
    {
        $allowed = array(
            self::FULL_NONE => self::FULL_NONE,
            self::FULL_CROPPED => self::FULL_CROPPED,
            self::FULL_HEIGHT => self::FULL_HEIGHT,
            self::FULL_WIDTH => self::FULL_WIDTH,
        );
        if (!isset($allowed[$fullMode])) {
            throw new FormatFullModeNotAvailable("Format '$fullMode' is not allowed in : " . implode(', ', $allowed));
        }
        $this->data[self::FULL] = $fullMode;
        return $this;
    }

    /**
     * @return float
     */
    public function getWidth()
    {
        return $this->data[self::WIDTH];
    }

    /**
     * @return float
     */
    public function getHeight()
    {
        return $this->data[self::HEIGHT];
    }

    /**
     * @return string
     */
    public function getFullMode()
    {
        return $this->data[self::FULL];
    }

    /**
     * @param string|null $defaultImage
     * @return $this
     */
    public function setDefaultImage($defaultImage = null)
    {
        $this->data[self::DEFAULT_IMAGE] = (string)$defaultImage;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDefaultImage()
    {
        return $this->data[self::DEFAULT_IMAGE];
    }

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    /**
     * @param string $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }
}
