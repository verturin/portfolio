<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Traitement des images avec GD :
 *  - contrôle du type réel (pas de confiance dans l'extension du nom de fichier),
 *  - redressement selon l'orientation EXIF puis SUPPRESSION de toutes les métadonnées
 *    (EXIF, GPS, IPTC, XMP) par ré-encodage,
 *  - redimensionnement de l'original trop grand, création de la vignette carrée et de la taille moyenne.
 */
class image_processor
{
	const SIZES = ['source', 'medium', 'thumb'];

	/** @var \verturin\portfolio\service\settings Réglages du Portfolio */
	protected $config;

	/** @var string */
	protected $storage;

	/** @var array type GD => [extension, mime] */
	protected $types = [
		IMAGETYPE_JPEG	=> ['jpg', 'image/jpeg'],
		IMAGETYPE_PNG	=> ['png', 'image/png'],
		IMAGETYPE_GIF	=> ['gif', 'image/gif'],
	];

	public function __construct(\verturin\portfolio\service\settings $config, $storage)
	{
		$this->config = $config;
		$this->storage = rtrim($storage, '/') . '/';

		if (defined('IMAGETYPE_WEBP') && function_exists('imagecreatefromwebp'))
		{
			$this->types[IMAGETYPE_WEBP] = ['webp', 'image/webp'];
		}
	}

	public function allowed_extensions()
	{
		$list = array_map('trim', explode(',', strtolower($this->config['portfolio_allowed_ext'])));
		if (in_array('jpg', $list, true) || in_array('jpeg', $list, true))
		{
			$list[] = 'jpg';
			$list[] = 'jpeg';
		}

		return array_values(array_unique(array_filter($list)));
	}

	public function path($photo_file, $size = 'source')
	{
		$size = in_array($size, self::SIZES, true) ? $size : 'source';

		return $this->storage . $size . '/' . $photo_file;
	}

	/**
	 * Traite un fichier envoyé (tableau issu de $request->file()).
	 *
	 * @return array ['error' => clé de langue] ou les informations du fichier enregistré
	 */
	public function process_upload(array $file)
	{
		if (empty($file['tmp_name']) || !empty($file['error']) || !is_uploaded_file($file['tmp_name']))
		{
			return ['error' => $this->upload_error_key(isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE)];
		}

		return $this->process_file($file['tmp_name'], isset($file['name']) ? $file['name'] : '');
	}

	/**
	 * @param string $tmp      Fichier source
	 * @param string $realname Nom d'origine (informatif)
	 */
	public function process_file($tmp, $realname = '')
	{
		$max_kb = (int) $this->config['portfolio_max_filesize'];
		$filesize = (int) @filesize($tmp);
		if ($max_kb && $filesize > $max_kb * 1024)
		{
			return ['error' => 'PORTFOLIO_ERR_FILESIZE'];
		}

		$info = @getimagesize($tmp);
		if (!$info || !isset($this->types[$info[2]]))
		{
			return ['error' => 'PORTFOLIO_ERR_TYPE'];
		}

		list($ext, $mime) = $this->types[$info[2]];
		if (!in_array($ext, $this->allowed_extensions(), true))
		{
			return ['error' => 'PORTFOLIO_ERR_TYPE'];
		}

		$width = (int) $info[0];
		$height = (int) $info[1];
		if ($width < 1 || $height < 1 || $width * $height > 100000000)
		{
			return ['error' => 'PORTFOLIO_ERR_DIMENSIONS'];
		}

		$this->ensure_memory($width, $height);

		$image = $this->load($tmp, $info[2]);
		if (!$image)
		{
			return ['error' => 'PORTFOLIO_ERR_READ'];
		}

		$transformed = false;

		// Redresse la photo selon l'orientation EXIF avant de supprimer les métadonnées
		if ($info[2] === IMAGETYPE_JPEG)
		{
			$oriented = $this->apply_orientation($image, $tmp);
			if ($oriented !== $image)
			{
				$image = $oriented;
				$transformed = true;
			}
		}

		// Original trop grand : réduit aux dimensions maximales
		$max_w = (int) $this->config['portfolio_max_width'];
		$max_h = (int) $this->config['portfolio_max_height'];
		if (($max_w && imagesx($image) > $max_w) || ($max_h && imagesy($image) > $max_h))
		{
			$image = $this->fit($image, $max_w ?: imagesx($image), $max_h ?: imagesy($image));
			$transformed = true;
		}

		$strip = (bool) $this->config['portfolio_strip_exif'];
		$name = $this->new_name($ext);
		$this->ensure_dirs($name);

		// GIF : pas d'EXIF ; on conserve l'animation si aucune transformation n'est nécessaire
		$reencode = $transformed || ($strip && $info[2] !== IMAGETYPE_GIF);

		if ($reencode)
		{
			if (!$this->save($image, $this->path($name, 'source'), $info[2]))
			{
				return ['error' => 'PORTFOLIO_ERR_WRITE'];
			}
		}
		else if (!@copy($tmp, $this->path($name, 'source')))
		{
			return ['error' => 'PORTFOLIO_ERR_WRITE'];
		}

		$result = $this->build_derivatives($image, $name, $info[2]);
		$this->free($image);

		if (!$result)
		{
			$this->delete_files($name);

			return ['error' => 'PORTFOLIO_ERR_WRITE'];
		}

		$source = $this->path($name, 'source');
		$final = @getimagesize($source);

		return [
			'photo_file'			=> $name,
			'photo_realname'		=> utf8_basename($realname),
			'photo_ext'				=> $ext,
			'photo_mimetype'		=> $mime,
			'photo_filesize'		=> (int) @filesize($source),
			'photo_width'			=> $final ? (int) $final[0] : $width,
			'photo_height'			=> $final ? (int) $final[1] : $height,
			'photo_hash'			=> (string) @sha1_file($source),
			'photo_exif_stripped'	=> ($reencode || $info[2] === IMAGETYPE_GIF) ? 1 : 0,
		];
	}

	/**
	 * Fait pivoter une photo enregistrée (90, 180, 270 degrés dans le sens horaire).
	 */
	public function rotate($photo_file, $degrees)
	{
		$source = $this->path($photo_file, 'source');
		$info = @getimagesize($source);
		if (!$info || !isset($this->types[$info[2]]))
		{
			return false;
		}

		$this->ensure_memory($info[0], $info[1]);
		$image = $this->load($source, $info[2]);
		if (!$image)
		{
			return false;
		}

		$rotated = imagerotate($image, -1 * (int) $degrees, 0);
		$this->free($image);
		if (!$rotated)
		{
			return false;
		}

		$this->prepare_alpha($rotated, $info[2]);
		$ok = $this->save($rotated, $source, $info[2]) && $this->build_derivatives($rotated, $photo_file, $info[2]);
		$this->free($rotated);
		clearstatcache();

		$final = @getimagesize($source);

		return $ok ? [
			'photo_width'		=> (int) $final[0],
			'photo_height'		=> (int) $final[1],
			'photo_filesize'	=> (int) @filesize($source),
			'photo_hash'		=> (string) @sha1_file($source),
		] : false;
	}

	/**
	 * Importe un original (sauvegarde / export) tel quel, sans ré-encodage, puis crée vignette et taille moyenne.
	 *
	 * @param string $file        Fichier source extrait de l'archive
	 * @param string $photo_file  Chemin relatif à réutiliser (restauration à l'identique) ou vide pour un nouveau nom
	 * @return array|false        Informations du fichier enregistré
	 */
	public function import_original($file, $photo_file = '')
	{
		$info = @getimagesize($file);
		if (!$info || !isset($this->types[$info[2]]))
		{
			return false;
		}

		list($ext, $mime) = $this->types[$info[2]];
		if ($photo_file === '' || strpos($photo_file, '..') !== false || !preg_match('#^[a-z0-9]{2}/[a-z0-9]+\.[a-z0-9]+$#i', $photo_file))
		{
			$photo_file = $this->new_name($ext);
		}

		$this->ensure_dirs($photo_file);
		$target = $this->path($photo_file, 'source');
		if (!@copy($file, $target))
		{
			return false;
		}
		@chmod($target, 0644);

		if (!$this->rebuild_derivatives($photo_file))
		{
			$this->delete_files($photo_file);

			return false;
		}

		return [
			'photo_file'		=> $photo_file,
			'photo_ext'			=> $ext,
			'photo_mimetype'	=> $mime,
			'photo_filesize'	=> (int) @filesize($target),
			'photo_width'		=> (int) $info[0],
			'photo_height'		=> (int) $info[1],
			'photo_hash'		=> (string) @sha1_file($target),
		];
	}

	/**
	 * Recrée vignette et taille moyenne à partir de l'original enregistré.
	 */
	public function rebuild_derivatives($photo_file)
	{
		$source = $this->path($photo_file, 'source');
		$info = @getimagesize($source);
		if (!$info || !isset($this->types[$info[2]]))
		{
			return false;
		}

		$this->ensure_dirs($photo_file);
		$this->ensure_memory($info[0], $info[1]);
		$image = $this->load($source, $info[2]);
		if (!$image)
		{
			return false;
		}

		$ok = $this->build_derivatives($image, $photo_file, $info[2]);
		$this->free($image);

		return $ok;
	}

	public function storage_dir()
	{
		return $this->storage;
	}

	public function delete_files($photo_file)
	{
		if (!$photo_file || strpos($photo_file, '..') !== false)
		{
			return;
		}

		foreach (self::SIZES as $size)
		{
			$path = $this->path($photo_file, $size);
			if (is_file($path))
			{
				@unlink($path);
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Interne
	 * ------------------------------------------------------------------ */

	protected function build_derivatives($image, $name, $type)
	{
		$medium_size = max(320, (int) $this->config['portfolio_medium_size']);
		$thumb_size = max(120, (int) $this->config['portfolio_thumb_size']);

		$medium = $this->fit($image, $medium_size, $medium_size, false);
		$ok = $this->save($medium, $this->path($name, 'medium'), $type);
		if ($medium !== $image)
		{
			$this->free($medium);
		}

		$thumb = $this->cover($image, $thumb_size, $thumb_size);
		$ok = $ok && $this->save($thumb, $this->path($name, 'thumb'), $type);
		$this->free($thumb);

		return $ok;
	}

	protected function load($file, $type)
	{
		switch ($type)
		{
			case IMAGETYPE_JPEG:
				$image = @imagecreatefromjpeg($file);
			break;
			case IMAGETYPE_PNG:
				$image = @imagecreatefrompng($file);
			break;
			case IMAGETYPE_GIF:
				$image = @imagecreatefromgif($file);
			break;
			default:
				$image = (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) ? @imagecreatefromwebp($file) : false;
		}

		if ($image)
		{
			$this->prepare_alpha($image, $type);
		}

		return $image;
	}

	protected function save($image, $path, $type)
	{
		$quality = min(100, max(40, (int) $this->config['portfolio_jpeg_quality']));

		switch ($type)
		{
			case IMAGETYPE_JPEG:
				imageinterlace($image, true);
				$ok = @imagejpeg($image, $path, $quality);
			break;
			case IMAGETYPE_PNG:
				$ok = @imagepng($image, $path, 6);
			break;
			case IMAGETYPE_GIF:
				$ok = @imagegif($image, $path);
			break;
			default:
				$ok = function_exists('imagewebp') ? @imagewebp($image, $path, $quality) : false;
		}

		if ($ok)
		{
			@chmod($path, 0644);
		}

		return (bool) $ok;
	}

	protected function prepare_alpha($image, $type)
	{
		if ($type === IMAGETYPE_PNG || (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP) || $type === IMAGETYPE_GIF)
		{
			imagealphablending($image, false);
			imagesavealpha($image, true);
		}
	}

	protected function blank($width, $height, $source)
	{
		$canvas = imagecreatetruecolor($width, $height);
		imagealphablending($canvas, false);
		imagesavealpha($canvas, true);
		imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));

		return $canvas;
	}

	/**
	 * Redimensionne pour tenir dans la boîte (sans agrandir).
	 */
	protected function fit($image, $max_w, $max_h, $free_source = true)
	{
		$w = imagesx($image);
		$h = imagesy($image);
		$ratio = min($max_w / $w, $max_h / $h, 1);

		if ($ratio >= 1)
		{
			return $image;
		}

		$nw = max(1, (int) round($w * $ratio));
		$nh = max(1, (int) round($h * $ratio));
		$canvas = $this->blank($nw, $nh, $image);
		imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

		if ($free_source)
		{
			$this->free($image);
		}

		return $canvas;
	}

	/**
	 * Vignette carrée recadrée au centre.
	 */
	protected function cover($image, $tw, $th)
	{
		$w = imagesx($image);
		$h = imagesy($image);
		$scale = max($tw / $w, $th / $h);
		$scale = min($scale, 1);

		$crop_w = (int) round(min($w, $tw / $scale));
		$crop_h = (int) round(min($h, $th / $scale));
		$src_x = (int) floor(($w - $crop_w) / 2);
		$src_y = (int) floor(($h - $crop_h) / 2);

		$out_w = min($tw, $crop_w);
		$out_h = min($th, $crop_h);

		$canvas = $this->blank($out_w, $out_h, $image);
		imagecopyresampled($canvas, $image, 0, 0, $src_x, $src_y, $out_w, $out_h, $crop_w, $crop_h);

		return $canvas;
	}

	protected function apply_orientation($image, $file)
	{
		if (!function_exists('exif_read_data'))
		{
			return $image;
		}

		$exif = @exif_read_data($file);
		$orientation = isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;

		switch ($orientation)
		{
			case 2:
				imageflip($image, IMG_FLIP_HORIZONTAL);
			return $image;
			case 3:
				$new = imagerotate($image, 180, 0);
			break;
			case 4:
				imageflip($image, IMG_FLIP_VERTICAL);
			return $image;
			case 5:
				imageflip($image, IMG_FLIP_HORIZONTAL);
				$new = imagerotate($image, 90, 0);
			break;
			case 6:
				$new = imagerotate($image, -90, 0);
			break;
			case 7:
				imageflip($image, IMG_FLIP_HORIZONTAL);
				$new = imagerotate($image, -90, 0);
			break;
			case 8:
				$new = imagerotate($image, 90, 0);
			break;
			default:
				return $image;
		}

		if ($new)
		{
			$this->free($image);

			return $new;
		}

		return $image;
	}

	protected function new_name($ext)
	{
		$hash = md5(unique_id() . microtime() . random_bytes(8));

		return substr($hash, 0, 2) . '/' . $hash . '.' . $ext;
	}

	protected function ensure_dirs($name)
	{
		$sub = dirname($name);
		foreach (self::SIZES as $size)
		{
			$dir = $this->storage . $size . '/' . $sub;
			if (!is_dir($dir))
			{
				@mkdir($dir, 0755, true);
			}
		}
	}

	protected function ensure_memory($width, $height)
	{
		// ~5 octets par pixel + marge pour la copie de travail
		$needed = (int) ($width * $height * 5 * 2.2) + 32 * 1024 * 1024;
		$limit = $this->bytes(@ini_get('memory_limit'));
		if ($limit > 0 && memory_get_usage() + $needed > $limit)
		{
			@ini_set('memory_limit', (string) (memory_get_usage() + $needed));
		}
	}

	protected function bytes($value)
	{
		$value = trim((string) $value);
		if ($value === '' || $value === '-1')
		{
			return -1;
		}

		$unit = strtolower(substr($value, -1));
		$num = (int) $value;
		switch ($unit)
		{
			case 'g':
				$num *= 1024;
			// no break
			case 'm':
				$num *= 1024;
			// no break
			case 'k':
				$num *= 1024;
		}

		return $num;
	}

	protected function free($image)
	{
		// imagedestroy() est inutile (et obsolète) à partir de PHP 8
		if (PHP_VERSION_ID < 80000 && is_resource($image))
		{
			imagedestroy($image);
		}
	}

	protected function upload_error_key($code)
	{
		switch ($code)
		{
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return 'PORTFOLIO_ERR_FILESIZE';
			case UPLOAD_ERR_NO_FILE:
				return 'PORTFOLIO_ERR_NO_FILE';
			default:
				return 'PORTFOLIO_ERR_UPLOAD';
		}
	}
}
