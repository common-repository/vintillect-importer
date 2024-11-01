<?php
namespace VintillectImporter\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Vintillect_CollageMaker {
  private $maxImages = 9;
  private $canvasWidth;
  private $canvasHeight;
  private $spacing;
  private $canvasRed = 255;
  private $canvasGreen = 255;
  private $canvasBlue = 255;
  private $canvasAlpha = 0; // for opacity (0 is opaque, 127 is transparent)
  private $bgColor;
  private $canvasImg;

  public function __construct($canvasWidth, $canvasHeight, $spacing=1) {
    $this->canvasWidth = $canvasWidth;
    $this->canvasHeight = $canvasHeight;
    $this->spacing = $spacing;

    $this->canvasImg = imagecreatetruecolor($this->canvasWidth, $this->canvasHeight);
    $this->bgColor = imagecolorallocate($this->canvasImg, $this->canvasRed, $this->canvasGreen, $this->canvasBlue);
    imagefill($this->canvasImg, 0, 0, $this->bgColor);
  }

  public function __destruct()
  {
    imagedestroy($this->canvasImg);
  }

  public function setCanvasColors($red, $green, $blue, $alpha) {
    $this->canvasRed = $red;
    $this->canvasGreen = $green;
    $this->canvasBlue = $blue;
    $this->canvasAlpha = $alpha;
    $this->resetFillBackground();
  }

  public function setSpacing($spacing) {
    $this->spacing = $spacing;
  }

  public function resetFillBackground() {
    $this->bgColor = imagecolorallocatealpha($this->canvasImg, $this->canvasRed, $this->canvasGreen, $this->canvasBlue, $this->canvasAlpha);
    imagefill($this->canvasImg, 0, 0, $this->bgColor);
  }

  public function saveImg($filePath) {
    imagesavealpha($this->canvasImg, true);
    imagepng($this->canvasImg, $filePath);
  }

  public function getImageFromExt($imagePath, $ext=null) {
    // If $ext is not provided, it will extract it from the end of the image path, which could be a file or URL (including an unwanted querystring).
    if (!$ext) {
      $ext = substr($imagePath, strrpos($imagePath, '.') + 1);
      $queryStartIdx = strpos($ext, '?');
      $ext = ($queryStartIdx === false) ? $ext : substr($ext, 0, $queryStartIdx);
    }
    
    $img = null;
  
    switch ($ext) {
      case 'bmp':
        $img = imagecreatefrombmp($imagePath);
        break;
      case 'gif':
        $img = imagecreatefromgif($imagePath);
        break;
      case 'jpg':
      case 'jpeg':
        $img = imagecreatefromjpeg($imagePath);
        break;
      case 'png':
        $img = imagecreatefrompng($imagePath);
        break;
      case 'webp':
        $img = imagecreatefromwebp($imagePath);
        break;
      default:
        // log bad file type
        break;
    }
  
    return $img;
  }
  
  // uses PHP's constants for image types; which can come from getimagesize()
  public function getImageFromImageType($imagePath, $imageType) {
    $img = null;
    
    switch ($imageType) {
      case IMG_BMP:
        $img = imagecreatefrombmp($imagePath);
        break;
      case IMG_GIF:
        $img = imagecreatefromgif($imagePath);
        break;
      case IMG_JPG:
      case IMG_JPEG:
        $img = imagecreatefromjpeg($imagePath);
        break;
      case IMG_PNG:
        $img = imagecreatefrompng($imagePath);
        break;
      case IMG_WEBP:
      // case IMG_WEBP_LOSSLESS: // only available starting in PHP 8.1
        $img = imagecreatefromwebp($imagePath);
        break;
      default:
        // log bad file type
        break;
    }
  
    return $img;
  }

  public function makeSourceImagesAndSizesFromPaths($imagePaths) {
    $imgArr = [];
    $srcImgSizes = [];

    for ($i=0; $i < count($imagePaths) && $i < $this->maxImages; $i++) {
      $imagePath = $imagePaths[$i];
      $imgSize = getimagesize($imagePath);
      $img = $this->getImageFromImageType($imagePath, $imgSize[2]);
  
      if (! $img) {
        // log error
        continue;
      }

      $imgArr[] = $img;
      $srcImgSizes[] = [$imgSize[0], $imgSize[1]];
    }

    return [ $imgArr, $srcImgSizes ];
  }
  
