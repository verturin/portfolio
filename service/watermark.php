<?php
/**
 * Portfolio — extension phpBB
 *
 * @copyright (c) 2026 verturin
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace verturin\portfolio\service;

/**
 * Filigrane dynamique (v1.1.0).
 *
 * Le filigrane est apposé AU MOMENT DE L'AFFICHAGE : il contient le nom du site et l'identifiant
 * du membre qui regarde (ou télécharge) la photo. Une copie volée permet donc de retrouver le compte
 * qui l'a récupérée. L'original enregistré n'est jamais modifié.
 *
 * Les images filigranées sont mises en cache (files/portfolio/cache/) par photo, taille, visiteur
 * et réglages ; la tâche cron purge ce cache régulièrement.
 */
class watermark
{
	const POSITIONS = ['bottom-right', 'bottom-left', 'top-right', 'top-left', 'center', 'across', 'diagonal', 'tile'];
	const LOGO_POSITIONS = ['bottom-right', 'bottom-left', 'top-right', 'top-left', 'center'];

	/** @var settings */
	protected $settings;

	/** @var \phpbb\config\config Configuration du forum (nom du site) */
	protected $board_config;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	protected $storage;

	/** @var string */
	protected $font;

	public function __construct(settings $settings, \phpbb\config\config $board_config, \phpbb\user $user, $storage, $ext_path)
	{
		$this->settings = $settings;
		$this->board_config = $board_config;
		$this->user = $user;
		$this->storage = rtrim($storage, '/') . '/';
		$this->font = rtrim($ext_path, '/') . '/fonts/DejaVuSans-Bold.ttf';
	}

	/* ------------------------------------------------------------------
	 * Décision
	 * ------------------------------------------------------------------ */

	public function is_enabled()
	{
		return (bool) $this->settings['portfolio_wm_enabled']
			&& ($this->settings['portfolio_wm_text_enabled'] || ($this->settings['portfolio_wm_logo_enabled'] && is_file($this->logo_path())));
	}

	/**
	 * La taille demandée doit-elle être filigranée ?
	 *
	 * @param string $size      thumb | medium | source
	 * @param bool   $is_member Le visiteur est propriétaire / co-gestionnaire de l'album
	 */
	public function applies($size, $is_member)
	{
		if (!$this->is_enabled())
		{
			return false;
		}

		if ($is_member && $this->settings['portfolio_wm_skip_members'])
		{
			return false;
		}

		if ($size === 'thumb')
		{
			return (bool) $this->settings['portfolio_wm_thumbs'];
		}

		return true;
	}

	/* ------------------------------------------------------------------
	 * Rendu (avec cache)
	 * ------------------------------------------------------------------ */

	/**
	 * Retourne le chemin d'une version filigranée de $source pour le visiteur courant,
	 * ou chaîne vide si impossible (on sert alors l'image sans filigrane… sauf si l'ACP l'interdit).
	 */
	public function get(array $photo, $source, $size)
	{
		$viewer_id = (int) $this->user->data['user_id'];
		$ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
		$key = sha1(implode('|', [$photo['photo_id'], $photo['photo_hash'], $size, $viewer_id, $this->settings_hash()]));
		$cached = $this->storage . 'cache/' . substr($key, 0, 2) . '/' . $key . '.' . $ext;

		if (is_file($cached))
		{
			return $cached;
		}

		if (!is_dir(dirname($cached)))
		{
			@mkdir(dirname($cached), 0755, true);
		}

		return $this->render($source, $cached, $this->text_for_viewer($photo)) ? $cached : '';
	}

	/**
	 * Aperçu pour l'ACP (image de démonstration).
	 */
	public function preview($target)
	{
		$image = imagecreatetruecolor(960, 640);
		for ($y = 0; $y < 640; $y++)
		{
			$c = imagecolorallocate($image, 40 + (int) ($y / 6), 90 + (int) ($y / 8), 140 + (int) ($y / 10));
			imageline($image, 0, $y, 959, $y, $c);
		}
		imagefilledellipse($image, 300, 360, 380, 380, imagecolorallocate($image, 240, 180, 80));
		imagefilledellipse($image, 680, 260, 260, 260, imagecolorallocate($image, 90, 200, 160));

		$this->apply($image, $this->text_for_viewer(['photo_id' => 0]));
		$ok = imagejpeg($image, $target, 88);

		return $ok;
	}

	/**
	 * Suffixe de version pour les URL d'images (le navigateur recharge après un changement de réglages).
	 */
	public function version()
	{
		return $this->is_enabled() ? substr($this->settings_hash(), 0, 6) : '';
	}

