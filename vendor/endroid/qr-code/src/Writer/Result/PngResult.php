<?php

declare(strict_types=1);

namespace Endroid\QrCode\Writer\Result;

use Endroid\QrCode\Matrix\MatrixInterface;

final class PngResult extends AbstractResult
{
    /** @var mixed */
    private $image;

    /** @param mixed $image */
    public function __construct(MatrixInterface $matrix, $image)
    {
        parent::__construct($matrix);

        $this->image = $image;
    }

    /** @return mixed */
    public function getImage()
    {
        return $this->image;
    }

    public function getString(): string
    {
        ob_start();
        imagepng($this->image);

        return strval(ob_get_clean());
    }

    public function getMimeType(): string
    {
        return 'image/png';
    }
}