  // returns points array [xStart, xEnd, yStart, yEnd] of source image to copy from
  public function getMiddleCropDimenstions($destWidth, $destHeight, $srcWidth, $srcHeight) {
    // $destWidth and $destHeight does not mean the canvas's size. It means a smaller size on canvas that you want to paint onto.
    $destAspectRatio = $destWidth / $destHeight;
    $srcAspectRatio = $srcWidth / $srcHeight;

    // If destination is wider than the source is wide by ratio, then source height is larger by ratio.
    // Therefore, it needs to copy from a vertical middle of the source.
    if ($destAspectRatio > $srcAspectRatio) {
      $xStart = 0;
      $srcHeightNew = $srcWidth / $destAspectRatio;
      $yStart = ($srcHeight - $srcHeightNew) / 2;
      return [$xStart, $yStart, $srcWidth, $srcHeightNew];
    }

    // If destination is narrower than the source is wide by ratio, then source height is smaller by ratio.
    // Therefore, it needs to copy from a horizontal middle of the source.
    elseif ($destAspectRatio < $srcAspectRatio) {
      $yStart = 0;
      $srcWidthNew = $destAspectRatio * $srcHeight;
      $xStart = ($srcWidth - $srcWidthNew) / 2;
      return [$xStart, $yStart, $srcWidthNew, $srcHeight];
    }

    // If the $destAspectRatio and $srcAspectRatio are the same, then no crop is necessary.
    return [0, 0, $srcWidth, $srcHeight];
  }

  public function copyImagesToCanvas($srcImgArr, $srcImgSizes) {
    $srcImgLength = count($srcImgArr);

    switch ($srcImgLength) {
      case 1:
        $this->copy1ImageToCanvas($srcImgArr, $srcImgSizes);
        break;
      case 2:
        $this->copy2ImagesToCanvas($srcImgArr, $srcImgSizes);
        break;
      case 3:
        $this->copy3ImagesToCanvas($srcImgArr, $srcImgSizes);
        break;
      case 4:
        $this->copy4ImagesToCanvas($srcImgArr, $srcImgSizes);
        break;
      case 5:
        $this->copy5ImagesToCanvas($srcImgArr, $srcImgSizes);
        break;
      case 6:
        $this->copy6ImagesToCanvas($srcImgArr, $srcImgSizes);
        break;
      case 7:
        $this->copy7ImagesToCanvas($srcImgArr, $srcImgSizes);
        break;
      case 8:
        $this->copy8ImagesToCanvas($srcImgArr, $srcImgSizes);
        break;
      case 9:
        $this->copy9ImagesToCanvas($srcImgArr, $srcImgSizes);
        break;
      default:
        // log error
        break;
    }
  }


  /*-----------------------------
      The following are functions to copy from source images to the collage canvas.
      $srcImgArr is PHP's image bytes. Use $this->getImageFromExt() or $this->getImageFromImageType() to get the image.
      $srcImgSizes is an array of [width, height] from $srcImgArr elements.
    ----------------------------- */
  
  // Makes a collage of 1 image.
  public function copy1ImageToCanvas($srcImgArr, $srcImgSizes) {
    $srcImgArrAreaPoints = [ [0, 0, $srcImgSizes[0][0], $srcImgSizes[0][1] ] ];
    imagecopyresampled($this->canvasImg, $srcImgArr[0], 0, 0, 0, 0, $this->canvasWidth, $this->canvasHeight, $srcImgArrAreaPoints[0][2], $srcImgArrAreaPoints[0][3]);
    // $srcImgArrAreaPoints[0][2] is the first (and only) element match $srcImgArr element. Index 2 is the end X matching the first image's width. Index 3 is the end X matching the first image's height.
  }