	public function text_for_viewer(array $photo)
	{
		$template = (string) $this->settings['portfolio_wm_text'];
		if ($template === '')
		{
			$template = '{SITENAME} · ID {USER_ID}';
		}

		$registered = !empty($this->user->data['is_registered']);

		$text = strtr(htmlspecialchars_decode($template, ENT_QUOTES), [
			'{SITENAME}'	=> htmlspecialchars_decode((string) $this->board_config['sitename'], ENT_QUOTES),
			'{SITEURL}'		=> preg_replace('#^https?://#', '', (string) $this->board_config['server_name']),
			'{USER_ID}'		=> $registered ? (string) (int) $this->user->data['user_id'] : '0',
			'{USERNAME}'	=> $registered ? htmlspecialchars_decode($this->user->data['username'], ENT_QUOTES) : 'Guest',
			'{PHOTO_ID}'	=> (string) (int) $photo['photo_id'],
			'{DATE}'		=> gmdate('Y-m-d'),
		]);

		return trim($text);
	}

	public function logo_path()
	{
		return $this->storage . 'wm/logo.png';
	}

	/**
	 * Supprime tout le cache (après changement de réglages ou à la demande).
	 *
	 * @param int $older_than Ne supprimer que les fichiers plus vieux que N secondes (0 = tout)
	 */
	public function clear_cache($older_than = 0)
	{
		$dir = $this->storage . 'cache';
		if (!is_dir($dir))
		{
			return 0;
		}

		$count = 0;
		$limit = time() - (int) $older_than;
		$items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($items as $item)
		{
			if ($item->isFile() && (!$older_than || $item->getMTime() < $limit))
			{
				@unlink($item->getPathname());
				$count++;
			}
			else if ($item->isDir() && !$older_than)
			{
				@rmdir($item->getPathname());
			}
		}

		return $count;
	}

	/* ------------------------------------------------------------------
	 * Dessin
	 * ------------------------------------------------------------------ */

	protected function settings_hash()
	{
		$values = [];
		foreach (['enabled', 'text_enabled', 'text', 'text_size', 'text_color', 'opacity', 'position', 'shadow', 'logo_enabled', 'logo_size', 'logo_position', 'logo_opacity', 'logo_version', 'revision'] as $key)
		{
			$values[] = $this->settings['portfolio_wm_' . $key];
		}

		return md5(implode('|', $values) . '|' . $this->board_config['sitename']);
	}

	protected function render($source, $target, $text)
	{
		$info = @getimagesize($source);
		if (!$info)
		{
			return false;
		}

		switch ($info[2])
		{
			case IMAGETYPE_JPEG:
				$image = @imagecreatefromjpeg($source);
			break;
			case IMAGETYPE_PNG:
				$image = @imagecreatefrompng($source);
			break;
			case IMAGETYPE_GIF:
				$image = @imagecreatefromgif($source);
			break;
			default:
				$image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false;
		}

		if (!$image)
		{
			return false;
		}

		if (!imageistruecolor($image))
		{
			imagepalettetotruecolor($image);
		}
		imagesavealpha($image, true);

		$this->apply($image, $text);

		$quality = min(100, max(40, (int) $this->settings['portfolio_jpeg_quality']));
		switch ($info[2])
		{
			case IMAGETYPE_JPEG:
				imageinterlace($image, true);
				$ok = @imagejpeg($image, $target, $quality);
			break;
			case IMAGETYPE_PNG:
				$ok = @imagepng($image, $target, 6);
			break;
			case IMAGETYPE_GIF:
				$ok = @imagegif($image, $target);
			break;
			default:
				$ok = @imagewebp($image, $target, $quality);
		}

		return (bool) $ok;
	}

	/**
	 * Applique logo et texte sur une image GD (modifiée sur place).
	 */
	public function apply($image, $text)
	{
		$w = imagesx($image);
		$h = imagesy($image);
		if ($w < 120 || $h < 120)
		{
			return;
		}

		imagealphablending($image, true);

		if ($this->settings['portfolio_wm_logo_enabled'] && is_file($this->logo_path()))
		{
			$this->draw_logo($image, $w, $h);
		}

		if ($this->settings['portfolio_wm_text_enabled'] && $text !== '')
		{
			$this->draw_text($image, $w, $h, $text);
		}
	}

