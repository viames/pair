<?php

declare(strict_types=1);

namespace Pair\Http;

/**
 * Shared MIME type and file-extension categories for upload validation and file controls.
 */
final class FileMediaType {

	/**
	 * MIME categories recognized by Pair upload and file input helpers.
	 *
	 * @var array<string, string[]>
	 */
	public const CATEGORIES = [
		'audio' => [
			'.aac',
			'.flac',
			'.mp3',
			'.m4a',
			'.oga',
			'.ogg',
			'.wav',
			'audio/*',
			'audio/aac',
			'audio/flac',
			'audio/mpeg',
			'audio/mpeg3',
			'audio/mp3',
			'audio/m4a',
			'audio/ogg',
			'audio/wav',
			'audio/x-aiff',
			'audio/x-pn-realaudio',
			'audio/x-wav',
		],
		'binary' => [
			'application/octet-stream',
		],
		'csv' => [
			'.csv',
			'application/csv',
			'text/comma-separated-values',
			'text/csv',
			'text/plain',
		],
		'document' => [
			'.doc',
			'.docx',
			'.docm',
			'application/msword',
			'text/plain',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-word.document.macroEnabled.12',
			'application/vnd.oasis.opendocument.text',
		],
		'flash' => [
			'.swf',
			'application/x-shockwave-flash',
		],
		'image' => [
			'.bmp',
			'.gif',
			'.heic',
			'.heif',
			'.ico',
			'.jpeg',
			'.jpg',
			'.png',
			'.svg',
			'.tiff',
			'.webp',
			'image/*',
			'image/apng',
			'image/avif',
			'image/bmp',
			'image/gif',
			'image/heic',
			'image/heif',
			'image/jpeg',
			'image/pjpeg',
			'image/png',
			'image/svg',
			'image/svg+xml',
			'image/tiff',
			'image/vnd.microsoft.icon',
			'image/x-windows-bmp',
		],
		'pdf' => [
			'.pdf',
			'application/pdf',
			'application/x-pdf',
			'application/acrobat',
			'applications/vnd.pdf',
			'text/pdf',
			'text/x-pdf',
		],
		'presentation' => [
			'.ppt',
			'.pptx',
			'.pptm',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'application/vnd.ms-powerpoint',
			'vnd.openxmlformats-officedocument.presentationml.presentation',
			'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
		],
		'spreadsheet' => [
			'.xls',
			'.xlsx',
			'.xlsm',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-excel',
			'vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-excel.sheet.macroEnabled.12',
		],
		'video' => [
			'.avi',
			'.flv',
			'.mp4',
			'.mpeg',
			'.mpg',
			'.qt',
			'.webm',
			'video/*',
			'video/mpeg',
			'video/mp4',
			'video/x-mpeg',
			'video/quicktime',
			'video/webm',
			'video/x-flv',
			'video/x-msvideo',
			'video/x-ms-asf',
		],
		'zip' => [
			'.bz2',
			'.gz',
			'.zip',
			'application/octet-stream',
			'application/x-bzip2',
			'application/x-zip-compressed',
			'application/zip',
		],
	];

	/**
	 * Return all known entries for one category.
	 *
	 * @return string[]
	 */
	public static function categoryTypes(string $category): array {

		$category = strtolower(trim($category));

		return self::CATEGORIES[$category] ?? [];

	}

	/**
	 * Return the first category matching a MIME type or file extension.
	 */
	public static function category(string $mediaType): ?string {

		$categories = self::categories($mediaType);

		return $categories[0] ?? null;

	}

	/**
	 * Return every category matching a MIME type or file extension.
	 *
	 * @return string[]
	 */
	public static function categories(string $mediaType): array {

		$mediaType = strtolower(trim($mediaType));

		if ('' === $mediaType) {
			return [];
		}

		$matches = [];

		foreach (self::CATEGORIES as $category => $types) {
			foreach ($types as $type) {
				if ($mediaType === strtolower($type)) {
					$matches[] = $category;
					break;
				}
			}
		}

		return $matches;

	}

	/**
	 * Return true when a MIME type or extension belongs to the requested category.
	 */
	public static function matchesCategory(string $mediaType, string $category): bool {

		$category = strtolower(trim($category));

		return in_array($category, self::categories($mediaType), true);

	}

}