  // Makes a collage of 2 images. One row, split column width.
  public function copy2ImagesToCanvas($imgArr, $srcImgSizes) {
    $destWidth = ($this->canvasWidth / 2);
    $imgArrAreaPoints = [
      $this->getMiddleCropDimenstions($destWidth, $this->canvasHeight, $srcImgSizes[0][0], $srcImgSizes[0][1]),
      $this->getMiddleCropDimenstions($destWidth, $this->canvasHeight, $srcImgSizes[1][0], $srcImgSizes[1][1])
    ];

    imagecopyresampled($this->canvasImg, $imgArr[0], 0,                   0, $imgArrAreaPoints[0][0], $imgArrAreaPoints[0][1], $destWidth, $this->canvasHeight, $imgArrAreaPoints[0][2], $imgArrAreaPoints[0][3]);

    $secondColumnStartX = $destWidth + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[1], $secondColumnStartX, 0, $imgArrAreaPoints[1][0], $imgArrAreaPoints[1][1], $destWidth + 1, $this->canvasHeight, $imgArrAreaPoints[1][2], $imgArrAreaPoints[1][3]);
  }


  // Makes a collage of 3 images. It has 2 rows; first image covers entire row; second and third images split the second row into 2 columns.
  public function copy3ImagesToCanvas($imgArr, $srcImgSizes) {
    $rowHeight = ($this->canvasHeight / 2);
    $secondRowWidth = ($this->canvasWidth / 2);
    $imgArrAreaPoints = [
      $this->getMiddleCropDimenstions($this->canvasWidth, $rowHeight, $srcImgSizes[0][0], $srcImgSizes[0][1]),
      $this->getMiddleCropDimenstions($secondRowWidth, $rowHeight, $srcImgSizes[1][0], $srcImgSizes[1][1]),
      $this->getMiddleCropDimenstions($secondRowWidth, $rowHeight, $srcImgSizes[2][0], $srcImgSizes[2][1])
    ];

    imagecopyresampled($this->canvasImg, $imgArr[0], 0,                 0,                  $imgArrAreaPoints[0][0], $imgArrAreaPoints[0][1], $this->canvasWidth,  $rowHeight, $imgArrAreaPoints[0][2], $imgArrAreaPoints[0][3]);


    $secondRowWidth = ($this->canvasWidth / 2);
    $secondRowStartY = $rowHeight + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[1], 0,                   $secondRowStartY, $imgArrAreaPoints[1][0], $imgArrAreaPoints[1][1], $secondRowWidth, $rowHeight, $imgArrAreaPoints[1][2], $imgArrAreaPoints[1][3]);

    $secondColumnStartX = $secondRowWidth + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[2], $secondColumnStartX, $secondRowStartY, $imgArrAreaPoints[2][0], $imgArrAreaPoints[2][1], $secondRowWidth, $rowHeight, $imgArrAreaPoints[2][2], $imgArrAreaPoints[2][3]);
  }


  // Makes a collage of 4 images with 2 rows and 2 columns. The image cells have equal width and height.
  public function copy4ImagesToCanvas($imgArr, $srcImgSizes) {
    $columnWidth = ($this->canvasWidth / 2);
    $rowHeight = ($this->canvasHeight / 2);
    $imgArrAreaPoints = [
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[0][0], $srcImgSizes[0][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[1][0], $srcImgSizes[1][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[2][0], $srcImgSizes[2][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[3][0], $srcImgSizes[3][1])
    ];

    imagecopyresampled($this->canvasImg, $imgArr[0], 0,                   0,                $imgArrAreaPoints[0][0], $imgArrAreaPoints[0][1], $columnWidth, $rowHeight, $imgArrAreaPoints[0][2], $imgArrAreaPoints[0][3]);

    $secondColumnStartX = $columnWidth + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[1], $secondColumnStartX, 0,                $imgArrAreaPoints[1][0], $imgArrAreaPoints[1][1], $columnWidth, $rowHeight, $imgArrAreaPoints[1][2], $imgArrAreaPoints[1][3]);


    $secondRowStartY = $rowHeight + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[2], 0,                   $secondRowStartY, $imgArrAreaPoints[2][0], $imgArrAreaPoints[2][1], $columnWidth, $rowHeight, $imgArrAreaPoints[2][2], $imgArrAreaPoints[2][3]);
    imagecopyresampled($this->canvasImg, $imgArr[3], $secondColumnStartX, $secondRowStartY, $imgArrAreaPoints[3][0], $imgArrAreaPoints[3][1], $columnWidth, $rowHeight, $imgArrAreaPoints[3][2], $imgArrAreaPoints[3][3]);
  }


  // Makes a collage of 5 images with 2 rows. First row has 2 image cells. Second row has 3 image cells.
  public function copy5ImagesToCanvas($imgArr, $srcImgSizes) {
    $firstRowColumnWidth = ($this->canvasWidth / 2);
    $secondRowColumnWidth = ($this->canvasWidth / 3);
    $rowHeight = ($this->canvasHeight / 2);
    $imgArrAreaPoints = [
      $this->getMiddleCropDimenstions($firstRowColumnWidth, $rowHeight, $srcImgSizes[0][0], $srcImgSizes[0][1]),
      $this->getMiddleCropDimenstions($firstRowColumnWidth, $rowHeight, $srcImgSizes[1][0], $srcImgSizes[1][1]),
      $this->getMiddleCropDimenstions($secondRowColumnWidth, $rowHeight, $srcImgSizes[2][0], $srcImgSizes[2][1]),
      $this->getMiddleCropDimenstions($secondRowColumnWidth, $rowHeight, $srcImgSizes[3][0], $srcImgSizes[3][1]),
      $this->getMiddleCropDimenstions($secondRowColumnWidth, $rowHeight, $srcImgSizes[4][0], $srcImgSizes[4][1])
    ];

    imagecopyresampled($this->canvasImg, $imgArr[0], 0,                           0,            $imgArrAreaPoints[0][0], $imgArrAreaPoints[0][1], $firstRowColumnWidth, $rowHeight, $imgArrAreaPoints[0][2], $imgArrAreaPoints[0][3]);

    $firstRowSecondColumnStartX = $firstRowColumnWidth + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[1], $firstRowSecondColumnStartX, 0,            $imgArrAreaPoints[1][0], $imgArrAreaPoints[1][1], $firstRowColumnWidth, $rowHeight, $imgArrAreaPoints[1][2], $imgArrAreaPoints[1][3]);


    $secondRowStartY = $rowHeight + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[2], 0,                       $secondRowStartY, $imgArrAreaPoints[2][0], $imgArrAreaPoints[2][1], $secondRowColumnWidth, $rowHeight, $imgArrAreaPoints[2][2], $imgArrAreaPoints[2][3]);

    $secondRowSecondColumnX = $secondRowColumnWidth + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[3], $secondRowSecondColumnX, $secondRowStartY, $imgArrAreaPoints[3][0], $imgArrAreaPoints[3][1], $secondRowColumnWidth, $rowHeight, $imgArrAreaPoints[3][2], $imgArrAreaPoints[3][3]);

    $secondRowThirdColumnX = ($secondRowColumnWidth + $this->spacing) * 2;
    imagecopyresampled($this->canvasImg, $imgArr[4], $secondRowThirdColumnX,  $secondRowStartY, $imgArrAreaPoints[4][0], $imgArrAreaPoints[4][1], $secondRowColumnWidth, $rowHeight, $imgArrAreaPoints[4][2], $imgArrAreaPoints[4][3]);
  }


  // Makes a collage of 6 images with 2 rows. First row has 2 image cells. Second row has 3 image cells.
  public function copy6ImagesToCanvas($imgArr, $srcImgSizes) {
    $columnWidth = ($this->canvasWidth / 3);
    $rowHeight = ($this->canvasHeight / 2);
    $imgArrAreaPoints = [
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[0][0], $srcImgSizes[0][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[1][0], $srcImgSizes[1][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[2][0], $srcImgSizes[2][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[3][0], $srcImgSizes[3][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[4][0], $srcImgSizes[4][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[5][0], $srcImgSizes[5][1])
    ];

    imagecopyresampled($this->canvasImg, $imgArr[0], 0, 0,                             $imgArrAreaPoints[0][0], $imgArrAreaPoints[0][1], $columnWidth, $rowHeight, $imgArrAreaPoints[0][2], $imgArrAreaPoints[0][3]);

    $secondColumnX = $columnWidth + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[1], $secondColumnX, 0,                $imgArrAreaPoints[1][0], $imgArrAreaPoints[1][1], $columnWidth, $rowHeight, $imgArrAreaPoints[1][2], $imgArrAreaPoints[1][3]);

    $thirdColumnX = ($columnWidth + $this->spacing) * 2;
    imagecopyresampled($this->canvasImg, $imgArr[2], $thirdColumnX,  0,                $imgArrAreaPoints[2][0], $imgArrAreaPoints[2][1], $columnWidth, $rowHeight, $imgArrAreaPoints[2][2], $imgArrAreaPoints[2][3]);


    $secondRowStartY = $rowHeight + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[3], 0,              $secondRowStartY, $imgArrAreaPoints[3][0], $imgArrAreaPoints[3][1], $columnWidth, $rowHeight, $imgArrAreaPoints[3][2], $imgArrAreaPoints[3][3]);
    imagecopyresampled($this->canvasImg, $imgArr[4], $secondColumnX, $secondRowStartY, $imgArrAreaPoints[4][0], $imgArrAreaPoints[4][1], $columnWidth, $rowHeight, $imgArrAreaPoints[4][2], $imgArrAreaPoints[4][3]);
    imagecopyresampled($this->canvasImg, $imgArr[5], $thirdColumnX,  $secondRowStartY, $imgArrAreaPoints[5][0], $imgArrAreaPoints[5][1], $columnWidth, $rowHeight, $imgArrAreaPoints[5][2], $imgArrAreaPoints[5][3]);
  }


  // Makes a collage of 7 images with 3 rows. First row has 2 image cells. Second row has 2 image cells. Second row has 3 image cells.
  public function copy7ImagesToCanvas($imgArr, $srcImgSizes) {
    $first2RowsColumnWidth = ($this->canvasWidth / 2);
    $thirdRowColumnWidth = ($this->canvasWidth / 3);
    $rowHeight = ($this->canvasHeight / 3);
    $imgArrAreaPoints = [
      $this->getMiddleCropDimenstions($first2RowsColumnWidth, $rowHeight, $srcImgSizes[0][0], $srcImgSizes[0][1]),
      $this->getMiddleCropDimenstions($first2RowsColumnWidth, $rowHeight, $srcImgSizes[1][0], $srcImgSizes[1][1]),
      $this->getMiddleCropDimenstions($first2RowsColumnWidth, $rowHeight, $srcImgSizes[2][0], $srcImgSizes[2][1]),
      $this->getMiddleCropDimenstions($first2RowsColumnWidth, $rowHeight, $srcImgSizes[3][0], $srcImgSizes[3][1]),
      $this->getMiddleCropDimenstions($thirdRowColumnWidth,   $rowHeight, $srcImgSizes[4][0], $srcImgSizes[4][1]),
      $this->getMiddleCropDimenstions($thirdRowColumnWidth,   $rowHeight, $srcImgSizes[5][0], $srcImgSizes[5][1]),
      $this->getMiddleCropDimenstions($thirdRowColumnWidth,   $rowHeight, $srcImgSizes[6][0], $srcImgSizes[6][1])
    ];

    imagecopyresampled($this->canvasImg, $imgArr[0], 0,                                0,                $imgArrAreaPoints[0][0], $imgArrAreaPoints[0][1], $first2RowsColumnWidth, $rowHeight, $imgArrAreaPoints[0][2], $imgArrAreaPoints[0][3]);

    $first2RowsSecondColumnStartX = $first2RowsColumnWidth + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[1], $first2RowsSecondColumnStartX,    0,                $imgArrAreaPoints[1][0], $imgArrAreaPoints[1][1], $first2RowsColumnWidth, $rowHeight, $imgArrAreaPoints[1][2], $imgArrAreaPoints[1][3]);


    $secondRowStartY = $rowHeight + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[2], 0,                                $secondRowStartY, $imgArrAreaPoints[2][0], $imgArrAreaPoints[2][1], $first2RowsColumnWidth, $rowHeight, $imgArrAreaPoints[2][2], $imgArrAreaPoints[2][3]);
    imagecopyresampled($this->canvasImg, $imgArr[3], $first2RowsSecondColumnStartX,    $secondRowStartY, $imgArrAreaPoints[3][0], $imgArrAreaPoints[3][1], $first2RowsColumnWidth, $rowHeight, $imgArrAreaPoints[3][2], $imgArrAreaPoints[3][3]);


    $thirdRowStartY = ($rowHeight + $this->spacing) * 2;
    imagecopyresampled($this->canvasImg, $imgArr[4], 0,                                $thirdRowStartY,  $imgArrAreaPoints[4][0], $imgArrAreaPoints[4][1], $thirdRowColumnWidth, $rowHeight, $imgArrAreaPoints[4][2], $imgArrAreaPoints[4][3]);

    $thirdRowSecondColumnStartX = $thirdRowColumnWidth + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[5], $thirdRowSecondColumnStartX,      $thirdRowStartY,  $imgArrAreaPoints[5][0], $imgArrAreaPoints[5][1], $thirdRowColumnWidth, $rowHeight, $imgArrAreaPoints[5][2], $imgArrAreaPoints[5][3]);

    $thirdRowThirdColumnStartX = ($thirdRowColumnWidth + $this->spacing) * 2;
    imagecopyresampled($this->canvasImg, $imgArr[6], $thirdRowThirdColumnStartX,       $thirdRowStartY,  $imgArrAreaPoints[6][0], $imgArrAreaPoints[6][1], $thirdRowColumnWidth, $rowHeight, $imgArrAreaPoints[6][2], $imgArrAreaPoints[6][3]);
  }


  // Makes a collage of 8 images with 3 rows. First row has 2 image cells. Second row has 3 image cells. Second row has 3 image cells.
  public function copy8ImagesToCanvas($imgArr, $srcImgSizes) {
    $firstRowColumnWidth = ($this->canvasWidth / 2);
    $last2RowsColumnWidth = ($this->canvasWidth / 3);
    $rowHeight = ($this->canvasHeight / 3);
    $imgArrAreaPoints = [
      $this->getMiddleCropDimenstions($firstRowColumnWidth,  $rowHeight, $srcImgSizes[0][0], $srcImgSizes[0][1]),
      $this->getMiddleCropDimenstions($firstRowColumnWidth,  $rowHeight, $srcImgSizes[1][0], $srcImgSizes[1][1]),
      $this->getMiddleCropDimenstions($last2RowsColumnWidth, $rowHeight, $srcImgSizes[2][0], $srcImgSizes[2][1]),
      $this->getMiddleCropDimenstions($last2RowsColumnWidth, $rowHeight, $srcImgSizes[3][0], $srcImgSizes[3][1]),
      $this->getMiddleCropDimenstions($last2RowsColumnWidth, $rowHeight, $srcImgSizes[4][0], $srcImgSizes[4][1]),
      $this->getMiddleCropDimenstions($last2RowsColumnWidth, $rowHeight, $srcImgSizes[5][0], $srcImgSizes[5][1]),
      $this->getMiddleCropDimenstions($last2RowsColumnWidth, $rowHeight, $srcImgSizes[6][0], $srcImgSizes[6][1]),
      $this->getMiddleCropDimenstions($last2RowsColumnWidth, $rowHeight, $srcImgSizes[7][0], $srcImgSizes[7][1])
    ];

    imagecopyresampled($this->canvasImg, $imgArr[0], 0,                           0,                $imgArrAreaPoints[0][0], $imgArrAreaPoints[0][1], $firstRowColumnWidth, $rowHeight, $imgArrAreaPoints[0][2], $imgArrAreaPoints[0][3]);

    $firstRowSecondColumnStartX = $firstRowColumnWidth + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[1], $firstRowSecondColumnStartX, 0,                $imgArrAreaPoints[1][0], $imgArrAreaPoints[1][1], $firstRowColumnWidth, $rowHeight, $imgArrAreaPoints[1][2], $imgArrAreaPoints[1][3]);


    $secondRowStartY = $rowHeight + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[2], 0,                           $secondRowStartY, $imgArrAreaPoints[2][0], $imgArrAreaPoints[2][1], $last2RowsColumnWidth, $rowHeight, $imgArrAreaPoints[2][2], $imgArrAreaPoints[2][3]);

    $last2RowsColumnStartX = $last2RowsColumnWidth + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[3], $last2RowsColumnStartX,      $secondRowStartY, $imgArrAreaPoints[3][0], $imgArrAreaPoints[3][1], $last2RowsColumnWidth, $rowHeight, $imgArrAreaPoints[3][2], $imgArrAreaPoints[3][3]);

    $last2RowsThirdColumnStartX = ($last2RowsColumnWidth + $this->spacing) * 2;
    imagecopyresampled($this->canvasImg, $imgArr[4], $last2RowsThirdColumnStartX, $secondRowStartY, $imgArrAreaPoints[4][0], $imgArrAreaPoints[4][1], $last2RowsColumnWidth, $rowHeight, $imgArrAreaPoints[4][2], $imgArrAreaPoints[4][3]);


    $thirdRowStartY = ($rowHeight + $this->spacing) * 2;
    imagecopyresampled($this->canvasImg, $imgArr[5], 0,                           $thirdRowStartY,  $imgArrAreaPoints[5][0], $imgArrAreaPoints[5][1], $last2RowsColumnWidth, $rowHeight, $imgArrAreaPoints[5][2], $imgArrAreaPoints[5][3]);
    imagecopyresampled($this->canvasImg, $imgArr[6], $last2RowsColumnStartX,      $thirdRowStartY,  $imgArrAreaPoints[6][0], $imgArrAreaPoints[6][1], $last2RowsColumnWidth, $rowHeight, $imgArrAreaPoints[6][2], $imgArrAreaPoints[6][3]);
    imagecopyresampled($this->canvasImg, $imgArr[7], $last2RowsThirdColumnStartX, $thirdRowStartY,  $imgArrAreaPoints[7][0], $imgArrAreaPoints[7][1], $last2RowsColumnWidth, $rowHeight, $imgArrAreaPoints[7][2], $imgArrAreaPoints[7][3]);
  }


  // Makes a collage of 9 images with 3 rows and 3 columns with equal width and height.
  public function copy9ImagesToCanvas($imgArr, $srcImgSizes) {
    $columnWidth = ($this->canvasWidth / 3);
    $rowHeight = ($this->canvasHeight / 3);
    $imgArrAreaPoints = [
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[0][0], $srcImgSizes[0][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[1][0], $srcImgSizes[1][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[2][0], $srcImgSizes[2][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[3][0], $srcImgSizes[3][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[4][0], $srcImgSizes[4][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[5][0], $srcImgSizes[5][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[6][0], $srcImgSizes[6][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[7][0], $srcImgSizes[7][1]),
      $this->getMiddleCropDimenstions($columnWidth, $rowHeight, $srcImgSizes[8][0], $srcImgSizes[8][1])
    ];

    imagecopyresampled($this->canvasImg, $imgArr[0], 0,                    0,                $imgArrAreaPoints[0][0], $imgArrAreaPoints[0][1], $columnWidth, $rowHeight, $imgArrAreaPoints[0][2], $imgArrAreaPoints[0][3]);

    $secondColumnStartX = $columnWidth + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[1], $secondColumnStartX,  0,                $imgArrAreaPoints[1][0], $imgArrAreaPoints[1][1], $columnWidth, $rowHeight, $imgArrAreaPoints[1][2], $imgArrAreaPoints[1][3]);

    $thirdColumnStartX = ($columnWidth + $this->spacing) * 2;
    imagecopyresampled($this->canvasImg, $imgArr[2], $thirdColumnStartX,   0,                $imgArrAreaPoints[2][0], $imgArrAreaPoints[2][1], $columnWidth, $rowHeight, $imgArrAreaPoints[2][2], $imgArrAreaPoints[2][3]);


    $secondRowStartY = $rowHeight + $this->spacing;
    imagecopyresampled($this->canvasImg, $imgArr[3], 0,                    $secondRowStartY, $imgArrAreaPoints[3][0], $imgArrAreaPoints[3][1], $columnWidth, $rowHeight, $imgArrAreaPoints[3][2], $imgArrAreaPoints[3][3]);
    imagecopyresampled($this->canvasImg, $imgArr[4], $secondColumnStartX,  $secondRowStartY, $imgArrAreaPoints[4][0], $imgArrAreaPoints[4][1], $columnWidth, $rowHeight, $imgArrAreaPoints[4][2], $imgArrAreaPoints[4][3]);
    imagecopyresampled($this->canvasImg, $imgArr[5], $thirdColumnStartX,   $secondRowStartY, $imgArrAreaPoints[5][0], $imgArrAreaPoints[5][1], $columnWidth, $rowHeight, $imgArrAreaPoints[5][2], $imgArrAreaPoints[5][3]);


    $thirdRowStartY = ($rowHeight + $this->spacing) * 2;
    imagecopyresampled($this->canvasImg, $imgArr[6], 0,                    $thirdRowStartY,  $imgArrAreaPoints[6][0], $imgArrAreaPoints[6][1], $columnWidth, $rowHeight, $imgArrAreaPoints[6][2], $imgArrAreaPoints[6][3]);
    imagecopyresampled($this->canvasImg, $imgArr[7], $secondColumnStartX,  $thirdRowStartY,  $imgArrAreaPoints[7][0], $imgArrAreaPoints[7][1], $columnWidth, $rowHeight, $imgArrAreaPoints[7][2], $imgArrAreaPoints[7][3]);
    imagecopyresampled($this->canvasImg, $imgArr[8], $thirdColumnStartX,   $thirdRowStartY,  $imgArrAreaPoints[8][0], $imgArrAreaPoints[8][1], $columnWidth, $rowHeight, $imgArrAreaPoints[8][2], $imgArrAreaPoints[8][3]);
  }

}