	protected function draw_text($image, $w, $h, $text)
	{
		$position = in_array($this->settings['portfolio_wm_position'], self::POSITIONS, true) ? $this->settings['portfolio_wm_position'] : 'bottom-right';
		$size_pct = min(30, max(1, (float) $this->settings['portfolio_wm_text_size']));
		$font_px = max(10, (int) round($w * $size_pct / 100));
		if ($w < 480)
		{
			// Vignettes (v1.6.0) : texte un peu plus grand en proportion pour rester lisible
			$font_px = max(11, (int) round($w * max($size_pct, 6) / 100));
		}
		$opacity = min(100, max(5, (int) $this->settings['portfolio_wm_opacity']));
		list($r, $g, $b) = $this->hex_to_rgb($this->settings['portfolio_wm_text_color']);
		$shadow = (bool) $this->settings['portfolio_wm_shadow'];

		$layer = $this->text_layer($text, $font_px, [$r, $g, $b], $opacity, $shadow);
		$margin = (int) round(min($w, $h) * 0.03);

		switch ($position)
		{
			case 'diagonal':
				$angle = rad2deg(atan2($h, $w));
				$layer = $this->rotate($layer, $angle);
				$this->copy_centered($image, $layer, $w / 2, $h / 2);
			break;

			case 'tile':
				// Mosaïque : texte plus petit et répété pour couvrir toute la photo
				$small = $this->text_layer($text, max(10, (int) round($font_px * 0.6)), [$r, $g, $b], $opacity, $shadow);
				$rotated = $this->rotate($small, 30);
				$lw = imagesx($rotated);
				$lh = imagesy($rotated);
				$step_x = (int) max(40, $lw * 1.05);
				$step_y = (int) max(30, $lh * 0.9);
				$row = 0;
				for ($y = -$lh / 2; $y < $h + $lh; $y += $step_y)
				{
					$offset = ($row++ % 2) ? $step_x / 2 : 0;
					for ($x = -$lw / 2 + $offset; $x < $w + $lw; $x += $step_x)
					{
						imagecopy($image, $rotated, (int) $x, (int) $y, 0, 0, $lw, $lh);
					}
				}
			break;

			case 'across':
				// Bande horizontale semi-transparente en travers de la photo
				$band_h = (int) round(imagesy($layer) * 1.6);
				$band_alpha = (int) round(127 - (127 * min(60, $opacity) / 100));
				imagefilledrectangle($image, 0, (int) (($h - $band_h) / 2), $w, (int) (($h + $band_h) / 2), imagecolorallocatealpha($image, 0, 0, 0, $band_alpha));
				$this->copy_centered($image, $layer, $w / 2, $h / 2);
			break;

			default:
				list($x, $y) = $this->corner($position, $w, $h, imagesx($layer), imagesy($layer), $margin);
				imagecopy($image, $layer, $x, $y, 0, 0, imagesx($layer), imagesy($layer));
		}
	}

	protected function draw_logo($image, $w, $h)
	{
		$logo = @imagecreatefrompng($this->logo_path());
		if (!$logo)
		{
			return;
		}

		$size_pct = min(60, max(3, (int) $this->settings['portfolio_wm_logo_size']));
		$target_w = max(16, (int) round($w * $size_pct / 100));
		$target_h = max(16, (int) round(imagesy($logo) * $target_w / imagesx($logo)));

		$scaled = imagecreatetruecolor($target_w, $target_h);
		imagealphablending($scaled, false);
		imagesavealpha($scaled, true);
		imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
		imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $target_w, $target_h, imagesx($logo), imagesy($logo));

		$this->fade($scaled, min(100, max(5, (int) $this->settings['portfolio_wm_logo_opacity'])));

