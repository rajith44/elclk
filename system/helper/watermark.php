<?php
/**
 * Apply the store watermark settings to an image file.
 */
function oc_apply_config_watermark(\Opencart\System\Engine\Registry $registry, string $target_file): bool {
	$config = $registry->get('config');

	if (!$config->get('config_watermark_status') || !$config->get('config_watermark_image')) {
		return false;
	}

	$watermark_image = $config->get('config_watermark_image');
	$watermark_path = DIR_IMAGE . html_entity_decode($watermark_image, ENT_QUOTES, 'UTF-8');

	if (!is_file($watermark_path) || !is_file($target_file)) {
		return false;
	}

	$target_real = realpath($target_file);
	$watermark_real = realpath($watermark_path);

	if ($target_real && $watermark_real && $target_real === $watermark_real) {
		return false;
	}

	$image_info = getimagesize($target_file);

	if ($image_info === false || !in_array($image_info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
		return false;
	}

	try {
		$image = new \Opencart\System\Library\Image($target_file);
		$watermark = new \Opencart\System\Library\Image($watermark_path);

		$size_type = $config->get('config_watermark_size_type') ?: 'percentage';
		$image_width = $image->getWidth();
		$image_height = $image->getHeight();
		$watermark_width = $watermark->getWidth();
		$watermark_height = $watermark->getHeight();

		if ($size_type == 'percentage') {
			$percentage = (float)($config->get('config_watermark_size_percentage') ?: 20);
			$percentage = max(1, min(100, $percentage));
			$target_width = (int)($image_width * ($percentage / 100));
			$target_height = (int)($watermark_height * ($target_width / max(1, $watermark_width)));

			if ($target_height > $image_height) {
				$target_height = (int)($image_height * ($percentage / 100));
				$target_width = (int)($watermark_width * ($target_height / max(1, $watermark_height)));
			}
		} else {
			$target_width = (int)($config->get('config_watermark_size_width') ?: 0);
			$target_height = (int)($config->get('config_watermark_size_height') ?: 0);

			if ($target_width > 0 && $target_height > 0) {
				// Use both dimensions.
			} elseif ($target_width > 0) {
				$target_height = (int)($watermark_height * ($target_width / max(1, $watermark_width)));
			} elseif ($target_height > 0) {
				$target_width = (int)($watermark_width * ($target_height / max(1, $watermark_height)));
			} else {
				$target_width = $watermark_width;
				$target_height = $watermark_height;
			}

			if ($target_width > $image_width) {
				$target_width = $image_width;
				$target_height = (int)($watermark_height * ($target_width / max(1, $watermark_width)));
			}

			if ($target_height > $image_height) {
				$target_height = $image_height;
				$target_width = (int)($watermark_width * ($target_height / max(1, $watermark_height)));
			}
		}

		if ($target_width > 0 && $target_height > 0 && ($target_width != $watermark_width || $target_height != $watermark_height)) {
			$watermark->resize($target_width, $target_height);
		}

		$position = $config->get('config_watermark_position') ?: 'bottomright';
		$image->watermark($watermark, $position);
		$image->save($target_file);

		return true;
	} catch (\Exception $e) {
		error_log('Watermark error: ' . $e->getMessage());

		return false;
	}
}

/**
 * Collect image files under catalog/ that can be watermarked.
 *
 * @return array<int, string>
 */
function oc_collect_watermark_target_files(string $directory, string $skip_relative = ''): array {
	$files = [];

	if (!is_dir($directory)) {
		return $files;
	}

	$skip_relative = str_replace('\\', '/', $skip_relative);
	$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
	$image_root = str_replace('\\', '/', DIR_IMAGE);

	$scan = function (string $path) use (&$scan, &$files, $skip_relative, $allowed, $image_root): void {
		$items = @scandir($path);

		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$full_path = $path . $item;

			if (is_dir($full_path)) {
				$scan($full_path . '/');
				continue;
			}

			if (!is_file($full_path)) {
				continue;
			}

			$normalized = str_replace('\\', '/', $full_path);
			$relative = ltrim(str_replace($image_root, '', $normalized), '/');

			if ($skip_relative !== '' && $relative === $skip_relative) {
				continue;
			}

			if (strpos($relative, 'cache/') === 0) {
				continue;
			}

			$extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

			if (!in_array($extension, $allowed, true)) {
				continue;
			}

			$files[] = $normalized;
		}
	};

	$scan(rtrim(str_replace('\\', '/', $directory), '/') . '/');
	sort($files);

	return $files;
}

/**
 * Delete generated thumbnails so they rebuild from updated originals.
 */
function oc_clear_image_cache(string $directory = ''): int {
	$directory = $directory ?: (DIR_IMAGE . 'cache/');
	$count = 0;

	if (!is_dir($directory)) {
		return 0;
	}

	foreach (array_diff(scandir($directory), ['.', '..']) as $item) {
		$path = $directory . $item;

		if (is_dir($path)) {
			$count += oc_clear_image_cache($path . '/');
			continue;
		}

		if (@unlink($path)) {
			$count++;
		}
	}

	return $count;
}
