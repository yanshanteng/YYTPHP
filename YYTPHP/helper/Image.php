<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

class Image
{
	/**
	 * 生成验证码图片
	 * @param int 验证码中字符的个数
	 * @param int 字符的选择库（数字、小写字母、大写字母、前三种混合）
	 * @param string 验证码字符串在 Session 中保存的键名
	 */
	public static function verify($length = 4, $type = 4, $name = 'verify')
    {
		if ($length < 3 || $length > 8) $length = 4;
		switch ($type) {
			case 1 : $vfyStr = "23456789"; break;
			case 2 : $vfyStr = "abcdefghjkmnpqrstwxyz"; break;
			case 3 : $vfyStr = "ABCDEFGHJKMNPQRSTWXYZ"; break;
			default : $vfyStr = "23456789abcdefghjkmnpqrstwxyzABCDEFGHJKMNPQRSTWXYZ"; break;
		}
		$vfyChar = substr(str_shuffle($vfyStr), 0, $length);
        Y::session($name, strtolower($vfyChar));

		$vfyWidth = $length * 20;
		$vfyHeight = 16;
		$vfyImage = imagecreate($vfyWidth, $vfyHeight);
		imagecolorallocate($vfyImage, 255, 255, 255);
		for ($i = 0; $i < $length; $i++) {
			$vfyColor = imagecolorallocate($vfyImage, rand(0, 100), rand(0, 150), rand(0, 200));
			imagestring($vfyImage, 5, $vfyWidth * $i / $length + rand(0, 10), rand(0, $vfyHeight - 15), $vfyChar[$i], $vfyColor);
		}
		$dotNumber = $length * 20;
		for ($i = 0; $i < $dotNumber; $i++) {
			$dotColor = imagecolorallocate($vfyImage, rand(0, 255), rand(0, 255), rand(0, 255));
			imagesetpixel($vfyImage, rand(0, $vfyWidth), rand(0, $vfyHeight), $dotColor);
		}
		$lineNumber = $length / 2;
		for ($i = 0; $i < $lineNumber; $i++) {
			$lineColor = imagecolorallocate($vfyImage, rand(0, 255), rand(0, 255), rand(0, 255));
			imageline($vfyImage, rand(0, $vfyWidth), rand(0, $vfyHeight), rand(0, $vfyWidth), rand(0, $vfyHeight), $lineColor);
		}
        self::imageType('jpeg', $vfyImage);
	}

    /**
     * 获取图片信息
     * @param string 图片路径
     * @return array (宽度, 高度, 类型)
     */
    public static function info($imgPath)
    {
        $imgInfo = array();
        list($imgInfo['width'], $imgInfo['height'], $imgInfo['type']) = getimagesize($imgPath);
        $imgInfo['type'] = image_type_to_extension($imgInfo['type'], false);
        return $imgInfo;
    }

    /**
	 * 根据图片类型选择函数打开图片
	 * @param string 图片路径
     * @param string 类型
	 * @return imagecreatefrom[*]
	 */
	public static function imageCreateFromType($imgPath, $type = '')
    {
        if (!$type) {
            $info = self::info($imgPath);
            $type = $info['type'];
        }
        $function = 'imagecreatefrom'.$type;
		return $function($imgPath);
	}

    /**
     * 输出或保存图片
     * @param string 图片类型
     * @param function 创建的图片函数
     * @param string 存放路径(留空直接输出)
     * @return mixed
     */
    public static function imageType($type, $imgCreate, $savePath = '')
    {
        $function  = 'image'.$type;
        if ($savePath) {
            $function($imgCreate, $savePath);
        } else {
            header('Content-Type:image/'.$type);
            $function($imgCreate);
        }
        imagedestroy($imgCreate);
    }

    /**
	 * 裁剪图片大小
	 * @param string 图片路径
	 * @param int 宽度
	 * @param int 高度(大于0的话图片则按该值裁剪)
     * @param string 存放路径(留空直接输出)
	 * @return mixed
	 */
    public static function cut($imgPath, $width, $height = 0, $savePath = '')
    {
        if ($height <= 0) $height = $width;
        $imgInfo = self::info($imgPath);
        $imgRatio = $imgInfo['width'] / $imgInfo['height'];
        $newRatio = $width / $height;
        if ($newRatio <= $imgRatio) {
            $croppedWidth = $width;
			$croppedHeight = $width * ($imgInfo['height'] / $imgInfo['width']);
        } else {
            $croppedWidth = $height * ($imgInfo['width'] / $imgInfo['height']);
            $croppedHeight = $height;
        }
        $img = self::imageCreateFromType($imgPath, $imgInfo['type']);
        $newImg = imagecreatetruecolor($croppedWidth, $croppedHeight);
        //保留透明度
        imagealphablending($newImg , false);
        imagesavealpha($newImg, true);

        imagecopyresampled($newImg, $img, 0, 0, 0, 0, $croppedWidth, $croppedHeight, $imgInfo['width'], $imgInfo['height']);
        imagedestroy($img);
        self::imageType($imgInfo['type'], $newImg, $savePath);
        //if ($savePath) return $savePath;
    }

    /**
     * 图片压缩并纠正图片方向
     * @param string 图片路径
     * @param number 压缩比例 1为原图压缩
     * @return string
     */
    public static function compress($imgPath, $percent = 1)
    {
        $imgInfo = self::info($imgPath);
        $img = self::imageCreateFromType($imgPath, $imgInfo['type']);
        $newWidth = $imgInfo['width'] * $percent;
        $newHeight = $imgInfo['height'] * $percent;
        $newImg = imagecreatetruecolor($newWidth, $newHeight);
        //保留透明度
        imagealphablending($newImg , false);
        imagesavealpha($newImg, true);

        imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $imgInfo['width'], $imgInfo['height']);
        imagedestroy($img);
        //纠正图片方向
        $exif = @exif_read_data($imgPath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 8: $newImg = imagerotate($newImg, 90, 0); break;
                case 3: $newImg = imagerotate($newImg, 180, 0); break;
                case 6: $newImg = imagerotate($newImg, -90, 0); break;
            }
        }
        self::imageType($imgInfo['type'], $newImg, $imgPath);
        return $imgPath;
    }
}