		$position = in_array($this->settings['portfolio_wm_logo_position'], self::LOGO_POSITIONS, true) ? $this->settings['portfolio_wm_logo_position'] : 'top-left';
		list($x, $y) = $this->corner($position, $w, $h, $target_w, $target_h, (int) round(min($w, $h) * 0.03));
		imagecopy($image, $scaled, $x, $y, 0, 0, $target_w, $target_h);
	}

	/**
	 * Calque transparent contenant le texte (police TrueType, repli sur la police interne de GD).
	 */
	protected function text_layer($text, $font_px, array $rgb, $opacity, $shadow)
	{
		$alpha = (int) round(127 * (1 - $opacity / 100));
		$shadow_alpha = (int) min(127, $alpha + (127 - $alpha) * 0.25);
		$pad = (int) max(3, $font_px * 0.2);

		if (function_exists('imagettftext') && is_file($this->font))
		{
			$box = imagettfbbox($font_px, 0, $this->font, $text);
			$tw = abs($box[2] - $box[0]);
			$th = abs($box[7] - $box[1]);
			$layer = $this->blank($tw + $pad * 2, $th + $pad * 2);
			$x = $pad - $box[0];
			$y = $pad - $box[7];
			if ($shadow)
			{
				// Contour de couleur opposée tout autour des lettres (v1.7.0) : le texte reste lisible
				// sur un ciel blanc comme sur une photo sombre. L'ancienne ombre (1 px en bas à droite)
				// disparaissait sur les fonds clairs.
				$outline = $this->contrast($rgb);
				$color = imagecolorallocatealpha($layer, $outline[0], $outline[1], $outline[2], $shadow_alpha);
				$off = (int) max(1, round($font_px / 14));
				foreach ([[-1, -1], [0, -1], [1, -1], [-1, 0], [1, 0], [-1, 1], [0, 1], [1, 1]] as $d)
				{
					imagettftext($layer, $font_px, 0, $x + $d[0] * $off, $y + $d[1] * $off, $color, $this->font, $text);
				}
			}
			imagettftext($layer, $font_px, 0, $x, $y, imagecolorallocatealpha($layer, $rgb[0], $rgb[1], $rgb[2], $alpha), $this->font, $text);

			return $layer;
		}

		// Repli : police bitmap agrandie (sans FreeType)
		$text = preg_replace('/[^\x20-\x7E]/', '-', $text);
		$fw = imagefontwidth(5) * strlen($text) + 4;
		$fh = imagefontheight(5) + 4;
		$small = $this->blank($fw, $fh);
		if ($shadow)
		{
			imagestring($small, 5, 3, 3, $text, imagecolorallocatealpha($small, 0, 0, 0, $shadow_alpha));
		}
		imagestring($small, 5, 2, 2, $text, imagecolorallocatealpha($small, $rgb[0], $rgb[1], $rgb[2], $alpha));

		$scale = $font_px / imagefontheight(5);
		$layer = $this->blank((int) ($fw * $scale), (int) ($fh * $scale));
		imagecopyresampled($layer, $small, 0, 0, 0, 0, imagesx($layer), imagesy($layer), $fw, $fh);

		return $layer;
	}

	/** Noir pour un texte clair, blanc pour un texte sombre. */
	protected function contrast(array $rgb)
	{
		$luma = 0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2];

		return $luma > 140 ? [0, 0, 0] : [255, 255, 255];
	}

	protected function blank($w, $h)
	{
		$img = imagecreatetruecolor(max(1, (int) $w), max(1, (int) $h));
		imagealphablending($img, false);
		imagesavealpha($img, true);
		imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
		imagealphablending($img, true);

		return $img;
	}

	protected function rotate($layer, $angle)
	{
		imagealphablending($layer, false);
		$rotated = imagerotate($layer, $angle, imagecolorallocatealpha($layer, 0, 0, 0, 127));
		imagealphablending($rotated, true);
		imagesavealpha($rotated, true);

		return $rotated;
	}

	protected function copy_centered($image, $layer, $cx, $cy)
	{
		imagecopy($image, $layer, (int) round($cx - imagesx($layer) / 2), (int) round($cy - imagesy($layer) / 2), 0, 0, imagesx($layer), imagesy($layer));
	}

	protected function corner($position, $w, $h, $lw, $lh, $margin)
	{
		switch ($position)
		{
			case 'top-left':
				return [$margin, $margin];
			case 'top-right':
				return [$w - $lw - $margin, $margin];
			case 'bottom-left':
				return [$margin, $h - $lh - $margin];
			case 'center':
				return [(int) (($w - $lw) / 2), (int) (($h - $lh) / 2)];
			default:
				return [$w - $lw - $margin, $h - $lh - $margin];
		}
	}

	/**
	 * Multiplie la transparence de chaque pixel (logo PNG à opacité réglable).
	 */
	protected function fade($image, $opacity)
	{
		if ($opacity >= 100)
		{
			return;
		}

		imagealphablending($image, false);
		$w = imagesx($image);
		$h = imagesy($image);
		for ($x = 0; $x < $w; $x++)
		{
			for ($y = 0; $y < $h; $y++)
			{
				$rgba = imagecolorat($image, $x, $y);
				$a = ($rgba >> 24) & 0x7F;
				$new_a = (int) round(127 - (127 - $a) * $opacity / 100);
				imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, ($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF, $new_a));
			}
		}
		imagealphablending($image, true);
	}

	protected function hex_to_rgb($hex)
	{
		$hex = ltrim((string) $hex, '#');
		if (strlen($hex) === 3)
		{
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if (!preg_match('/^[0-9a-f]{6}$/i', $hex))
		{
			return [255, 255, 255];
		}

		return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
	}
}
