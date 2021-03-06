<?php
declare(strict_types = 1);

namespace FemtoPixel\Crop;

/**
 * Class ResizeEngine
 * @package FemtoPixel\Crop
 */
class ResizeEngine
{
    const OUTPUT_FILE = 'file';
    const OUTPUT_BROWSER = 'browser';
    const OUTPUT_RETURN = 'return';

    const INFO_WIDTH = 'width';
    const INFO_HEIGHT = 'height';
    const INFO_MIME = 'mime';

    private $libGd;

    /**
     * image resize function
     * @param string $file - file name to resize
     * @param int $width - new image width
     * @param int $height - new image height
     * @param bool $crop - crop image to requested size
     * @param string $output - name of the new file (include path if needed)
     * @return boolean|resource
     */
    public function resize(string $file, int $width = 0, int $height = 0, bool $crop = false, string $output = self::OUTPUT_BROWSER)
    {
        $libGd = $this->getGd();
        # Setting defaults and meta
        $info = $libGd->getimagesize($file);
        list($widthOld, $heightOld) = $info;
        $cropHeight = $cropWidth = 0;

        $finalWidth = ($width <= 0) ? $widthOld : $width;
        $finalHeight = ($height <= 0) ? $heightOld : $height;
        # Calculating proportionality

        if ($crop) {
            $widthX = $widthOld / $width;
            $heightX = $heightOld / $height;

            $realWidth = min($widthX, $heightX);
            $cropWidth = ($widthOld - $width * $realWidth) / 2;
            $cropHeight = ($heightOld - $height * $realWidth) / 2;
        }

        # Loading image to memory according to type
        $image = $this->getResource($info[2], $file);
        if (!$image) {
            return false;
        }

        # This is the resizing/resampling/transparency-preserving magic
        $imageResized = $this->prepareImageResized($finalWidth, $finalHeight, $info[2], $image);

        $libGd->imagecopyresampled(
            $imageResized,
            $image,
            0,
            0,
            $cropWidth,
            $cropHeight,
            $finalWidth,
            $finalHeight,
            $widthOld - 2 * $cropWidth,
            $heightOld - 2 * $cropHeight
        );

        # Preparing a method of providing result
        switch (strtolower($output)) {
            case self::OUTPUT_BROWSER:
                $mime = $libGd->image_type_to_mime_type($info[2]);
                $this->phpHeader("Content-Type: $mime");
                $output = null;
                break;
            case self::OUTPUT_FILE:
                $output = $file;
                break;
            case self::OUTPUT_RETURN:
                return $imageResized;
                break;
            default:
                return false;
        }
        return $this->render($info[2], $imageResized, $output);
    }

    /**
     * @param string $string
     * @param bool $replace
     * @param null $httpResponseCode
     * @codeCoverageIgnore
     */
    protected function phpHeader(string $string, bool $replace = true, $httpResponseCode = null)
    {
        header($string, $replace, $httpResponseCode);
    }

    /**
     * @param string $type
     * @param resource $resource
     * @param string|null $output
     * @return bool
     */
    protected function render(string $type, $resource, ?string $output = null) : bool
    {
        $libGd = $this->getGd();
        # Writing image according to type to the output destination and image quality
        $quality = 100;
        switch ($type) {
            case IMAGETYPE_GIF:
                $libGd->imagegif($resource, $output);
                break;
            case IMAGETYPE_JPEG:
                $libGd->imagejpeg($resource, $output, $quality);
                break;
            case IMAGETYPE_PNG:
                $quality = 9 - (int)((0.9 * $quality) / 10.0);
                $libGd->imagepng($resource, $output, $quality);
                break;
            default:
                return false;
        }
        return true;
    }

    /**
     * @param int $width
     * @param int $height
     * @param string $type
     * @param resource $image
     * @return resource
     */
    protected function prepareImageResized(int $width, int $height, string $type, $image)
    {
        $libGd = $this->getGd();
        $imageResized = $libGd->imagecreatetruecolor($width, $height);
        if (($type == IMAGETYPE_GIF) || ($type == IMAGETYPE_PNG)) {
            $transparency = $libGd->imagecolortransparent($image);
            $palletsize = $libGd->imagecolorstotal($image);

            if ($transparency >= 0 && $transparency < $palletsize) {
                $transparentColor = $libGd->imagecolorsforindex($image, $transparency);
                $transparency = $libGd->imagecolorallocate(
                    $imageResized,
                    $transparentColor['red'],
                    $transparentColor['green'],
                    $transparentColor['blue']
                );
                $libGd->imagefill($imageResized, 0, 0, $transparency);
                $libGd->imagecolortransparent($imageResized, $transparency);
            } elseif ($type == IMAGETYPE_PNG) {
                $libGd->imagealphablending($imageResized, false);
                $color = $libGd->imagecolorallocatealpha($imageResized, 0, 0, 0, 127);
                $libGd->imagefill($imageResized, 0, 0, $color);
                $libGd->imagesavealpha($imageResized, true);
            }
        }
        return $imageResized;
    }

    /**
     * @param string $type
     * @param string $file
     * @return bool|resource
     */
    protected function getResource(string $type, string $file)
    {
        $libGd = $this->getGd();
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = $libGd->imagecreatefromjpeg($file);
                break;
            case IMAGETYPE_GIF:
                $image = $libGd->imagecreatefromgif($file);
                break;
            case IMAGETYPE_PNG:
                $image = $libGd->imagecreatefrompng($file);
                break;
            default:
                return false;
        }
        return $image;
    }

    /**
     * @param string $filePath
     * @return string
     * @throws \Exception
     */
    protected function determineFormat(string $filePath) : string
    {
        $formatInfo = $this->getGd()->getimagesize($filePath);

        // non-image files will return false
        if ($formatInfo === false) {
            throw new \Exception("File is not a valid image: {$filePath}");
        }

        $mimeType = isset($formatInfo['mime']) ? $formatInfo['mime'] : '';

        switch ($mimeType) {
            case 'image/gif':
            case 'image/jpeg':
            case 'image/png':
                return $mimeType;
            default:
                throw new \Exception("Image format not supported: {$mimeType}");
        }
    }

    /**
     * @param string $filePath
     * @return string
     * @throws \Exception
     */
    protected function verifyFormatCompatibility(string $filePath) : string
    {
        $gdInfo = $this->getGd()->gd_info();
        $format = $this->determineFormat($filePath);

        switch ($format) {
            case 'image/gif':
                $isCompatible = (isset($gdInfo['GIF Create Support']) && $gdInfo['GIF Create Support']);
                break;
            case 'image/jpeg':
                $isCompatible = (isset($gdInfo['JPG Support']) || isset($gdInfo['JPEG Support']));
                break;
            case 'image/png':
                $isCompatible = (isset($gdInfo['PNG Support']) && $gdInfo['PNG Support']);
                break;
            default:
                $isCompatible = false;
        }

        if (!$isCompatible) {
            // one last check for "JPEG" instead
            $isCompatible = (isset($gdInfo['JPEG Support']) && $gdInfo['JPEG Support']);

            if (!$isCompatible) {
                throw new \Exception("Your GD installation does not support {$format} image types");
            }
        }
        return $format;
    }

    /**
     * @param string $filePath
     * @return array
     * @throws \Exception
     */
    public function getImageInfo(string $filePath) : array
    {
        $libGd = $this->getGd();
        $format = $this->verifyFormatCompatibility($filePath);
        switch ($format) {
            case 'image/gif':
                $resource = $libGd->imagecreatefromgif($filePath);
                break;
            case 'image/jpeg':
                $resource = $libGd->imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $resource = $libGd->imagecreatefrompng($filePath);
                break;
            default:
                throw new \Exception("Your GD installation does not support {$format} image types");
        }

        return array(
            self::INFO_WIDTH => $libGd->imagesx($resource),
            self::INFO_HEIGHT => $libGd->imagesy($resource),
            self::INFO_MIME => $format,
        );
    }

    /**
     * @return ResizeEngine\Gd
     * @codeCoverageIgnore
     */
    protected function getGd() : ResizeEngine\Gd
    {
        return $this->libGd = $this->libGd ?: new ResizeEngine\Gd();
    }
}
